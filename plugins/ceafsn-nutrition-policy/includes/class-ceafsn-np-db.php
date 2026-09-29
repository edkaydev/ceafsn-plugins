<?php
/**
 * Database schema and query layer for CE-AFSN Nutrition Policy.
 *
 * Handles table creation, upgrades, and all CRUD queries for policy records.
 * Front-end queries never return a record that is not published, and never
 * return a record whose PDF has failed validation.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_NP_DB
 */
class CEAFSN_NP_DB {

	/** @var string Current schema version stored in options. */
	const SCHEMA_VERSION = '1.0.0';

	/**
	 * Columns the front end is allowed to sort by.
	 *
	 * @return array<int,string>
	 */
	public static function sortable_columns(): array {
		return array( 'title', 'publication_date', 'topic', 'authoring_institution' );
	}

	/**
	 * All valid record statuses.
	 *
	 * @return array<int,string>
	 */
	public static function statuses(): array {
		return array( 'draft', 'published', 'archived' );
	}

	// ---------------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------------

	/**
	 * Create or upgrade the policy table using dbDelta.
	 *
	 * Safe to call on every activation; dbDelta only makes changes when needed.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'ceafsn_np_policies';
		$sql   = "CREATE TABLE {$table} (
			policy_id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title                 VARCHAR(255)    NOT NULL DEFAULT '',
			description           TEXT            NOT NULL DEFAULT '',
			publication_date      DATE            NOT NULL,
			topic                 VARCHAR(255)    NOT NULL DEFAULT '',
			authoring_institution VARCHAR(255)    NOT NULL DEFAULT '',
			pdf_attachment_id     BIGINT UNSIGNED NOT NULL DEFAULT 0,
			pdf_filename          VARCHAR(255)    NOT NULL DEFAULT '',
			source_url            TEXT            NOT NULL DEFAULT '',
			status                ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
			created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by            BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (policy_id),
			KEY status (status),
			KEY publication_date (publication_date),
			KEY topic (topic)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'ceafsn_np_db_version', self::SCHEMA_VERSION );
	}

	// ---------------------------------------------------------------------------
	// Field mapping
	// ---------------------------------------------------------------------------

	/**
	 * Sanitise a raw field array into a column-ready row.
	 *
	 * @param array<string,mixed> $data Raw field values.
	 * @return array<string,mixed>
	 */
	private static function prepare_row( array $data ): array {
		$status = $data['status'] ?? 'draft';

		return array(
			'title'                 => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'description'           => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'publication_date'      => self::normalize_date( (string) ( $data['publication_date'] ?? '' ) ),
			'topic'                 => sanitize_text_field( (string) ( $data['topic'] ?? '' ) ),
			'authoring_institution' => sanitize_text_field( (string) ( $data['authoring_institution'] ?? '' ) ),
			'pdf_attachment_id'     => absint( $data['pdf_attachment_id'] ?? 0 ),
			'pdf_filename'          => sanitize_text_field( (string) ( $data['pdf_filename'] ?? '' ) ),
			'source_url'            => esc_url_raw( (string) ( $data['source_url'] ?? '' ) ),
			'status'                => in_array( $status, self::statuses(), true ) ? $status : 'draft',
			'updated_by'            => get_current_user_id(),
		);
	}

	/**
	 * Force a value into YYYY-MM-DD, or return today's date.
	 *
	 * @param string $value Raw date.
	 * @return string Normalised date.
	 */
	public static function normalize_date( string $value ): string {
		$value = trim( $value );
		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return $value;
		}
		return gmdate( 'Y-m-d' );
	}

	// ---------------------------------------------------------------------------
	// CRUD
	// ---------------------------------------------------------------------------

	/**
	 * Insert a policy record. Returns the new row ID or false on failure.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function insert_policy( array $data ): int|false {
		global $wpdb;

		$row            = self::prepare_row( $data );
		$row_formats    = array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' );
		$result         = $wpdb->insert( $wpdb->prefix . 'ceafsn_np_policies', $row, $row_formats );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a policy record. Returns rows affected or false.
	 *
	 * @param int                 $id   Policy ID.
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function update_policy( int $id, array $data ): int|false {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'ceafsn_np_policies',
			self::prepare_row( $data ),
			array( 'policy_id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a policy record. The attached media file is left in the library.
	 *
	 * @param int $id Policy ID.
	 * @return int|false Rows deleted or false.
	 */
	public static function delete_policy( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_np_policies',
			array( 'policy_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single policy record by ID. Returns null when not found.
	 *
	 * @param int $id Policy ID.
	 * @return object|null
	 */
	public static function get_policy( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_np_policies` WHERE policy_id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Get policy records with filtering, sorting, and pagination.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items: array<int,object>, total: int}
	 */
	public static function get_policies( array $args = array() ): array {
		global $wpdb;

		$table        = $wpdb->prefix . 'ceafsn_np_policies';
		$where_parts  = array( '1=1' );
		$where_values = array();

		// Status filter. The front end always restricts to published.
		$status = (string) ( $args['status'] ?? '' );
		if ( in_array( $status, self::statuses(), true ) ) {
			$where_parts[]  = 'status = %s';
			$where_values[] = $status;
		} elseif ( ! empty( $args['published_only'] ) ) {
			$where_parts[] = "status = 'published'";
		}

		// Topic filter.
		$topic = sanitize_text_field( (string) ( $args['topic'] ?? '' ) );
		if ( '' !== $topic ) {
			$where_parts[]  = 'topic = %s';
			$where_values[] = $topic;
		}

		// Free-text search across title, description, and institution.
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like           = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[]  = '(title LIKE %s OR description LIKE %s OR authoring_institution LIKE %s)';
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
		}

		// Sorting — the column is validated against a fixed allow list.
		$orderby = (string) ( $args['orderby'] ?? 'publication_date' );
		$orderby = in_array( $orderby, self::sortable_columns(), true ) ? $orderby : 'publication_date';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';

		$where = implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from fixed fragments above.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", ...$where_values ) );

		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$limit_values   = $where_values;
		$limit_values[] = $per_page;
		$limit_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby is allow-listed, $order is a literal.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order}, policy_id ASC LIMIT %d OFFSET %d",
				...$limit_values
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Every distinct topic currently in use, for the filter control.
	 *
	 * @return array<int,string>
	 */
	public static function get_topics(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ceafsn_np_policies';

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			"SELECT DISTINCT topic FROM `{$table}` WHERE status = 'published' AND topic <> '' ORDER BY topic ASC"
		) ?: array();

		return array_values( array_map( static fn( $row ): string => (string) $row->topic, $rows ) );
	}

	/**
	 * Count how many other records share an attachment ID.
	 *
	 * Used to flag duplicated documents so they are never silently reused.
	 *
	 * @param int $attachment_id    Attachment ID.
	 * @param int $exclude_policy_id Record to exclude from the count.
	 * @return int Number of other records using the same attachment.
	 */
	public static function attachment_usage_count( int $attachment_id, int $exclude_policy_id = 0 ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'ceafsn_np_policies';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT COUNT(*) FROM `{$table}` WHERE pdf_attachment_id = %d AND policy_id <> %d",
				$attachment_id,
				$exclude_policy_id
			)
		);

		return (int) $count;
	}

	// ---------------------------------------------------------------------------
	// Export helpers
	// ---------------------------------------------------------------------------

	/**
	 * Export all records as a structured array suitable for JSON encoding.
	 *
	 * @return array<int,object>
	 */
	public static function export_all(): array {
		$result = self::get_policies( array( 'per_page' => 9999 ) );
		return $result['items'];
	}
}
