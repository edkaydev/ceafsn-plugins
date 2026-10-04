<?php
/**
 * Plugin Name:       CE-AFSN Nutrition Policy
 * Plugin URI:        https://ceafsn.duckdns.org/nutrition-policy-modeling/
 * Description:       Admin-managed library of nutrition and food security policy documents. Every published record requires a validated, readable PDF. Shortcode: [ceafsn_policy_table]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            edkaydev
 * Author URI:        https://www.linkedin.com/in/edkaydev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-np
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_NP_VERSION',     '1.0.0' );
define( 'CEAFSN_NP_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_NP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_NP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_NP_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes.
require_once CEAFSN_NP_PLUGIN_DIR . 'includes/class-ceafsn-np-validator.php';
require_once CEAFSN_NP_PLUGIN_DIR . 'includes/class-ceafsn-np-db.php';
require_once CEAFSN_NP_PLUGIN_DIR . 'includes/class-ceafsn-np-activator.php';
require_once CEAFSN_NP_PLUGIN_DIR . 'admin/class-ceafsn-np-admin.php';
require_once CEAFSN_NP_PLUGIN_DIR . 'public/class-ceafsn-np-public.php';

// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_NP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_NP_Activator', 'deactivate' ) );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function ceafsn_np_init(): void {
	// Admin screens.
	if ( is_admin() ) {
		// Registered per request, not at activation: a filter added during
		// activation does not survive to the next page load, and a global one
		// would break uploads everywhere else on the site.
		CEAFSN_NP_Activator::register_upload_filter();

		$admin = new CEAFSN_NP_Admin();
		$admin->init();
	}

	// Front-end shortcode (always registered so it works in the block editor preview too).
	$public = new CEAFSN_NP_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_np_init' );
