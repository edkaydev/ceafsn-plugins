<?php
/**
 * Standalone test bootstrap for CE-AFSN Open Datasets.
 *
 * Defines the minimal set of WordPress functions the plugin touches so the
 * test suite can run with plain `php` on a machine with no WordPress install
 * and no Composer dependencies:
 *
 *     php plugins/ceafsn-open-datasets/tests/run-tests.php
 *
 * @package CEAFSN_OD
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

/**
 * Mutable test state shared with the stubs below.
 */
final class CEAFSN_OD_Test_State {

	/** @var array<string,mixed> Option store. */
	public static array $options = array();

	/** @var array<int,array<string,mixed>> Enqueued styles. */
	public static array $styles = array();

	/** @var array<int,array<string,mixed>> Enqueued scripts. */
	public static array $scripts = array();

	/** @var array<int,array<string,mixed>> Registered shortcodes. */
	public static array $shortcodes = array();

	/** @var array<string,array<int,mixed>> Callbacks registered per action hook. */
	public static array $actions = array();

	/** @var array<string,array<int,mixed>> Callbacks registered per filter hook. */
	public static array $filters = array();

	/** @var array<int,array<string,mixed>> Redirects performed. */
	public static array $redirects = array();

	/** @var bool Set when wp_die() is called. */
	public static bool $died = false;

	/** @var string Message passed to wp_die(). */
	public static string $die_message = '';

	/** @var array<int,array<string,mixed>> Fake attachment records. */
	public static array $attachments = array();

	/** @var bool Whether wp_enqueue_media() was called. */
	public static bool $media_enqueued = false;

	/** @var array<string,string> Script translations registered by handle. */
	public static array $script_translations = array();

	/** @var array<int,string> URLs passed to wp_safe_remote_head(). */
	public static array $http_requests = array();

	/** @var array<string,string> Text domains loaded, mapped to their path. */
	public static array $textdomains = array();

	/**
	 * Reset all state between tests.
	 */
	public static function reset(): void {
		self::$options      = array();
		self::$styles       = array();
		self::$scripts      = array();
		self::$shortcodes   = array();
		self::$actions      = array();
		self::$filters      = array();
		self::$redirects    = array();
		self::$died         = false;
		self::$die_message  = '';
		self::$attachments  = array();
		self::$media_enqueued = false;
		self::$script_translations = array();
		self::$http_requests = array();
		self::$textdomains   = array();
	}

	/**
	 * Register a fake attachment.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $file Absolute path on disk.
	 * @param string $mime MIME type.
	 */
	public static function add_attachment( int $id, string $file, string $mime = 'text/csv' ): void {
		self::$attachments[ $id ] = array(
			'file' => $file,
			'mime' => $mime,
		);
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
		CEAFSN_OD_Test_State::$actions[ $hook ][] = $callback;
		return true;
	}
}

/**
 * Fire every callback registered for an action.
 *
 * @param string $hook Hook name.
 */
