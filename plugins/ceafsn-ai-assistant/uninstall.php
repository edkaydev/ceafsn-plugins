<?php
/**
 * Uninstall routine for CE-AFSN AI Assistant.
 *
 * Runs only when the plugin is deleted via Plugins → Delete in wp-admin.
 * Deactivation alone never touches data.
 *
 * @package CEAFSN_AI
 */

// WordPress sets this constant before calling uninstall.php. Any other
// execution path — direct HTTP, CLI without the constant, etc. — is rejected.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop the chunks table.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}ceafsn_ai_chunks`" );

// Remove all plugin options.
$options = array(
	'ceafsn_ai_db_version',
	'ceafsn_ai_provider',
	'ceafsn_ai_key_openai',
	'ceafsn_ai_key_gemini',
	'ceafsn_ai_key_grok',
	'ceafsn_ai_key_claude',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove any lingering transients.
delete_transient( 'ceafsn_ai_admin_notices' );
