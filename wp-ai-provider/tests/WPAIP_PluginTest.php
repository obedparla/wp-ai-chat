<?php

use PHPUnit\Framework\TestCase;

class WPAIP_PluginTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
		$GLOBALS['wp_actions'] = array();
		$GLOBALS['wp_activation_hooks'] = array();
		$GLOBALS['wp_deactivation_hooks'] = array();
		$GLOBALS['wpdb']->reset();
	}

	public function test_plugin_constants_defined(): void {
		$this->assertTrue( defined( 'WPAIP_VERSION' ) );
		$this->assertTrue( defined( 'WPAIP_PLUGIN_DIR' ) );
		$this->assertTrue( defined( 'WPAIP_PLUGIN_URL' ) );
		$this->assertTrue( defined( 'WPAIP_PLUGIN_BASENAME' ) );
		$this->assertSame( '1.0.0', WPAIP_VERSION );
	}

	public function test_loader_class_exists(): void {
		$this->assertTrue( class_exists( 'WPAIP_Loader' ) );
	}

	public function test_loader_init_runs_without_error(): void {
		$loader = new WPAIP_Loader();
		$loader->init();
		$this->assertTrue( true );
	}

	public function test_activate_creates_default_settings(): void {
		wpaip_activate();

		$settings = get_option( 'wpaip_settings' );
		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'openai_api_key', $settings );
		$this->assertArrayHasKey( 'model', $settings );
		$this->assertArrayHasKey( 'reasoning_effort', $settings );
		$this->assertSame( '', $settings['openai_api_key'] );
		$this->assertSame( 'gpt-5-mini', $settings['model'] );
		$this->assertSame( 'low', $settings['reasoning_effort'] );
		$this->assertSame( 2000, $settings['daily_message_budget'] );
		$this->assertSame( 1000000, $settings['daily_token_budget'] );
	}

	public function test_activate_creates_usage_table(): void {
		wpaip_activate();

		$create_query = implode( "\n", $GLOBALS['wpdb']->queries );

		$this->assertStringContainsString( 'CREATE TABLE wp_wpaip_usage_daily', $create_query );
	}

	public function test_maybe_update_db_creates_usage_table_and_drops_legacy_option(): void {
		update_option( 'wpaip_usage_daily', array( '2026-06-01' => array() ) );

		wpaip_maybe_update_db();

		$create_query = implode( "\n", $GLOBALS['wpdb']->queries );

		$this->assertStringContainsString( 'CREATE TABLE wp_wpaip_usage_daily', $create_query );
		$this->assertFalse( get_option( 'wpaip_usage_daily' ) );
		$this->assertSame( WPAIP_DB_VERSION, get_option( 'wpaip_db_version' ) );
	}

	public function test_activate_does_not_overwrite_existing_settings(): void {
		update_option( 'wpaip_settings', array( 'openai_api_key' => 'sk-existing', 'model' => 'gpt-5' ) );

		wpaip_activate();

		$settings = get_option( 'wpaip_settings' );
		$this->assertSame( 'sk-existing', $settings['openai_api_key'] );
		$this->assertSame( 'gpt-5', $settings['model'] );
	}

	public function test_deactivate_runs_without_error(): void {
		wpaip_deactivate();
		$this->assertTrue( true );
	}

	public function test_wpaip_init_runs_without_error(): void {
		wpaip_init();
		$this->assertTrue( true );
	}
}
