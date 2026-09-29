<?php
/**
 * Plugin Name:       CE-AFSN Projects & Publications
 * Plugin URI:        https://ceafsn.duckdns.org/publications/
 * Description:       Admin-managed library of CE-AFSN projects, reports, and publications. Every published record requires its own validated, readable PDF. Shortcode: [ceafsn_projects_pubs]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            CE-AFSN
 * Author URI:        https://ceafsn.duckdns.org/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-pp
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_PP_VERSION',     '1.0.0' );
define( 'CEAFSN_PP_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_PP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_PP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_PP_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes.
require_once CEAFSN_PP_PLUGIN_DIR . 'includes/class-ceafsn-pp-validator.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'includes/class-ceafsn-pp-db.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'includes/class-ceafsn-pp-activator.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'admin/class-ceafsn-pp-admin.php';
require_once CEAFSN_PP_PLUGIN_DIR . 'public/class-ceafsn-pp-public.php';

// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_PP_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_PP_Activator', 'deactivate' ) );

/**
 * Load translations.
 *
 * Registered on init so a translation drop-in in wp-content/languages takes
 * precedence over the plugin's own languages/ directory, which is what the
 * WordPress.org language pack expects.
 */
function ceafsn_pp_load_textdomain(): void {
	load_plugin_textdomain(
		'ceafsn-pp',
		false,
		dirname( CEAFSN_PP_PLUGIN_BASE ) . '/languages'
	);
}
add_action( 'init', 'ceafsn_pp_load_textdomain' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 */
function ceafsn_pp_init(): void {
	// Admin screens.
	if ( is_admin() ) {
		// Registered per request, not at activation: a filter added during
		// activation does not survive to the next page load, and a global one
		// would break uploads everywhere else on the site.
		CEAFSN_PP_Activator::register_upload_filter();

		$admin = new CEAFSN_PP_Admin();
		$admin->init();
	} else {
		// The legacy route redirect is front-end only.
		CEAFSN_PP_Activator::register_legacy_redirect();
	}

	// Front-end shortcode (always registered so it works in the block editor preview too).
	$public = new CEAFSN_PP_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_pp_init' );
