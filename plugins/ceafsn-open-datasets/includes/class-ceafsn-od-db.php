<?php
/**
 * Database schema and query layer for CE-AFSN Open Datasets.
 *
 * Handles table creation, upgrades, and all CRUD queries for dataset records.
 * Front-end queries never return a record that is not published, and never
 * return a record without a download route.
 *
 * @package CEAFSN_OD
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_OD_DB
 */
class CEAFSN_OD_DB {

	/** @var string Current schema version stored in options. */
	const SCHEMA_VERSION = '1.0.0';

	/** @var string Option that stores the opt-in uninstall flag. */
	const UNINSTALL_OPTION = 'ceafsn_od_uninstall_delete_data';

	/**
	 * Columns the front end is allowed to sort by.
	 *
	 * @return array<int,string>
	 */
	public static function sortable_columns(): array {
		return array( 'name', 'category', 'last_updated', 'file_type' );
	}

	/**
	 * All valid record statuses.
	 *
	 * @return array<int,string>
	 */
	public static function statuses(): array {
		return array( 'draft', 'published', 'archived' );
	}

	/**
	 * Human labels for each file type.
	 *
	 * @return array<string,string>
	 */
	public static function file_type_labels(): array {
		return array(
			'csv'   => __( 'CSV', 'ceafsn-od' ),
			'zip'   => __( 'ZIP', 'ceafsn-od' ),
			'xlsx'  => __( 'XLSX', 'ceafsn-od' ),
			'other' => __( 'Other', 'ceafsn-od' ),
		);
	}

	/**
	 * Human labels for each status.
	 *
	 * @return array<string,string>
	 */
	public static function status_labels(): array {
		return array(
			'draft'     => __( 'Draft', 'ceafsn-od' ),
			'published' => __( 'Published', 'ceafsn-od' ),
			'archived'  => __( 'Archived', 'ceafsn-od' ),
		);
	}

	// ---------------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------------

	/**
	 * Create or upgrade the dataset table using dbDelta.
	 *
	 * Safe to call on every activation; dbDelta only makes changes when needed.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . 'ceafsn_od_datasets';
		$sql   = "CREATE TABLE {$table} (
			dataset_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name                VARCHAR(255)    NOT NULL DEFAULT '',
			description         TEXT            NOT NULL DEFAULT '',
			category            VARCHAR(255)    NOT NULL DEFAULT '',
			coverage_area       VARCHAR(255)    NOT NULL DEFAULT '',
			last_updated        DATE            NOT NULL,
			file_attachment_id  BIGINT UNSIGNED NOT NULL DEFAULT 0,
			download_url        TEXT            NOT NULL DEFAULT '',
			file_type           ENUM('csv','zip','xlsx','other') NOT NULL DEFAULT 'csv',
			file_size           BIGINT UNSIGNED NOT NULL DEFAULT 0,
			data_license        VARCHAR(255)    NOT NULL DEFAULT '',
			methodology_url     TEXT            NOT NULL DEFAULT '',
			contact_owner       VARCHAR(255)    NOT NULL DEFAULT '',
			status              ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
			created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by          BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (dataset_id),
			KEY status (status),
			KEY last_updated (last_updated),
			KEY category (category)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'ceafsn_od_db_version', self::SCHEMA_VERSION );
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
		// Enum values are matched case-insensitively so a hand-edited import or
		// a value that slipped past sanitize_key() still lands on a valid enum.
		$status    = strtolower( trim( (string) ( $data['status'] ?? 'draft' ) ) );
		$file_type = strtolower( trim( (string) ( $data['file_type'] ?? 'csv' ) ) );

		return array(
			'name'               => sanitize_text_field( (string) ( $data['name'] ?? '' ) ),
			'description'        => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
			'category'           => sanitize_text_field( (string) ( $data['category'] ?? '' ) ),
			'coverage_area'      => sanitize_text_field( (string) ( $data['coverage_area'] ?? '' ) ),
			'last_updated'       => self::normalize_date( (string) ( $data['last_updated'] ?? '' ) ),
			'file_attachment_id' => absint( $data['file_attachment_id'] ?? 0 ),
			'download_url'       => esc_url_raw( (string) ( $data['download_url'] ?? '' ) ),
			'file_type'          => in_array( $file_type, CEAFSN_OD_Validator::FILE_TYPES, true ) ? $file_type : 'other',
			'file_size'          => absint( $data['file_size'] ?? 0 ),
			'data_license'       => sanitize_text_field( (string) ( $data['data_license'] ?? '' ) ),
			'methodology_url'    => esc_url_raw( (string) ( $data['methodology_url'] ?? '' ) ),
			'contact_owner'      => sanitize_text_field( (string) ( $data['contact_owner'] ?? '' ) ),
			'status'             => in_array( $status, self::statuses(), true ) ? $status : 'draft',
			'updated_by'         => get_current_user_id(),
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
	 * Column formats for $wpdb->insert() and $wpdb->update(), in the same order
	 * as prepare_row().
	 *
	 * @return array<int,string>
	 */
	private static function row_formats(): array {
		return array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%d' );
	}

