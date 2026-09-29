<?php
/**
 * Activation and deactivation for CE-AFSN Grants & Funding.
 *
 * @package CEAFSN_GF
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_GF_Activator
 */
class CEAFSN_GF_Activator {

	/** @var string Admin page slug this plugin owns. */
	const PAGE_SLUG = 'ceafsn-gf';

	/** @var string Option recording whether closed opportunities stay listed. */
	const SHOW_CLOSED_OPTION = 'ceafsn_gf_show_closed';

	/**
	 * Plugin activation.
	 *
	 * Creates or upgrades the table and seeds the default options.
	 *
	 * The upload restriction is not registered here. A filter added during
	 * activation only exists for that one request, and a global one would strip
	 * every other upload type from the whole site. It is registered per admin
	 * request by register_upload_filter() and scoped to this plugin's screen.
	 *
	 * @return void
	 */
	public static function activate(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		CEAFSN_GF_DB::create_tables();

		// Seed the option that decides whether closed opportunities stay
		// listed, but never overwrite a choice an administrator has made.
		if ( null === get_option( self::SHOW_CLOSED_OPTION, null ) ) {
			update_option( self::SHOW_CLOSED_OPTION, true );
		}

		flush_rewrite_rules( false );
	}

	/**
	 * Plugin deactivation.
	 *
	 * Deactivation deliberately deletes no data and no options. Uninstalling
	 * is where records are removed, and only when the administrator has said so
	 * on the Uninstall tab.
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
	 * Whether the current request is this plugin's admin screen.
	 *
	 * @return bool True when the request targets the grants admin page.
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
	 * Allow call PDFs only, on this plugin's screen.
	 *
	 * A global `upload_mimes` filter returns a value for every upload on the
	 * site, so replacing the whole list would break images for pages, plugin
	 * assets, and other plugins' imports. The restriction is therefore scoped
	 * to this plugin's editor, where it does what it is meant to do.
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
			'pdf' => 'application/pdf',
		);
	}
}
