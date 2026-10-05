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

		// Register the CE-AFSN capabilities and the research editor role. The
		// call is idempotent, so activating several CE-AFSN plugins in one
		// request is harmless.
		if ( class_exists( 'CEAFSN_Caps' ) ) {
			CEAFSN_Caps::install();
		}

		// Create the shared audit log alongside this plugin's own tables.
		if ( class_exists( 'CEAFSN_Audit_Log' ) ) {
			CEAFSN_Audit_Log::create_table();
		}


		// Seed the placeholder list, but never overwrite an existing one.
		if ( false === get_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION ) ) {
			add_option( CEAFSN_PP_Validator::PLACEHOLDER_OPTION, array( 'ceafsn.pdf' ) );
		}

		// The redirect is on by default, but an administrator's earlier choice is
		// never overwritten by a later activation. A null default tells a missing
		// option apart from one that is deliberately set to false.
		if ( null === get_option( self::REDIRECT_OPTION, null ) ) {
			update_option( self::REDIRECT_OPTION, true );
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * Deactivation deliberately deletes no data and no options, so the redirect
	 * preference survives: reactivating the plugin restores the redirect without
	 * the administrator having to set it up again. While the plugin is
	 * deactivated its code does not run, so `/privacy-policy-2/` simply 404s
	 * until it is switched back on. Turning the plugin back on is how the admin
	 * "removes" the redirect without losing any of their data.
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
	 * Allow the document PDF and cover images, only on this plugin's screen.
	 *
	 * A global `upload_mimes` filter returns a value for every upload on the
	 * site, so replacing the whole list would break images for pages, plugin
	 * assets, and other plugins' imports. The restriction is therefore scoped
	 * to this plugin's editor, where it does what it is meant to do.
	 *
	 * The authoritative checks are server-side: CEAFSN_PP_Validator rejects
	 * anything that is not a readable PDF, and the save handler rejects a cover
	 * that is not an image. This filter only keeps the file picker honest, so
	 * the cover image types it leaves available are the ones the record form
	 * actually accepts.
	 *
	 * @param array<string,string> $mimes Allowed mime types keyed by extension group.
	 * @return array<string,string>
	 */
	public static function restrict_upload_mimes( array $mimes ): array {
		if ( ! self::is_plugin_screen() ) {
			return $mimes;
		}

		unset( $mimes );

		return array(
			'pdf'                                => 'application/pdf',
			'jpg|jpeg'                           => 'image/jpeg',
			'png'                                => 'image/png',
			'gif'                                => 'image/gif',
			'webp'                               => 'image/webp',
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
		$target = self::legacy_redirect_target();

		if ( '' === $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );
		exit;
	}

	/**
	 * The URL the legacy route should redirect to, or an empty string when it
	 * should not redirect at all.
	 *
	 * Kept separate from the redirect itself so the decision can be checked
	 * without terminating the request.
	 *
	 * @return string Absolute URL, or '' when the request is left alone.
	 */
	public static function legacy_redirect_target(): string {
		if ( ! self::redirect_enabled() ) {
			return '';
		}

		if ( untrailingslashit( self::request_path() ) !== untrailingslashit( self::LEGACY_PATH ) ) {
			return '';
		}

		// is_404() guards the case where an administrator has since created a
		// real page at the legacy slug: that page wins over the redirect.
		if ( function_exists( 'is_404' ) && ! is_404() ) {
			return '';
		}

		return home_url( self::TARGET_PATH );
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
