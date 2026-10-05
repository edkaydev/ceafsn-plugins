<?php
/**
 * Activation and deactivation hooks for CE-AFSN M&E Dashboard.
 *
 * @package CEAFSN_MED
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class CEAFSN_MED_Activator
 */
class CEAFSN_MED_Activator {

	/**
	 * Plugin activation.
	 *
	 * - Creates or upgrades database tables.
	 * - Sets the stored schema version.
	 * - Registers the CE-AFSN capabilities and research editor role.
	 * - Creates the shared audit log table.
	 * - Flushes rewrite rules so the shortcode page is immediately accessible.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Verify the current user has permission to activate plugins.
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Create / upgrade tables.
		CEAFSN_MED_DB::create_tables();

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

		// Set default options only if they don't already exist.
		if ( false === get_option( 'ceafsn_med_preview_mode' ) ) {
			add_option( 'ceafsn_med_preview_mode', '0' );
		}

		// Flush rewrite rules so any page using the shortcode resolves correctly.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * Deactivation deliberately does NOT delete any data.
	 * Data removal requires an explicit admin action in the Uninstall tab.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Flush rewrite rules to remove any plugin-registered rewrites.
		flush_rewrite_rules();
	}
}
