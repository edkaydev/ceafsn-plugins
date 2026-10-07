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
	/** @var array<string,array{value:mixed,expiry:int}> */
	public static array $transients = array();
	/** @var array<int,string> Text domains handed to load_plugin_textdomain(). */
	public static array $textdomains = array();
	/** @var array<int,array<string,mixed>> Menu pages registered with add_menu_page(). */
	public static array $menus = array();
	/** @var array<int,array<string,mixed>> Submenu pages registered with add_submenu_page(). */
	public static array $submenus = array();
	/** @var array<int,array{location:string,status:int}> Redirects recorded. */
	public static array $redirects = array();
	/** @var array<int,array<string,mixed>> Queued wp_remote_post() replies. */
	public static array $http_queue = array();
	/** @var array<int,array{url:string,args:array<string,mixed>}> Every outbound HTTP call. */
	public static array $http_calls = array();
	/** @var array<int,object> Published posts readable by get_posts()/get_post_field(). */
	public static array $posts = array();
	/** @var array<string,array<string,bool>> Role name => capabilities. */
	public static array $roles = array();

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
		self::$textdomains = array();
		self::$menus       = array();
		self::$submenus    = array();
		self::$redirects   = array();
		self::$http_queue  = array();
		self::$http_calls  = array();
		self::$posts       = array();
		self::$roles       = array();

		// The fake database belongs to the test too.
		if ( isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && method_exists( $GLOBALS['wpdb'], 'reset_state' ) ) {
			$GLOBALS['wpdb']->reset_state();
		}
	}

	/**
	 * Queue a canned HTTP reply for the next wp_remote_post() call.
	 *
	 * @param array<string,mixed> $reply Reply with optional 'code', 'body', or 'error'.
	 * @return void
	 */
	public static function queue_http( array $reply ): void {
		self::$http_queue[] = $reply;
	}

	/**
	 * Register a published post the overview screen can count.
	 *
	 * @param int    $id      Post ID.
	 * @param string $content Post content.
	 * @param string $type    Post type.
	 * @param string $status  Post status.
	 * @return void
	 */
	public static function add_post( int $id, string $content, string $type = 'page', string $status = 'publish' ): void {
		self::$posts[] = (object) array(
			'ID'            => $id,
			'post_content'  => $content,
			'post_type'     => $type,
			'post_status'   => $status,
			'post_title'    => 'Post ' . $id,
			'post_name'     => 'post-' . $id,
		);
	}
}

/**
 * Minimal WP_Role stand-in backed by the shared role store.
 */
final class FakeRole {

