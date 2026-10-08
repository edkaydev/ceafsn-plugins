<?php
/**
 * Content indexer for CE-AFSN AI Assistant.
 *
 * Crawls all six CE-AFSN plugin tables plus WordPress pages and posts,
 * splits content into chunks, calls the embeddings API, and stores
 * everything in the chunks table for fast cosine retrieval.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_Indexer
 */
class CEAFSN_AI_Indexer {

	/**
	 * Seconds to pause between embedding API calls to respect rate limits.
	 *
	 * Free-tier APIs (especially Gemini) may throttle at ~60 RPM. A 50 ms
	 * sleep keeps the burst rate well under that ceiling even on a cold index.
	 *
	 * @var int Microseconds (50 000 = 0.05 s).
	 */
	const RATE_LIMIT_PAUSE_US = 50000;

	/**
	 * Chunks a single-record refresh will build before deferring to a bulk run.
	 *
	 * A refresh runs inside the request that saved the record, so a very long
	 * document would hold that request open for one API call per chunk. Past
	 * this many, the record is left unindexed, the index is flagged as stale,
	 * and the operator finishes it with one press of the bulk index button.
	 *
	 * @var int
	 */
	const REFRESH_MAX_CHUNKS = 8;

	/**
	 * Option recording content changes that the index has not absorbed yet.
	 *
	 * @var string
	 */
	const DIRTY_OPTION = 'ceafsn_ai_index_dirty';

	/**
	 * The six CE-AFSN plugin tables and the rule that decides what is public.
	 *
	 * Every table is indexed through this registry so a new source cannot be
	 * added without also declaring its visibility rule. The rules differ for a
	 * real reason: publications carry a members_only access level, and the M&E
	 * dashboard has no draft state at all, so applying the blanket
	 * `status = 'published'` predicate to them would either leak restricted
	 * content or index nothing whatsoever.
	 *
	 * @var array<string,array{table:string,id:string,columns:string,visibility:string}>
	 */
	const TABLE_SOURCES = array(
		'ceafsn_gf'  => array(
			'table'      => 'ceafsn_gf_grants',
			'id'         => 'grant_id',
			'columns'    => 'gf_columns',
			'visibility' => "status = 'published'",
		),
		'ceafsn_rf'  => array(
			'table'      => 'ceafsn_rf_fellowships',
			'id'         => 'fellowship_id',
			'columns'    => 'rf_columns',
			'visibility' => "status = 'published'",
		),
		'ceafsn_pp'  => array(
			'table'      => 'ceafsn_pp_publications',
			'id'         => 'publication_id',
			'columns'    => 'pp_columns',
			'visibility' => "status = 'published' AND access_level = 'public'",
		),
		'ceafsn_od'  => array(
			'table'      => 'ceafsn_od_datasets',
			'id'         => 'dataset_id',
			'columns'    => 'od_columns',
			'visibility' => "status = 'published'",
		),
		'ceafsn_np'  => array(
			'table'      => 'ceafsn_np_policies',
			'id'         => 'policy_id',
			'columns'    => 'np_columns',
			'visibility' => "status = 'published'",
		),
		'ceafsn_med' => array(
			'table'      => 'ceafsn_med_projects',
			'id'         => 'project_id',
			'columns'    => 'med_columns',
			'visibility' => '1 = 1',
		),
	);

