<?php
/**
 * Database schema and query layer for CE-AFSN Research Fellowships.
 *
 * Two distinct concepts are stored in two distinct columns, because they answer
 * different questions and collapsing them is what makes listings lie:
 *
 *   - `status`        Is this record visible at all? draft / published / archived.
 *   - `status_manual` What did the administrator say, on a record with no dates?
 *                     open / upcoming / closed / archived.
 *
 * What a visitor actually sees is derived from the dates by CEAFSN_RF_Status,
 * never read straight from either column.
 *
 * @package CEAFSN_RF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_RF_DB
 */
class CEAFSN_RF_DB {

	/** @var string Current schema version stored in options. */
	const SCHEMA_VERSION = '1.0.0';

	/** @var string Table name without the site prefix. */
	const TABLE_SUFFIX = 'ceafsn_rf_fellowships';

	/**
	 * Columns the front end is allowed to sort by.
	 *
	 * @return array<int,string>
	 */
	public static function sortable_columns(): array {
		return array( 'title', 'closing_date', 'opening_date' );
	}

	/**
	 * All valid record states.
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
	 * Create or upgrade the fellowships table using dbDelta.
	 *
	 * Safe to call on every activation; dbDelta only makes changes when needed.
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table    = $wpdb->prefix . self::TABLE_SUFFIX;
		$statuses = implode( ',', array_map( static fn( string $s ): string => "'{$s}'", self::statuses() ) );
		$manual   = implode( ',', array_map( static fn( string $s ): string => "'{$s}'", CEAFSN_RF_Status::manual_values() ) );

		// Dates are nullable rather than zero-dated: a fellowship without a
		// closing date is a real state the plugin has to represent, and
		// '0000-00-00' is rejected outright by strict-mode MySQL.
		$sql = "CREATE TABLE {$table} (
			fellowship_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title             VARCHAR(255)    NOT NULL DEFAULT '',
			track_domain      VARCHAR(255)    NOT NULL DEFAULT '',
			duration          VARCHAR(100)    NOT NULL DEFAULT '',
			eligibility       TEXT            NOT NULL DEFAULT '',
			host_supervisor   VARCHAR(255)    NOT NULL DEFAULT '',
			stipend_info      TEXT            NOT NULL DEFAULT '',
			show_stipend      TINYINT(1)      NOT NULL DEFAULT 0,
			opening_date      DATE            NULL DEFAULT NULL,
			closing_date      DATE            NULL DEFAULT NULL,
			application_url   TEXT            NOT NULL DEFAULT '',
			call_pdf_id       BIGINT UNSIGNED NOT NULL DEFAULT 0,
			contact_email     VARCHAR(190)    NOT NULL DEFAULT '',
			show_contact      TINYINT(1)      NOT NULL DEFAULT 0,
			status_override   TINYINT(1)      NOT NULL DEFAULT 0,
			override_note     TEXT            NOT NULL DEFAULT '',
			status_manual     ENUM({$manual}) NOT NULL DEFAULT 'upcoming',
			status            ENUM({$statuses}) NOT NULL DEFAULT 'draft',
			created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by        BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (fellowship_id),
			KEY status (status),
			KEY status_manual (status_manual),
			KEY closing_date (closing_date),
			KEY opening_date (opening_date)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'ceafsn_rf_db_version', self::SCHEMA_VERSION );
	}

	// ---------------------------------------------------------------------------
	// Field mapping
	// ---------------------------------------------------------------------------

	/**
	 * Sanitise a raw field array into a column-ready row.
	 *
	 * Every enum is coerced against a fixed allow list, so a hand-crafted POST
	 * cannot write a value MySQL would silently coerce to an empty string.
	 *
	 * @param array<string,mixed> $data Raw field values.
	 * @return array<string,mixed>
	 */
	private static function prepare_row( array $data ): array {
		$status = (string) ( $data['status'] ?? 'draft' );
		$manual = (string) ( $data['status_manual'] ?? 'upcoming' );

		return array(
			'title'           => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'track_domain'    => sanitize_text_field( (string) ( $data['track_domain'] ?? '' ) ),
			'duration'        => sanitize_text_field( (string) ( $data['duration'] ?? '' ) ),
			'eligibility'     => sanitize_textarea_field( (string) ( $data['eligibility'] ?? '' ) ),
			'host_supervisor' => sanitize_text_field( (string) ( $data['host_supervisor'] ?? '' ) ),
			'stipend_info'    => sanitize_textarea_field( (string) ( $data['stipend_info'] ?? '' ) ),
			'show_stipend'    => empty( $data['show_stipend'] ) ? 0 : 1,
			'opening_date'    => self::normalize_optional_date( (string) ( $data['opening_date'] ?? '' ) ),
			'closing_date'    => self::normalize_optional_date( (string) ( $data['closing_date'] ?? '' ) ),
			'application_url' => esc_url_raw( (string) ( $data['application_url'] ?? '' ) ),
			'call_pdf_id'     => absint( $data['call_pdf_id'] ?? 0 ),
			'contact_email'   => sanitize_email( (string) ( $data['contact_email'] ?? '' ) ),
			'show_contact'    => empty( $data['show_contact'] ) ? 0 : 1,
			'status_override' => empty( $data['status_override'] ) ? 0 : 1,
			'override_note'   => sanitize_textarea_field( (string) ( $data['override_note'] ?? '' ) ),
			'status_manual'   => in_array( $manual, CEAFSN_RF_Status::manual_values(), true ) ? $manual : 'upcoming',
			'status'          => in_array( $status, self::statuses(), true ) ? $status : 'draft',
			'updated_by'      => get_current_user_id(),
		);
	}

