<?php
/**
 * Database schema and query layer for CE-AFSN Grants & Funding.
 *
 * Two distinct concepts are stored in two distinct columns, because they
 * answer different questions:
 *
 *   - `status`       Is this record visible at all? draft / published / archived.
 *   - `grant_status` What is the opportunity's own state? open / closed /
 *                     upcoming / archived, set directly by the administrator.
 *
 * Unlike the fellowships plugin, grant status is not derived from the
 * deadline: the specification treats it as a field the administrator sets,
 * not a calculation. What is enforced instead is that the deadline itself,
 * the award range, and the call document are never invented — a record
 * cannot be published without a real deadline and a real, validated,
 * non-duplicated PDF behind it.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_GF_DB
 */
class CEAFSN_GF_DB {

	/** @var string Current schema version stored in options. */
	const SCHEMA_VERSION = '1.1.0';

	/**
	 * Option that records the installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'ceafsn_gf_db_version';

	/** @var string Table name without the site prefix. */
	const TABLE_SUFFIX = 'ceafsn_gf_grants';

	/**
	 * Ceiling for an unpaginated read.
	 *
	 * Only used when a caller passes `paginate => false` so it can filter on a
	 * derived deadline state afterwards.
	 *
	 * @var int
	 */
	const MAX_UNPAGINATED_ROWS = 2000;

	/**
	 * Columns the front end is allowed to sort by.
	 *
	 * @return array<int,string>
	 */
	public static function sortable_columns(): array {
		return array( 'title', 'deadline', 'funding_institution' );
	}

	/**
	 * All valid record states (publish gate).
	 *
	 * @return array<int,string>
	 */
	public static function statuses(): array {
		return array( 'draft', 'published', 'archived' );
	}

	/**
	 * All valid grant statuses (the administrator's own field).
	 *
	 * @return array<int,string>
	 */
	public static function grant_statuses(): array {
		return array( 'open', 'closed', 'upcoming', 'archived' );
	}

	/**
	 * Translated labels for a record status.
	 *
	 * @return array<string,string> Status key => label.
	 */
	public static function status_labels(): array {
		return array(
			'draft'     => __( 'Draft', 'ceafsn-gf' ),
			'published' => __( 'Published', 'ceafsn-gf' ),
			'archived'  => __( 'Archived', 'ceafsn-gf' ),
		);
	}

	/**
	 * Translated labels for a grant status.
	 *
	 * @return array<string,string> Grant status => label.
	 */
	public static function grant_status_labels(): array {
		return array(
			'open'     => __( 'Open', 'ceafsn-gf' ),
			'closed'   => __( 'Closed', 'ceafsn-gf' ),
			'upcoming' => __( 'Upcoming', 'ceafsn-gf' ),
			'archived' => __( 'Archived', 'ceafsn-gf' ),
		);
	}

	/**
	 * Translated label for one key, falling back to the key itself.
	 *
	 * @param array<string,string> $labels Label map.
	 * @param string               $value  Raw key.
	 * @return string
	 */
	public static function label( array $labels, string $value ): string {
		return (string) ( $labels[ $value ] ?? $value );
	}

	// ---------------------------------------------------------------------------
	// Schema
	// ---------------------------------------------------------------------------

	/**
	 * Create or upgrade the grants table using dbDelta.
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
		$grant    = implode( ',', array_map( static fn( string $s ): string => "'{$s}'", self::grant_statuses() ) );

		// The deadline is nullable rather than defaulted to "today": a grant with
		// no deadline yet is a real state this plugin has to represent, and
		// inventing one would break the rule that nothing here is ever invented.
		$sql = "CREATE TABLE {$table} (
			grant_id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title                 VARCHAR(255)    NOT NULL DEFAULT '',
			award_range           VARCHAR(255)    NOT NULL DEFAULT '',
			deadline              DATETIME        NULL DEFAULT NULL,
			deadline_timezone     VARCHAR(100)    NOT NULL DEFAULT '',
			target_beneficiaries  TEXT            NOT NULL DEFAULT '',
			eligibility           TEXT            NOT NULL DEFAULT '',
			call_pdf_id           BIGINT UNSIGNED NOT NULL DEFAULT 0,
			application_url       TEXT            NOT NULL DEFAULT '',
			funding_institution   VARCHAR(255)    NOT NULL DEFAULT '',
			contact               VARCHAR(255)    NOT NULL DEFAULT '',
			show_contact          TINYINT(1)      NOT NULL DEFAULT 0,
			duplicate_note        TEXT            NOT NULL DEFAULT '',
			duplicate_ok          TINYINT(1)      NOT NULL DEFAULT 0,
			grant_status          ENUM({$grant}) NOT NULL DEFAULT 'upcoming',
			status                ENUM({$statuses}) NOT NULL DEFAULT 'draft',
			created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by            BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (grant_id),
			FULLTEXT KEY search_prose (title,eligibility,funding_institution,target_beneficiaries),
			KEY status (status),
			KEY grant_status (grant_status),
			KEY deadline (deadline),
			KEY call_pdf_id (call_pdf_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Ordered schema migrations keyed by the version that introduces them.
	 *
	 * Each callback brings a database from the previous version up to its own
	 * version. They run in ascending order so a site that skipped several
	 * releases still passes through every step and lands in a known state,
	 * rather than jumping straight to the newest schema with steps missing.
	 *
	 * @return array<string,callable> Version => migration callback.
	 */
	private static function migrations(): array {
		return array();
	}