	/**
	 * The other plugins' record actions, so an edit made in their screens is
	 * reflected in the index without waiting for the next bulk run.
	 *
	 * These fire at priority 1: every one of those handlers redirects and
	 * exits, so a later callback would never run. The work itself is deferred
	 * to shutdown, by which point the row has been written.
	 *
	 * @var array<string,array{type:string,id:string,event:string}>
	 */
	const RECORD_HOOKS = array(
		'admin_post_ceafsn_gf_save_grant'        => array( 'type' => 'ceafsn_gf',  'id' => 'grant_id',       'event' => 'save' ),
		'admin_post_ceafsn_gf_delete_grant'      => array( 'type' => 'ceafsn_gf',  'id' => 'grant_id',       'event' => 'delete' ),
		'admin_post_ceafsn_rf_save_fellowship'   => array( 'type' => 'ceafsn_rf',  'id' => 'fellowship_id',  'event' => 'save' ),
		'admin_post_ceafsn_rf_delete_fellowship' => array( 'type' => 'ceafsn_rf',  'id' => 'fellowship_id',  'event' => 'delete' ),
		'admin_post_ceafsn_pp_save_publication'  => array( 'type' => 'ceafsn_pp',  'id' => 'publication_id', 'event' => 'save' ),
		'admin_post_ceafsn_pp_delete_publication' => array( 'type' => 'ceafsn_pp', 'id' => 'publication_id', 'event' => 'delete' ),
		'admin_post_ceafsn_od_save_dataset'      => array( 'type' => 'ceafsn_od',  'id' => 'dataset_id',     'event' => 'save' ),
		'admin_post_ceafsn_od_delete_dataset'    => array( 'type' => 'ceafsn_od',  'id' => 'dataset_id',     'event' => 'delete' ),
		'admin_post_ceafsn_np_save_policy'       => array( 'type' => 'ceafsn_np',  'id' => 'policy_id',      'event' => 'save' ),
		'admin_post_ceafsn_np_delete_policy'     => array( 'type' => 'ceafsn_np',  'id' => 'policy_id',      'event' => 'delete' ),
		'admin_post_ceafsn_med_save_project'     => array( 'type' => 'ceafsn_med', 'id' => 'project_id',     'event' => 'save' ),
		'admin_post_ceafsn_med_delete_project'   => array( 'type' => 'ceafsn_med', 'id' => 'project_id',     'event' => 'delete' ),
	);

	/**
	 * Record events waiting for the saving request to finish writing.
	 *
	 * @var array<int,array{type:string,id:int,event:string}>
	 */
	private static array $pending = array();

	/** @var bool Whether a shutdown callback is already registered. */
	private static bool $shutdown_queued = false;

	/**
	 * Index all content sources and return a summary.
	 *
	 * Indexing runs inside the web request that posts the form, so it cannot
	 * be allowed to run for as long as it likes: a first build over a few
	 * hundred records makes one embedding API call per chunk, and PHP's
	 * max_execution_time would kill the request half way through, leaving a
	 * silently truncated knowledge base. $max_seconds therefore caps the run
	 * and the caller is told to press the button again — every source wipes
	 * its own chunks before re-inserting, so repeating the run converges on a
	 * complete index instead of duplicating rows.
	 *
	 * @param bool $full_rebuild Delete existing chunks before indexing.
	 * @param int  $max_seconds  Stop cleanly after this many seconds. 0 = no cap.
	 * @return array{indexed: int, skipped: int, errors: int, sources: string[], partial: bool}
	 */
	public static function run( bool $full_rebuild = false, int $max_seconds = 0 ): array {
		$embed_provider = CEAFSN_AI_Providers::embeddings();

		$summary = array(
			'indexed' => 0,
			'skipped' => 0,
			'errors'  => 0,
			'sources' => array(),
			'partial' => false,
		);

		if ( null === $embed_provider ) {
			$summary['errors']++;
			$summary['sources'][] = __( 'No embeddings provider configured — add an API key.', 'ceafsn-ai' );
			return $summary;
		}

		if ( $full_rebuild ) {
			CEAFSN_AI_DB::delete_all_chunks();
		}

		// Build the list of content items to index.
		$items = self::collect_items();

		$started  = microtime( true );
		$deadline = $max_seconds > 0 ? $started + $max_seconds : 0.0;

		foreach ( $items as $index => $item ) {
			// Check between items, never in the middle of one, so a source is
			// either fully re-indexed or left as it was before this run.
			if ( 0.0 !== $deadline && microtime( true ) >= $deadline ) {
				$summary['partial'] = true;
				$summary['sources'] = array_unique( array_slice( array_column( $items, 'source_type' ), 0, $index ) );
				return $summary;
			}

			$result = self::index_item( $item, $embed_provider, $full_rebuild );
			$summary['indexed'] += $result['indexed'];
			$summary['skipped'] += $result['skipped'];
			$summary['errors']  += $result['errors'];
		}

		$summary['sources'] = array_unique( array_column( $items, 'source_type' ) );

		// A run that finished cleanly has caught up with every edit, including
		// the ones that arrived while no provider was configured. A partial or
		// erroring run has not, so the flag survives it and the Overview keeps
		// saying so.
		if ( ! $summary['partial'] && 0 === $summary['errors'] ) {
			self::clear_dirty();
		}

		return $summary;
	}

