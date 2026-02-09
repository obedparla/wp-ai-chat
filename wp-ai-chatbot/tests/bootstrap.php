<?php
/**
 * PHPUnit bootstrap file for WP AI Chatbot.
 */

define( 'ABSPATH', __DIR__ . '/../' );
define( 'WPAIC_PLUGIN_DIR', __DIR__ . '/../' );
define( 'WPAIC_PLUGIN_URL', 'http://example.com/wp-content/plugins/wp-ai-chatbot/' );
define( 'WPAIC_PLUGIN_BASENAME', 'wp-ai-chatbot/wp-ai-chatbot.php' );
define( 'WPAIC_VERSION', '1.0.0' );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-stubs.php';

global $wpdb;
