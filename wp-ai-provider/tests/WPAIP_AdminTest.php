<?php

use PHPUnit\Framework\TestCase;

class WPAIP_AdminTest extends TestCase {
	private WPAIP_Admin $admin;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['wp_options']             = array();
		$GLOBALS['wp_actions']             = array();
		$GLOBALS['wp_admin_pages']         = array();
		$GLOBALS['wp_registered_settings'] = array();
		$GLOBALS['wp_settings_sections']   = array();
		$GLOBALS['wp_settings_fields']     = array();
		$GLOBALS['wp_is_admin']            = true;
		$GLOBALS['wp_current_user_can']    = true;

		wpaip_activate();
		$this->admin = new WPAIP_Admin();
	}

	protected function tearDown(): void {
		$GLOBALS['wp_options']             = array();
		$GLOBALS['wp_actions']             = array();
		$GLOBALS['wp_admin_pages']         = array();
		$GLOBALS['wp_registered_settings'] = array();
		$GLOBALS['wp_settings_sections']   = array();
		$GLOBALS['wp_settings_fields']     = array();
		unset( $GLOBALS['wp_is_admin'] );
		unset( $GLOBALS['wp_current_user_can'] );
		parent::tearDown();
	}

	public function test_admin_class_exists(): void {
		$this->assertTrue( class_exists( 'WPAIP_Admin' ) );
	}

	public function test_init_registers_admin_menu_hook(): void {
		$this->admin->init();

		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['wp_actions'] );
	}

	public function test_init_registers_admin_init_hook(): void {
		$this->admin->init();

		$this->assertArrayHasKey( 'admin_init', $GLOBALS['wp_actions'] );
	}

	public function test_add_admin_menu_registers_page(): void {
		$this->admin->add_admin_menu();

		$this->assertArrayHasKey( 'wp-ai-provider', $GLOBALS['wp_admin_pages'] );
		$page = $GLOBALS['wp_admin_pages']['wp-ai-provider'];
		$this->assertSame( 'manage_options', $page['capability'] );
	}

	public function test_register_settings_registers_setting(): void {
		$this->admin->register_settings();

		$this->assertArrayHasKey( 'wpaip_settings', $GLOBALS['wp_registered_settings'] );
		$setting = $GLOBALS['wp_registered_settings']['wpaip_settings'];
		$this->assertSame( 'wpaip_settings_group', $setting['group'] );
		$this->assertIsCallable( $setting['args']['sanitize_callback'] );
	}

	public function test_register_settings_adds_section(): void {
		$this->admin->register_settings();

		$this->assertArrayHasKey( 'wp-ai-provider', $GLOBALS['wp_settings_sections'] );
		$this->assertArrayHasKey( 'wpaip_main_section', $GLOBALS['wp_settings_sections']['wp-ai-provider'] );
	}

	public function test_register_settings_adds_site_key_field(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'site_key', $fields );
	}

	public function test_register_settings_adds_api_key_field(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'openai_api_key', $fields );
	}

	public function test_register_settings_adds_model_field(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'model', $fields );
	}

	// PRD: site key is displayed read-only and copyable
	public function test_site_key_field_renders_readonly_input(): void {
		ob_start();
		$this->admin->render_site_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'readonly', $output );
		$this->assertStringContainsString( 'id="wpaip-site-key"', $output );
	}

	// PRD: copy button exists for site key
	public function test_site_key_field_has_copy_button(): void {
		ob_start();
		$this->admin->render_site_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Copy', $output );
		$this->assertStringContainsString( 'clipboard', $output );
	}

	// PRD: site key displays auto-generated value
	public function test_site_key_field_displays_generated_key(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$site_key = $settings['site_key'];

		ob_start();
		$this->admin->render_site_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( $site_key, $output );
		$this->assertNotEmpty( $site_key );
	}

	// PRD: API key input is password-masked
	public function test_api_key_field_renders_password_input(): void {
		ob_start();
		$this->admin->render_api_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="password"', $output );
		$this->assertStringContainsString( 'wpaip_settings[openai_api_key]', $output );
	}

	// PRD: save API key persists after reload
	public function test_sanitize_settings_saves_api_key(): void {
		$input     = array( 'openai_api_key' => 'sk-test-key-12345', 'model' => 'gpt-4o-mini' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'sk-test-key-12345', $sanitized['openai_api_key'] );
	}

	// PRD: API key is trimmed on save
	public function test_sanitize_settings_trims_api_key(): void {
		$input     = array( 'openai_api_key' => '  sk-test-key-12345  ', 'model' => 'gpt-4o-mini' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'sk-test-key-12345', $sanitized['openai_api_key'] );
	}

	// PRD: model is validated on save
	public function test_sanitize_settings_validates_model(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-4o' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'gpt-4o', $sanitized['model'] );
	}

	public function test_sanitize_settings_rejects_invalid_model(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'invalid-model' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'gpt-4o-mini', $sanitized['model'] );
	}

	// PRD: default model is displayed and editable
	public function test_model_field_renders_select(): void {
		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'wpaip_settings[model]', $output );
		$this->assertStringContainsString( 'gpt-4o-mini', $output );
		$this->assertStringContainsString( 'gpt-4o', $output );
		$this->assertStringContainsString( 'gpt-5', $output );
	}

	public function test_model_field_selects_current_value(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key' => '',
			'model'          => 'gpt-4o',
			'site_key'       => 'test-key',
		) );

		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="gpt-4o"[^>]*selected/', $output );
	}

	// PRD: site key is preserved during sanitization (never overwritten by form)
	public function test_sanitize_settings_preserves_site_key(): void {
		$settings = get_option( 'wpaip_settings', array() );
		$original_site_key = $settings['site_key'];

		$input     = array( 'openai_api_key' => 'sk-new', 'model' => 'gpt-4o-mini' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( $original_site_key, $sanitized['site_key'] );
	}

	public function test_sanitize_settings_strips_html_from_api_key(): void {
		$input     = array( 'openai_api_key' => '<script>alert("xss")</script>sk-test', 'model' => 'gpt-4o-mini' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertStringNotContainsString( '<script>', $sanitized['openai_api_key'] );
	}

	public function test_sanitize_settings_defaults_model_when_missing(): void {
		$input     = array( 'openai_api_key' => 'sk-test' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'gpt-4o-mini', $sanitized['model'] );
	}

	public function test_get_available_models_returns_expected(): void {
		$models = $this->admin->get_available_models();

		$this->assertArrayHasKey( 'gpt-4o-mini', $models );
		$this->assertArrayHasKey( 'gpt-4o', $models );
		$this->assertArrayHasKey( 'gpt-5', $models );
	}

	public function test_render_settings_page_requires_manage_options(): void {
		$GLOBALS['wp_current_user_can'] = false;

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_render_settings_page_outputs_form(): void {
		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'options.php', $output );
	}

	public function test_loader_initializes_admin_when_is_admin(): void {
		$GLOBALS['wp_is_admin'] = true;
		$loader = new WPAIP_Loader();
		$loader->init();

		$this->assertArrayHasKey( 'admin_menu', $GLOBALS['wp_actions'] );
		$this->assertArrayHasKey( 'admin_init', $GLOBALS['wp_actions'] );
	}
}
