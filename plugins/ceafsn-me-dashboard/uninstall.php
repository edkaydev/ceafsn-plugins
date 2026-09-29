<?php
/**
 * Uninstall handler for CE-AFSN M&E Dashboard.
 *
 * This file is called by WordPress when the plugin is deleted via
 * Plugins → Delete. It only removes data when the administrator has
 * explicitly enabled data deletion through the plugin's Uninstall
 * settings tab. Deactivation alone never deletes data.
 *
 * @package CEAFSN_MED
 */

// WordPress calls this directly; bail if accessed outside that context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if the administrator explicitly opted in.
$delete_data = get_option( 'ceafsn_med_uninstall_delete_data', false );

if ( ! $delete_data ) {
	// Option not set — leave all data intact.
	return;
}

global $wpdb;

// Drop custom tables.
$tables = array(
	$wpdb->prefix . 'ceafsn_med_metrics',
	$wpdb->prefix . 'ceafsn_med_demographics',
	$wpdb->prefix . 'ceafsn_med_projects',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are hardcoded, not user input.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// Remove all plugin options.
$options = array(
	'ceafsn_med_db_version',
	'ceafsn_med_preview_mode',
	'ceafsn_med_uninstall_delete_data',
);

foreach ( $options as $option ) {
	delete_option( $option );
}