	/**
	 * Column order and formats shared by insert and update.
	 *
	 * @return array<int,string>
	 */
	private static function row_formats(): array {
		return array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%d' );
	}

	/**
	 * Force a value into YYYY-MM-DD, or return null when there is no date.
	 *
	 * An empty date stays empty. Substituting today for a missing closing date
	 * would silently close an opportunity, and substituting a far-future date
	 * would silently keep it open forever.
	 *
	 * @param string $value Raw date.
	 * @return string|null Normalised date, or null when absent.
	 */
	public static function normalize_optional_date( string $value ): ?string {
		$value = trim( $value );

		if ( '' === $value || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return null;
		}

		// Reject dates that do not exist, e.g. 2025-02-30.
		$parts = array_map( 'intval', explode( '-', $value ) );
		if ( ! checkdate( $parts[1], $parts[2], $parts[0] ) ) {
			return null;
		}

		return $value;
	}

	// ---------------------------------------------------------------------------
	// CRUD
	// ---------------------------------------------------------------------------

	/**
	 * Insert a fellowship record. Returns the new row ID or false on failure.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function insert_fellowship( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_SUFFIX,
			self::prepare_row( $data ),
			self::row_formats()
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a fellowship record. Returns rows affected or false.
	 *
	 * @param int                 $id   Fellowship ID.
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function update_fellowship( int $id, array $data ): int|false {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . self::TABLE_SUFFIX,
			self::prepare_row( $data ),
			array( 'fellowship_id' => $id ),
			self::row_formats(),
			array( '%d' )
		);
	}

	/**
	 * Delete a fellowship record. The attached media is left in the library.
	 *
	 * @param int $id Fellowship ID.
	 * @return int|false Rows deleted or false.
	 */
	public static function delete_fellowship( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . self::TABLE_SUFFIX,
			array( 'fellowship_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single fellowship record by ID. Returns null when not found.
	 *
	 * @param int $id Fellowship ID.
	 * @return object|null
	 */
	public static function get_fellowship( int $id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE fellowship_id = %d LIMIT 1", $id )
		);
	}

	/**
	 * Get fellowship records with filtering, sorting, and pagination.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items: array<int,object>, total: int}
	 */
	public static function get_fellowships( array $args = array() ): array {
		global $wpdb;

		$table        = $wpdb->prefix . self::TABLE_SUFFIX;
		$where_parts  = array( '1=1' );
		$where_values = array();

		// Record state. The front end always restricts to published.
		$status = (string) ( $args['status'] ?? '' );
		if ( in_array( $status, self::statuses(), true ) ) {
			$where_parts[]  = 'status = %s';
			$where_values[] = $status;
		} elseif ( ! empty( $args['published_only'] ) ) {
			$where_parts[] = "status = 'published'";
		}

		// Track / domain filter, matched on the stored column rather than a
		// separate taxonomy the site does not have.
		$track = sanitize_text_field( (string) ( $args['track_domain'] ?? '' ) );
		if ( '' !== $track ) {
			$where_parts[]  = 'track_domain = %s';
			$where_values[] = $track;
		}

		// Free-text search across the fields a visitor would search by.
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like           = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[]  = '(title LIKE %s OR track_domain LIKE %s OR eligibility LIKE %s OR host_supervisor LIKE %s)';
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
		}

		// Sorting — the column is validated against a fixed allow list, so a
		// crafted query string cannot reach the ORDER BY clause.
		$orderby = (string) ( $args['orderby'] ?? 'closing_date' );
		$orderby = in_array( $orderby, self::sortable_columns(), true ) ? $orderby : 'closing_date';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'desc' ) ) ? 'ASC' : 'DESC';

		$where = implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from fixed fragments above.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", ...$where_values ) );

		$per_page = max( 1, (int) ( $args['per_page'] ?? 12 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$limit_values   = $where_values;
		$limit_values[] = $per_page;
		$limit_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby is allow-listed, $order is a literal.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order}, fellowship_id ASC LIMIT %d OFFSET %d",
				...$limit_values
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Every distinct track / domain that has a published record.
	 *
	 * @return array<int,string>
	 */
	public static function get_tracks(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$rows = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			"SELECT DISTINCT track_domain FROM `{$table}` WHERE status = 'published' AND track_domain <> '' ORDER BY track_domain ASC"
		);

		return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
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
		$result = self::get_fellowships( array( 'per_page' => 9999 ) );

		return $result['items'];
	}
}
