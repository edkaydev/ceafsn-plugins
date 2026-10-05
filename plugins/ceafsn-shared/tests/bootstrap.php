<?php
/**
 * Standalone test bootstrap for the CE-AFSN shared library.
 *
 * Defines the minimal set of WordPress functions the library touches so the
 * suite can run with plain `php`, with no WordPress install and no Composer
 * dependencies:
 *
 *     php plugins/ceafsn-shared/tests/run-tests.php
 *
 * @package CEAFSN_Shared
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

/**
 * Mutable test state shared with the stubs below.
 */
final class CEAFSN_Shared_Test_State {

	/** @var array<string,mixed> Option store. */
	public static array $options = array();

	/** @var array<int,array<string,mixed>> Capabilities the current user holds. */
	public static array $user_caps = array();

	/** @var int User id reported by get_current_user_id(). */
	public static int $user_id = 0;

	/** @var array<string,array<string,bool>> Caps per registered role. */
	public static array $roles = array();

	/** @var array<int,array<int,mixed>> select() calls, for asserting the LIKE shape. */
	public static array $queries = array();

	/**
	 * Reset all state between tests.
	 */
	public static function reset(): void {
		self::$options   = array();
		self::$user_caps = array();
		self::$user_id   = 0;
		self::$queries   = array();
		self::$roles     = array(
			'administrator' => array(
				'read'           => true,
				'manage_options' => true,
			),
		);
	}

	/**
	 * Drop a role from the store.
	 *
	 * @param string $role_name Role name.
	 * @return void
	 */
	public static function drop_role( string $role_name ): void {
		unset( self::$roles[ $role_name ] );
	}
}

/**
 * Role object standing in for WP_Role.
 */
final class FakeRole {

	/**
	 * Capabilities live in CEAFSN_Shared_Test_State::$roles, the single store,
	 * so add_cap() and remove_cap() persist the way they do on a real WP_Role.
	 *
	 * @var string Role name.
	 */
	private string $name;

	/**
	 * @param string $name Role name.
	 */
	public function __construct( string $name ) {
		$this->name = $name;
	}

	/**
	 * @param string $cap Capability name.
	 * @return bool True when granted.
	 */
	public function has_cap( string $cap ): bool {
		return ! empty( CEAFSN_Shared_Test_State::$roles[ $this->name ][ $cap ] );
	}

	/**
	 * @param string $cap Capability name.
	 * @return void
	 */
	public function add_cap( string $cap ): void {
		CEAFSN_Shared_Test_State::$roles[ $this->name ][ $cap ] = true;
	}

	/**
	 * @param string $cap Capability name.
	 * @return void
	 */
	public function remove_cap( string $cap ): void {
		unset( CEAFSN_Shared_Test_State::$roles[ $this->name ][ $cap ] );
	}

	/**
	 * @return string Role name.
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * @return array<string,bool> Capabilities.
	 */
	public function caps(): array {
		return CEAFSN_Shared_Test_State::$roles[ $this->name ] ?? array();
	}
}

/**
 * Minimal $wpdb stand-in.
 */
final class FakeWpdb {

	/** @var string Table prefix. */
	public string $prefix = 'wp_';

	/** @var int Row id returned by the last insert. */
	public int $insert_id = 0;

	/** @var array<int,array<string,mixed>> Rows handed to insert(). */
	public array $inserted_rows = array();

	/** @var array<int,array<int,mixed>> Queries handed to prepare(). */
	public array $prepared = array();

	/**
	 * Charset clause for CREATE TABLE statements.
	 *
	 * @return string Charset and collation.
	 */
	public function get_charset_collate(): string {
		return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
	}

	/**
	 * @param string $table  Table name.
	 * @param array<string,mixed> $data Row values.
	 * @param array<int,string> $format Ignored, as in WordPress.
	 * @return int|false Rows affected, or false on failure.
	 */
	public function insert( string $table, array $data, array $format = array() ) {
		if ( ! empty( $GLOBALS['ceafsn_shared_fail_insert'] ) ) {
			return false;
		}

		$this->inserted_rows[] = array( 'table' => $table, 'data' => $data );
		$this->insert_id        = count( $this->inserted_rows );

		return 1;
	}

