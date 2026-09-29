<?php
/**
 * Activation, deactivation, and route migration for CE-AFSN Projects & Publications.
 *
 * @package CEAFSN_PP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_PP_Activator
 */
class CEAFSN_PP_Activator {

	/** @var string Admin page slug this plugin owns. */
	const PAGE_SLUG = 'ceafsn-pp';

	/** @var string Legacy path that must permanently redirect. */
	const LEGACY_PATH = '/privacy-policy-2/';

	/** @var string Path the legacy route points at. */
	const TARGET_PATH = '/publications/';

	/** @var string Option recording whether the redirect is active. */
	const REDIRECT_OPTION = 'ceafsn_pp_legacy_redirect';

	/**
	 * Plugin activation.
	 *
	 * - Creates or upgrades the publications table.
	 * - Seeds the known-placeholder filename list.
	 * - Enables the legacy route redirect.
	 *
	 * The upload restriction is not registered here. A filter added during
	 * activation only exists for that one request, and a global one would strip
	 * every other upload type from the whole site. It is registered per admin
	 * request by register_upload_filter() and scoped to this plugin's screen.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verify the current user has permission to activate plugins.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Create / upgrade the table.
		CEAFSN_PP_DB::create_tables();

		// Seed the placeholder list, but never overwrite an existing one.
		if ( false === get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ) ) {
			add_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION, array( 'ceafsn.pdf' ) );
		}

		// The redirect stays on until an administrator turns it off in Settings.
		update_option( self::REDIRECT_OPTION, true );

		flush_rewrite_rules( false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * Deactivation deliberately does NOT delete any data. The legacy redirect
	 * also stays registered, because a deactivated plugin must not break inbound
	 * links that are still being followed.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		remove_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ) );
		flush_rewrite_rules( false );
	}

	/**
	 * Register the scoped upload restriction for this request.
	 *
	 * @return void
	 */
	public static function register_upload_filter(): void {
		add_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ) );
	}

	/**
	 * Register the legacy route redirect for this request.
	 *
	 * @return void
	 */
	public static function register_legacy_redirect(): void {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect_legacy_route' ) );
	}

	/**
	 * Whether the current request is this plugin's admin screen.
	 *
	 * @return bool True when the request targets the publications admin page.
	 */
	public static function is_plugin_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check.
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check.
		return self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Allow only PDF, but only while this plugin's screen is on screen.
	 *
	 * A global `upload_mimes` filter returns a value for every upload on the
	 * site, so replacing the whole list would break images for pages, plugin
	 * assets, and other plugins' imports. The restriction is therefore scoped
	 * to this plugin's editor, where it does what it is meant to do.
	 *
	 * The authoritative check is server-side validation in CEAFSN_PP_Validator;
	 * this filter is a usability guard that keeps the file picker honest.
	 *
	 * @param array<string,string> $mimes Allowed mime types keyed by extension group.
	 * @return array<string,string>
	 */
	public static function restrict_upload_mimes( array $mimes ): array {
		// The cover image needs to be attachable, so images stay available on
		// this screen; the PDF is enforced at save time.
		if ( ! self::is_plugin_screen() ) {
			return $mimes;
		}

		unset( $mimes );

		return array(
			'pdf' => 'application/pdf',
		);
	}

	/**
	 * Redirect the legacy privacy route to the publications page.
	 *
	 * Runs on template_redirect so it fires only for front-end requests that
	 * reached a template, which means the target page is a real page rather
	 * than an asset or a REST request.
	 *
	 * @return void
	 */
	public static function maybe_redirect_legacy_route(): void {
		if ( ! get_option( self::REDIRECT_OPTION, true ) ) {
			return;
		}

		$request = self::request_path();

		if ( untrailingslashit( $request ) !== untrailingslashit( self::LEGACY_PATH ) ) {
			return;
		}

		// is_404() guards the case where an administrator has since created a
		// real page at the legacy slug: that page wins over the redirect.
		if ( function_exists( 'is_404' ) && ! is_404() ) {
			return;
		}

		wp_safe_redirect( home_url( self::TARGET_PATH ), 301 );
		exit;
	}

	/**
	 * The path of the current front-end request, without query string.
	 *
	 * @return string Request path beginning with a slash.
	 */
	public static function request_path(): string {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '';

		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		return '' !== $path ? $path : '/';
	}

	/**
	 * Whether the legacy redirect is currently enabled.
	 *
	 * @return bool True when the redirect will run.
	 */
	public static function redirect_enabled(): bool {
		return (bool) get_option( self::REDIRECT_OPTION, true );
	}
}
