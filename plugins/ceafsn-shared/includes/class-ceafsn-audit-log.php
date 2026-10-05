<?php
/**
 * Shared audit log for the CE-AFSN plugin suite.
 *
 * One table records who changed which record across all six plugins, so an
 * institution can answer "who unpublished this dataset and why" without reading
 * six separate logs.
 *
 * Two decisions are enforced here rather than at each of the twelve call sites,
 * because a rule that is only applied consistently by hand is a rule that is
 * eventually broken:
 *
 * 1. Only the difference is stored. A twenty-column publication that changes
 *    one field logs that one field, not two full row copies.
 * 2. Sensitive columns never reach the table. The audit log is a less protected
 *    copy of the data, so it must not become a place where a value that was
 *    deliberately not shown on a public page is duplicated into.
 *
 * @package CEAFSN_Shared
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append-only audit log.
 */
final class CEAFSN_Audit_Log {

	/**
	 * Unqualified table name.
	 */
	public const TABLE = 'ceafsn_audit_log';

	/**
	 * Option holding the installed schema version.
	 */
	public const VERSION_OPTION = 'ceafsn_audit_db_version';

	/**
	 * Schema version this build installs.
	 */
	public const SCHEMA_VERSION = '1.0.0';

	/**
	 * Columns whose values are replaced before anything is written.
	 *
	 * Matched case-insensitively as substrings, so `user_email` and `email` are
	 * both caught by the single entry `email`.
	 *
	 * @var string[]
	 */
	private const REDACTED_SUBSTRINGS = array( 'pass', 'secret', 'token', 'api_key', 'apikey', 'email', 'salt', 'nonce', 'session' );

	/**
	 * Substituted for a redacted value, so the log still records that the column
	 * was touched without disclosing it.
	 */
	public const REDACTED = '[redacted]';

