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
		$GLOBALS['wpdb']->reset();

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

	public function test_register_settings_adds_freemius_product_id_field(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'freemius_product_id', $fields );
	}

	public function test_register_settings_adds_freemius_api_token_field(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'freemius_api_token', $fields );
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

	public function test_register_settings_adds_reasoning_effort_field(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'reasoning_effort', $fields );
	}

	public function test_register_settings_adds_daily_budget_fields(): void {
		$this->admin->register_settings();

		$fields = $GLOBALS['wp_settings_fields']['wp-ai-provider']['wpaip_main_section'];
		$this->assertArrayHasKey( 'daily_message_budget', $fields );
		$this->assertArrayHasKey( 'daily_token_budget', $fields );
	}

	public function test_daily_budget_fields_render_number_inputs(): void {
		ob_start();
		$this->admin->render_daily_message_budget_field();
		$this->admin->render_daily_token_budget_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wpaip_settings[daily_message_budget]', $output );
		$this->assertStringContainsString( 'wpaip_settings[daily_token_budget]', $output );
		$this->assertStringContainsString( 'type="number"', $output );
	}

	public function test_sanitize_settings_saves_daily_budgets(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token', 'daily_message_budget' => 50, 'daily_token_budget' => 250000 );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 50, $sanitized['daily_message_budget'] );
		$this->assertSame( 250000, $sanitized['daily_token_budget'] );
	}

	public function test_sanitize_settings_clamps_negative_budgets_to_zero(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token', 'daily_message_budget' => -5, 'daily_token_budget' => -1 );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 0, $sanitized['daily_message_budget'] );
		$this->assertSame( 0, $sanitized['daily_token_budget'] );
	}

	public function test_sanitize_settings_defaults_budgets_when_missing(): void {
		update_option( 'wpaip_settings', array() );

		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( WPAIP_Admin::DEFAULT_DAILY_MESSAGE_BUDGET, $sanitized['daily_message_budget'] );
		$this->assertSame( WPAIP_Admin::DEFAULT_DAILY_TOKEN_BUDGET, $sanitized['daily_token_budget'] );
	}

	public function test_freemius_product_id_field_renders_number_input(): void {
		ob_start();
		$this->admin->render_freemius_product_id_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="number"', $output );
		$this->assertStringContainsString( 'wpaip_settings[freemius_product_id]', $output );
	}

	public function test_freemius_api_token_field_renders_password_input(): void {
		ob_start();
		$this->admin->render_freemius_api_token_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="password"', $output );
		$this->assertStringContainsString( 'wpaip_settings[freemius_api_token]', $output );
	}

	// FIX-6: saved token must never be echoed into the page source.
	public function test_freemius_api_token_field_does_not_echo_saved_token(): void {
		update_option( 'wpaip_settings', array( 'freemius_api_token' => 'fs-secret-token-9876' ) );

		ob_start();
		$this->admin->render_freemius_api_token_field();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'fs-secret-token-9876', $output );
		$this->assertStringContainsString( 'value=""', $output );
		$this->assertStringContainsString( '••••••••••••9876', $output );
	}

	public function test_freemius_api_token_field_renders_empty_placeholder_when_no_token_saved(): void {
		ob_start();
		$this->admin->render_freemius_api_token_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'placeholder=""', $output );
		$this->assertStringNotContainsString( 'A token is saved', $output );
	}

	// FIX-6: blank submission keeps the saved token (placeholder pattern).
	public function test_sanitize_settings_keeps_saved_freemius_api_token_when_blank(): void {
		update_option( 'wpaip_settings', array( 'freemius_api_token' => 'fs-existing-token-4321' ) );

		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => '' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'fs-existing-token-4321', $sanitized['freemius_api_token'] );
	}

	public function test_sanitize_settings_replaces_saved_freemius_api_token_with_new_value(): void {
		update_option( 'wpaip_settings', array( 'freemius_api_token' => 'fs-existing-token-4321' ) );

		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-new-token-8765' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'fs-new-token-8765', $sanitized['freemius_api_token'] );
	}

	public function test_freemius_product_id_field_displays_saved_value(): void {
		update_option( 'wpaip_settings', array(
			'freemius_product_id' => 4321,
			'freemius_api_token'  => 'fs-token',
			'openai_api_key'      => '',
			'model'               => 'gpt-5-mini',
		) );

		ob_start();
		$this->admin->render_freemius_product_id_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '4321', $output );
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
		$input     = array( 'openai_api_key' => 'sk-test-key-12345', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'sk-test-key-12345', $sanitized['openai_api_key'] );
	}

	// PRD: API key is trimmed on save
	public function test_sanitize_settings_trims_api_key(): void {
		$input     = array( 'openai_api_key' => '  sk-test-key-12345  ', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'sk-test-key-12345', $sanitized['openai_api_key'] );
	}

	// NEW-B: saved key must never be echoed into the page source.
	public function test_api_key_field_does_not_echo_saved_key(): void {
		update_option( 'wpaip_settings', array( 'openai_api_key' => 'sk-secret-key-9876' ) );

		ob_start();
		$this->admin->render_api_key_field();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'sk-secret-key-9876', $output );
		$this->assertStringContainsString( 'value=""', $output );
		$this->assertStringContainsString( '••••••••••••9876', $output );
	}

	public function test_api_key_field_renders_empty_placeholder_when_no_key_saved(): void {
		ob_start();
		$this->admin->render_api_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'placeholder=""', $output );
		$this->assertStringNotContainsString( 'A key is saved', $output );
	}

	// NEW-B: blank submission keeps the saved key (placeholder pattern).
	public function test_sanitize_settings_keeps_saved_api_key_when_blank(): void {
		update_option( 'wpaip_settings', array( 'openai_api_key' => 'sk-existing-key-4321' ) );

		$input     = array( 'openai_api_key' => '', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'sk-existing-key-4321', $sanitized['openai_api_key'] );
	}

	public function test_sanitize_settings_replaces_saved_api_key_with_new_value(): void {
		update_option( 'wpaip_settings', array( 'openai_api_key' => 'sk-existing-key-4321' ) );

		$input     = array( 'openai_api_key' => 'sk-new-key-8765', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'sk-new-key-8765', $sanitized['openai_api_key'] );
	}

	public function test_mask_api_key_shows_bullets_and_last_four(): void {
		$this->assertSame( '••••••••••••3456', WPAIP_Admin::mask_api_key( 'sk-test-123456' ) );
	}

	public function test_mask_api_key_returns_empty_for_empty_key(): void {
		$this->assertSame( '', WPAIP_Admin::mask_api_key( '' ) );
	}

	public function test_mask_api_key_hides_short_keys_entirely(): void {
		$this->assertSame( '••••••••••••', WPAIP_Admin::mask_api_key( 'abcd' ) );
	}

	// Model is validated on save against the allowed list.
	public function test_sanitize_settings_validates_model(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5.4-nano', 'reasoning_effort' => 'medium', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'gpt-5.4-nano', $sanitized['model'] );
	}

	public function test_sanitize_settings_rejects_invalid_model(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-9', 'reasoning_effort' => 'medium', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'gpt-5-mini', $sanitized['model'] );
	}

	// Reasoning effort is stored separately from the model and validated on save.
	public function test_sanitize_settings_validates_reasoning_effort(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'reasoning_effort' => 'high', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'high', $sanitized['reasoning_effort'] );
	}

	public function test_sanitize_settings_rejects_invalid_reasoning_effort(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'model' => 'gpt-5-mini', 'reasoning_effort' => 'turbo', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'low', $sanitized['reasoning_effort'] );
	}

	public function test_model_field_renders_select_with_all_models(): void {
		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'wpaip_settings[model]', $output );
		$this->assertStringContainsString( 'gpt-5-mini', $output );
		$this->assertStringContainsString( 'gpt-5.4-mini', $output );
		$this->assertStringContainsString( 'gpt-5.4-nano', $output );
	}

	public function test_model_field_selects_current_value(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => '',
			'model'               => 'gpt-5.4-nano',
			'reasoning_effort'    => 'medium',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-token',
		) );

		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="gpt-5\.4-nano"[^>]*selected/', $output );
	}

	public function test_reasoning_effort_field_renders_select(): void {
		ob_start();
		$this->admin->render_reasoning_effort_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'wpaip_settings[reasoning_effort]', $output );
		$this->assertStringContainsString( 'none', $output );
		$this->assertStringContainsString( 'low', $output );
		$this->assertStringContainsString( 'medium', $output );
		$this->assertStringContainsString( 'high', $output );
	}

	public function test_reasoning_effort_field_selects_current_value(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => '',
			'model'               => 'gpt-5-mini',
			'reasoning_effort'    => 'high',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-token',
		) );

		ob_start();
		$this->admin->render_reasoning_effort_field();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="high"[^>]*selected/', $output );
	}

	public function test_render_settings_page_displays_validated_installs(): void {
		update_option(
			'wpaip_install_registry',
			array(
				123 => array(
					'install_id'        => 123,
					'site_url'          => 'https://store.example.com',
					'status'            => 'licensed',
					'license_id'        => 456,
					'usage_bucket_key'  => 'fs_install_123',
					'last_validated_at' => '2026-04-08 10:00:00',
					'last_seen_at'      => '2026-04-08 10:01:00',
					'last_error_message' => '',
				),
			)
		);

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Validated Chatbot Installs', $output );
		$this->assertStringContainsString( 'https://store.example.com', $output );
		$this->assertStringContainsString( 'fs_install_123', $output );
	}

	public function test_render_settings_page_displays_daily_usage_per_install(): void {
		update_option(
			'wpaip_install_registry',
			array(
				123 => array(
					'install_id'        => 123,
					'site_url'          => 'https://store.example.com',
					'status'            => 'licensed',
					'license_id'        => 456,
					'usage_bucket_key'  => 'fs_install_123',
					'last_validated_at' => '2026-04-08 10:00:00',
					'last_seen_at'      => '2026-04-08 10:01:00',
					'last_error_message' => '',
				),
			)
		);
		$GLOBALS['wpdb']->insert(
			WPAIP_Usage_Tracker::get_table_name(),
			array(
				'usage_day'    => gmdate( 'Y-m-d' ),
				'usage_bucket' => 'fs_install_123',
				'messages'     => 12,
				'total_tokens' => 45230,
			)
		);

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Usage Today', $output );
		$this->assertStringContainsString( '12 msgs', $output );
		$this->assertStringContainsString( '45,230 tokens', $output );
		// No input_tokens recorded — cache-hit % must be omitted, not divide by zero.
		$this->assertStringNotContainsString( 'cached', $output );
	}

	// NEW-B: Usage Today shows cache-hit % (cached_input_tokens / input_tokens).
	public function test_render_settings_page_displays_cache_hit_percent(): void {
		update_option(
			'wpaip_install_registry',
			array(
				123 => array(
					'install_id'        => 123,
					'site_url'          => 'https://store.example.com',
					'status'            => 'licensed',
					'license_id'        => 456,
					'usage_bucket_key'  => 'fs_install_123',
					'last_validated_at' => '2026-04-08 10:00:00',
					'last_seen_at'      => '2026-04-08 10:01:00',
					'last_error_message' => '',
				),
			)
		);
		$GLOBALS['wpdb']->insert(
			WPAIP_Usage_Tracker::get_table_name(),
			array(
				'usage_day'           => gmdate( 'Y-m-d' ),
				'usage_bucket'        => 'fs_install_123',
				'messages'            => 45,
				'input_tokens'        => 10000,
				'cached_input_tokens' => 8800,
				'output_tokens'       => 5000,
				'total_tokens'        => 15000,
			)
		);

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '45 msgs', $output );
		$this->assertStringContainsString( '15,000 tokens', $output );
		$this->assertStringContainsString( '88% cached', $output );
	}

	public function test_sanitize_settings_saves_freemius_fields(): void {
		$input     = array( 'openai_api_key' => 'sk-new', 'model' => 'gpt-5-mini', 'freemius_product_id' => 5678, 'freemius_api_token' => 'token-xyz' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 5678, $sanitized['freemius_product_id'] );
		$this->assertSame( 'token-xyz', $sanitized['freemius_api_token'] );
	}

	public function test_sanitize_settings_strips_html_from_api_key(): void {
		$input     = array( 'openai_api_key' => '<script>alert("xss")</script>sk-test', 'model' => 'gpt-5-mini', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertStringNotContainsString( '<script>', $sanitized['openai_api_key'] );
	}

	public function test_sanitize_settings_defaults_model_when_missing(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'gpt-5-mini', $sanitized['model'] );
	}

	public function test_sanitize_settings_defaults_reasoning_effort_when_missing(): void {
		$input     = array( 'openai_api_key' => 'sk-test', 'freemius_product_id' => 1234, 'freemius_api_token' => 'fs-token' );
		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 'low', $sanitized['reasoning_effort'] );
	}

	public function test_get_available_models_returns_expected(): void {
		$models = WPAIP_Admin::get_available_models();

		$this->assertArrayHasKey( 'gpt-5-mini', $models );
		$this->assertArrayHasKey( 'gpt-5.4-mini', $models );
		$this->assertArrayHasKey( 'gpt-5.4-nano', $models );
	}

	public function test_get_available_reasoning_efforts_returns_expected(): void {
		$efforts = WPAIP_Admin::get_available_reasoning_efforts();

		$this->assertSame( array( 'none', 'low', 'medium', 'high' ), array_keys( $efforts ) );
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
