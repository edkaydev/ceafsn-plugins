<?php
/**
 * Standalone test bootstrap for CE-AFSN AI Assistant.
 *
 * Provides the minimal WordPress function stubs needed to run the test
 * suite with plain `php` — no WordPress install, no Composer.
 *
 * @package CEAFSN_AI
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

/**
 * Shared mutable state for test stubs.
 */
final class CEAFSN_AI_Test_State {
	/** @var array<string,mixed> */
	public static array $options = array();
	/** @var array<int,array<string,mixed>> */
	public static array $styles = array();
	/** @var array<int,array<string,mixed>> */
	public static array $scripts = array();
	/** @var array<string,mixed> */
	public static array $shortcodes = array();
	/** @var array<string,array<int,mixed>> */
	public static array $actions = array();
	/** @var array<int,array<string,mixed>> */
	public static array $rest_routes = array();
	/** @var bool */
	public static bool $died = false;
	/** @var string */
	public static string $die_message = '';
	/** @var array<int,array<string,mixed>> */
	public static array $transients = array();

	public static function reset(): void {
		self::$options     = array();
		self::$styles      = array();
		self::$scripts     = array();
		self::$shortcodes  = array();
		self::$actions     = array();
		self::$rest_routes = array();
		self::$died        = false;
		self::$die_message = '';
		self::$transients  = array();
	}
}