	/**
	 * Insert a dataset record. Returns the new row ID or false on failure.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function insert_dataset( array $data ): int|false {
		global $wpdb;

		$row = self::prepare_row( $data );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->insert(), no cache layer.
		$result = $wpdb->insert( $wpdb->prefix . 'ceafsn_od_datasets', $row, self::row_formats() );

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a dataset record. Returns rows affected or false.
	 *
	 * @param int                 $id   Dataset ID.
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function update_dataset( int $id, array $data ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->update(), no cache layer.
		return $wpdb->update(
			$wpdb->prefix . 'ceafsn_od_datasets',
			self::prepare_row( $data ),
			array( 'dataset_id' => $id ),
			self::row_formats(),
			array( '%d' )
		);
	}

	/**
	 * Delete a dataset record. The attached media file is left in the library.
	 *
	 * @param int $id Dataset ID.
	 * @return int|false Rows deleted or false.
	 */
	public static function delete_dataset( int $id ): int|false {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->delete(), no cache layer.
		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_od_datasets',
			array( 'dataset_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single dataset record by ID. Returns null when not found.
	 *
	 * @param int $id Dataset ID.
	 * @return object|null
	 */
	public static function get_dataset( int $id ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->get_row(), table name only.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_od_datasets` WHERE dataset_id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Get dataset records with filtering, sorting, and pagination.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items: array<int,object>, total: int}
	 */
	public static function get_datasets( array $args = array() ): array {
		global $wpdb;

		$table        = $wpdb->prefix . 'ceafsn_od_datasets';
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

		// Category filter.
		$category = sanitize_text_field( (string) ( $args['category'] ?? '' ) );
		if ( '' !== $category ) {
			$where_parts[]  = 'category = %s';
			$where_values[] = $category;
		}

		// File type filter.
		$file_type = (string) ( $args['file_type'] ?? '' );
		if ( in_array( $file_type, CEAFSN_OD_Validator::FILE_TYPES, true ) ) {
			$where_parts[]  = 'file_type = %s';
			$where_values[] = $file_type;
		}

		// Free-text search across name, description, coverage, and license.
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like           = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[]  = '(name LIKE %s OR description LIKE %s OR coverage_area LIKE %s OR data_license LIKE %s)';
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
			$where_values[] = $like;
		}

		// Sorting — the column is validated against a fixed allow list.
		$orderby = (string) ( $args['orderby'] ?? 'last_updated' );
		$orderby = in_array( $orderby, self::sortable_columns(), true ) ? $orderby : 'last_updated';
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
				"SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order}, dataset_id ASC LIMIT %d OFFSET %d",
				...$limit_values
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Every distinct category currently in use, for the filter control.
	 *
	 * @return array<int,string>
	 */
	public static function get_categories(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'ceafsn_od_datasets';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $wpdb->get_results(), no cache layer.
			"SELECT DISTINCT category FROM `{$table}` WHERE status = 'published' AND category <> '' ORDER BY category ASC"
		) ?: array();

		return array_values( array_map( static fn( $row ): string => (string) $row->category, $rows ) );
	}

	/**
	 * The download URL for a record: the attached file if present, else the external URL.
	 *
	 * The attachment wins because this plugin controls that file. A record with
	 * neither returns an empty string, which callers must treat as "no download".
	 * The stored URL is passed through esc_url_raw(), so a row that somehow holds
	 * a javascript: or data: URL also resolves to "no download" rather than
	 * rendering a dangerous link.
	 *
	 * @param object $row Dataset row.
	 * @return string Download URL, or '' when the record offers none.
	 */
	public static function resolve_download_url( object $row ): string {
		$attachment_id = (int) ( $row->file_attachment_id ?? 0 );
		if ( $attachment_id > 0 ) {
			$url = wp_get_attachment_url( $attachment_id );
			return $url ? esc_url_raw( (string) $url ) : '';
		}

		return esc_url_raw( (string) ( $row->download_url ?? '' ) );
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
		$result = self::get_datasets( array( 'per_page' => 9999 ) );
		return $result['items'];
	}
}
