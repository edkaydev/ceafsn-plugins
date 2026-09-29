<?php
/**
 * Standalone test bootstrap for CE-AFSN M&E Dashboard.
 *
 * Defines the minimal set of WordPress functions the plugin touches so the
 * test suite can run with plain `php` on a machine with no WordPress install
 * and no Composer dependencies:
 *
 *     php plugins/ceafsn-me-dashboard/tests/run-tests.php
 *
 * When the WordPress test suite is present the same files can also be run
 * under PHPUnit via `tests/test-ceafsn-med.php` — the assertions here are
 * plain PHP so they stay framework independent.
 *
 * @package CEAFSN_MED
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

/**
 * Mutable test state shared with the stubs below.
 */
final class CEAFSN_Test_State {
	/** @var array<string,mixed> Option store. */
	public static array $options = array();

	/** @var array<int,array<string,mixed>> Enqueued styles. */
	public static array $styles = array();

	/** @var array<int,array<string,mixed>> Enqueued scripts. */
	public static array $scripts = array();

	/** @var array<int,array<string,mixed>> Registered shortcodes. */
	public static array $shortcodes = array();

	/** @var array<int,string> Redirects performed. */
	public static array $redirects = array();

	/** @var array<string,array<int,mixed>> Callbacks registered per action hook. */
	public static array $actions = array();

	/** @var bool Set when wp_die() is called. */
	public static bool $died = false;

	/** @var string Message passed to wp_die(). */
	public static string $die_message = '';

	/**
	 * Reset all state between tests.
	 */
	public static function reset(): void {
		self::$options   = array();
		self::$styles    = array();
		self::$scripts   = array();
		self::$shortcodes = array();
		self::$redirects = array();
		self::$actions   = array();
		self::$died      = false;
		self::$die_message = '';
	}
}

// -----------------------------------------------------------------------------
// WordPress function stubs
// -----------------------------------------------------------------------------

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * Record an action so tests can fire it.
	 *
	 * @param string $hook     Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $args     Accepted args.
	 * @return bool Always true.
	 */
	function add_action( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		unset( $priority, $args );
		CEAFSN_Test_State::$actions[ $hook ][] = $callback;
		return true;
	}
}

/**
 * Fire every callback registered for an action.
 *
 * @param string $hook Hook name.
 */
