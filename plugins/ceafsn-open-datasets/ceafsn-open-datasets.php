<?php
/**
 * Plugin Name:       CE-AFSN Open Datasets
 * Plugin URI:        https://ceafsn.duckdns.org/open-datasets/
 * Description:       Open data repository for CE-AFSN food security and nutrition datasets. Every download points to a real, validated CSV, ZIP, or XLSX file. Shortcode: [ceafsn_open_datasets]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            edkaydev
 * Author URI:        https://www.linkedin.com/in/edkaydev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-od
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_OD_VERSION',     '1.0.0' );
define( 'CEAFSN_OD_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_OD_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_OD_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_OD_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes.
require_once CEAFSN_OD_PLUGIN_DIR . 'includes/class-ceafsn-od-validator.php';
require_once CEAFSN_OD_PLUGIN_DIR . 'includes/class-ceafsn-od-db.php';
require_once CEAFSN_OD_PLUGIN_DIR . 'includes/class-ceafsn-od-request.php';
require_once CEAFSN_OD_PLUGIN_DIR . 'includes/class-ceafsn-od-activator.php';
require_once CEAFSN_OD_PLUGIN_DIR . 'admin/class-ceafsn-od-admin.php';
require_once CEAFSN_OD_PLUGIN_DIR . 'public/class-ceafsn-od-public.php';

// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_OD_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_OD_Activator', 'deactivate' ) );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function ceafsn_od_init(): void {
	load_plugin_textdomain( 'ceafsn-od', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Admin screens.
	if ( is_admin() ) {
		// Registered per request, not at activation: a filter added during
		// activation does not survive to the next page load.
		CEAFSN_OD_Activator::register_upload_filter();

		$admin = new CEAFSN_OD_Admin();
		$admin->init();
	}

	// Front-end shortcode (always registered so it works in the block editor preview too).
	$public = new CEAFSN_OD_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_od_init' );
