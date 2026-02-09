<?php
/**
 * Tests for plugin activation functionality.
 */

use PHPUnit\Framework\TestCase;

class WPAIC_ActivationTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_activation_creates_default_settings(): void {
		// Ensure no settings exist before activation
		$this->assertFalse( get_option( 'wpaic_settings', false ) );

		// Simulate activation
		wpaic_activate();

		// Verify settings created
		$settings = get_option( 'wpaic_settings' );
		$this->assertIsArray( $settings );
	}

	public function test_activation_sets_empty_api_key(): void {
		wpaic_activate();
		$settings = get_option( 'wpaic_settings' );

		$this->assertArrayHasKey( 'openai_api_key', $settings );
		$this->assertEquals( '', $settings['openai_api_key'] );
	}

	public function test_activation_sets_default_model(): void {
		wpaic_activate();
		$settings = get_option( 'wpaic_settings' );

		$this->assertArrayHasKey( 'model', $settings );
		$this->assertEquals( 'gpt-4o-mini', $settings['model'] );
	}

	public function test_activation_sets_greeting_message(): void {
		wpaic_activate();
		$settings = get_option( 'wpaic_settings' );

		$this->assertArrayHasKey( 'greeting_message', $settings );
		$this->assertEquals( 'Hello! How can I help you today?', $settings['greeting_message'] );
	}

	public function test_activation_enables_plugin_by_default(): void {
		wpaic_activate();
		$settings = get_option( 'wpaic_settings' );

		$this->assertArrayHasKey( 'enabled', $settings );
		$this->assertTrue( $settings['enabled'] );
	}

	public function test_activation_sets_empty_system_prompt(): void {
		wpaic_activate();
		$settings = get_option( 'wpaic_settings' );

		$this->assertArrayHasKey( 'system_prompt', $settings );
		$this->assertEquals( '', $settings['system_prompt'] );
	}

	public function test_activation_does_not_overwrite_existing_settings(): void {
		// Set custom settings before activation
		$custom_settings = array(
			'openai_api_key'   => 'my-custom-key',
			'model'            => 'gpt-4o',
			'greeting_message' => 'Welcome!',
			'enabled'          => false,
			'system_prompt'    => 'Custom prompt',
		);
		update_option( 'wpaic_settings', $custom_settings );

		// Run activation
		wpaic_activate();

		// Verify settings not overwritten
		$settings = get_option( 'wpaic_settings' );
		$this->assertEquals( 'my-custom-key', $settings['openai_api_key'] );
		$this->assertEquals( 'gpt-4o', $settings['model'] );
		$this->assertEquals( 'Welcome!', $settings['greeting_message'] );
		$this->assertFalse( $settings['enabled'] );
		$this->assertEquals( 'Custom prompt', $settings['system_prompt'] );
	}
}
