<?php
/**
 * Uninstall handler: drops all plugin tables, deletes options/transients,
 * and clears scheduled cron hooks.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wpaic_tables = array(
	$wpdb->prefix . 'wpaic_conversations',
	$wpdb->prefix . 'wpaic_messages',
	$wpdb->prefix . 'wpaic_support_requests',
	$wpdb->prefix . 'wpaic_events',
	$wpdb->prefix . 'wpaic_data_sources',
	$wpdb->prefix . 'wpaic_training_data',
	$wpdb->prefix . 'wpaic_faqs',
);

foreach ( $wpaic_tables as $wpaic_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
	$wpdb->query( "DROP TABLE IF EXISTS $wpaic_table" );
}

$wpaic_options = array(
	'wpaic_settings',
	'wpaic_db_version',
	'wpaic_onboarding',
	'wpaic_content_index_meta',
	'wpaic_search_index_meta',
);

foreach ( $wpaic_options as $wpaic_option ) {
	delete_option( $wpaic_option );
}

delete_transient( 'wpaic_activation_redirect' );

wp_clear_scheduled_hook( 'wpaic_daily_retention' );
