<?php
/**
 * Tests for WPAIC_CLI dummyjson importer helpers.
 */

use PHPUnit\Framework\TestCase;

// class-wpaic-cli.php returns early unless WP-CLI is present; define the
// constant and a minimal class stub (constant and class namespaces are
// separate in PHP, mirroring real WP-CLI which defines both).
if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}

if ( ! class_exists( 'WP_CLI' ) ) {
	class WP_CLI {
		/**
		 * @param mixed ...$args
		 */
		public static function add_command( ...$args ): void {}
	}
}

require_once __DIR__ . '/../includes/class-wpaic-cli.php';

class WPAIC_CLITest extends TestCase {
	public function test_humanize_category_name_converts_dummyjson_slug_to_display_name(): void {
		$reflect = new ReflectionMethod( WPAIC_CLI::class, 'humanize_category_name' );
		$reflect->setAccessible( true );
		$cli = new WPAIC_CLI();

		$this->assertSame( 'Kitchen Accessories', $reflect->invoke( $cli, 'kitchen-accessories' ) );
		$this->assertSame( 'Womens Shoes', $reflect->invoke( $cli, 'womens-shoes' ) );
		$this->assertSame( 'Beauty', $reflect->invoke( $cli, 'beauty' ) );
		$this->assertSame( '', $reflect->invoke( $cli, '' ) );
	}
}