	/**
	 * Create the audit table.
	 *
	 * Uses dbDelta so the statement can be re-run on every activation without
	 * erroring, and so a future column addition is picked up by existing sites.
	 */
	public static function create_table(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table   = $wpdb->prefix . self::TABLE;
		$collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action varchar(40) NOT NULL DEFAULT '',
			entity_type varchar(60) NOT NULL DEFAULT '',
			entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
			payload_before longtext NULL,
			payload_after longtext NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY entity (entity_type,entity_id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$collate};";

		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::SCHEMA_VERSION, false );
	}

	/**
	 * Create the table when the installed version is behind.
	 *
	 * Registered on admin_init by each plugin so a table added after the initial
	 * deployment appears without a manual re-activation.
	 *
	 * @return bool True when an upgrade ran.
	 */
	public static function maybe_upgrade(): bool {
		$stored = get_option( self::VERSION_OPTION, '' );

		if ( self::SCHEMA_VERSION === $stored ) {
			return false;
		}
		if ( is_string( $stored ) && '' !== $stored && version_compare( $stored, self::SCHEMA_VERSION, '>' ) ) {
			// A newer build owns this table. Doing nothing is safer than
			// downgrading a schema that may already carry new columns.
			return false;
		}

		self::create_table();

		return true;
	}

	/**
	 * Record one action.
	 *
	 * Callers pass the whole before and after rows; reducing them to a redacted
	 * diff is this method's job, which is what keeps the six plugins honest.
	 *
	 * @param string               $action      Action name, e.g. `create` or `delete`.
	 * @param string               $entity_type Record type, e.g. `metric`.
	 * @param int                  $entity_id   Record id.
	 * @param array<string,mixed>|object|null $before Row before the change.
	 * @param array<string,mixed>|object|null $after  Row after the change.
	 * @param string[]                      $ignore  Extra columns to leave out.
	 * @return int Inserted row id, or 0 on failure.
	 */
	public static function record( string $action, string $entity_type, int $entity_id, array|object|null $before, array|object|null $after, array $ignore = array() ): int {
		global $wpdb;

		$before = ( null === $before ) ? null : (array) $before;
		$after  = ( null === $after ) ? null : (array) $after;

		$diff = self::diff( $before, $after, $ignore );

		// A create has no "before" and a delete has no "after"; an update that
		// changed nothing is not worth a row.
		if ( ! $diff['has_changes'] ) {
			return 0;
		}

		$inserted = $wpdb->insert(
			$wpdb->prefix . self::TABLE,
			array(
				'user_id'        => (int) get_current_user_id(),
				'action'         => substr( sanitize_key( $action ), 0, 40 ),
				'entity_type'    => substr( sanitize_key( $entity_type ), 0, 60 ),
				'entity_id'      => $entity_id,
				'payload_before' => $diff['has_before'] ? wp_json_encode( $diff['before'] ) : null,
				'payload_after'  => $diff['has_after'] ? wp_json_encode( $diff['after'] ) : null,
				'created_at'     => current_time( 'mysql', true ),
			)
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Reduce two rows to the columns that changed, with sensitive values removed.
	 *
	 * @param array<string,mixed>|null $before  Row before the change.
	 * @param array<string,mixed>|null $after   Row after the change.
	 * @param string[]                $ignore  Extra columns to leave out.
	 * @return array{has_changes:bool,has_before:bool,has_after:bool,before:array<string,mixed>,after:array<string,mixed>}
	 */
	public static function diff( array|object|null $before, array|object|null $after, array $ignore = array() ): array {
		$result = array(
			'has_changes' => false,
			'has_before'  => null !== $before,
			'has_after'   => null !== $after,
			'before'      => array(),
			'after'       => array(),
		);

		// Only columns present on both sides can be compared. A create or a
		// delete keeps whatever side it has, which is the whole point of logging
		// that the record existed at all.
		$before = ( null === $before ) ? null : (array) $before;
		$after  = ( null === $after ) ? null : (array) $after;

		if ( null === $before || null === $after ) {
			foreach ( self::redact( $before ?? array(), $ignore ) as $key => $value ) {
				$result['before'][ $key ] = $value;
			}
			foreach ( self::redact( $after ?? array(), $ignore ) as $key => $value ) {
				$result['after'][ $key ] = $value;
			}
			$result['has_changes'] = ( null !== $before ) || ( null !== $after );

			return $result;
		}

		$before = self::redact( $before, $ignore );
		$after  = self::redact( $after, $ignore );

		foreach ( $after as $key => $new_value ) {
			if ( ! array_key_exists( $key, $before ) ) {
				$result['before'][ $key ] = null;
				$result['after'][ $key ]  = $new_value;
				continue;
			}
			if ( self::same_value( $before[ $key ], $new_value ) ) {
				continue;
			}
			$result['before'][ $key ] = $before[ $key ];
			$result['after'][ $key ]  = $new_value;
		}

		// A column that was cleared is a change even though it is absent from
		// the "after" side of an update.
		foreach ( $before as $key => $old_value ) {
			if ( array_key_exists( $key, $after ) ) {
				continue;
			}
			$result['before'][ $key ] = $old_value;
			$result['after'][ $key ]  = null;
		}

		$result['has_changes'] = ! empty( $result['after'] );

		return $result;
	}

	/**
	 * Whether a column is redacted.
	 *
	 * @param string $column Column name.
	 * @return bool True when the value must not be stored.
	 */
	public static function is_redacted_column( string $column ): bool {
		$needle = strtolower( $column );
		foreach ( self::REDACTED_SUBSTRINGS as $fragment ) {
			if ( false !== strpos( $needle, $fragment ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Drop ignored columns and redact sensitive ones.
	 *
	 * @param array<string,mixed> $row    Row values.
	 * @param string[]            $ignore Extra columns to drop.
	 * @return array<string,mixed> Safe values.
	 */
	private static function redact( array $row, array $ignore ): array {
		$safe = array();
		foreach ( $row as $column => $value ) {
			$column = (string) $column;
			if ( in_array( $column, $ignore, true ) ) {
				continue;
			}
			if ( self::is_redacted_column( $column ) ) {
				$safe[ $column ] = self::REDACTED;
				continue;
			}
			$safe[ $column ] = self::scalarize( $value );
		}

		return $safe;
	}

	/**
	 * Convert a value to something safe to store and compare.
	 *
	 * Objects and closures are represented by their type rather than dumped, so
	 * a payload can never smuggle a serialized object into the log.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed Storable value.
	 */
	private static function scalarize( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return $value;
		}
		if ( is_array( $value ) ) {
			return array_map( array( self::class, 'scalarize' ), $value );
		}

		return '[' . gettype( $value ) . ']';
	}

	/**
	 * Compare two values the way the database would.
	 *
	 * MySQL is loose about '5' versus 5, so a numeric string left unchanged in
	 * the form must not be reported as an edit.
	 *
	 * @param mixed $a Old value.
	 * @param mixed $b New value.
	 * @return bool True when equal.
	 */
	private static function same_value( $a, $b ): bool {
		if ( is_numeric( $a ) && is_numeric( $b ) ) {
			return (string) ( 0 + $a ) === (string) ( 0 + $b );
		}

		return $a === $b;
	}

	/**
	 * Read log entries, newest first.
	 *
	 * @param array{entity_type?:string,entity_id?:int,user_id?:int,per_page?:int} $args Filters.
	 * @return array<int,object> Log rows.
	 */
	public static function entries( array $args = array() ): array {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['entity_type'] ) ) {
			$where[]  = 'entity_type = %s';
			$params[] = (string) $args['entity_type'];
		}
		if ( ! empty( $args['entity_id'] ) ) {
			$where[]  = 'entity_id = %d';
			$params[] = (int) $args['entity_id'];
		}
		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		$per_page = isset( $args['per_page'] ) ? max( 1, (int) $args['per_page'] ) : 50;

		$sql = 'SELECT * FROM ' . $wpdb->prefix . self::TABLE
			. ' WHERE ' . implode( ' AND ', $where )
			. ' ORDER BY id DESC LIMIT %d';

		$params[] = $per_page;

		return (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Delete every entry.
	 */
	public static function truncate(): void {
		global $wpdb;

		$table = $wpdb->prefix . self::TABLE;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}
}