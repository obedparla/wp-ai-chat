<?php
/**
 * Plugin Name: WP AI Chatbot
 * Plugin URI: https://github.com/obedmarquez/wp-ai-chatbot
 * Description: AI-powered chatbot widget with product knowledge and tool calling capabilities.
 * Version: 1.0.0
 * Author: Obed Marquez
 * Author URI: https://obedmarquez.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-ai-chatbot
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPAIC_VERSION', '1.0.0' );
define( 'WPAIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPAIC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( WPAIC_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WPAIC_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-loader.php';

/**
 * Read local file contents using WP filesystem.
 *
 * @param string $path File path.
 * @return string|false File contents or false on failure.
 */
function wpaic_file_get_contents( string $path ): string|false {
	global $wp_filesystem;
	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();
	if ( $wp_filesystem instanceof WP_Filesystem_Base && $wp_filesystem->exists( $path ) ) {
		return $wp_filesystem->get_contents( $path );
	}
	return false;
}

/**
 * Check if WooCommerce is active.
 *
 * @return bool True if WooCommerce is active.
 */
function wpaic_is_woocommerce_active(): bool {
	return class_exists( 'WooCommerce' );
}

function wpaic_init(): void {
	$loader = new WPAIC_Loader();
	$loader->init();
}

add_action( 'plugins_loaded', 'wpaic_init' );

register_activation_hook( __FILE__, 'wpaic_activate' );
register_deactivation_hook( __FILE__, 'wpaic_deactivate' );

function wpaic_activate(): void {
	add_option(
		'wpaic_settings',
		array(
			'openai_api_key'   => '',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello! How can I help you today?',
			'enabled'          => true,
			'system_prompt'    => '',
			'theme_color'      => '#0073aa',
		)
	);

	wpaic_create_tables();
	flush_rewrite_rules();
}

function wpaic_create_tables(): void {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$conversations_table    = $wpdb->prefix . 'wpaic_conversations';
	$messages_table         = $wpdb->prefix . 'wpaic_messages';
	$support_requests_table = $wpdb->prefix . 'wpaic_support_requests';

	$sql = "CREATE TABLE $conversations_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		session_id varchar(64) NOT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		user_ip varchar(45) DEFAULT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY session_id (session_id),
		KEY user_id (user_id),
		KEY created_at (created_at)
	) $charset_collate;

	CREATE TABLE $messages_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		conversation_id bigint(20) unsigned NOT NULL,
		role varchar(20) NOT NULL,
		content longtext NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY conversation_id (conversation_id),
		KEY created_at (created_at)
	) $charset_collate;

	CREATE TABLE $support_requests_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		customer_name varchar(255) NOT NULL,
		customer_email varchar(255) NOT NULL,
		conversation_id bigint(20) unsigned DEFAULT NULL,
		transcript longtext NOT NULL,
		status varchar(20) NOT NULL DEFAULT 'new',
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY customer_email (customer_email),
		KEY status (status),
		KEY created_at (created_at)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	wpaic_create_training_tables();
}

function wpaic_deactivate(): void {
	flush_rewrite_rules();
}

function wpaic_create_training_tables(): void {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	$data_sources_table  = $wpdb->prefix . 'wpaic_data_sources';
	$training_data_table = $wpdb->prefix . 'wpaic_training_data';
	$faqs_table          = $wpdb->prefix . 'wpaic_faqs';

	$sql = "CREATE TABLE $data_sources_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		name varchar(64) NOT NULL,
		label varchar(255) NOT NULL,
		description text NOT NULL,
		columns text NOT NULL,
		row_count int(11) unsigned NOT NULL DEFAULT 0,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY name (name)
	) $charset_collate;

	CREATE TABLE $training_data_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		source_id bigint(20) unsigned NOT NULL,
		row_data longtext NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY source_id (source_id)
	) $charset_collate;

	CREATE TABLE $faqs_table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		question text NOT NULL,
		answer text NOT NULL,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}
