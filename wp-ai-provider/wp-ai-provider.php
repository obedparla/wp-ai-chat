<?php
/**
 * Plugin Name: WP AI Provider
 * Plugin URI: https://github.com/obedmarquez/wp-ai
 * Description: Transparent OpenAI proxy server for WP AI Chatbot instances. Holds the API key, forwards chat requests, streams responses.
 * Version: 1.0.0
 * Author: Obed Marquez
 * Author URI: https://obedmarquez.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-ai-provider
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WPAIP_VERSION' ) ) {
	define( 'WPAIP_VERSION', '1.0.0' );
	define( 'WPAIP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'WPAIP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
	define( 'WPAIP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

if ( file_exists( WPAIP_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once WPAIP_PLUGIN_DIR . 'vendor/autoload.php';
}

require_once WPAIP_PLUGIN_DIR . 'includes/class-wpaip-loader.php';

function wpaip_init(): void {
	$loader = new WPAIP_Loader();
	$loader->init();
}

add_action( 'plugins_loaded', 'wpaip_init' );

register_activation_hook( __FILE__, 'wpaip_activate' );
register_deactivation_hook( __FILE__, 'wpaip_deactivate' );

function wpaip_activate(): void {
	add_option(
		'wpaip_settings',
		array(
			'openai_api_key' => '',
			'model'          => 'gpt-4o-mini',
			'site_key'       => wp_generate_uuid4(),
		)
	);
	flush_rewrite_rules();
}

function wpaip_deactivate(): void {
	flush_rewrite_rules();
}
