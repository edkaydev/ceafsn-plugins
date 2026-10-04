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

// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_MED_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_MED_Activator', 'deactivate' ) );

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
