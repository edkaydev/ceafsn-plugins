<?php
/**
 * Database schema and query layer for CE-AFSN M&E Dashboard.
 *
 * Handles table creation, upgrades, and all CRUD queries for
 * metrics, demographics, and projects. Never queries for private
 * records on the front end.
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_MED_DB
 */
class CEAFSN_MED_DB {

	/** @var string Current schema version stored in options. */
	const SCHEMA_VERSION = '1.1.0';

	/**
	 * Option that records the installed schema version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'ceafsn_med_db_version';

	/**
	 * Translated labels for a project status.
	 *
	 * The stored value is a machine key such as `active`. Rendering it directly
	 * would put an untranslated token in front of an editor, so every display
	 * path goes through this map.
	 *
	 * @return array<string,string> Status key => label.
	 */
	public static function project_status_labels(): array {
		return array(
			'active'    => __( 'Active', 'ceafsn-med' ),
			'completed' => __( 'Completed', 'ceafsn-med' ),
			'suspended' => __( 'Suspended', 'ceafsn-med' ),
		);
	}

	/**
	 * Translated labels for a verification status.
	 *
	 * @return array<string,string> Verification key => label.
	 */
	public static function verification_labels(): array {
		return array(
			'verified'   => __( 'Verified', 'ceafsn-med' ),
			'pending'    => __( 'Pending', 'ceafsn-med' ),
			'unverified' => __( 'Unverified', 'ceafsn-med' ),
		);
	}

	/**
	 * Translated labels for a demographic value type.
	 *
	 * @return array<string,string> Value type => label.
	 */
	public static function value_type_labels(): array {
		return array(
			'count'      => __( 'Count', 'ceafsn-med' ),
			'percentage' => __( 'Percentage', 'ceafsn-med' ),
		);
	}

	/**
	 * Translated labels for a record visibility flag.
	 *
	 * @return array<string,string> Visibility => label.
	 */
	public static function visibility_labels(): array {
		return array(
			'public'  => __( 'Public', 'ceafsn-med' ),
			'private' => __( 'Private', 'ceafsn-med' ),
		);
	}

