<?php
/**
 * Plugin Name:       CE-AFSN Research Fellowships
 * Plugin URI:        https://ceafsn.duckdns.org/research-fellowships/
 * Description:       Admin-managed listing of CE-AFSN research fellowships and opportunities. Status is derived from real closing dates, so nothing is advertised as open without proof. Shortcode: [ceafsn_fellowships]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            edkaydev
 * Author URI:        https://www.linkedin.com/in/edkaydev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-rf
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_RF_VERSION',     '1.0.0' );
define( 'CEAFSN_RF_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_RF_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_RF_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_RF_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes. The status class is required first: the database schema
// and the validator both consult it.
require_once CEAFSN_RF_PLUGIN_DIR . 'includes/class-ceafsn-rf-status.php';
require_once CEAFSN_RF_PLUGIN_DIR . 'includes/class-ceafsn-rf-db.php';
require_once CEAFSN_RF_PLUGIN_DIR . 'includes/class-ceafsn-rf-validator.php';
require_once CEAFSN_RF_PLUGIN_DIR . 'includes/class-ceafsn-rf-activator.php';
require_once CEAFSN_RF_PLUGIN_DIR . 'admin/class-ceafsn-rf-admin.php';
require_once CEAFSN_RF_PLUGIN_DIR . 'public/class-ceafsn-rf-public.php';

// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_RF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_RF_Activator', 'deactivate' ) );

/**
 * Load translations.
 *
 * Registered on init so a translation drop-in in wp-content/languages takes
 * precedence over the plugin's own languages/ directory, which is what the
 * WordPress.org language pack expects.
 *
 * @return void
 */
function ceafsn_rf_load_textdomain(): void {
	load_plugin_textdomain(
		'ceafsn-rf',
		false,
		dirname( CEAFSN_RF_PLUGIN_BASE ) . '/languages'
	);
}
add_action( 'init', 'ceafsn_rf_load_textdomain' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 *
 * @return void
 */
function ceafsn_rf_init(): void {
	if ( is_admin() ) {
		// Registered per request, not at activation: a filter added during
		// activation does not survive to the next page load, and a global one
		// would break uploads everywhere else on the site.
		CEAFSN_RF_Activator::register_upload_filter();

		$admin = new CEAFSN_RF_Admin();
		$admin->init();
	}

	// The shortcode is always registered, admin or not, so it works in the
	// block editor preview as well as on the front end.
	$public = new CEAFSN_RF_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_rf_init' );