	/**
	 * @param string $sql Query with placeholders.
	 * @param mixed  ...$args Values.
	 * @return string Query with values substituted.
	 */
	public function prepare( string $sql, ...$args ): string {
		if ( 1 === count( $args ) && is_array( $args[0] ) ) {
			$args = $args[0];
		}
		$this->prepared[] = $args;

		$i = 0;
		$out = preg_replace_callback(
			'/%[sd]/',
			function ( array $m ) use ( $args, &$i ): string {
				$value = $args[ $i ] ?? null;
				++$i;

				return '%d' === $m[0] ? (string) (int) $value : "'" . (string) $value . "'";
			},
			$sql
		);

		return (string) $out;
	}

	/**
	 * @param string $sql Query.
	 * @return array<int,object> Empty result set.
	 */
	public function get_results( string $sql ): array {
		CEAFSN_Shared_Test_State::$queries[] = $sql;

		return array();
	}

	/**
	 * @param string $sql Query.
	 * @return int Zero rows.
	 */
	public function query( string $sql ): int {
		CEAFSN_Shared_Test_State::$queries[] = $sql;

		return 0;
	}
}

CEAFSN_Shared_Test_State::reset();

$GLOBALS['wpdb'] = new FakeWpdb();

/**
 * @param string $text  Text.
 * @param string $domain Text domain.
 * @return string Text.
 */
function __( string $text, string $domain = 'default' ): string { // phpcs:ignore
	return $text;
}

/**
 * @param string $text Text.
 * @param string $domain Text domain.
 * @return string Text.
 */
function esc_html__( string $text, string $domain = 'default' ): string { // phpcs:ignore
	return $text;
}

/**
 * @param string $key Option name.
 * @param mixed  $default Default value.
 * @return mixed Option value.
 */
function get_option( string $key, $default = false ) {
	return CEAFSN_Shared_Test_State::$options[ $key ] ?? $default;
}

/**
 * @param string $key   Option name.
 * @param mixed  $value Option value.
 * @param bool   $autoload Ignored.
 * @return bool True.
 */
function update_option( string $key, $value, $autoload = null ): bool { // phpcs:ignore
	CEAFSN_Shared_Test_State::$options[ $key ] = $value;

	return true;
}

/**
 * @param string $capability Capability name.
 * @return bool True when the current user holds it.
 */
function current_user_can( string $capability ): bool {
	return ! empty( CEAFSN_Shared_Test_State::$user_caps[ $capability ] );
}

/**
 * @return int Current user id.
 */
function get_current_user_id(): int {
	return CEAFSN_Shared_Test_State::$user_id;
}

/**
 * @param string $role_name Role name.
 * @return FakeRole|null Role, or null when unknown.
 */
function get_role( string $role_name ): ?FakeRole {
	if ( ! isset( CEAFSN_Shared_Test_State::$roles[ $role_name ] ) ) {
		return null;
	}

	return new FakeRole( $role_name );
}

/**
 * @param string $role_name Role name.
 * @param string $display   Display name.
 * @param array<string,bool> $caps Capabilities.
 * @return FakeRole New role.
 */
function add_role( string $role_name, string $display, array $caps = array() ): FakeRole {
	CEAFSN_Shared_Test_State::$roles[ $role_name ] = $caps;

	return new FakeRole( $role_name );
}

/**
 * @param string $role_name Role name.
 * @return void
 */
function remove_role( string $role_name ): void {
	CEAFSN_Shared_Test_State::drop_role( $role_name );
}

/**
 * @param string $key Value.
 * @return string Sanitized key.
 */
function sanitize_key( string $key ): string {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
}

/**
 * @param mixed $data Value to encode.
 * @return string JSON.
 */
function wp_json_encode( $data ): string {
	return (string) json_encode( $data );
}

/**
 * @param string $type Time type.
 * @param bool   $gmt  Whether to use UTC.
 * @return string Formatted time.
 */
function current_time( string $type = 'mysql', bool $gmt = false ): string { // phpcs:ignore
	return '2026-01-01 00:00:00';
}

require_once dirname( __DIR__ ) . '/ceafsn-shared-load.php';