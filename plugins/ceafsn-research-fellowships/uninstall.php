<?php
/**
 * Uninstall handler for CE-AFSN Research Fellowships.
 *
 * This file is loaded by WordPress only when an administrator deletes the
 * plugin. It is deliberately not a class: the include path may differ between
 * the plugin, mu-plugin, and symlinked installs, so it depends on nothing but
 * WordPress itself and the $wpdb global.
 *
 * Records are removed only when the administrator ticked the opt-in box on the
 * Settings tab. Media Library attachments are never deleted, because the same
 * file may be referenced elsewhere on the site and deleting another page's
 * document is not something an uninstall should decide.
 *
 * @package CEAFSN_RF
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$ceafsn_rf_table = $wpdb->prefix . 'ceafsn_rf_fellowships';

// The option holds the administrator's explicit choice. Absent or false means
// the records stay: a plugin deletion should never destroy content by surprise.
if ( ! get_option( 'ceafsn_rf_uninstall_delete_data', false ) ) {
	// Still clear the plugin's own options, which carry no content.
	delete_option( 'ceafsn_rf_show_closed' );
	delete_option( 'ceafsn_rf_uninstall_delete_data' );
	return;
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is built from a core-controlled prefix.
$wpdb->query( "DROP TABLE IF EXISTS `{$ceafsn_rf_table}`" );

delete_option( 'ceafsn_rf_show_closed' );
delete_option( 'ceafsn_rf_uninstall_delete_data' );
delete_option( 'ceafsn_rf_db_version' );