// ---- WordPress stubs ----

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $cb, int $pri = 10, int $args = 1 ): bool {
		unset( $pri, $args );
		CEAFSN_AI_Test_State::$actions[ $hook ][] = $cb;
		return true;
	}
}
function ceafsn_ai_test_fire_action( string $hook ): void {
	foreach ( CEAFSN_AI_Test_State::$actions[ $hook ] ?? array() as $cb ) {
		if ( is_callable( $cb ) ) { call_user_func( $cb ); }
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $h, $cb, int $p = 10, int $a = 1 ): bool { return true; }
}
if ( ! function_exists( 'add_shortcode' ) ) {
	function add_shortcode( string $tag, $cb ): void {
		CEAFSN_AI_Test_State::$shortcodes[ $tag ] = $cb;
	}
}
if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( string $ns, string $route, array $args ): bool {
		CEAFSN_AI_Test_State::$rest_routes[] = compact( 'ns', 'route', 'args' );
		return true;
	}
}
if ( ! function_exists( 'register_activation_hook' ) ) {
	function register_activation_hook( string $f, $cb ): void {}
}
if ( ! function_exists( 'register_deactivation_hook' ) ) {
	function register_deactivation_hook( string $f, $cb ): void {}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $f ): string { return rtrim( dirname( $f ), '/' ) . '/'; }
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $f ): string { return 'https://example.test/wp-content/plugins/ceafsn-ai-assistant/'; }
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $f ): string { return basename( dirname( $f ) ) . '/' . basename( $f ); }
}
if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool { return false; }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		return ! empty( ( CEAFSN_AI_Test_State::$options['__caps'] ?? array() )[ $cap ] );
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int { return 1; }
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		return array_key_exists( $name, CEAFSN_AI_Test_State::$options )
			? CEAFSN_AI_Test_State::$options[ $name ]
			: $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $name, $value, $autoload = null ): bool {
		CEAFSN_AI_Test_State::$options[ $name ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $name ): bool {
		unset( CEAFSN_AI_Test_State::$options[ $name ] );
		return true;
	}
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $key ) {
		return CEAFSN_AI_Test_State::$transients[ $key ]['value'] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, $value, int $expiry = 0 ): bool {
		CEAFSN_AI_Test_State::$transients[ $key ] = array( 'value' => $value, 'expiry' => $expiry );
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $key ): bool {
		unset( CEAFSN_AI_Test_State::$transients[ $key ] );
		return true;
	}
}
if ( ! function_exists( 'wp_enqueue_style' ) ) {
	function wp_enqueue_style( string $handle, string $src = '', array $deps = array(), $ver = '', string $media = 'all' ): void {
		CEAFSN_AI_Test_State::$styles[] = compact( 'handle', 'src' );
	}
}
if ( ! function_exists( 'wp_enqueue_script' ) ) {
	function wp_enqueue_script( string $handle, string $src = '', array $deps = array(), $ver = '', bool $in_footer = false ): void {
		CEAFSN_AI_Test_State::$scripts[] = compact( 'handle', 'src' );
	}
}
if ( ! function_exists( 'wp_style_is' ) ) {
	function wp_style_is( string $handle, string $list = 'enqueued' ): bool {
		foreach ( CEAFSN_AI_Test_State::$styles as $s ) {
			if ( $s['handle'] === $handle ) return true;
		}
		return false;
	}
}
if ( ! function_exists( 'wp_script_is' ) ) {
	function wp_script_is( string $handle, string $list = 'enqueued' ): bool {
		foreach ( CEAFSN_AI_Test_State::$scripts as $s ) {
			if ( $s['handle'] === $handle ) return true;
		}
		return false;
	}
}
if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action = '-1' ): string { return 'testnonce'; }
}
if ( ! function_exists( 'wp_verify_nonce' ) ) {
	function wp_verify_nonce( string $nonce, string $action = '-1' ): int|false { return 1; }
}
if ( ! function_exists( 'check_admin_referer' ) ) {
	function check_admin_referer( string $action = '-1', string $query_arg = '_wpnonce' ): bool { return true; }
}
if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce', bool $ref = true, bool $display = true ): string {
		$html = '<input type="hidden" name="' . $name . '" value="testnonce">';
		if ( $display ) echo $html;
		return $html;
	}
}
if ( ! function_exists( 'wp_safe_redirect' ) ) {
	function wp_safe_redirect( string $location, int $status = 302 ): bool { return true; }
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $msg = '', $code = null ): void {
		CEAFSN_AI_Test_State::$died        = true;
		CEAFSN_AI_Test_State::$die_message = is_scalar( $msg ) ? (string) $msg : '';
		throw new RuntimeException( 'wp_die' );
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	function wp_remote_post( string $url, array $args = array() ): array|WP_Error {
		return array( '__stub' => true, '__url' => $url, '__args' => $args );
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ): int { return 200; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ): string { return '{}'; }
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool { return $thing instanceof WP_Error; }
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		public function __construct( string $code = '', string $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message(): string { return $this->message; }
		public function get_error_code(): string    { return $this->code; }
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0 ): string|false { return json_encode( $data, $options ); }
}
if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( string $string, array $allowed_html ): string { return strip_tags( $string, '<p><br><strong><em><ul><ol><li>' ); }
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string { return strip_tags( $text ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( string $v ): string { return trim( strip_tags( (string) preg_replace( '/\s+/', ' ', $v ) ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( string $v ): string { return trim( strip_tags( $v ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $v ): string { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $v ) ); }
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string { return filter_var( trim( $url ), FILTER_SANITIZE_URL ) ?: ''; }
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string { return htmlspecialchars( esc_url_raw( $url ), ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $t, string $d = 'default' ): string { return esc_html( $t ); }
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { return $text; }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( float $n, int $d = 0 ): string { return number_format( $n, $d ); }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( string $format, int $ts = 0 ): string { return gmdate( $format, $ts ?: time() ); }
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string { return 'https://example.test/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $id = 0 ): string { return 'https://example.test/?p=' . $id; }
}
if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string { return 'https://example.test/wp-json/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
}
if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( bool $hard = true ): void {}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $v ): int { return abs( (int) $v ); }
}
if ( ! function_exists( 'selected' ) ) {
	function selected( $one, $two = true, bool $display = true ): string {
		$html = (string) $one === (string) $two ? ' selected="selected"' : '';
		if ( $display ) echo $html;
		return $html;
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other = null ): void {
		$disabled = is_array( $other ) && isset( $other['disabled'] ) ? ' disabled' : '';
		echo "<input type=\"submit\" class=\"button button-{$type}\" value=\"" . esc_attr( $text ) . "\"{$disabled}>";
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
}
if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page(): string { return ''; }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain(): void {}
}