	// -------------------------------------------------------------------------
	// Content collection
	// -------------------------------------------------------------------------

	/**
	 * Gather all indexable content items from every source.
	 *
	 * Each item is a normalised array so the indexing loop stays source-agnostic.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_items(): array {
		$items = self::collect_wp_content();

		foreach ( self::TABLE_SOURCES as $source_type => $source ) {
			$items = array_merge(
				$items,
				self::collect_plugin_table(
					$source_type,
					$source['table'],
					$source['id'],
					self::{$source['columns']}(),
					$source['visibility']
				)
			);
		}

		return $items;
	}

	/**
	 * Collect published, publicly readable WordPress pages and posts.
	 *
	 * Two conditions beyond the status matter. A password-protected post is
	 * not readable by a visitor who has not given the password, so indexing
	 * its body would hand that text to anyone who asks the assistant. A post
	 * that is not published is not on the site either.
	 *
	 * @param int|null $only_id Collect just this post. 0 or null = all of them.
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_wp_content( ?int $only_id = null ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT ID, post_title, post_content, post_type, post_name
			 FROM `{$wpdb->posts}`
			 WHERE post_status = 'publish'
			   AND post_type IN ('post','page')
			   AND post_password = ''";

		if ( null !== $only_id && $only_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql .= $wpdb->prepare( ' AND ID = %d', $only_id );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql . ' ORDER BY ID ASC' ) ?: array();

		$items = array();

		foreach ( $rows as $row ) {
			$text = trim( wp_strip_all_tags( (string) $row->post_title ) )
				. "\n"
				. trim( wp_strip_all_tags( (string) $row->post_content ) );

			$items[] = array(
				'source_type'  => 'wp_' . (string) $row->post_type,
				'source_id'    => (int) $row->ID,
				'source_label' => (string) $row->post_title,
				'source_url'   => (string) get_permalink( (int) $row->ID ),
				'lang'         => 'en',
				'text'         => $text,
			);
		}

		return $items;
	}

	/**
	 * Collect publicly readable records from one CE-AFSN plugin table.
	 *
	 * @param string               $source_type Source type key.
	 * @param string               $table_suffix Table name without prefix.
	 * @param string               $id_column Primary key column name.
	 * @param array<string,string> $columns Map of column => label (used in text assembly).
	 * @param string               $visibility SQL predicate for "a visitor may read this".
	 * @param int|null             $only_id Collect just this record. 0 or null = all of them.
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_plugin_table(
		string $source_type,
		string $table_suffix,
		string $id_column,
		array $columns,
		string $visibility = "status = 'published'",
		?int $only_id = null
	): array {
		global $wpdb;

		$table = $wpdb->prefix . $table_suffix;

		// Check the table exists before querying it. The name is escaped for
		// LIKE because a table prefix is full of underscores, and an unescaped
		// underscore matches any single character.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) )
		);
		if ( strtolower( (string) $exists ) !== strtolower( $table ) ) {
			return array();
		}

		// The predicate is a class constant, never request input, and both
		// identifiers below come from the same constant.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "SELECT * FROM `{$table}` WHERE ({$visibility})";

		if ( null !== $only_id && $only_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql .= $wpdb->prepare( " AND {$id_column} = %d", $only_id );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $sql . " ORDER BY {$id_column} ASC" ) ?: array();

		$items = array();

		foreach ( $rows as $row ) {
			$parts = array();

			foreach ( $columns as $col => $label ) {
				$val = trim( wp_strip_all_tags( (string) ( $row->$col ?? '' ) ) );
				if ( '' !== $val ) {
					$parts[] = $label . ': ' . $val;
				}
			}

			$title = trim( wp_strip_all_tags( (string) ( $row->title ?? $row->name ?? $row->label ?? '' ) ) );
			$id    = (int) ( $row->$id_column ?? 0 );

			$items[] = array(
				'source_type'  => $source_type,
				'source_id'    => $id,
				'source_label' => $title,
				'source_url'   => self::source_url( $source_type ),
				'lang'         => 'en',
				'text'         => implode( "\n", $parts ),
			);
		}

		return $items;
	}

	// -------------------------------------------------------------------------
	// Keeping the index in step with the content
	// -------------------------------------------------------------------------

	/**
	 * Collect exactly one record, as the bulk collectors would have.
	 *
	 * @param string $source_type Source type key.
	 * @param int    $source_id   Record id.
	 * @return array<string,mixed>|null Null when the record does not exist or is not publicly visible.
	 */
	public static function collect_source( string $source_type, int $source_id ): ?array {
		if ( $source_id <= 0 ) {
			return null;
		}

		if ( 'wp_page' === $source_type || 'wp_post' === $source_type ) {
			$rows = self::collect_wp_content( $source_id );
		} elseif ( isset( self::TABLE_SOURCES[ $source_type ] ) ) {
			$source = self::TABLE_SOURCES[ $source_type ];
			$rows   = self::collect_plugin_table(
				$source_type,
				$source['table'],
				$source['id'],
				self::{$source['columns']}(),
				$source['visibility'],
				$source_id
			);
		} else {
			return null;
		}

		foreach ( $rows as $row ) {
			if ( (int) ( $row['source_id'] ?? 0 ) === $source_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Drop the indexed chunks of one record, or of a whole source type.
	 *
	 * @param string $source_type Source type key.
	 * @param int    $source_id   Record id. 0 drops every record of this type.
	 * @return void
	 */
	public static function invalidate( string $source_type, int $source_id = 0 ): void {
		if ( $source_id > 0 ) {
			CEAFSN_AI_DB::delete_chunks( $source_type, $source_id );
			return;
		}

		CEAFSN_AI_DB::delete_chunks_by_type( $source_type );
	}

	/**
	 * Re-index one record immediately, or report that a bulk run is needed.
	 *
	 * The caller is a save handler, so the sequence matters: the old chunks go
	 * first and the new ones are written afterwards, and the method never
	 * returns true having left stale text behind. Anything this cannot finish
	 * — no provider configured, a document too large for one request, an
	 * embedding that failed — is reported as false so the caller can flag the
	 * index as stale rather than let it answer from outdated content.
	 *
	 * @param string $source_type Source type key.
	 * @param int    $source_id   Record id.
	 * @return bool True when the index now matches the record.
	 */
	public static function refresh_source( string $source_type, int $source_id ): bool {
		$embed_provider = CEAFSN_AI_Providers::embeddings();
		$item           = self::collect_source( $source_type, $source_id );

		// Nothing visible to index: a draft, a members-only publication, a
		// password-protected page, or a record that is simply gone. Whatever
		// was stored for it must go, because a visitor must not be able to
		// retrieve it through the assistant.
		$text = ( null === $item ) ? '' : trim( (string) ( $item['text'] ?? '' ) );
		if ( null === $item || '' === $text ) {
			self::invalidate( $source_type, $source_id );
			return null !== $item;
		}

		if ( null === $embed_provider ) {
			self::invalidate( $source_type, $source_id );
			return false;
		}

		if ( count( CEAFSN_AI_DB::split_into_chunks( $text ) ) > self::REFRESH_MAX_CHUNKS ) {
			self::invalidate( $source_type, $source_id );
			return false;
		}

		$result = self::index_item( $item, $embed_provider, false );
		return 0 === $result['errors'];
	}

	// -------------------------------------------------------------------------
	// Staleness flag
	// -------------------------------------------------------------------------

	/**
	 * Record that content changed in a way the index has not absorbed.
	 *
	 * @return void
	 */
	public static function mark_dirty(): void {
		$state = (array) get_option( self::DIRTY_OPTION, array() );
		$first = (int) ( $state['first'] ?? 0 );

		update_option(
			self::DIRTY_OPTION,
			array(
				'count' => (int) ( $state['count'] ?? 0 ) + 1,
				'first' => $first > 0 ? $first : time(),
				'last'  => time(),
			),
			false
		);
	}

	/**
	 * Read the staleness flag for display.
	 *
	 * @return array{count:int,first:int,last:int}
	 */
	public static function dirty_state(): array {
		$state = (array) get_option( self::DIRTY_OPTION, array() );

		return array(
			'count' => (int) ( $state['count'] ?? 0 ),
			'first' => (int) ( $state['first'] ?? 0 ),
			'last'  => (int) ( $state['last'] ?? 0 ),
		);
	}

	/**
	 * Clear the staleness flag once a run has caught up.
	 *
	 * @return void
	 */
	public static function clear_dirty(): void {
		delete_option( self::DIRTY_OPTION );
	}

	// -------------------------------------------------------------------------
	// Content hooks
	// -------------------------------------------------------------------------

	/**
	 * Register the hooks that keep the index in step with saved content.
	 *
	 * Called once from the plugin bootstrap. The record hooks belong to the
	 * other six plugins: they fire regardless of whether those plugins are
	 * active, because a hook nobody fires costs nothing.
	 *
	 * @return void
	 */
	public static function register_hooks(): void {
		// Priority 20 so any content another plugin rewrites on save at the
		// default priority has already been written when we read the row.
		add_action( 'save_post', array( __CLASS__, 'on_post_saved' ), 20, 2 );
		add_action( 'trashed_post', array( __CLASS__, 'on_post_removed' ), 10, 2 );
		add_action( 'deleted_post', array( __CLASS__, 'on_post_removed' ), 10, 2 );

		foreach ( self::RECORD_HOOKS as $record_hook => $record ) {
			add_action(
				$record_hook,
				static function () use ( $record ): void {
					CEAFSN_AI_Indexer::queue_record_event( $record['type'], $record['id'], $record['event'] );
				},
				1
			);
		}
	}

	/**
	 * Re-index a page or post after it is saved.
	 *
	 * Duck-typed on purpose: save_post runs for every post type and every
	 * plugin's CPT, and the hook is exercised by code that does not build a
	 * WP_Post. Anything that is not a published page or post is simply not
	 * indexed, so it is left out of the index rather than described.
	 *
	 * @param int          $post_id Post id.
	 * @param object|array $post    The post that was saved, when the caller passes it.
	 * @return void
	 */
	public static function on_post_saved( $post_id, $post = null ): void {
		if ( ! is_object( $post ) || ! isset( $post->post_type ) ) {
			return;
		}

		$source_type = 'wp_' . (string) $post->post_type;
		if ( 'wp_page' !== $source_type && 'wp_post' !== $source_type ) {
			return;
		}

		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( 'publish' === (string) ( $post->post_status ?? '' ) ) {
			if ( ! self::refresh_source( $source_type, $post_id ) ) {
				self::mark_dirty();
			}
			return;
		}

		self::invalidate( $source_type, $post_id );
	}

	/**
	 * Remove a page or post from the index when it is trashed or deleted.
	 *
	 * @param int          $post_id Post id.
	 * @param object|array $post    The post, when the caller passes it.
	 * @return void
	 */
	public static function on_post_removed( $post_id, $post = null ): void {
		$post_id = (int) $post_id;
		if ( $post_id <= 0 ) {
			return;
		}

		if ( is_object( $post ) && isset( $post->post_type ) ) {
			$source_type = 'wp_' . (string) $post->post_type;
			if ( 'wp_page' !== $source_type && 'wp_post' !== $source_type ) {
				return;
			}
			self::invalidate( $source_type, $post_id );
			return;
		}

		// No post object: trashed_post only passes an id. Both targeted
		// deletes are issued, and each is a no-op for the type the id is not.
		self::invalidate( 'wp_page', $post_id );
		self::invalidate( 'wp_post', $post_id );
	}

	/**
	 * Note a record save or delete from another plugin for the shutdown pass.
	 *
	 * Priority 1, because those handlers redirect and exit: a callback at the
	 * default priority would never run. The row is read at shutdown, after
	 * they have written it.
	 *
	 * @param string $source_type Source type key.
	 * @param string $id_field    POST or GET field carrying the record id.
	 * @param string $event       Either save or delete.
	 * @return void
	 */
	public static function queue_record_event( string $source_type, string $id_field, string $event ): void {
		$raw = $_POST[ $id_field ] ?? $_GET[ $id_field ] ?? 0;

		self::$pending[] = array(
			'type'  => $source_type,
			'id'    => is_scalar( $raw ) ? absint( $raw ) : 0,
			'event' => $event,
		);

		if ( self::$shutdown_queued ) {
			return;
		}

		self::$shutdown_queued = true;
		add_action( 'shutdown', array( __CLASS__, 'flush_pending_events' ) );
	}

	/**
	 * Apply the record events queued during the request that just ended.
	 *
	 * @return void
	 */
	public static function flush_pending_events(): void {
		$pending             = self::$pending;
		self::$pending       = array();
		self::$shutdown_queued = false;

		foreach ( $pending as $event ) {
			$source_type = (string) $event['type'];
			$source_id   = (int) $event['id'];

			if ( 'delete' === $event['event'] ) {
				if ( $source_id > 0 ) {
					self::invalidate( $source_type, $source_id );
				} else {
					// The id never reached us. The whole type is dropped; a
					// bulk index rebuilds every record that is still there.
					self::invalidate( $source_type );
					self::mark_dirty();
				}
				continue;
			}

			if ( $source_id > 0 ) {
				if ( ! self::refresh_source( $source_type, $source_id ) ) {
					self::mark_dirty();
				}
				continue;
			}

			// A new record with no id in the request: nothing stale is stored
			// for it, but the index does not have it either.
			self::mark_dirty();
		}
	}

	// -------------------------------------------------------------------------
	// Per-item indexing
	// -------------------------------------------------------------------------

	/**
	 * Split one content item into chunks, embed each, and persist.
	 *
	 * @param array<string,mixed>   $item           Normalised content item.
	 * @param CEAFSN_AI_Provider    $embed_provider Embeddings provider.
	 * @param bool                  $full_rebuild   If true, chunks were already wiped globally.
	 * @return array{indexed: int, skipped: int, errors: int}
	 */
	private static function index_item( array $item, CEAFSN_AI_Provider $embed_provider, bool $full_rebuild ): array {
		$result = array( 'indexed' => 0, 'skipped' => 0, 'errors' => 0 );

		$text = (string) ( $item['text'] ?? '' );
		if ( '' === trim( $text ) ) {
			$result['skipped']++;
			return $result;
		}

		// On incremental runs wipe existing chunks for this source so stale
		// content does not accumulate.
		if ( ! $full_rebuild ) {
			CEAFSN_AI_DB::delete_chunks( (string) $item['source_type'], (int) $item['source_id'] );
		}

		$chunks = CEAFSN_AI_DB::split_into_chunks( $text );

		foreach ( $chunks as $chunk_text ) {
			usleep( self::RATE_LIMIT_PAUSE_US );

			$vector = $embed_provider->embed( $chunk_text );

			if ( null === $vector ) {
				$result['errors']++;
				continue;
			}

			$inserted = CEAFSN_AI_DB::insert_chunk(
				array(
					'source_type'  => $item['source_type'],
					'source_id'    => $item['source_id'],
					'source_label' => $item['source_label'],
					'source_url'   => $item['source_url'],
					'lang'         => $item['lang'],
					'chunk_text'   => $chunk_text,
					'embedding'    => $vector,
					'provider'     => $embed_provider->key(),
				)
			);

			if ( false === $inserted ) {
				$result['errors']++;
			} else {
				$result['indexed']++;
			}
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Column maps — what fields to include in the indexed text per plugin
	// -------------------------------------------------------------------------

	/** @return array<string,string> */
	private static function gf_columns(): array {
		return array(
			'title'                => __( 'Title', 'ceafsn-ai' ),
			'eligibility'          => __( 'Eligibility', 'ceafsn-ai' ),
			'funding_institution'  => __( 'Funding institution', 'ceafsn-ai' ),
			'target_beneficiaries' => __( 'Target beneficiaries', 'ceafsn-ai' ),
			'grant_status'         => __( 'Status', 'ceafsn-ai' ),
			'deadline'             => __( 'Deadline', 'ceafsn-ai' ),
		);
	}

	/** @return array<string,string> */
	private static function rf_columns(): array {
		return array(
			'title'           => __( 'Title', 'ceafsn-ai' ),
			'track_domain'    => __( 'Research track', 'ceafsn-ai' ),
			'eligibility'     => __( 'Eligibility', 'ceafsn-ai' ),
			'host_supervisor' => __( 'Host / Supervisor', 'ceafsn-ai' ),
			'duration'        => __( 'Duration', 'ceafsn-ai' ),
		);
	}

	/** @return array<string,string> */
	private static function pp_columns(): array {
		return array(
			'title'              => __( 'Title', 'ceafsn-ai' ),
			'executive_summary'  => __( 'Summary', 'ceafsn-ai' ),
			'author_institution' => __( 'Authors / Institution', 'ceafsn-ai' ),
			'content_type'       => __( 'Type', 'ceafsn-ai' ),
			'publication_date'   => __( 'Publication date', 'ceafsn-ai' ),
			'doi_citation'       => __( 'DOI / Citation', 'ceafsn-ai' ),
		);
	}

	/** @return array<string,string> */
	private static function od_columns(): array {
		return array(
			'name'          => __( 'Name', 'ceafsn-ai' ),
			'description'   => __( 'Description', 'ceafsn-ai' ),
			'coverage_area' => __( 'Coverage area', 'ceafsn-ai' ),
			'category'      => __( 'Category', 'ceafsn-ai' ),
			'data_license'  => __( 'License', 'ceafsn-ai' ),
		);
	}

	/** @return array<string,string> */
	private static function np_columns(): array {
		return array(
			'title'                => __( 'Title', 'ceafsn-ai' ),
			'description'          => __( 'Description', 'ceafsn-ai' ),
			'authoring_institution' => __( 'Authoring institution', 'ceafsn-ai' ),
			'topic'                => __( 'Topic', 'ceafsn-ai' ),
			'publication_date'     => __( 'Publication date', 'ceafsn-ai' ),
		);
	}

	/** @return array<string,string> */
	private static function med_columns(): array {
		return array(
			'title'                  => __( 'Title', 'ceafsn-ai' ),
			'principal_investigator' => __( 'Principal investigator', 'ceafsn-ai' ),
			'target_region'          => __( 'Target region', 'ceafsn-ai' ),
			'status'                 => __( 'Status', 'ceafsn-ai' ),
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Map a source type to the canonical front-end URL for that content type.
	 *
	 * @param string $source_type Source type key.
	 * @return string URL or empty string.
	 */
	private static function source_url( string $source_type ): string {
		$map = array(
			'ceafsn_gf'  => home_url( '/scholarships-grants/' ),
			'ceafsn_rf'  => home_url( '/research-fellowships/' ),
			'ceafsn_pp'  => home_url( '/publications/' ),
			'ceafsn_od'  => home_url( '/open-datasets/' ),
			'ceafsn_np'  => home_url( '/nutrition-policy-modeling/' ),
			'ceafsn_med' => home_url( '/me-dashboard/' ),
		);

		return $map[ $source_type ] ?? '';
	}
}
