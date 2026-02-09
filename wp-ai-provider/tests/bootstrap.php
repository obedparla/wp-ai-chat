<?php
/**
 * PHPUnit bootstrap file for WP AI Provider.
 */

define( 'WPAIP_TESTING', true );
define( 'ABSPATH', __DIR__ . '/../' );
define( 'WPAIP_PLUGIN_DIR', __DIR__ . '/../' );
define( 'WPAIP_PLUGIN_URL', 'http://example.com/wp-content/plugins/wp-ai-provider/' );
define( 'WPAIP_PLUGIN_BASENAME', 'wp-ai-provider/wp-ai-provider.php' );
define( 'WPAIP_VERSION', '1.0.0' );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/wp-stubs.php';
require_once __DIR__ . '/../wp-ai-provider.php';
require_once __DIR__ . '/../includes/class-wpaip-streamer.php';
require_once __DIR__ . '/../includes/class-wpaip-api.php';
