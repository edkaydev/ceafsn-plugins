<?php
/**
 * Activation and deactivation hooks for CE-AFSN Nutrition Policy.
 *
 * @package CEAFSN_NP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_NP_Activator
 */
class CEAFSN_NP_Activator {

	/** @var string Admin page slug this plugin owns. */
	const PAGE_SLUG = 'ceafsn-np';

	/**
	 * Plugin activation.
	 *
	 * - Creates or upgrades the policy table.
	 * - Seeds the known-placeholder filename list.
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
		CEAFSN_NP_DB::create_tables();

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
		if ( false === get_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION ) ) {
			add_option( CEAFSN_NP_Validator::PLACEHOLDER_OPTION, array( 'ceafsn.pdf' ) );
		}
	}

	/**
	 * Plugin deactivation.
	 *
	 * Deactivation deliberately does NOT delete any data. Data removal requires
	 * an explicit admin action in the Uninstall tab.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		remove_filter( 'upload_mimes', array( __CLASS__, 'restrict_upload_mimes' ) );
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
	 * Whether the current request is this plugin's policy screen.
	 *
	 * @return bool True when the request targets the policy admin page.
	 */
	public static function is_policy_screen(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check.
		if ( ! is_admin() || ! isset( $_GET['page'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only context check.
		return self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Allow only PDF, but only while the policy screen is on screen.
	 *
	 * A global `upload_mimes` filter returns a value for every upload on the
	 * site, so replacing the whole list would break images for pages, plugin
	 * assets, and other plugins' imports. The restriction is therefore scoped
	 * to this plugin's editor, where it does what it is meant to do.
	 *
	 * The authoritative check is server-side validation in CEAFSN_NP_Validator;
	 * this filter is a usability guard that keeps the file picker honest.
	 *
	 * @param array<string,string> $mimes Allowed mime types keyed by extension group.
	 * @return array<string,string>
	 */
	public static function restrict_upload_mimes( array $mimes ): array {
		if ( ! self::is_policy_screen() ) {
			return $mimes;
		}

		unset( $mimes );

		return array( 'pdf' => 'application/pdf' );
	}
}