	/** @var string Role name. */
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
		return ! empty( CEAFSN_AI_Test_State::$roles[ $this->name ][ $cap ] );
	}

	/**
	 * @param string $cap Capability name.
	 * @return void
	 */
	public function add_cap( string $cap ): void {
		CEAFSN_AI_Test_State::$roles[ $this->name ][ $cap ] = true;
	}

	/**
	 * @param string $cap Capability name.
	 * @return void
	 */
	public function remove_cap( string $cap ): void {
		unset( CEAFSN_AI_Test_State::$roles[ $this->name ][ $cap ] );
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
		return CEAFSN_AI_Test_State::$roles[ $this->name ] ?? array();
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
function ceafsn_ai_test_fire_action( string $hook, ...$args ): void {
	foreach ( CEAFSN_AI_Test_State::$actions[ $hook ] ?? array() as $cb ) {
		if ( is_callable( $cb ) ) {
			call_user_func_array( $cb, $args );
		}
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
if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( array $pairs, array $atts, string $shortcode = '' ): array {
		unset( $shortcode );
		$out = array();
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
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
	function is_admin(): bool { return ! empty( CEAFSN_AI_Test_State::$options['__is_admin'] ); }
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $cap ): bool {
		$caps = CEAFSN_AI_Test_State::$options['__caps'] ?? array();
		return ! empty( $caps[ $cap ] );
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
if ( ! function_exists( 'get_role' ) ) {
	/**
	 * Look up a role, mirroring WP's null-when-missing contract.
	 *
	 * @param string $role_name Role name.
	 * @return FakeRole|null Role, or null when it does not exist.
	 */
	function get_role( string $role_name ): ?FakeRole {
		return isset( CEAFSN_AI_Test_State::$roles[ $role_name ] ) ? new FakeRole( $role_name ) : null;
	}
}
if ( ! function_exists( 'add_role' ) ) {
	/**
	 * Create a role.
	 *
	 * @param string              $role_name Role name.
	 * @param string              $display   Display name.
	 * @param array<string,bool>  $caps      Capabilities.
	 * @return FakeRole New role.
	 */
	function add_role( string $role_name, string $display, array $caps = array() ): FakeRole {
		unset( $display );
		CEAFSN_AI_Test_State::$roles[ $role_name ] = $caps;
		return new FakeRole( $role_name );
	}
}
if ( ! function_exists( 'remove_role' ) ) {
	/**
	 * Delete a role.
	 *
	 * @param string $role_name Role name.
	 * @return void
	 */
	function remove_role( string $role_name ): void {
		unset( CEAFSN_AI_Test_State::$roles[ $role_name ] );
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
	function wp_safe_redirect( string $location, int $status = 302 ): bool {
		CEAFSN_AI_Test_State::$redirects[] = compact( 'location', 'status' );
		return true;
	}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $msg = '', $code = null ): void {
		unset( $code );
		CEAFSN_AI_Test_State::$died        = true;
		CEAFSN_AI_Test_State::$die_message = is_scalar( $msg ) ? (string) $msg : '';
		throw new RuntimeException( 'wp_die' );
	}
}
if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * Replay the next queued HTTP reply, or an empty 200 when nothing is queued.
	 *
	 * @param string               $url  Endpoint.
	 * @param array<string,mixed>  $args Request arguments.
	 * @return array<string,mixed>|WP_Error
	 */
	function wp_remote_post( string $url, array $args = array() ): array|WP_Error {
		CEAFSN_AI_Test_State::$http_calls[] = compact( 'url', 'args' );

		$queued = array_shift( CEAFSN_AI_Test_State::$http_queue );

		if ( isset( $queued['error'] ) && $queued['error'] instanceof WP_Error ) {
			return $queued['error'];
		}

		return array(
			'code' => isset( $queued['code'] ) ? (int) $queued['code'] : 200,
			'body' => isset( $queued['body'] ) ? (string) $queued['body'] : '{}',
		);
	}
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $response ): int {
		return is_array( $response ) ? (int) ( $response['code'] ?? 0 ) : 0;
	}
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ): string {
		return is_array( $response ) ? (string) ( $response['body'] ?? '' ) : '';
	}
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
if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		/** @var array<string,mixed> */
		private array $params;
		/** @param array<string,mixed> $params */
		public function __construct( array $params = array() ) { $this->params = $params; }
		public function get_param( string $name ) { return $this->params[ $name ] ?? null; }
	}
}
if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		/** @var mixed */
		private mixed $data;
		private int $status;
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_data() { return $this->data; }
		public function get_status(): int { return $this->status; }
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $options = 0 ): string|false { return json_encode( $data, $options ); }
}
if ( ! function_exists( 'wp_kses' ) ) {
	function wp_kses( string $string, array $allowed_html ): string { return strip_tags( $string, '<p><br><strong><em><ul><ol><li>' ); }
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( string $text ): string {
		$allowed = '<a><strong><em><b><i><br><code><span><p><ul><ol><li>';
		return strip_tags( $text, $allowed );
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text, bool $remove_breaks = false ): string { return strip_tags( $text ); }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $v ): string { return trim( strip_tags( (string) preg_replace( '/\s+/', ' ', (string) $v ) ) ); }
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $v ): string { return trim( strip_tags( (string) $v ) ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $v ): string { return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
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
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( string $t ): string { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $t, string $d = 'default' ): string { return esc_html( $t ); }
}
if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $t, string $d = 'default' ): void { echo esc_html( $t ); }
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $t, string $d = 'default' ): string { return esc_attr( $t ); }
}
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $t, string $d = 'default' ): void { echo esc_attr( $t ); }
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string { return $text; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}
if ( ! function_exists( 'number_format_i18n' ) ) {
	function number_format_i18n( $n, int $d = 0 ): string { return number_format( (float) $n, $d ); }
}
if ( ! function_exists( 'date_i18n' ) ) {
	function date_i18n( $format, int $ts = 0 ): string { return gmdate( (string) $format, $ts ?: time() ); }
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
if ( ! function_exists( 'add_query_arg' ) ) {
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

		$split    = explode( '?', $base, 2 );
		$path     = $split[0];
		$query    = array();
		$existing = $split[1] ?? '';

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
if ( ! function_exists( 'checked' ) ) {
	function checked( $one, $two = true, bool $display = true ): string {
		$html = (string) $one === (string) $two ? ' checked="checked"' : '';
		if ( $display ) echo $html;
		return $html;
	}
}
if ( ! function_exists( 'disabled' ) ) {
	function disabled( $one, $two = true, bool $display = true ): string {
		$html = (string) $one === (string) $two ? ' disabled="disabled"' : '';
		if ( $display ) echo $html;
		return $html;
	}
}
if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other = null ): void {
		$is_disabled = is_array( $other ) && isset( $other['disabled'] ) ? ' disabled' : '';
		echo "<input type=\"submit\" class=\"button button-{$type}\" value=\"" . esc_attr( $text ) . "\"{$is_disabled}>";
	}
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) { return is_string( $value ) ? stripslashes( $value ) : $value; }
}
if ( ! function_exists( 'load_plugin_textdomain' ) ) {
	function load_plugin_textdomain( string $domain, $deprecated = false, string $path = '' ): bool {
		unset( $deprecated, $path );
		CEAFSN_AI_Test_State::$textdomains[] = $domain;
		return true;
	}
}
if ( ! function_exists( 'add_menu_page' ) ) {
	/**
	 * Record a top-level admin menu entry and hand back a stable hook name.
	 *
	 * @param string   $page_title Page title.
	 * @param string   $menu_title Menu title.
	 * @param string   $capability Capability.
	 * @param string   $menu_slug  Menu slug.
	 * @param callable $callback   Render callback.
	 * @param string   $icon       Dashicon.
	 * @param int      $position   Position.
	 * @return string Hook suffix.
	 */
	function add_menu_page(
		string $page_title,
		string $menu_title,
		string $capability,
		string $menu_slug,
		$callback = '',
		string $icon = '',
		int $position = 0
	): string {
		$hook = 'toplevel_page_' . $menu_slug;
		CEAFSN_AI_Test_State::$menus[] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon', 'position' ) + array( 'hook' => $hook );
		return $hook;
	}
}
if ( ! function_exists( 'add_submenu_page' ) ) {
	/**
	 * Record a submenu entry and hand back a stable hook name.
	 *
	 * @param string   $parent_slug Parent slug.
	 * @param string   $page_title  Page title.
	 * @param string   $menu_title  Menu title.
	 * @param string   $capability  Capability.
	 * @param string   $menu_slug   Menu slug.
	 * @param callable $callback    Render callback.
	 * @return string Hook suffix.
	 */
	function add_submenu_page(
		string $parent_slug,
		string $page_title,
		string $menu_title,
		string $capability,
		string $menu_slug,
		$callback = ''
	): string {
		// WordPress reuses the parent hook for the submenu that shares its slug.
		$hook = $parent_slug === $menu_slug
			? 'toplevel_page_' . $menu_slug
			: 'admin_page_' . $menu_slug;

		CEAFSN_AI_Test_State::$submenus[] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback' ) + array( 'hook' => $hook );
		return $hook;
	}
}
if ( ! function_exists( 'has_shortcode' ) ) {
	function has_shortcode( string $content, string $tag ): bool {
		return str_contains( $content, '[' . $tag );
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ): array {
		$types   = (array) ( $args['post_type'] ?? array( 'post', 'page' ) );
		$status  = (string) ( $args['post_status'] ?? 'publish' );
		$limit   = (int) ( $args['numberposts'] ?? 5 );
		$wanted  = array();

		foreach ( CEAFSN_AI_Test_State::$posts as $post ) {
			if ( ! in_array( $post->post_type, $types, true ) ) continue;
			if ( $post->post_status !== $status ) continue;
			$wanted[] = $post;
		}

		usort( $wanted, static fn( $a, $b ) => $b->ID <=> $a->ID );
		$wanted = array_slice( $wanted, 0, $limit > 0 ? $limit : count( $wanted ) );

		return ( 'ids' === ( $args['fields'] ?? '' ) )
			? array_map( static fn( $p ) => (int) $p->ID, $wanted )
			: $wanted;
	}
}
if ( ! function_exists( 'get_post_field' ) ) {
	function get_post_field( string $field, int $post_id = 0 ): string {
		foreach ( CEAFSN_AI_Test_State::$posts as $post ) {
			if ( (int) $post->ID === $post_id ) {
				return (string) ( $post->$field ?? '' );
			}
		}
		return '';
	}
}
