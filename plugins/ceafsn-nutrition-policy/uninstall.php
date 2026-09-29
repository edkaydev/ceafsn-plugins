<?php
/**
 * Uninstall handler for CE-AFSN Nutrition Policy.
 *
 * This file is called by WordPress when the plugin is deleted via
 * Plugins → Delete. It only removes data when the administrator has
 * explicitly enabled data deletion through the plugin's Uninstall
 * settings tab. Deactivation alone never deletes data.
 *
 * @package CEAFSN_NP
 */

// WordPress calls this directly; bail if accessed outside that context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if the administrator explicitly opted in.
$delete_data = get_option( 'ceafsn_np_uninstall_delete_data', false );

if ( ! $delete_data ) {
	// Option not set — leave all data intact.
	return;
}

global $wpdb;

// Drop the custom table. Attached media stays in the library: other plugins
// and pages may still reference those files.
$table = $wpdb->prefix . 'ceafsn_np_policies';

// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is hardcoded, not user input.
$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );

// Remove all plugin options.
$options = array(
	'ceafsn_np_db_version',
	'ceafsn_np_placeholder_files',
	'ceafsn_np_uninstall_delete_data',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