function ceafsn_od_test_fire_action( string $hook ): void {
	foreach ( CEAFSN_OD_Test_State::$actions[ $hook ] ?? array() as $callback ) {
		if ( is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Record a filter so tests can apply it.
	 *
	 * @param string $hook     Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @param int    $args     Accepted args.
	 * @return bool Always true.
	 */
	function add_filter( string $hook, $callback, int $priority = 10, int $args = 1 ): bool {
		unset( $priority, $args );
		CEAFSN_OD_Test_State::$filters[ $hook ][] = $callback;
		return true;
	}
}

if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Remove a recorded filter.
	 *
	 * @param string $hook     Hook name.
	 * @param mixed  $callback Callback.
	 * @param int    $priority Priority.
	 * @return bool Whether something was removed.
	 */
	function remove_filter( string $hook, $callback, int $priority = 10 ): bool {
		unset( $priority );
		$before = count( CEAFSN_OD_Test_State::$filters[ $hook ] ?? array() );
		CEAFSN_OD_Test_State::$filters[ $hook ] = array_values(
			array_filter(
				CEAFSN_OD_Test_State::$filters[ $hook ] ?? array(),
				static fn( $registered ) => $registered !== $callback
			)
		);
		return $before !== count( CEAFSN_OD_Test_State::$filters[ $hook ] );
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Apply recorded filter callbacks.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @param mixed  ...$args Extra args.
	 * @return mixed Filtered value.
	 */
	function apply_filters( string $hook, $value, ...$args ) {
		foreach ( CEAFSN_OD_Test_State::$filters[ $hook ] ?? array() as $callback ) {
			if ( is_callable( $callback ) ) {
				$value = call_user_func( $callback, $value, ...$args );
			}
		}
		return $value;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	/**
	 * Add an option if it does not exist.
	 *
	 * @param string $name  Option name.
	 * @param mixed  $value Value.
	 * @return bool Whether it was added.
	 */
	function add_option( string $name, $value = '' ): bool {
		if ( array_key_exists( $name, CEAFSN_OD_Test_State::$options ) ) {
			return false;
		}
		CEAFSN_OD_Test_State::$options[ $name ] = $value;
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
		CEAFSN_OD_Test_State::$shortcodes[ $tag ] = $callback;
	}
}

if ( ! function_exists( 'shortcode_atts' ) ) {
	/**
	 * Merge shortcode attributes with defaults.
	 *
	 * @param array<string,mixed> $pairs Defaults.
	 * @param array<string,mixed> $atts  Supplied attributes.
	 * @param string              $shortcode Shortcode tag.
	 * @return array<string,mixed>
	 */
	function shortcode_atts( array $pairs, array $atts, string $shortcode = '' ): array {
		unset( $shortcode );
		$out = array();
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
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
		return 'https://example.test/wp-content/plugins/ceafsn-open-datasets/';
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

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Parse a URL.
	 *
	 * @param string $url       URL.
	 * @param int    $component Component constant.
	 * @return mixed Component value, or false.
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( (string) $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
	}
}

if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	/**
	 * Record the text domain load.
	 *
	 * @param string $domain Text domain.
	 * @param bool   $abs    Unused.
	 * @param string $path   Languages path.
	 * @return bool
	 */
	function load_plugin_textdomain( string $domain, bool $abs = false, string $path = '' ): bool {
		CEAFSN_OD_Test_State::$textdomains[ $domain ] = $path;
		return true;
	}
}

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * Path basename using forward slashes.
	 *
	 * @param string $path      Path.
	 * @param string $suffix    Suffix to trim.
	 * @return string Basename.
	 */
	function wp_basename( string $path, string $suffix = '' ): string {
		return urldecode( basename( str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ), $suffix ) );
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
	 * Admin context stub, driven by the test state.
	 *
	 * @return bool Whether the current request is an admin request.
	 */
	function is_admin(): bool {
		return ! empty( CEAFSN_OD_Test_State::$options['__is_admin'] );
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
		return array_key_exists( $name, CEAFSN_OD_Test_State::$options )
			? CEAFSN_OD_Test_State::$options[ $name ]
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
		CEAFSN_OD_Test_State::$options[ $name ] = $value;
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
		unset( CEAFSN_OD_Test_State::$options[ $name ] );
		return true;
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	/**
	 * Resolve an attachment to a file path.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function get_attached_file( $attachment_id ) {
		$attachment = CEAFSN_OD_Test_State::$attachments[ (int) $attachment_id ] ?? null;
		return $attachment ? (string) $attachment['file'] : false;
	}
}

if ( ! function_exists( 'get_post_mime_type' ) ) {
	/**
	 * Resolve an attachment MIME type.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function get_post_mime_type( $attachment_id ) {
		$attachment = CEAFSN_OD_Test_State::$attachments[ (int) $attachment_id ] ?? null;
		return $attachment ? (string) $attachment['mime'] : false;
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	/**
	 * Resolve an attachment to a public URL.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function wp_get_attachment_url( $attachment_id ) {
		$attachment = CEAFSN_OD_Test_State::$attachments[ (int) $attachment_id ] ?? null;
		if ( ! $attachment ) {
			return false;
		}
		return 'https://example.test/wp-content/uploads/' . basename( (string) $attachment['file'] );
	}
}

if ( ! function_exists( 'wp_enqueue_media' ) ) {
	/**
	 * Record that the media library was requested.
	 */
	function wp_enqueue_media(): void {
		CEAFSN_OD_Test_State::$media_enqueued = true;
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
		CEAFSN_OD_Test_State::$styles[] = compact( 'handle', 'src', 'deps', 'ver' );
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
		CEAFSN_OD_Test_State::$scripts[] = compact( 'handle', 'src', 'deps', 'ver', 'in_footer' );
	}
}

if ( ! function_exists( 'wp_style_is' ) ) {
	/**
	 * Style status stub.
	 *
	 * @param string $handle Handle.
	 * @param string $list   Status list.
	 * @return bool Whether the handle is enqueued.
	 */
	function wp_style_is( string $handle, string $list = 'enqueued' ): bool {
		unset( $list );
		foreach ( CEAFSN_OD_Test_State::$styles as $style ) {
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
	 * @return bool Whether the handle is enqueued.
	 */
	function wp_script_is( string $handle, string $list = 'enqueued' ): bool {
		unset( $list );
		foreach ( CEAFSN_OD_Test_State::$scripts as $script ) {
			if ( $script['handle'] === $handle ) {
				return true;
			}
		}
		return false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability stub driven by the test state.
	 *
	 * @param string $capability Capability.
	 * @return bool Whether the user has the capability.
	 */
	function current_user_can( string $capability ): bool {
		$caps = CEAFSN_OD_Test_State::$options['__caps'] ?? array();
		return ! empty( $caps[ $capability ] );
	}
}

if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Record a wp_die() call instead of terminating the process.
	 *
	 * @param string $message Message.
	 * @param mixed  $code    Status code.
	 * @throws RuntimeException Always, so tests can catch the abort.
	 */
	function wp_die( $message = '', $code = null ): void {
		unset( $code );
		CEAFSN_OD_Test_State::$died        = true;
		CEAFSN_OD_Test_State::$die_message = is_scalar( $message ) ? (string) $message : '';
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
		CEAFSN_OD_Test_State::$redirects[] = array(
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

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Permalink stub for the current page.
	 *
	 * @return string Page URL.
	 */
	function get_permalink(): string {
		return 'https://example.test/open-datasets/';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Add or replace query arguments on a URL.
	 *
	 * @param array<string,mixed>|string $key   Args map or single key.
	 * @param mixed                       $value Value when a single key is given.
	 * @param string                      $url   Base URL.
	 * @return string Resulting URL.
	 */
	function add_query_arg( $key, $value = null, $url = null ) {
		if ( is_array( $key ) ) {
			$args = $key;
			$url  = is_string( $value ) ? $value : '';
		} else {
			$args = array( (string) $key => (string) $value );
			$url  = is_string( $url ) ? $url : '';
		}

		$parts    = explode( '#', $url, 2 );
		$fragment = isset( $parts[1] ) ? '#' . $parts[1] : '';
		$base     = $parts[0];

		$split      = explode( '?', $base, 2 );
		$path       = $split[0];
		$query      = array();
		$existing   = $split[1] ?? '';

		if ( '' !== $existing ) {
			parse_str( $existing, $query );
		}

		foreach ( $args as $name => $arg ) {
			if ( null === $arg || '' === $arg ) {
				unset( $query[ $name ] );
				continue;
			}
			$query[ $name ] = $arg;
		}

		ksort( $query );
		$string = http_build_query( $query );

		return $path . ( '' !== $string ? '?' . $string : '' ) . $fragment;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Nonce field stub.
	 *
	 * @param string $action  Nonce action.
	 * @param string $name    Field name.
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
	 * @param string $action    Nonce action.
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
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $value ) );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	/**
	 * Strip path separators and unsafe characters from a file name.
	 *
	 * @param string $name Raw file name.
	 * @return string Clean file name.
	 */
	function sanitize_file_name( string $name ): string {
		$name = basename( $name );
		$name = (string) preg_replace( '/[^A-Za-z0-9._-]/', '', $name );
		return trim( $name, '.-' );
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
		$url = (string) preg_replace( '/[^a-zA-Z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', '', $url );

		// Mirror wp_allowed_protocols(): anything without an allow-listed scheme
		// is rejected outright, which is what stops javascript: and data: links.
		if ( preg_match( '#^([a-zA-Z][a-zA-Z0-9+.\-]*):#', $url, $matches ) ) {
			$allowed = array( 'http', 'https', 'mailto', 'ftp', 'ftps' );
			if ( ! in_array( strtolower( $matches[1] ), $allowed, true ) ) {
				return '';
			}
		}

		return $url;
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

if ( ! function_exists( 'esc_html_x' ) ) {
	/**
	 * Context translation, escaped.
	 *
	 * @param string $text    Text.
	 * @param string $context Context.
	 * @param string $domain  Text domain.
	 * @return string Escaped text.
	 */
	function esc_html_x( string $text, string $context, string $domain = 'default' ): string {
		unset( $context, $domain );
		return esc_html( $text );
	}
}

if ( ! function_exists( 'selected' ) ) {
	/**
	 * Echo a selected attribute when two values match.
	 *
	 * @param mixed $one     First value.
	 * @param mixed $two     Second value.
	 * @param bool  $display Whether to echo.
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

if ( ! function_exists( 'checked' ) ) {
	/**
	 * Echo a checked attribute when two values match.
	 *
	 * @param mixed $one     First value.
	 * @param mixed $two     Second value.
	 * @param bool  $display Whether to echo.
	 * @return string Attribute HTML.
	 */
	function checked( $one, $two = true, bool $display = true ): string {
		$html = (string) $one === (string) $two ? ' checked="checked"' : '';
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
		return (int) ( CEAFSN_OD_Test_State::$options['__user_id'] ?? 1 );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * JSON encoder stub.
	 *
	 * @param mixed $data  Data.
	 * @param int   $flags Encoding flags.
	 * @return string|false JSON.
	 */
	function wp_json_encode( $data, int $flags = 0 ) {
		return json_encode( $data, $flags );
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

if ( ! function_exists( 'number_format_i18n' ) ) {
	/**
	 * Locale-aware number formatting stub.
	 *
	 * @param float $number   Number.
	 * @param int   $decimals Decimal places.
	 * @return string
	 */
	function number_format_i18n( $number, int $decimals = 0 ): string {
		return number_format( (float) $number, $decimals );
	}
}

if ( ! function_exists( 'wp_set_script_translations' ) ) {
	/**
	 * Record a script translation registration.
	 *
	 * @param string $handle Handle.
	 * @param string $domain Text domain.
	 * @return bool Always true.
	 */
	function wp_set_script_translations( string $handle, string $domain = 'default' ): bool {
		CEAFSN_OD_Test_State::$script_translations[ $handle ] = $domain;
		return true;
	}
}

// -----------------------------------------------------------------------------
// HTTP stubs
// -----------------------------------------------------------------------------

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in.
	 */
	class WP_Error {

		/** @var string Error code. */
		public string $code;

		/** @var string Error message. */
		public string $message;

		/**
		 * Build an error.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Error message accessor.
		 *
		 * @return string Message.
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * Error detection stub.
	 *
	 * @param mixed $thing Value.
	 * @return bool Whether the value is a WP_Error.
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! function_exists( 'wp_safe_remote_head' ) ) {
	/**
	 * HEAD request stub driven by a test-controlled URL map.
	 *
	 * CEAFSN_OD_Test_State::$options['__http'] maps a URL to either
	 * array( code, headers ) or a WP_Error instance.
	 *
	 * @param string             $url  Request URL.
	 * @param array<string,mixed> $args Request arguments.
	 * @return array<string,mixed>|WP_Error
	 */
	function wp_safe_remote_head( $url = '', $args = array() ) {
		$url = (string) $url;

		CEAFSN_OD_Test_State::$http_requests[] = $url;

		$map = CEAFSN_OD_Test_State::$options['__http'] ?? array();

		if ( ! isset( $map[ $url ] ) ) {
			return new WP_Error( 'http_request_failed', 'No response configured for ' . $url );
		}

		$entry = $map[ $url ];

		if ( $entry instanceof WP_Error ) {
			return $entry;
		}

		return array(
			'response' => array( 'code' => (int) ( $entry[0] ?? 0 ) ),
			'headers'  => (array) ( $entry[1] ?? array() ),
			'body'     => '',
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * Read the status code from a response array.
	 *
	 * @param mixed $response Response.
	 * @return int Status code.
	 */
	function wp_remote_retrieve_response_code( $response ): int {
		return (int) ( is_array( $response ) ? ( $response['response']['code'] ?? 0 ) : 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	/**
	 * Read a header from a response array.
	 *
	 * @param mixed  $response Response.
	 * @param string $header   Header name.
	 * @return string Header value, or '' when absent.
	 */
	function wp_remote_retrieve_header( $response, string $header ): string {
		if ( ! is_array( $response ) ) {
			return '';
		}

		$headers = (array) ( $response['headers'] ?? array() );
		$needle  = strtolower( $header );

		foreach ( $headers as $name => $value ) {
			if ( strtolower( (string) $name ) === $needle ) {
				return (string) $value;
			}
		}

		return '';
	}
}
