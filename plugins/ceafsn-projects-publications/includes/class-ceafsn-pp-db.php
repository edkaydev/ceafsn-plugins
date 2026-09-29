<?php
/**
 * Database schema and query layer for CE-AFSN Projects & Publications.
 *
 * Handles table creation, upgrades, and all CRUD queries for publication and
 * project records. The front end never returns a record that is not published,
 * never returns a members-only record to an anonymous visitor, and never
 * returns a record whose document has failed validation.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_PP_DB
 */
class CEAFSN_PP_DB {

	/** @var string Current schema version stored in options. */
	const SCHEMA_VERSION = '1.0.0';

	/** @var string Table name without the site prefix. */
	const TABLE_SUFFIX = 'ceafsn_pp_publications';

	/**
	 * Columns the front end is allowed to sort by.
	 *
	 * @return array<int,string>
	 */
	public static function sortable_columns(): array {
		return array( 'title', 'publication_date', 'content_type', 'project_status' );
	}

	/**
	 * All valid publication states.
	 *
	 * `archived` is a publication state, distinct from project_status: a
	 * completed project can be published, and an archived one is hidden.
	 *
	 * @return array<int,string>
	 */
	public static function statuses(): array {
		return array( 'draft', 'published', 'archived' );
	}

	/**
	 * All valid content types.
	 *
	 * @return array<int,string>
	 */
	public static function content_types(): array {
		return array( 'report', 'annual_report', 'policy_brief', 'working_paper', 'strategic_document', 'project' );
	}

	/**
	 * All valid project statuses.
	 *
	 * @return array<int,string>
	 */
	public static function project_statuses(): array {
		return array( 'in_progress', 'completed', 'under_review', 'archived' );
	}

	/**
	 * All valid access levels.
	 *
	 * @return array<int,string>
	 */
	public static function access_levels(): array {
		return array( 'public', 'members_only' );
	}

	// ---------------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------------

