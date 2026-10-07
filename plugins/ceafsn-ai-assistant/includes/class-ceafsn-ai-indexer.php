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
		$items = array();

		$items = array_merge( $items, self::collect_wp_content() );
		$items = array_merge( $items, self::collect_plugin_table( 'ceafsn_gf',  'ceafsn_gf_grants',        'grant_id',       self::gf_columns() ) );
		$items = array_merge( $items, self::collect_plugin_table( 'ceafsn_rf',  'ceafsn_rf_fellowships',   'fellowship_id',  self::rf_columns() ) );
		$items = array_merge( $items, self::collect_plugin_table( 'ceafsn_pp',  'ceafsn_pp_publications',  'publication_id', self::pp_columns() ) );
		$items = array_merge( $items, self::collect_plugin_table( 'ceafsn_od',  'ceafsn_od_datasets',      'dataset_id',     self::od_columns() ) );
		$items = array_merge( $items, self::collect_plugin_table( 'ceafsn_np',  'ceafsn_np_policies',      'policy_id',      self::np_columns() ) );
		$items = array_merge( $items, self::collect_plugin_table( 'ceafsn_med', 'ceafsn_med_projects',     'project_id',     self::med_columns() ) );

		return $items;
	}

	/**
	 * Collect published WordPress pages and posts.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_wp_content(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_content, post_type, post_name
			 FROM `{$wpdb->posts}`
			 WHERE post_status = 'publish'
			   AND post_type IN ('post','page')
			 ORDER BY ID ASC"
		) ?: array();

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
	 * Collect published records from one CE-AFSN plugin table.
	 *
	 * @param string               $source_type Source type key.
	 * @param string               $table_suffix Table name without prefix.
	 * @param string               $id_column Primary key column name.
	 * @param array<string,string> $columns Map of column => label (used in text assembly).
	 * @return array<int,array<string,mixed>>
	 */
	private static function collect_plugin_table(
		string $source_type,
		string $table_suffix,
		string $id_column,
		array $columns
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT * FROM `{$table}` WHERE status = 'published' ORDER BY {$id_column} ASC"
		) ?: array();

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
			'description'          => __( 'Description', 'ceafsn-ai' ),
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
			'description'     => __( 'Description', 'ceafsn-ai' ),
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