	/**
	 * Translated label for one key, falling back to the key itself.
	 *
	 * An unmapped key is returned unchanged rather than title-cased: it stays
	 * visibly a machine value instead of pretending to be a translated word.
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
	 * Create or upgrade all plugin tables using dbDelta.
	 *
	 * Safe to call on every activation; dbDelta only makes changes when needed.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// ---- Metrics ----
		$metrics_table = $wpdb->prefix . 'ceafsn_med_metrics';
		$sql_metrics   = "CREATE TABLE {$metrics_table} (
			metric_id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label          VARCHAR(255)    NOT NULL DEFAULT '',
			value          VARCHAR(255)    NOT NULL DEFAULT '',
			unit           VARCHAR(100)    NOT NULL DEFAULT '',
			definition     TEXT            NOT NULL DEFAULT '',
			source         VARCHAR(255)    NOT NULL DEFAULT '',
			reporting_period VARCHAR(100)  NOT NULL DEFAULT '',
			visibility     ENUM('public','private') NOT NULL DEFAULT 'private',
			created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by     BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (metric_id),
			KEY visibility_reporting (visibility,reporting_period)
		) {$charset_collate};";

		// ---- Demographics ----
		$demo_table = $wpdb->prefix . 'ceafsn_med_demographics';
		$sql_demo   = "CREATE TABLE {$demo_table} (
			group_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			label            VARCHAR(255)    NOT NULL DEFAULT '',
			value            DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
			value_type       ENUM('count','percentage') NOT NULL DEFAULT 'count',
			reporting_period VARCHAR(100)    NOT NULL DEFAULT '',
			source           VARCHAR(255)    NOT NULL DEFAULT '',
			visibility       ENUM('public','private') NOT NULL DEFAULT 'private',
			created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by       BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (group_id),
			KEY visibility_reporting (visibility,reporting_period)
		) {$charset_collate};";

		// ---- Projects ----
		$projects_table = $wpdb->prefix . 'ceafsn_med_projects';
		$sql_projects   = "CREATE TABLE {$projects_table} (
			project_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title                 VARCHAR(255)    NOT NULL DEFAULT '',
			principal_investigator VARCHAR(255)   NOT NULL DEFAULT '',
			target_region         VARCHAR(255)    NOT NULL DEFAULT '',
			status                ENUM('active','completed','suspended') NOT NULL DEFAULT 'active',
			verification_status   ENUM('verified','pending','unverified') NOT NULL DEFAULT 'pending',
			last_updated          DATE            NOT NULL,
			source_url            TEXT            NOT NULL DEFAULT '',
			created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			updated_by            BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (project_id),
			FULLTEXT KEY search_prose (title,principal_investigator),
			KEY status_verification (status,verification_status),
			KEY last_updated (last_updated)
		) {$charset_collate};";

		dbDelta( $sql_metrics );
		dbDelta( $sql_demo );
		dbDelta( $sql_projects );

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
	// Metrics
	// ---------------------------------------------------------------------------

	/**
	 * Insert a new metric. Returns the new row ID or false on failure.
	 *
	 * @param array<string,mixed> $data Sanitized field values.
	 * @return int|false
	 */
	public static function insert_metric( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ceafsn_med_metrics',
			array(
				'label'            => sanitize_text_field( $data['label'] ?? '' ),
				'value'            => sanitize_text_field( $data['value'] ?? '' ),
				'unit'             => sanitize_text_field( $data['unit'] ?? '' ),
				'definition'       => sanitize_textarea_field( $data['definition'] ?? '' ),
				'source'           => sanitize_text_field( $data['source'] ?? '' ),
				'reporting_period' => sanitize_text_field( $data['reporting_period'] ?? '' ),
				'visibility'       => in_array( $data['visibility'] ?? '', array( 'public', 'private' ), true )
								? $data['visibility']
								: 'private',
				'updated_by'       => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update an existing metric. Returns rows affected or false.
	 *
	 * @param int                 $id   Metric ID.
	 * @param array<string,mixed> $data Sanitized field values.
	 * @return int|false
	 */
	public static function update_metric( int $id, array $data ): int|false {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'ceafsn_med_metrics',
			array(
				'label'            => sanitize_text_field( $data['label'] ?? '' ),
				'value'            => sanitize_text_field( $data['value'] ?? '' ),
				'unit'             => sanitize_text_field( $data['unit'] ?? '' ),
				'definition'       => sanitize_textarea_field( $data['definition'] ?? '' ),
				'source'           => sanitize_text_field( $data['source'] ?? '' ),
				'reporting_period' => sanitize_text_field( $data['reporting_period'] ?? '' ),
				'visibility'       => in_array( $data['visibility'] ?? '', array( 'public', 'private' ), true )
								? $data['visibility']
								: 'private',
				'updated_by'       => get_current_user_id(),
			),
			array( 'metric_id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a metric by ID.
	 *
	 * @param int $id Metric ID.
	 * @return int|false Rows deleted or false.
	 */
	public static function delete_metric( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_med_metrics',
			array( 'metric_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single metric by ID. Returns null if not found.
	 *
	 * @param int $id Metric ID.
	 * @return object|null
	 */
	public static function get_metric( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_med_metrics` WHERE metric_id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Get all metrics. Admin may see all; front end sees only public.
	 *
	 * @param bool $public_only When true, returns only visibility='public'.
	 * @return array<int,object>
	 */
	public static function get_metrics( bool $public_only = true ): array {
		global $wpdb;

		if ( $public_only ) {
			return $wpdb->get_results(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_med_metrics`
				 WHERE visibility = 'public'
				 ORDER BY label ASC"
			) ?: array();
		}

		return $wpdb->get_results(
			"SELECT * FROM `{$wpdb->prefix}ceafsn_med_metrics`
			 ORDER BY label ASC"
		) ?: array();
	}

	// ---------------------------------------------------------------------------
	// Demographics
	// ---------------------------------------------------------------------------

	/**
	 * Insert a demographic group. Returns new row ID or false.
	 *
	 * @param array<string,mixed> $data Sanitized field values.
	 * @return int|false
	 */
	public static function insert_demographic( array $data ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'ceafsn_med_demographics',
			array(
				'label'            => sanitize_text_field( $data['label'] ?? '' ),
				'value'            => (float) ( $data['value'] ?? 0 ),
				'value_type'       => in_array( $data['value_type'] ?? '', array( 'count', 'percentage' ), true )
								? $data['value_type']
								: 'count',
				'reporting_period' => sanitize_text_field( $data['reporting_period'] ?? '' ),
				'source'           => sanitize_text_field( $data['source'] ?? '' ),
				'visibility'       => in_array( $data['visibility'] ?? '', array( 'public', 'private' ), true )
								? $data['visibility']
								: 'private',
				'updated_by'       => get_current_user_id(),
			),
			array( '%s', '%f', '%s', '%s', '%s', '%s', '%d' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a demographic group.
	 *
	 * @param int                 $id   Group ID.
	 * @param array<string,mixed> $data Sanitized field values.
	 * @return int|false
	 */
	public static function update_demographic( int $id, array $data ): int|false {
		global $wpdb;

		return $wpdb->update(
			$wpdb->prefix . 'ceafsn_med_demographics',
			array(
				'label'            => sanitize_text_field( $data['label'] ?? '' ),
				'value'            => (float) ( $data['value'] ?? 0 ),
				'value_type'       => in_array( $data['value_type'] ?? '', array( 'count', 'percentage' ), true )
								? $data['value_type']
								: 'count',
				'reporting_period' => sanitize_text_field( $data['reporting_period'] ?? '' ),
				'source'           => sanitize_text_field( $data['source'] ?? '' ),
				'visibility'       => in_array( $data['visibility'] ?? '', array( 'public', 'private' ), true )
								? $data['visibility']
								: 'private',
				'updated_by'       => get_current_user_id(),
			),
			array( 'group_id' => $id ),
			array( '%s', '%f', '%s', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a demographic group.
	 *
	 * @param int $id Group ID.
	 * @return int|false
	 */
	public static function delete_demographic( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_med_demographics',
			array( 'group_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single demographic group by ID.
	 *
	 * @param int $id Group ID.
	 * @return object|null
	 */
	public static function get_demographic( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_med_demographics` WHERE group_id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Get all demographic groups.
	 *
	 * @param bool $public_only Restrict to public records on the front end.
	 * @return array<int,object>
	 */
	public static function get_demographics( bool $public_only = true ): array {
		global $wpdb;

		if ( $public_only ) {
			return $wpdb->get_results(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_med_demographics`
				 WHERE visibility = 'public'
				 ORDER BY label ASC"
			) ?: array();
		}

		return $wpdb->get_results(
			"SELECT * FROM `{$wpdb->prefix}ceafsn_med_demographics`
			 ORDER BY label ASC"
		) ?: array();
	}

	// ---------------------------------------------------------------------------
	// Projects
	// ---------------------------------------------------------------------------

	/**
	 * Insert a project. Returns new row ID or false.
	 *
	 * @param array<string,mixed> $data Sanitized field values.
	 * @return int|false
	 */
	public static function insert_project( array $data ): int|false {
		global $wpdb;

		$valid_statuses       = array( 'active', 'completed', 'suspended' );
		$valid_verifications  = array( 'verified', 'pending', 'unverified' );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ceafsn_med_projects',
			array(
				'title'                  => sanitize_text_field( $data['title'] ?? '' ),
				'principal_investigator' => sanitize_text_field( $data['principal_investigator'] ?? '' ),
				'target_region'          => sanitize_text_field( $data['target_region'] ?? '' ),
				'status'                 => in_array( $data['status'] ?? '', $valid_statuses, true )
								? $data['status']
								: 'active',
				'verification_status'    => in_array( $data['verification_status'] ?? '', $valid_verifications, true )
								? $data['verification_status']
								: 'pending',
				'last_updated'           => sanitize_text_field( $data['last_updated'] ?? gmdate( 'Y-m-d' ) ),
				'source_url'             => esc_url_raw( $data['source_url'] ?? '' ),
				'updated_by'             => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' )
		);

		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a project.
	 *
	 * @param int                 $id   Project ID.
	 * @param array<string,mixed> $data Sanitized field values.
	 * @return int|false
	 */
	public static function update_project( int $id, array $data ): int|false {
		global $wpdb;

		$valid_statuses      = array( 'active', 'completed', 'suspended' );
		$valid_verifications = array( 'verified', 'pending', 'unverified' );

		return $wpdb->update(
			$wpdb->prefix . 'ceafsn_med_projects',
			array(
				'title'                  => sanitize_text_field( $data['title'] ?? '' ),
				'principal_investigator' => sanitize_text_field( $data['principal_investigator'] ?? '' ),
				'target_region'          => sanitize_text_field( $data['target_region'] ?? '' ),
				'status'                 => in_array( $data['status'] ?? '', $valid_statuses, true )
								? $data['status']
								: 'active',
				'verification_status'    => in_array( $data['verification_status'] ?? '', $valid_verifications, true )
								? $data['verification_status']
								: 'pending',
				'last_updated'           => sanitize_text_field( $data['last_updated'] ?? gmdate( 'Y-m-d' ) ),
				'source_url'             => esc_url_raw( $data['source_url'] ?? '' ),
				'updated_by'             => get_current_user_id(),
			),
			array( 'project_id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a project.
	 *
	 * @param int $id Project ID.
	 * @return int|false
	 */
	public static function delete_project( int $id ): int|false {
		global $wpdb;

		return $wpdb->delete(
			$wpdb->prefix . 'ceafsn_med_projects',
			array( 'project_id' => $id ),
			array( '%d' )
		);
	}

	/**
	 * Get a single project by ID.
	 *
	 * @param int $id Project ID.
	 * @return object|null
	 */
	public static function get_project( int $id ): ?object {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}ceafsn_med_projects` WHERE project_id = %d LIMIT 1",
				$id
			)
		);
	}

	/**
	 * Get projects with optional filtering and pagination.
	 *
	 * @param array<string,mixed> $args {
	 *   @type string $status         Filter by status ('active','completed','suspended','').
	 *   @type string $verification   Filter by verification_status.
	 *   @type string $search         Search title or PI name.
	 *   @type int    $per_page       Records per page. Default 20.
	 *   @type int    $page           Page number (1-based). Default 1.
	 * }
	 * @return array{ items: array<int,object>, total: int }
	 */
	public static function get_projects( array $args = array() ): array {
		global $wpdb;

		$table        = $wpdb->prefix . 'ceafsn_med_projects';
		$where_parts  = array( '1=1' );
		$where_values = array();

		// Status filter.
		$valid_statuses = array( 'active', 'completed', 'suspended' );
		if ( ! empty( $args['status'] ) && in_array( $args['status'], $valid_statuses, true ) ) {
			$where_parts[]  = 'status = %s';
			$where_values[] = $args['status'];
		}

		// Verification filter.
		$valid_verif = array( 'verified', 'pending', 'unverified' );
		if ( ! empty( $args['verification'] ) && in_array( $args['verification'], $valid_verif, true ) ) {
			$where_parts[]  = 'verification_status = %s';
			$where_values[] = $args['verification'];
		}

		// Search filter. FULLTEXT keeps a word-anywhere match without the
		// unindexable scan a leading-wildcard LIKE would force.
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$fallback = self::searchable_columns( array( 'title', 'principal_investigator' ), array() );
			$clause   = class_exists( 'CEAFSN_Search' )
				? CEAFSN_Search::clause( array( 'title', 'principal_investigator' ), array(), $search )
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


		$where = implode( ' AND ', $where_parts );

		// Count total.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$table}` WHERE {$where}", ...$where_values ) );

		// Pagination.
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$limit_values   = $where_values;
		$limit_values[] = $per_page;
		$limit_values[] = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$table}` WHERE {$where} ORDER BY last_updated DESC LIMIT %d OFFSET %d",
				...$limit_values
			)
		) ?: array();

		return array(
			'items' => $items,
			'total' => $total,
		);
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
	 * Export all data as a structured array suitable for JSON encoding.
	 *
	 * @return array<string,array<int,object>>
	 */
	public static function export_all(): array {
		return array(
			'metrics'      => self::get_metrics( false ),
			'demographics' => self::get_demographics( false ),
			'projects'     => self::get_projects( array( 'per_page' => 9999 ) )['items'],
		);
	}
}
