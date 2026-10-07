<?php
/**
 * Uninstall routine for CE-AFSN AI Assistant.
 *
 * Runs only when the plugin is deleted via Plugins → Delete in wp-admin.
 * Deactivation alone never touches data.
 *
 * This file is deliberately not a class: the include path can differ between
 * plugin, mu-plugin, and symlinked installs, so it depends on nothing but
 * WordPress itself and the $wpdb global.
 *
 * @package CEAFSN_AI
 */

// WordPress sets this constant before calling uninstall.php. Any other
// execution path — direct HTTP, CLI without the constant, etc. — is rejected.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$ceafsn_ai_table = $wpdb->prefix . 'ceafsn_ai_chunks';

// The option holds the administrator's explicit choice. Absent or false means
// the index and the stored keys stay: a plugin deletion should never destroy a
// month of embedding work, or a secret another tool still needs, by surprise.
$ceafsn_ai_delete = (bool) get_option( 'ceafsn_ai_uninstall_delete_data', false );

// Bookkeeping options carry neither content nor credentials, so they go either
// way. Rate-limit transients are not listed because they expire on their own
// after sixty seconds and cannot be enumerated from here.
delete_option( 'ceafsn_ai_db_version' );
delete_option( 'ceafsn_ai_provider' );
delete_option( 'ceafsn_ai_uninstall_delete_data' );
delete_transient( 'ceafsn_ai_admin_notices' );

if ( ! $ceafsn_ai_delete ) {
	return;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.PreparedSQL.InterpolatedNotPrepared -- table name is built from a core-controlled prefix.
$wpdb->query( "DROP TABLE IF EXISTS `{$ceafsn_ai_table}`" );

$ceafsn_ai_keys = array(
	'ceafsn_ai_key_openai',
	'ceafsn_ai_key_gemini',
	'ceafsn_ai_key_grok',
	'ceafsn_ai_key_claude',
);

$ceafsn_ai_models = array();
foreach ( array( 'openai', 'gemini', 'grok', 'claude' ) as $ceafsn_ai_provider_key ) {
	$ceafsn_ai_models[] = 'ceafsn_ai_model_' . $ceafsn_ai_provider_key;
	$ceafsn_ai_models[] = 'ceafsn_ai_embed_model_' . $ceafsn_ai_provider_key;
}

foreach ( array_merge( $ceafsn_ai_keys, $ceafsn_ai_models ) as $ceafsn_ai_option ) {
	delete_option( $ceafsn_ai_option );
}
