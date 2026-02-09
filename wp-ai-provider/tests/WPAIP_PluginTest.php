<?php

use PHPUnit\Framework\TestCase;

class WPAIP_PluginTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
		$GLOBALS['wp_actions'] = array();
		$GLOBALS['wp_activation_hooks'] = array();
		$GLOBALS['wp_deactivation_hooks'] = array();
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
		$this->assertSame( '', $settings['openai_api_key'] );
		$this->assertSame( 'gpt-4o-mini', $settings['model'] );
	}

	public function test_activate_does_not_overwrite_existing_settings(): void {
		update_option( 'wpaip_settings', array( 'openai_api_key' => 'sk-existing', 'model' => 'gpt-4o' ) );

		wpaip_activate();

		$settings = get_option( 'wpaip_settings' );
		$this->assertSame( 'sk-existing', $settings['openai_api_key'] );
		$this->assertSame( 'gpt-4o', $settings['model'] );
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
