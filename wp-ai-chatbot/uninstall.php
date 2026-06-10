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
	'wpaic_index_version',
);

foreach ( $wpaic_options as $wpaic_option ) {
	delete_option( $wpaic_option );
}

delete_transient( 'wpaic_activation_redirect' );

wp_clear_scheduled_hook( 'wpaic_daily_retention' );
wp_clear_scheduled_hook( 'wpaic_rebuild_product_index' );

// Remove the TNT search index files under uploads/wpaic/.
$wpaic_upload_dir = wp_upload_dir( null, false );
if ( ! empty( $wpaic_upload_dir['basedir'] ) ) {
	$wpaic_index_dir = $wpaic_upload_dir['basedir'] . '/wpaic';
	if ( is_dir( $wpaic_index_dir ) && ! is_link( $wpaic_index_dir ) ) {
		$wpaic_iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $wpaic_index_dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $wpaic_iterator as $wpaic_file ) {
			if ( $wpaic_file->isDir() && ! $wpaic_file->isLink() ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing plugin-owned index directory on uninstall.
				rmdir( $wpaic_file->getPathname() );
			} else {
				wp_delete_file( $wpaic_file->getPathname() );
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Removing plugin-owned index directory on uninstall.
		rmdir( $wpaic_index_dir );
	}
}