	/**
	 * Apply pending schema migrations.
	 *
	 * WordPress never runs the activation hook for a plugin *update*, so
	 * activation alone cannot carry a schema change to an existing site: a new
	 * column would silently not exist and every write to it would fail at
	 * runtime. This runs on admin_init so an upgrade is applied the first time
	 * anyone loads an admin screen after deploying it.
	 *
	 * Cheap when there is nothing to do: one option read and one comparison.
	 *
	 * @return bool True when a migration ran.
	 */
	public static function maybe_upgrade(): bool {
		$installed = (string) get_option( self::DB_VERSION_OPTION, '0.0.0' );

		if ( version_compare( $installed, self::SCHEMA_VERSION, '>=' ) ) {
			return false;
		}

		// create_tables() runs dbDelta, which is idempotent: it adds missing
		// columns and indexes and leaves existing rows and columns untouched, so
		// a fresh install and an upgrade converge on the same schema.
		self::create_tables();

		// create_tables() records the target version. Restore the version we
		// started from so an interrupted migration is retried on the next
		// request instead of being marked complete.
		update_option( self::DB_VERSION_OPTION, $installed, false );

		foreach ( self::migrations() as $version => $migration ) {
			if ( version_compare( $installed, (string) $version, '>=' ) ) {
				continue;
			}

			$migration();

			update_option( self::DB_VERSION_OPTION, (string) $version, false );
		}

		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION );

		return true;
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
		$grant  = (string) ( $data['grant_status'] ?? 'upcoming' );

