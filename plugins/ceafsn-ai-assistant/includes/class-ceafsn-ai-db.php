<?php
/**
 * Database schema and query layer for CE-AFSN AI Assistant.
 *
 * Stores indexed content chunks and their embedding vectors so the
 * query engine can find the most relevant context for any question
 * without a full-table scan.
 *
 * @package CEAFSN_AI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_AI_DB
 */
class CEAFSN_AI_DB {

	/** @var string Current schema version. */
	const SCHEMA_VERSION = '1.0.0';

	/** @var string Option that records the installed schema version. */
	const DB_VERSION_OPTION = 'ceafsn_ai_db_version';

	/**
	 * Maximum characters kept per chunk.
	 *
	 * Embedding APIs charge per token; most models cap at ~8 000 tokens
	 * per request. 800 characters ≈ 200 tokens — enough for one meaningful
	 * paragraph while staying well under the ceiling.
	 *
	 * @var int
	 */
	const CHUNK_SIZE = 800;

	/**
	 * Create or upgrade all plugin tables using dbDelta.
	 *
	 * Safe to call on every activation: dbDelta only alters tables when
	 * the definition has changed.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ---- Chunks ----
		// One row per content chunk. The embedding is stored as a JSON array
		// of floats. PHP serialises/deserialises it; MySQL stores it as TEXT.
		// A production installation may migrate this column to a dedicated
		// vector-search engine (pgvector, Pinecone, Qdrant) without touching
		// the rest of the plugin — that path is left open by keeping the
		// similarity math in PHP rather than SQL.
		$table = $wpdb->prefix . 'ceafsn_ai_chunks';
		$sql   = "CREATE TABLE {$table} (
			chunk_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source_type  VARCHAR(64)     NOT NULL DEFAULT '',
			source_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			source_label VARCHAR(255)    NOT NULL DEFAULT '',
			source_url   TEXT            NOT NULL DEFAULT '',
			lang         VARCHAR(8)      NOT NULL DEFAULT 'en',
			chunk_text   TEXT            NOT NULL,
			embedding    MEDIUMTEXT      NOT NULL DEFAULT '',
			provider     VARCHAR(32)     NOT NULL DEFAULT '',
			indexed_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (chunk_id),
			KEY source   (source_type, source_id),
			KEY lang     (lang),
			KEY provider (provider)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Apply pending schema migrations.
	 *
	 * Cheap no-op when there is nothing to do.
	 *
	 * @return bool True when a migration ran.
	 */
	public static function maybe_upgrade(): bool {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed, self::SCHEMA_VERSION, '>=' ) ) {
			return false;
		}

		self::create_tables();
		return true;
	}

	// -------------------------------------------------------------------------
	// Chunk writes
	// -------------------------------------------------------------------------

	/**
	 * Delete all chunks for a given source so they can be re-indexed.
	 *
	 * @param string $source_type E.g. 'post', 'ceafsn_gf', 'ceafsn_rf'.
	 * @param int    $source_id   Record ID within that source type.
	 * @return int|false Rows deleted or false on failure.
	 */
	public static function delete_chunks( string $source_type, int $source_id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_ai_chunks',
			array(
				'source_type' => $source_type,
				'source_id'   => $source_id,
			),
			array( '%s', '%d' )
		);
	}

	/**
	 * Delete all chunks for a source type (e.g. wipe and re-index a whole plugin).
	 *
	 * @param string $source_type Source type key.
	 * @return int|false
	 */
	public static function delete_chunks_by_type( string $source_type ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_ai_chunks',
			array( 'source_type' => $source_type ),
			array( '%s' )
		);
	}

	/**
	 * Wipe the entire index.
	 *
	 * @return int|false
	 */
	public static function delete_all_chunks(): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- intentional full wipe.
		return $wpdb->query( "DELETE FROM `{$wpdb->prefix}ceafsn_ai_chunks`" );
	}

	/**
	 * Insert one chunk with its embedding vector.
	 *
	 * @param array<string,mixed> $data {
	 *   @type string $source_type  Source type key.
	 *   @type int    $source_id    Source record ID.
	 *   @type string $source_label Human-readable title/label of the source.
	 *   @type string $source_url   URL to the source page.
	 *   @type string $lang         Language code, 'en' or 'pt'.
	 *   @type string $chunk_text   The text content of this chunk.
	 *   @type float[] $embedding   Vector returned by the embeddings API.
	 *   @type string $provider     Provider key, e.g. 'openai'.
	 * }
	 * @return int|false New chunk_id or false.
	 */
	public static function insert_chunk( array $data ): int|false {
		global $wpdb;

		$embedding = $data['embedding'] ?? array();
		$json      = is_array( $embedding ) && ! empty( $embedding )
			? wp_json_encode( $embedding )
			: '';

		$result = $wpdb->insert(
			$wpdb->prefix . 'ceafsn_ai_chunks',
			array(
				'source_type'  => sanitize_key( $data['source_type'] ?? '' ),
				'source_id'    => (int) ( $data['source_id'] ?? 0 ),
				'source_label' => sanitize_text_field( $data['source_label'] ?? '' ),
				'source_url'   => esc_url_raw( $data['source_url'] ?? '' ),
				'lang'         => sanitize_key( $data['lang'] ?? 'en' ),
				'chunk_text'   => sanitize_textarea_field( $data['chunk_text'] ?? '' ),
				'embedding'    => (string) $json,
				'provider'     => sanitize_key( $data['provider'] ?? '' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	// -------------------------------------------------------------------------
	// Chunk reads
	// -------------------------------------------------------------------------

	/**
	 * Fetch all chunks that have an embedding, optionally filtered by provider.
	 *
	 * Used by the query engine to build the candidate set for cosine search.
	 * A LIMIT keeps memory bounded; 2 000 chunks at ~1 536 floats each is
	 * ~47 MB — acceptable for a low-traffic research site.
	 *
	 * @param string $provider Optional provider filter (e.g. 'openai').
	 * @param int    $limit    Maximum rows to load. Default 2000.
	 * @return array<int,object>
	 */
	public static function get_chunks_with_embeddings( string $provider = '', int $limit = 2000 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ceafsn_ai_chunks';

		if ( '' !== $provider ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT chunk_id, source_label, source_url, lang, chunk_text, embedding
					 FROM `{$table}`
					 WHERE provider = %s AND embedding != ''
					 LIMIT %d",
					$provider,
					$limit
				)
			) ?: array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT chunk_id, source_label, source_url, lang, chunk_text, embedding
				 FROM `{$table}`
				 WHERE embedding != ''
				 LIMIT %d",
				$limit
			)
		) ?: array();
	}

	/**
	 * Chunk counts grouped by the embeddings provider that produced them.
	 *
	 * Embeddings from two vendors are not comparable — the dimensions and the
	 * meaning of each axis differ — so a query only ever scores chunks from a
	 * single provider. When a site switches provider, the old rows become
	 * invisible rather than wrong, and this count is what tells the operator
	 * to rebuild instead of leaving them wondering why answers stopped.
	 *
	 * @return array<string,int> Provider key => chunk count.
	 */
	public static function provider_breakdown(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT provider, COUNT(*) AS n FROM `{$wpdb->prefix}ceafsn_ai_chunks` GROUP BY provider"
		) ?: array();

		$out = array();
		foreach ( $rows as $row ) {
			$out[ (string) $row->provider ] = (int) $row->n;
		}

		return $out;
	}

	/**
	 * Total number of indexed chunks.
	 *
	 * @return int
	 */
	public static function count_chunks(): int {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$wpdb->prefix}ceafsn_ai_chunks`" );
	}

	/**
	 * Count chunks that have an embedding stored.
	 *
	 * @return int
	 */
	public static function count_embedded_chunks(): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$wpdb->prefix}ceafsn_ai_chunks` WHERE embedding != ''"
		);
	}

	/**
	 * Most recent indexed_at timestamp.
	 *
	 * @return string ISO datetime or empty string when nothing is indexed.
	 */
	public static function last_indexed_at(): string {
		global $wpdb;

		return (string) ( $wpdb->get_var(
			"SELECT MAX(indexed_at) FROM `{$wpdb->prefix}ceafsn_ai_chunks`"
		) ?? '' );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Split a long string into overlapping chunks of at most CHUNK_SIZE chars.
	 *
	 * A 10 % overlap ensures a sentence that straddles a boundary is not lost.
	 *
	 * @param string $text Full text to split.
	 * @return string[] Chunks.
	 */
	public static function split_into_chunks( string $text ): array {
		$text = trim( $text );
		if ( '' === $text ) {
			return array();
		}

		$size    = self::CHUNK_SIZE;
		$overlap = (int) round( $size * 0.10 );
		$chunks  = array();
		$len     = mb_strlen( $text );
		$start   = 0;

		while ( $start < $len ) {
			$chunk = mb_substr( $text, $start, $size );
			if ( '' !== trim( $chunk ) ) {
				$chunks[] = $chunk;
			}
			// Advance by size minus overlap so consecutive chunks share context.
			$start += $size - $overlap;
		}

		return $chunks;
	}
}
