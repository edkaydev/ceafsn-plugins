<?php
/**
 * Plugin Name:       CE-AFSN Grants & Funding
 * Plugin URI:        https://ceafsn.duckdns.org/scholarships-grants/
 * Description:       Admin-managed listing of CE-AFSN grants and scholarships. No funding amounts, statuses, or deadlines are ever invented, and every record requires its own validated, unique Official Call PDF. Shortcode: [ceafsn_grants]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            edkaydev
 * Author URI:        https://www.linkedin.com/in/edkaydev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-gf
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_GF_VERSION',     '1.0.0' );
define( 'CEAFSN_GF_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_GF_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_GF_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_GF_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes. The DB class is required first: the validator consults
// its enum allow lists.
require_once CEAFSN_GF_PLUGIN_DIR . 'includes/class-ceafsn-gf-db.php';
require_once CEAFSN_GF_PLUGIN_DIR . 'includes/class-ceafsn-gf-validator.php';
require_once CEAFSN_GF_PLUGIN_DIR . 'includes/class-ceafsn-gf-activator.php';
require_once CEAFSN_GF_PLUGIN_DIR . 'admin/class-ceafsn-gf-admin.php';
require_once CEAFSN_GF_PLUGIN_DIR . 'public/class-ceafsn-gf-public.php';

// The shared capability registry and audit log are used when the ceafsn-shared
// library is installed. They are optional: this plugin keeps working, and keeps
// falling back to manage_options, when the folder is absent.
if ( ! class_exists( 'CEAFSN_Caps', false ) ) {
	$ceafsn_gf_shared = dirname( CEAFSN_GF_PLUGIN_DIR ) . 'ceafsn-shared/ceafsn-shared-load.php';
	if ( file_exists( $ceafsn_gf_shared ) ) {
		require_once $ceafsn_gf_shared;
		unset( $ceafsn_gf_shared );
	}
}


// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_GF_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_GF_Activator', 'deactivate' ) );

/**
 * Load translations.
 *
 * Registered on init so a translation drop-in in wp-content/languages takes
 * precedence over the plugin's own languages/ directory, which is what the
 * WordPress.org language pack expects.
 *
 * @return void
 */
function ceafsn_gf_load_textdomain(): void {
	load_plugin_textdomain(
		'ceafsn-gf',
		false,
		dirname( CEAFSN_GF_PLUGIN_BASE ) . '/languages'
	);
}
add_action( 'init', 'ceafsn_gf_load_textdomain' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 *
 * @return void
 */
function ceafsn_gf_init(): void {
	if ( is_admin() ) {
		// Registered per request, not at activation: a filter added during
		// activation does not survive to the next page load, and a global one
		// would break uploads everywhere else on the site.
		CEAFSN_GF_Activator::register_upload_filter();

		$admin = new CEAFSN_GF_Admin();
		$admin->init();
	}

	// The shortcode is always registered, admin or not, so it works in the
	// block editor preview as well as on the front end.
	$public = new CEAFSN_GF_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_gf_init' );

/**
 * Apply pending schema migrations.
 *
 * Activation only runs when someone clicks Activate. WordPress does not call
 * the activation hook for a plugin update, so without this an upgrade to a
 * release that adds a column would leave existing sites without that column.
 * admin_init is the earliest hook that runs on every admin request and nowhere
 * else, which keeps the check off the front end.
 */
add_action( 'admin_init', array( 'CEAFSN_GF_DB', 'maybe_upgrade' ) );
if ( class_exists( 'CEAFSN_Audit_Log' ) ) {
	add_action( 'admin_init', array( 'CEAFSN_Audit_Log', 'maybe_upgrade' ) );
}