function ceafsn_test_fire_action( string $hook ): void {
	foreach ( CEAFSN_Test_State::$actions[ $hook ] ?? array() as $callback ) {
		if ( is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * No-op filter stub.
	 *
	 * @param string $hook     Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $args     Accepted args.
	 */
	function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		unset( $hook, $callback, $priority, $args );
		return true;
	}
}

if ( ! function_exists( 'add_shortcode' ) ) {
	/**
	 * Record a registered shortcode.
	 *
	 * @param string $tag      Shortcode tag.
	 * @param mixed  $callback Callback.
	 */
	function add_shortcode( string $tag, $callback ): void {
		CEAFSN_Test_State::$shortcodes[ $tag ] = $callback;
	}
}

if ( ! function_exists( 'plugin_dir_path' ) ) {
	/**
	 * Directory path for a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string Path with trailing slash.
	 */
	function plugin_dir_path( string $file ): string {
		return rtrim( dirname( $file ), '/' ) . '/';
	}
}

if ( ! function_exists( 'plugin_dir_url' ) ) {
	/**
	 * URL for a plugin directory.
	 *
	 * @param string $file Plugin file.
	 * @return string URL with trailing slash.
	 */
	function plugin_dir_url( string $file ): string {
		unset( $file );
		return 'https://example.test/wp-content/plugins/ceafsn-me-dashboard/';
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * Basename of a plugin file.
	 *
	 * @param string $file Plugin file.
	 * @return string Basename.
	 */
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}

if ( ! function_exists( 'register_activation_hook' ) ) {
	/**
	 * No-op activation hook stub.
	 *
	 * @param string $file     Plugin file.
	 * @param mixed  $callback Callback.
	 */
	function register_activation_hook( string $file, $callback ): void {
		unset( $file, $callback );
	}
}

if ( ! function_exists( 'register_deactivation_hook' ) ) {
	/**
	 * No-op deactivation hook stub.
	 *
	 * @param string $file     Plugin file.
	 * @param mixed  $callback Callback.
	 */
	function register_deactivation_hook( string $file, $callback ): void {
		unset( $file, $callback );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	/**
	 * Front-end context stub.
	 *
	 * @return bool Always false in tests.
	 */
	function is_admin(): bool {
		return false;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Read from the test option store.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default Default value.
	 * @return mixed Stored value or default.
	 */
	function get_option( string $name, $default = false ) {
		return array_key_exists( $name, CEAFSN_Test_State::$options )
			? CEAFSN_Test_State::$options[ $name ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * Write to the test option store.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 * @return bool Always true.
	 */
	function update_option( string $name, $value ): bool {
		CEAFSN_Test_State::$options[ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Remove from the test option store.
	 *
	 * @param string $name Option name.
	 * @return bool Always true.
	 */
	function delete_option( string $name ): bool {
		unset( CEAFSN_Test_State::$options[ $name ] );
		return true;
	}
}

if ( ! function_exists( 'wp_enqueue_style' ) ) {
	/**
	 * Record an enqueued style.
	 *
	 * @param string $handle Handle.
	 * @param string $src    Source URL.
	 * @param array  $deps   Dependencies.
	 * @param string $ver    Version.
	 */
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), string $ver = '' ): void {
		CEAFSN_Test_State::$styles[] = compact( 'handle', 'src', 'deps', 'ver' );
	}
}

if ( ! function_exists( 'wp_enqueue_script' ) ) {
	/**
	 * Record an enqueued script.
	 *
	 * @param string $handle    Handle.
	 * @param string $src       Source URL.
	 * @param array  $deps      Dependencies.
	 * @param string $ver       Version.
	 * @param bool   $in_footer Whether to print in the footer.
	 */
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), string $ver = '', bool $in_footer = false ): void {
		CEAFSN_Test_State::$scripts[] = compact( 'handle', 'src', 'deps', 'ver', 'in_footer' );
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	/**
	 * Style status stub.
	 *
	 * @param string $handle Handle.
	 * @param string $list   Status list.
	 * @return bool Whether the handle is registered.
	 */
	function wp_style_is( string $handle, string $list = 'enqueued' ): bool {
		unset( $list );
		foreach ( CEAFSN_Test_State::$styles as $style ) {
			if ( $style['handle'] === $handle ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'wp_script_is' ) ) {
	/**
	 * Script status stub.
	 *
	 * @param string $handle Handle.
	 * @param string $list   Status list.
	 * @return bool Whether the handle is registered.
	 */
	function wp_script_is( string $handle, string $list = 'enqueued' ): bool {
		unset( $list );
		foreach ( CEAFSN_Test_State::$scripts as $script ) {
			if ( $script['handle'] === $handle ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability stub — tests set the answer with ceafsn_test_set_caps().
	 *
	 * @param string $capability Capability.
	 * @return bool Whether the current user has the capability.
	 */
	function current_user_can( string $capability ): bool {
		$caps = CEAFSN_Test_State::$options['__caps'] ?? array();
		return ! empty( $caps[ $capability ] );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Record a wp_die() call instead of terminating the process.
	 *
	 * @param string $message Message.
	 * @param mixed  $code    Status code.
	 */
	function wp_die( $message = '', $code = null ): void {
		unset( $code );
		CEAFSN_Test_State::$died        = true;
		CEAFSN_Test_State::$die_message = is_scalar( $message ) ? (string) $message : '';
		throw new RuntimeException( 'wp_die' );
	}
}

if ( ! function_exists( 'wp_safe_redirect' ) ) {
	/**
	 * Record a redirect instead of sending headers.
	 *
	 * @param string $location Target URL.
	 * @param int    $status   HTTP status.
	 * @return bool Always true.
	 */
	function wp_safe_redirect( string $location, int $status = 302 ): bool {
		CEAFSN_Test_State::$redirects[] = array(
			'location' => $location,
			'status'   => $status,
		);
		return true;
	}
}

if ( ! function_exists( 'wp_get_referer' ) ) {
	/**
	 * Referer stub.
	 *
	 * @return string|false Always false.
	 */
	function wp_get_referer() {
		return false;
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	/**
	 * Admin URL stub.
	 *
	 * @param string $path Path.
	 * @return string Absolute URL.
	 */
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * Home URL stub.
	 *
	 * @param string $path Path.
	 * @return string Absolute URL.
	 */
	function home_url( string $path = '' ): string {
		return 'https://example.test/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append a query argument.
	 *
	 * @param string $key   Argument key.
	 * @param string $value Argument value.
	 * @param string $url   Base URL.
	 * @return string Resulting URL.
	 */
	function add_query_arg( string $key, string $value, string $url ): string {
		$separator = str_contains( $url, '?' ) ? '&' : '?';
		return $url . $separator . rawurlencode( $key ) . '=' . rawurlencode( $value );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Nonce field stub.
	 *
	 * @param string $action Nonce action.
	 * @param string $name   Field name.
	 * @param bool   $referer Whether to add a referer field.
	 * @param bool   $display Whether to echo.
	 * @return string Field HTML.
	 */
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true ): string {
		unset( $referer );
		$html = '<input type="hidden" name="' . esc_attr( $name ) . '" value="testnonce" />';
		if ( $display ) {
			echo $html;
		}
		return $html;
	}
}

if ( ! function_exists( 'wp_nonce_url' ) ) {
	/**
	 * Nonce URL stub.
	 *
	 * @param string $actionurl Action URL.
	 * @param string $action    Nonce action.
	 * @param string $name      Query arg name.
	 * @return string URL with nonce.
	 */
	function wp_nonce_url( string $actionurl, string $action = '-1', string $name = '_wpnonce' ): string {
		return add_query_arg( $name, 'testnonce', $actionurl );
	}
}

if ( ! function_exists( 'check_admin_referer' ) ) {
	/**
	 * Nonce verification stub.
	 *
	 * @param string $action Nonce action.
	 * @param string $query_arg Query arg name.
	 * @return bool Always true in tests.
	 */
	function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): bool {
		unset( $action, $query_arg );
		return true;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Remove slashes.
	 *
	 * @param mixed $value Value.
	 * @return mixed Unslashed value.
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Strip tags and collapse whitespace.
	 *
	 * @param string $value Raw value.
	 * @return string Clean value.
	 */
	function sanitize_text_field( string $value ): string {
		$value = strip_tags( $value );
		$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
		return trim( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	/**
	 * Strip tags from multi-line input.
	 *
	 * @param string $value Raw value.
	 * @return string Clean value.
	 */
	function sanitize_textarea_field( string $value ): string {
		return trim( strip_tags( $value ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Lowercase key-safe string.
	 *
	 * @param string $value Raw value.
	 * @return string Clean value.
	 */
	function sanitize_key( string $value ): string {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Sanitise a URL for storage.
	 *
	 * @param string $url Raw URL.
	 * @return string Clean URL.
	 */
	function esc_url_raw( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$url = preg_replace( '/[^a-zA-Z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', '', $url );
		return (string) $url;
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Sanitise a URL for output.
	 *
	 * @param string $url Raw URL.
	 * @return string Clean URL.
	 */
	function esc_url( string $url ): string {
		return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape for HTML output.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape for an attribute value.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	/**
	 * Escape for a textarea.
	 *
	 * @param string $text Raw text.
	 * @return string Escaped text.
	 */
	function esc_textarea( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'absint' ) ) {
	/**
	 * Cast to a non-negative integer.
	 *
	 * @param mixed $value Raw value.
	 * @return int Absolute integer.
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub returning the original string.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string Text.
	 */
	function __( string $text, string $domain = 'default' ): string {
		unset( $domain );
		return $text;
	}
}

if ( ! function_exists( '_x' ) ) {
	/**
	 * Context translation stub.
	 *
	 * @param string $text    Text.
	 * @param string $context Context.
	 * @param string $domain  Text domain.
	 * @return string Text.
	 */
	function _x( string $text, string $context, string $domain = 'default' ): string {
		unset( $context, $domain );
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Plural translation stub.
	 *
	 * @param string $single Singular text.
	 * @param string $plural Plural text.
	 * @param int    $number Count.
	 * @param string $domain Text domain.
	 * @return string Text.
	 */
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		unset( $domain );
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Translate and escape.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string Escaped text.
	 */
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Translate, escape, and echo.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function esc_html_e( string $text, string $domain = 'default' ): void {
		echo esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Translate and escape for an attribute.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string Escaped text.
	 */
	function esc_attr__( string $text, string $domain = 'default' ): string {
		return esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	/**
	 * Translate, escape for an attribute, and echo.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 */
	function esc_attr_e( string $text, string $domain = 'default' ): void {
		echo esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Number formatting stub.
	 *
	 * @param float $number   Number.
	 * @param int   $decimals Decimal places.
	 * @return string Formatted number.
	 */
	function number_format_i18n( float $number, int $decimals = 0 ): string {
		return number_format( $number, $decimals );
	}
}

if ( ! function_exists( 'date_i18n' ) ) {
	/**
	 * Format a date using the site format.
	 *
	 * @param string $format    Date format.
	 * @param int    $timestamp Unix timestamp, 0 for now.
	 * @param bool   $gmt       Whether the timestamp is GMT.
	 * @return string Formatted date.
	 */
	function date_i18n( string $format, int $timestamp = 0, bool $gmt = false ): string {
		unset( $gmt );
		return gmdate( $format, 0 === $timestamp ? time() : $timestamp );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Permalink stub for the current page.
	 *
	 * @return string Page URL.
	 */
	function get_permalink(): string {
		return 'https://example.test/me-dashboard/';
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Echo a selected attribute when two values match.
	 *
	 * @param mixed $one      First value.
	 * @param mixed $two      Second value.
	 * @param bool  $display  Whether to echo.
	 * @return string Attribute HTML.
	 */
	function selected( $one, $two = true, bool $display = true ): string {
		$html = (string) $one === (string) $two ? ' selected="selected"' : '';
		if ( $display ) {
			echo $html;
		}
		return $html;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Current user ID stub.
	 *
	 * @return int User ID, 1 by default.
	 */
	function get_current_user_id(): int {
		return (int) ( CEAFSN_Test_State::$options['__user_id'] ?? 1 );
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	/**
	 * Nonce creation stub.
	 *
	 * @param string $action Nonce action.
	 * @return string Nonce value.
	 */
	function wp_create_nonce( string $action = '-1' ): string {
		unset( $action );
		return 'testnonce';
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Nonce verification stub.
	 *
	 * @param string $nonce  Nonce value.
	 * @param string $action Nonce action.
	 * @return int|false Always 1.
	 */
	function wp_verify_nonce( string $nonce, string $action = '-1' ): int|false {
		unset( $nonce, $action );
		return 1;
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	/**
	 * No-op rewrite flush stub.
	 *
	 * @param bool $hard Whether to write .htaccess.
	 */
	function flush_rewrite_rules( bool $hard = true ): void {
		unset( $hard );
	}
}