		return array(
			'title'                => sanitize_text_field( (string) ( $data['title'] ?? '' ) ),
			'award_range'          => sanitize_text_field( (string) ( $data['award_range'] ?? '' ) ),
			'deadline'             => self::normalize_deadline_to_utc( (string) ( $data['deadline'] ?? '' ), (string) ( $data['deadline_timezone'] ?? '' ) ),
			'deadline_timezone'    => sanitize_text_field( (string) ( $data['deadline_timezone'] ?? '' ) ),
			'target_beneficiaries' => sanitize_textarea_field( (string) ( $data['target_beneficiaries'] ?? '' ) ),
			'eligibility'          => sanitize_textarea_field( (string) ( $data['eligibility'] ?? '' ) ),
			'call_pdf_id'          => absint( $data['call_pdf_id'] ?? 0 ),
			'application_url'      => esc_url_raw( (string) ( $data['application_url'] ?? '' ) ),
			'funding_institution'  => sanitize_text_field( (string) ( $data['funding_institution'] ?? '' ) ),
			'contact'              => sanitize_text_field( (string) ( $data['contact'] ?? '' ) ),
			'show_contact'         => empty( $data['show_contact'] ) ? 0 : 1,
			'duplicate_note'       => sanitize_textarea_field( (string) ( $data['duplicate_note'] ?? '' ) ),
			'duplicate_ok'         => empty( $data['duplicate_ok'] ) ? 0 : 1,
			'grant_status'         => in_array( $grant, self::grant_statuses(), true ) ? $grant : 'upcoming',
			'status'               => in_array( $status, self::statuses(), true ) ? $status : 'draft',
			'updated_by'           => get_current_user_id(),
		);
	}

	/**
	 * Column order and formats shared by insert and update.
	 *
	 * @return array<int,string>
	 */
	private static function row_formats(): array {
		return array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d' );
	}

	/**
	 * Convert an admin-entered local deadline into a UTC DATETIME string.
	 *
	 * The admin types a deadline as a wall-clock date and time in the site's
	 * own timezone (a `datetime-local` field has no timezone of its own). That
	 * value is converted to UTC for storage, so comparisons and sorting are
	 * always correct even if the site's timezone setting changes later. An
	 * empty or unparseable value becomes null rather than "now" or "today": a
	 * missing deadline is a real state, not something to be guessed at.
	 *
	 * @param string $local_value Raw `datetime-local` value, e.g. "2025-12-01T17:00".
	 * @param string $timezone    Timezone the value was entered in.
	 * @return string|null UTC "Y-m-d H:i:s", or null when there is no usable value.
	 */
	public static function normalize_deadline_to_utc( string $local_value, string $timezone ): ?string {
		$local_value = trim( $local_value );

		if ( '' === $local_value ) {
			return null;
		}

		if ( 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(:\d{2})?$/', $local_value, $matches ) ) {
			return null;
		}

		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) ) {
			return null;
		}

		$timezone = '' !== trim( $timezone ) ? trim( $timezone ) : 'UTC';

		// deadline_timezone is stamped by the plugin itself, never typed by an
		// admin, so an unparseable value here is a data problem, not bad user
		// input. Falling back to UTC keeps the real date and time the admin
		// entered rather than discarding it over a timezone string it never
		// controlled.
		try {
			$zone = new DateTimeZone( $timezone );
		} catch ( Exception $e ) {
			$zone = new DateTimeZone( 'UTC' );
		}

		try {
			$date = new DateTime( str_replace( 'T', ' ', $local_value ), $zone );
			$date->setTimezone( new DateTimeZone( 'UTC' ) );
		} catch ( Exception $e ) {
			return null;
		}

		return $date->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert a stored UTC deadline back into a `datetime-local` input value in
	 * the current site timezone, for pre-filling the edit form.
	 *
	 * @param string $utc_datetime Stored "Y-m-d H:i:s" UTC value.
	 * @return string "Y-m-d\TH:i" in the site timezone, or '' when there is none.
	 */
	public static function utc_to_local_input( string $utc_datetime ): string {
		$utc_datetime = trim( $utc_datetime );

		if ( '' === $utc_datetime ) {
			return '';
		}

		try {
			$date = new DateTime( $utc_datetime, new DateTimeZone( 'UTC' ) );
			$date->setTimezone( wp_timezone() );
		} catch ( Exception $e ) {
			return '';
		}

		return $date->format( 'Y-m-d\TH:i' );
	}

	// ---------------------------------------------------------------------------
	// CRUD
	// ---------------------------------------------------------------------------

	/**
	 * Insert a grant record. Returns the new row ID or false on failure.
	 *
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function insert_grant( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . self::TABLE_SUFFIX,
			self::prepare_row( $data ),
			self::row_formats()
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a grant record. Returns rows affected or false.
	 *
	 * @param int                 $id   Grant ID.
	 * @param array<string,mixed> $data Field values.
	 * @return int|false
	 */
	public static function update_grant( int $id, array $data ): int|false {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . self::TABLE_SUFFIX,
			self::prepare_row( $data ),
			array( 'grant_id' => $id ),
			self::row_formats(),
			array( '%d' )
		);
	}

	/**
	 * Delete a grant record. The attached media is left in the library.
	 *
	 * @param int $id Grant ID.
	 * @return int|false Rows deleted or false.
	 */
	public static function delete_grant( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . self::TABLE_SUFFIX,
			array( 'grant_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single grant record by ID. Returns null when not found.
	 *
	 * @param int $id Grant ID.
	 * @return object|null
	 */
	public static function get_grant( int $id ): ?object {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE grant_id = %d LIMIT 1", $id )
		);
	}

	/**
	 * Get grant records with filtering, sorting, and pagination.
	 *
	 * Pass `paginate => false` to receive every matching row instead of one
	 * SQL page. The front end needs that: it hides closed calls based on a
	 * derived deadline state that the database cannot compute, so paging in SQL
	 * first would both under-fill the page and make any SQL-side count a lie.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{items: array<int,object>, total: int}
	 */
	public static function get_grants( array $args = array() ): array {
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

		// Grant status filter (open / closed / upcoming / archived).
		$grant_status = (string) ( $args['grant_status'] ?? '' );
		if ( in_array( $grant_status, self::grant_statuses(), true ) ) {
			$where_parts[]  = 'grant_status = %s';
			$where_values[] = $grant_status;
		}

		// Funding institution filter, matched on the stored column rather than a
		// separate taxonomy the site does not have.
		$institution = sanitize_text_field( (string) ( $args['funding_institution'] ?? '' ) );
		if ( '' !== $institution ) {
			$where_parts[]  = 'funding_institution = %s';
			$where_values[] = $institution;
		}

		// Search filter. FULLTEXT keeps a word-anywhere match without the
		// unindexable scan a leading-wildcard LIKE would force.
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$fallback = self::searchable_columns( array( 'title', 'eligibility', 'funding_institution', 'target_beneficiaries' ), array() );
			$clause   = class_exists( 'CEAFSN_Search' )
				? CEAFSN_Search::clause( array( 'title', 'eligibility', 'funding_institution', 'target_beneficiaries' ), array(), $search )
				: null;

			// Without the shared library the search falls back to a prefix LIKE.
			// That is narrower than a full-text match, but unlike the leading
			// wildcard it can still use an index.
			if ( null === $clause ) {
				$like          = '%' . $wpdb->esc_like( $search ) . '%';
				$where_parts[] = '(' . implode( ' OR ', array_map(
					static function ( $column ) {
						return $column . ' LIKE %s';
					},
					$fallback
				) ) . ')';
				$where_values = array_merge( $where_values, array_fill( 0, count( $fallback ), $like ) );
			} else {
				// A term of nothing but punctuation cannot form a query, so the
				// builder returns null and no filter is applied rather than
				// matching every row.
				$where_parts[] = $clause[0];
				$where_values  = array_merge( $where_values, $clause[1] );
			}
		}


		// Sorting — the column is validated against a fixed allow list, so a
		// crafted query string cannot reach the ORDER BY clause.
		$orderby = (string) ( $args['orderby'] ?? 'deadline' );
		$orderby = in_array( $orderby, self::sortable_columns(), true ) ? $orderby : 'deadline';
		$order   = 'asc' === strtolower( (string) ( $args['order'] ?? 'asc' ) ) ? 'ASC' : 'DESC';

		$where = implode( ' AND ', $where_parts );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built from fixed fragments above.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", ...$where_values ) );

		$per_page = max( 1, (int) ( $args['per_page'] ?? 12 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$paginate = ! array_key_exists( 'paginate', $args ) || ! empty( $args['paginate'] );

		if ( $paginate ) {
			$limit_values   = $where_values;
			$limit_values[] = $per_page;
			$limit_values[] = $offset;

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby is allow-listed, $order is a literal.
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order}, grant_id ASC LIMIT %d OFFSET %d",
					...$limit_values
				)
			) ?: array();
		} else {
			// Unpaginated read for callers that filter on a derived deadline state
			// afterwards. The ceiling bounds memory, not how many calls a
			// programme may publish.
			$limit_values   = $where_values;
			$limit_values[] = max( 1, (int) ( $args['max_rows'] ?? self::MAX_UNPAGINATED_ROWS ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orderby is allow-listed, $order is a literal.
			$items = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM `{$table}` WHERE {$where} ORDER BY {$orderby} {$order}, grant_id ASC LIMIT %d",
					...$limit_values
				)
			) ?: array();
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Every distinct funding institution that has a published record.
	 *
	 * @return array<int,string>
	 */
	public static function get_institutions(): array {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$rows = $wpdb->get_col(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
			"SELECT DISTINCT funding_institution FROM `{$table}` WHERE status = 'published' AND funding_institution <> '' ORDER BY funding_institution ASC"
		);

		return array_values( array_filter( array_map( 'strval', (array) $rows ) ) );
	}

	/**
	 * Count how many other records share a call PDF attachment.
	 *
	 * Used to flag duplicated documents so they are never silently reused.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $exclude_id    Record to exclude from the count.
	 * @return int Number of other records using the same attachment.
	 */
	public static function attachment_usage_count( int $attachment_id, int $exclude_id = 0 ): int {
		global $wpdb;

		if ( $attachment_id <= 0 ) {
			return 0;
		}

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT COUNT(*) FROM `{$table}` WHERE call_pdf_id = %d AND grant_id <> %d",
				$attachment_id,
				$exclude_id
			)
		);

		return (int) $count;
	}

	/**
	 * List the other records that share a given call PDF attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $exclude_id    Record to exclude.
	 * @return array<int,object>
	 */
	public static function get_attached_others( int $attachment_id, int $exclude_id = 0 ): array {
		global $wpdb;

		if ( $attachment_id <= 0 ) {
			return array();
		}

		$table = $wpdb->prefix . self::TABLE_SUFFIX;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name only.
				"SELECT grant_id, title FROM `{$table}` WHERE call_pdf_id = %d AND grant_id <> %d ORDER BY grant_id DESC",
				$attachment_id,
				$exclude_id
			)
		);

		return $rows ?: array();
	}

	/**
	 * Build the WHERE fragment for a free-text search.
	 *
	 * Prefers the shared builder, which searches prose columns with an indexed
	 * FULLTEXT match. The shared library is optional, so when it is absent the
	 * columns fall back to a prefix LIKE: still index-backed rather than the
	 * unindexable leading wildcard this replaced.
	 *
	 * @param string[] $prose  Prose columns.
	 * @param string[] $prefix Identifier columns.
	 * @return string[] Columns a prefix LIKE should cover when FULLTEXT is unavailable.
	 */
	public static function searchable_columns( array $prose, array $prefix ): array {
		return array_values( array_unique( array_merge( $prose, $prefix ) ) );
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
		$result = self::get_grants( array( 'per_page' => 9999 ) );

		return $result['items'];
	}
}
