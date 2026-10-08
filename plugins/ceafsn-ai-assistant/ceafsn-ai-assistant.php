<?php
/**
 * Plugin Name:       CE-AFSN AI Assistant
 * Plugin URI:        https://ceafsn.duckdns.org/ai-assistant/
 * Description:       AI-powered question-and-answer assistant for CE-AFSN content. Answers questions about research, fellowships, grants, datasets, and publications in English and Portuguese. Shortcode: [ceafsn_ai_assistant]
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            edkaydev
 * Author URI:        https://www.linkedin.com/in/edkaydev
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ceafsn-ai
 * Domain Path:       /languages
 */

// Prevent direct file access.
defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'CEAFSN_AI_VERSION',     '1.0.0' );
define( 'CEAFSN_AI_PLUGIN_FILE', __FILE__ );
define( 'CEAFSN_AI_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'CEAFSN_AI_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'CEAFSN_AI_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// Autoload includes — DB first, then providers, then indexer and query engine.
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-db.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-provider.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-provider-openai.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-provider-gemini.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-provider-grok.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-provider-claude.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-providers.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-indexer.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-query.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'includes/class-ceafsn-ai-activator.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'admin/class-ceafsn-ai-admin.php';
require_once CEAFSN_AI_PLUGIN_DIR . 'public/class-ceafsn-ai-public.php';

// Optional shared library for capabilities and audit log.
if ( ! class_exists( 'CEAFSN_Caps', false ) ) {
	$ceafsn_ai_shared = CEAFSN_AI_PLUGIN_DIR . '../ceafsn-shared/ceafsn-shared-load.php';
	if ( file_exists( $ceafsn_ai_shared ) ) {
		require_once $ceafsn_ai_shared;
		unset( $ceafsn_ai_shared );
	}
}

// Activation / deactivation hooks.
register_activation_hook(   __FILE__, array( 'CEAFSN_AI_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CEAFSN_AI_Activator', 'deactivate' ) );

/**
 * Load translations on init so language-pack drop-ins take precedence.
 *
 * @return void
 */
function ceafsn_ai_load_textdomain(): void {
	load_plugin_textdomain(
		'ceafsn-ai',
		false,
		dirname( CEAFSN_AI_PLUGIN_BASE ) . '/languages'
	);
}
add_action( 'init', 'ceafsn_ai_load_textdomain' );

/**
 * Bootstrap the plugin after all plugins are loaded.
 *
 * @return void
 */
function ceafsn_ai_init(): void {
	CEAFSN_AI_Indexer::register_hooks();

	if ( is_admin() ) {
		$admin = new CEAFSN_AI_Admin();
		$admin->init();
	}

	$public = new CEAFSN_AI_Public();
	$public->init();
}
add_action( 'plugins_loaded', 'ceafsn_ai_init' );

/**
 * Apply pending schema migrations on every admin request.
 *
 * @return void
 */
add_action( 'admin_init', array( 'CEAFSN_AI_DB', 'maybe_upgrade' ) );
if ( class_exists( 'CEAFSN_Audit_Log' ) ) {
	add_action( 'admin_init', array( 'CEAFSN_Audit_Log', 'maybe_upgrade' ) );
}