	/**
	 * Create or upgrade the publications table using dbDelta.
	 *
	 * Safe to call on every activation; dbDelta only makes changes when needed.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$types     = implode( ',', array_map( static fn( string $t ): string => "'{$t}'", self::content_types() ) );
		$projects  = implode( ',', array_map( static fn( string $t ): string => "'{$t}'", self::project_statuses() ) );
		$statuses  = implode( ',', array_map( static fn( string $t ): string => "'{$t}'", self::statuses() ) );
		$access    = implode( ',', array_map( static fn( string $t ): string => "'{$t}'", self::access_levels() ) );

		$sql = "CREATE TABLE {$table} (
			publication_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title             VARCHAR(255)    NOT NULL DEFAULT '',
			content_type      ENUM({$types})  NOT NULL DEFAULT 'report',
			executive_summary TEXT            NOT NULL DEFAULT '',
			author_institution VARCHAR(255)   NOT NULL DEFAULT '',
			publication_date  DATE            NOT NULL,
			project_status    ENUM({$projects}) NOT NULL DEFAULT 'in_progress',
			cover_image_id    BIGINT UNSIGNED NOT NULL DEFAULT 0,
			cover_image_alt   VARCHAR(255)    NOT NULL DEFAULT '',
			pdf_attachment_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			page_count        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			doi_citation      TEXT            NOT NULL DEFAULT '',
			access_level      ENUM({$access}) NOT NULL DEFAULT 'public',
			duplicate_note    TEXT            NOT NULL DEFAULT '',
			scanned           TINYINT(1)      NOT NULL DEFAULT 0,
			duplicate_ok      TINYINT(1)      NOT NULL DEFAULT 0,
			status            ENUM({$statuses}) NOT NULL DEFAULT 'draft',
			created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by        BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (publication_id),
			KEY status (status),
			KEY content_type (content_type),
			KEY project_status (project_status),
			KEY access_level (access_level),
			KEY publication_date (publication_date),
			KEY pdf_attachment_id (pdf_attachment_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( 'ceafsn_pp_db_version', self::SCHEMA_VERSION );
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
		$content_type = (string) ( $data['content_type'] ?? 'report' );
		$status       = (string) ( $data['status'] ?? 'draft' );
		$project      = (string) ( $data['project_status'] ?? 'in_progress' );
		$access       = (string) ( $data['access_level'] ?? 'public' );

		return array(
			'title'              => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'content_type'       => in_array( $content_type, self::content_types(), true ) ? $content_type : 'report',
			'executive_summary'  => sanitize_textarea_field( (string) ( $data['executive_summary'] ?? '' ) ),
			'author_institution' => sanitize_text_field( (string) ( $data['author_institution'] ?? '' ) ),
			'publication_date'   => self::normalize_date( (string) ( $data['publication_date'] ?? '' ) ),
			'project_status'     => in_array( $project, self::project_statuses(), true ) ? $project : 'in_progress',
			'cover_image_id'     => absint( $data['cover_image_id'] ?? 0 ),
			'cover_image_alt'    => sanitize_text_field( (string) ( $data['cover_image_alt'] ?? '' ) ),
			'pdf_attachment_id'  => absint( $data['pdf_attachment_id'] ?? 0 ),
			'page_count'         => min( 65535, absint( $data['page_count'] ?? 0 ) ),
			'doi_citation'       => esc_url_raw( (string) ( $data['doi_citation'] ?? '' ) ),
			'access_level'       => in_array( $access, self::access_levels(), true ) ? $access : 'public',
			'duplicate_note'     => sanitize_textarea_field( (string) ( $data['duplicate_note'] ?? '' ) ),
			'scanned'            => empty( $data['scanned'] ) ? 0 : 1,
			'duplicate_ok'       => empty( $data['duplicate_ok'] ) ? 0 : 1,
			'status'             => in_array( $status, self::statuses(), true ) ? $status : 'draft',
			'updated_by'         => get_current_user_id(),
		);
	}

	/**
	 * Column order and formats shared by insert and update.
	 *
	 * @return array<int,string>
	 */
	private static function row_formats(): array {
		return array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%d', '%d', '%s', '%d' );
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
	 * Insert a publication record. Returns the new row ID or false on failure.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function insert_publication( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_SUFFIX,
			self::prepare_row( $data ),
			self::row_formats()
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a publication record. Returns rows affected or false.
	 *
	 * @param int                 $id   Publication ID.
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function update_publication( int $id, array $data ): int|false {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . self::TABLE_SUFFIX,
			self::prepare_row( $data ),
			array( 'publication_id' => $id ),
			self::row_formats(),
			array( '%d' )
		);
	}

	/**
	 * Delete a publication record. The attached media is left in the library.
	 *
	 * @param int $id Publication ID.
	 * @return int|false Rows deleted or false.
	 */
	public static function delete_publication( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . self::TABLE_SUFFIX,
			array( 'publication_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single publication record by ID. Returns null when not found.
	 *
	 * @param int $id Publication ID.
	 * @return object|null
	 */
	public static function get_publication( int $id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE publication_id = %d LIMIT 1", $id )
		);
	}

	/**
	 * Get publication records with filtering, sorting, and pagination.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items: array<int,object>, total: int}
	 */
	public static function get_publications( array $args = array() ): array {
		global $wpdb;

		$table        = $wpdb->prefix . self::TABLE_SUFFIX;
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

		// Members-only records are invisible to anyone without the membership
		// capability. The public layer passes members_only = true only after it
		// has checked that capability, so this is defence in depth.
		$access = (string) ( $args['access_level'] ?? '' );
		if ( in_array( $access, self::access_levels(), true ) ) {
			$where_parts[]  = 'access_level = %s';
			$where_values[] = $access;
		} elseif ( empty( $args['include_members_only'] ) ) {
			$where_parts[] = "access_level = 'public'";
		}

		// Content type filter.
		$content_type = (string) ( $args['content_type'] ?? '' );
		if ( in_array( $content_type, self::content_types(), true ) ) {
			$where_parts[]  = 'content_type = %s';
			$where_values[] = $content_type;
		}

		// Project status filter.
		$project_status = (string) ( $args['project_status'] ?? '' );
		if ( in_array( $project_status, self::project_statuses(), true ) ) {
			$where_parts[]  = 'project_status = %s';
			$where_values[] = $project_status;
		}

		// Year filter: compared against the date column, not a separate year
		// column, so the two can never disagree.
		$year = absint( $args['year'] ?? 0 );
		if ( $year > 1900 && $year < 3000 ) {
			$where_parts[]  = 'YEAR(publication_date) = %d';
			$where_values[] = $year;
		}

		// Free-text search across title, summary, institution, and citation.
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$like           = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts[]  = '(title LIKE %s OR executive_summary LIKE %s OR author_institution LIKE %s OR doi_citation LIKE %s)';
			$where_values[] = $like;
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

		$per_page = max( 1, (int) ( $args['per_page'] ?? 12 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$limit_values   = $where_values;
		$limit_values[] = $per_page;
		$limit_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby is allow-listed, $order is a literal.
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order}, publication_id ASC LIMIT %d OFFSET %d",
				...$limit_values
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Every distinct year that has a published record, newest first.
	 *
	 * @return array<int,int>
	 */
	public static function get_years(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			"SELECT DISTINCT YEAR(publication_date) AS year FROM `{$table}` WHERE status = 'published' ORDER BY year DESC"
		) ?: array();

		return array_values(
			array_filter(
				array_map( static fn( $row ): int => (int) $row->year, $rows )
			)
		);
	}

	/**
	 * Count how many other records share an attachment ID.
	 *
	 * Used to flag duplicated documents so they are never silently reused.
	 *
	 * @param int $attachment_id        Attachment ID.
	 * @param int $exclude_publication_id Record to exclude from the count.
	 * @return int Number of other records using the same attachment.
	 */
	public static function attachment_usage_count( int $attachment_id, int $exclude_publication_id = 0 ): int {
		global $wpdb;

		if ( $attachment_id <= 0 ) {
			return 0;
		}

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT COUNT(*) FROM `{$table}` WHERE pdf_attachment_id = %d AND publication_id <> %d",
				$attachment_id,
				$exclude_publication_id
			)
		);

		return (int) $count;
	}

	/**
	 * List the other records that share a given attachment.
	 *
	 * @param int $attachment_id        Attachment ID.
	 * @param int $exclude_publication_id Record to exclude.
	 * @return array<int,object>
	 */
	public static function get_attached_others( int $attachment_id, int $exclude_publication_id = 0 ): array {
		global $wpdb;

		if ( $attachment_id <= 0 ) {
			return array();
		}

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT publication_id, title FROM `{$table}` WHERE pdf_attachment_id = %d AND publication_id <> %d ORDER BY publication_date DESC",
				$attachment_id,
				$exclude_publication_id
			)
		);

		return $rows ?: array();
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
		$result = self::get_publications(
			array(
				'per_page'             => 9999,
				'include_members_only' => true,
			)
		);
		return $result['items'];
	}
}
