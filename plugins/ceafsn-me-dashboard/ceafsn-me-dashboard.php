<?php
/**
 * Plugin Name:       CE-AFSN M&E Dashboard
 * Plugin URI:        https://ceafsn.duckdns.org/me-dashboard/
 * Description:       Institutional monitoring and evaluation dashboard. Admin-managed metrics, demographics, and project registry. Shortcode: [ceafsn_me_dashboard]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            edkaydev
 * Author URI:        https://www.linkedin.com/in/edkaydev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-med
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_MED_VERSION',     '1.0.0' );
define( 'CEAFSN_MED_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_MED_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_MED_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_MED_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes.
require_once CEAFSN_MED_PLUGIN_DIR . 'includes/class-ceafsn-med-db.php';
require_once CEAFSN_MED_PLUGIN_DIR . 'includes/class-ceafsn-med-activator.php';
require_once CEAFSN_MED_PLUGIN_DIR . 'admin/class-ceafsn-med-admin.php';
require_once CEAFSN_MED_PLUGIN_DIR . 'public/class-ceafsn-med-public.php';

// The shared capability registry and audit log are used when the ceafsn-shared
// library is installed. They are optional: this plugin keeps working, and keeps
// falling back to manage_options, when the folder is absent.
if ( ! class_exists( 'CEAFSN_Caps', false ) ) {
	$ceafsn_med_shared = dirname( CEAFSN_MED_PLUGIN_DIR ) . 'ceafsn-shared/ceafsn-shared-load.php';
	if ( file_exists( $ceafsn_med_shared ) ) {
		require_once $ceafsn_med_shared;
		unset( $ceafsn_med_shared );
	}
}


// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_MED_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_MED_Activator', 'deactivate' ) );

/**
 * Load translations.
 *
 * Registered on init rather than from plugins_loaded: WordPress 6.7 deprecated
 * loading textdomains earlier than init, because a language-pack drop-in in
 * wp-content/languages only takes precedence from that point on. Without this
 * hook every translated string on CE-AFSN M&E Dashboard fell back to the English
 * source.
 */
function ceafsn_med_load_textdomain(): void {
	load_plugin_textdomain(
		'ceafsn-med',
		false,
		dirname( CEAFSN_MED_PLUGIN_BASE ) . '/languages'
	);
}
add_action( 'init', 'ceafsn_med_load_textdomain' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function ceafsn_med_init(): void {
	// Admin screens.
	if ( is_admin() ) {
		$admin = new CEAFSN_MED_Admin();
		$admin->init();
	}

	// Front-end shortcode (always registered so it works in block editor previews too).
	$public = new CEAFSN_MED_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_med_init' );

/**
 * Apply pending schema migrations.
 *
 * Activation only runs when someone clicks Activate. WordPress does not call
 * the activation hook for a plugin update, so without this an upgrade to a
 * release that adds a column would leave existing sites without that column.
 * admin_init is the earliest hook that runs on every admin request and nowhere
 * else, which keeps the check off the front end.
 */
add_action( 'admin_init', array( 'CEAFSN_MED_DB', 'maybe_upgrade' ) );
if ( class_exists( 'CEAFSN_Audit_Log' ) ) {
	add_action( 'admin_init', array( 'CEAFSN_Audit_Log', 'maybe_upgrade' ) );
}
