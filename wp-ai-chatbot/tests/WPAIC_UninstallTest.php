<?php
/**
 * Tests for uninstall.php cleanup.
 */

use PHPUnit\Framework\TestCase;

class WPAIC_UninstallTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_uninstall_drops_tables_deletes_options_and_clears_cron(): void {
		global $wpdb;

		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );
		WPAICTestHelper::set_option( 'wpaic_db_version', '1.1.0' );
		WPAICTestHelper::set_option( 'wpaic_onboarding', array( 'dismissed' => false ) );
		WPAICTestHelper::set_option( 'wpaic_content_index_meta', array( 'updated_at' => '2026-06-01' ) );
		WPAICTestHelper::set_option( 'wpaic_search_index_meta', array( 'updated_at' => '2026-06-01' ) );
		set_transient( 'wpaic_activation_redirect', true, 60 );
		wp_schedule_event( time(), 'daily', 'wpaic_daily_retention' );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', true );
		}
		require __DIR__ . '/../uninstall.php';

		$expected_tables = array(
			'wp_wpaic_conversations',
			'wp_wpaic_messages',
			'wp_wpaic_support_requests',
			'wp_wpaic_events',
			'wp_wpaic_data_sources',
			'wp_wpaic_training_data',
			'wp_wpaic_faqs',
		);
		foreach ( $expected_tables as $expected_table ) {
			$this->assertContains( "DROP TABLE IF EXISTS $expected_table", $wpdb->queries );
		}

		$this->assertFalse( get_option( 'wpaic_settings', false ) );
		$this->assertFalse( get_option( 'wpaic_db_version', false ) );
		$this->assertFalse( get_option( 'wpaic_onboarding', false ) );
		$this->assertFalse( get_option( 'wpaic_content_index_meta', false ) );
		$this->assertFalse( get_option( 'wpaic_search_index_meta', false ) );
		$this->assertFalse( get_transient( 'wpaic_activation_redirect' ) );
		$this->assertFalse( wp_next_scheduled( 'wpaic_daily_retention' ) );
	}
}
