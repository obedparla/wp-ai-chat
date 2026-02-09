<?php
/**
 * Tests for WPAIC_Admin class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-logs.php';
require_once __DIR__ . '/../includes/class-wpaic-admin.php';

class WPAIC_AdminTest extends TestCase {
	private WPAIC_Admin $admin;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		$this->admin = new WPAIC_Admin();
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_sanitize_settings_sanitizes_api_key(): void {
		$input = array(
			'openai_api_key'   => '  sk-test-key-12345  ',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'sk-test-key-12345', $sanitized['openai_api_key'] );
	}

	public function test_sanitize_settings_sanitizes_model(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => '  gpt-4o  ',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'gpt-4o', $sanitized['model'] );
	}

	public function test_sanitize_settings_defaults_model_when_empty(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'gpt-4o-mini', $sanitized['model'] );
	}

	public function test_sanitize_settings_sanitizes_greeting_message(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => "  Hello!\nHow can I help?  ",
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( "Hello!\nHow can I help?", $sanitized['greeting_message'] );
	}

	public function test_sanitize_settings_enabled_is_true_when_set(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $sanitized['enabled'] );
	}

	public function test_sanitize_settings_enabled_is_false_when_not_set(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $sanitized['enabled'] );
	}

	public function test_sanitize_settings_enabled_is_false_when_empty(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $sanitized['enabled'] );
	}

	public function test_sanitize_settings_sanitizes_system_prompt(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => "  You are a helpful bot.\nBe nice.  ",
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( "You are a helpful bot.\nBe nice.", $sanitized['system_prompt'] );
	}

	public function test_sanitize_settings_handles_missing_api_key(): void {
		$input = array(
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '', $sanitized['openai_api_key'] );
	}

	public function test_sanitize_settings_sanitizes_theme_color(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'theme_color'      => '#ff5500',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '#ff5500', $sanitized['theme_color'] );
	}

	public function test_sanitize_settings_defaults_theme_color_when_invalid(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'theme_color'      => 'not-a-color',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '#0073aa', $sanitized['theme_color'] );
	}

	public function test_sanitize_settings_defaults_theme_color_when_missing(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '#0073aa', $sanitized['theme_color'] );
	}

	public function test_sanitize_settings_strips_html_from_api_key(): void {
		$input = array(
			'openai_api_key'   => '<script>alert("xss")</script>sk-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'alert("xss")sk-key', $sanitized['openai_api_key'] );
	}

	public function test_render_api_key_field_outputs_password_input(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => 'sk-test-key-secret',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_api_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="password"', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[openai_api_key]"', $output );
		$this->assertStringContainsString( 'value="sk-test-key-secret"', $output );
	}

	public function test_render_api_key_field_empty_when_no_settings(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_api_key_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value=""', $output );
	}

	public function test_render_model_field_outputs_select(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model' => 'gpt-4o',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[model]"', $output );
		$this->assertStringContainsString( 'gpt-4o-mini', $output );
		$this->assertStringContainsString( 'gpt-4o', $output );
		$this->assertStringContainsString( 'gpt-5', $output );
		$this->assertStringContainsString( 'Recommended - Fast &amp; Cheap', $output );
		$this->assertStringContainsString( 'Balanced', $output );
		$this->assertStringContainsString( 'Best - Expensive', $output );
		$this->assertStringContainsString( 'class="description"', $output );
		$this->assertStringNotContainsString( 'gpt-3.5-turbo', $output );
		$this->assertStringNotContainsString( 'gpt-4-turbo', $output );
		$this->assertStringNotContainsString( 'o1', $output );
		$this->assertStringNotContainsString( 'o3-mini', $output );
	}

	public function test_render_model_field_selects_current_model(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model' => 'gpt-5',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/gpt-5.*selected="selected"/', $output );
	}

	public function test_render_model_field_defaults_to_gpt4o_mini(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_model_field();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/gpt-4o-mini.*selected="selected"/', $output );
	}

	public function test_render_greeting_field_outputs_textarea(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'greeting_message' => 'Welcome to our store!',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_greeting_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<textarea', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[greeting_message]"', $output );
		$this->assertStringContainsString( 'Welcome to our store!', $output );
	}

	public function test_render_greeting_field_shows_default_when_empty(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_greeting_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Hello! How can I help you today?', $output );
	}

	public function test_render_enabled_field_outputs_checkbox(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'enabled' => true,
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_enabled_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[enabled]"', $output );
		$this->assertStringContainsString( 'checked="checked"', $output );
	}

	public function test_render_enabled_field_unchecked_when_disabled(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'enabled' => false,
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_enabled_field();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'checked="checked"', $output );
	}

	public function test_render_system_prompt_field_outputs_textarea(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'system_prompt' => 'You are a custom assistant.',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_system_prompt_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<textarea', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[system_prompt]"', $output );
		$this->assertStringContainsString( 'You are a custom assistant.', $output );
	}

	public function test_render_system_prompt_field_has_placeholder(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_system_prompt_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'placeholder=', $output );
		$this->assertStringContainsString( 'Leave empty for default prompt', $output );
	}

	public function test_render_system_prompt_field_has_description(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_system_prompt_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p class="description">', $output );
		$this->assertStringContainsString( 'personality and behavior', $output );
	}

	public function test_render_theme_color_field_outputs_color_input(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'theme_color' => '#ff5500',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_theme_color_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="text"', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[theme_color]"', $output );
		$this->assertStringContainsString( 'value="#ff5500"', $output );
		$this->assertStringContainsString( 'wpaic-color-picker', $output );
	}

	public function test_render_theme_color_field_defaults_to_wp_blue(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_theme_color_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'value="#0073aa"', $output );
	}

	public function test_render_theme_color_field_has_description(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_theme_color_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p class="description">', $output );
		$this->assertStringContainsString( 'primary color', $output );
	}

	public function test_settings_persist_after_sanitization(): void {
		$input = array(
			'openai_api_key'   => 'sk-test-persist',
			'model'            => 'gpt-4o',
			'greeting_message' => 'Hello, welcome!',
			'enabled'          => '1',
			'system_prompt'    => 'You are helpful.',
		);

		$sanitized = $this->admin->sanitize_settings( $input );
		update_option( 'wpaic_settings', $sanitized );

		$retrieved = get_option( 'wpaic_settings' );

		$this->assertEquals( 'sk-test-persist', $retrieved['openai_api_key'] );
		$this->assertEquals( 'gpt-4o', $retrieved['model'] );
		$this->assertEquals( 'Hello, welcome!', $retrieved['greeting_message'] );
		$this->assertTrue( $retrieved['enabled'] );
		$this->assertEquals( 'You are helpful.', $retrieved['system_prompt'] );
	}

	public function test_render_settings_page_requires_capability(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', false );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_render_settings_page_outputs_form_when_authorized(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<form', $output );
		$this->assertStringContainsString( 'action="options.php"', $output );
		$this->assertStringContainsString( 'method="post"', $output );
	}

	public function test_render_logs_page_requires_capability(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', false );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	public function test_render_logs_page_outputs_table_when_authorized(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Chat Logs', $output );
		$this->assertStringContainsString( '<table', $output );
		$this->assertStringContainsString( 'wp-list-table', $output );
	}

	public function test_all_settings_fields_have_correct_names(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_api_key_field();
		$api_output = ob_get_clean();

		ob_start();
		$this->admin->render_model_field();
		$model_output = ob_get_clean();

		ob_start();
		$this->admin->render_greeting_field();
		$greeting_output = ob_get_clean();

		ob_start();
		$this->admin->render_enabled_field();
		$enabled_output = ob_get_clean();

		ob_start();
		$this->admin->render_system_prompt_field();
		$prompt_output = ob_get_clean();

		$this->assertStringContainsString( 'wpaic_settings[openai_api_key]', $api_output );
		$this->assertStringContainsString( 'wpaic_settings[model]', $model_output );
		$this->assertStringContainsString( 'wpaic_settings[greeting_message]', $greeting_output );
		$this->assertStringContainsString( 'wpaic_settings[enabled]', $enabled_output );
		$this->assertStringContainsString( 'wpaic_settings[system_prompt]', $prompt_output );
	}

	public function test_sanitize_settings_sanitizes_language(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'language'         => '  es  ',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'es', $sanitized['language'] );
	}

	public function test_sanitize_settings_defaults_language_to_auto(): void {
		$input = array(
			'openai_api_key'   => 'test-key',
			'model'            => 'gpt-4o-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'auto', $sanitized['language'] );
	}

	public function test_render_language_field_outputs_select(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'language' => 'fr',
			)
		);

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_language_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<select', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[language]"', $output );
		$this->assertStringContainsString( 'Auto-detect', $output );
		$this->assertStringContainsString( 'English', $output );
		$this->assertStringContainsString( 'Spanish', $output );
		$this->assertStringContainsString( 'French', $output );
	}

	public function test_render_language_field_defaults_to_auto(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_language_field();
		$output = ob_get_clean();

		$this->assertMatchesRegularExpression( '/value="auto"[^>]*selected/', $output );
	}

	public function test_render_language_field_has_description(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_language_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<p class="description">', $output );
		$this->assertStringContainsString( 'Language for chatbot responses', $output );
	}

	public function test_get_available_models_returns_jan_2026_models(): void {
		$this->admin = new WPAIC_Admin();
		$models      = $this->admin->get_available_models();

		$this->assertCount( 3, $models );
		$this->assertArrayHasKey( 'gpt-4o-mini', $models );
		$this->assertArrayHasKey( 'gpt-4o', $models );
		$this->assertArrayHasKey( 'gpt-5', $models );
		$this->assertArrayNotHasKey( 'gpt-3.5-turbo', $models );
		$this->assertArrayNotHasKey( 'gpt-4-turbo', $models );
		$this->assertArrayNotHasKey( 'o1', $models );
		$this->assertArrayNotHasKey( 'o3-mini', $models );
	}

	public function test_ajax_upload_csv_returns_error_when_no_file_selected(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$_POST = array(
			'source_name'        => 'testdata',
			'source_label'       => 'Test Data',
			'source_description' => 'Test description',
		);

		// Empty file input (no file selected)
		$_FILES = array(
			'csv_file' => array(
				'name'     => '',
				'type'     => '',
				'tmp_name' => '',
				'error'    => UPLOAD_ERR_NO_FILE,
				'size'     => 0,
			),
		);

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_upload_csv();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertArrayHasKey( 'message', $e->data );
			$this->assertStringContainsString( 'select a CSV file', $e->data['message'] );
		}

		// Clean up
		$_POST  = array();
		$_FILES = array();
	}

	public function test_sanitize_settings_api_tab_preserves_general_settings(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'enabled'          => true,
			'greeting_message' => 'Hi there!',
			'language'         => 'es',
			'openai_api_key'   => 'old-key',
			'model'            => 'gpt-4o-mini',
		) );

		$input = array(
			'active_tab'     => 'api',
			'openai_api_key' => 'new-key-123',
			'model'          => 'gpt-4o',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'new-key-123', $sanitized['openai_api_key'] );
		$this->assertEquals( 'gpt-4o', $sanitized['model'] );
		$this->assertTrue( $sanitized['enabled'] );
		$this->assertEquals( 'Hi there!', $sanitized['greeting_message'] );
		$this->assertEquals( 'es', $sanitized['language'] );
	}

	public function test_sanitize_settings_general_tab_preserves_api_settings(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'openai_api_key' => 'sk-existing-key',
			'model'          => 'gpt-4o',
			'enabled'        => false,
		) );

		$input = array(
			'active_tab'       => 'general',
			'enabled'          => '1',
			'greeting_message' => 'Welcome!',
			'language'         => 'fr',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $sanitized['enabled'] );
		$this->assertEquals( 'Welcome!', $sanitized['greeting_message'] );
		$this->assertEquals( 'fr', $sanitized['language'] );
		$this->assertEquals( 'sk-existing-key', $sanitized['openai_api_key'] );
		$this->assertEquals( 'gpt-4o', $sanitized['model'] );
	}

	public function test_sanitize_settings_engagement_tab_preserves_appearance(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'chatbot_name'  => 'TestBot',
			'chatbot_logo'  => 'https://example.com/logo.png',
			'theme_color'   => '#ff0000',
			'system_prompt' => 'Be helpful',
		) );

		$input = array(
			'active_tab'        => 'engagement',
			'handoff_enabled'   => '1',
			'proactive_enabled' => '1',
			'proactive_delay'   => '5',
			'proactive_message' => 'Need help?',
			'proactive_pages'   => 'shop',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $sanitized['handoff_enabled'] );
		$this->assertTrue( $sanitized['proactive_enabled'] );
		$this->assertEquals( 5, $sanitized['proactive_delay'] );
		$this->assertEquals( 'TestBot', $sanitized['chatbot_name'] );
		$this->assertEquals( 'https://example.com/logo.png', $sanitized['chatbot_logo'] );
		$this->assertEquals( '#ff0000', $sanitized['theme_color'] );
		$this->assertEquals( 'Be helpful', $sanitized['system_prompt'] );
	}

	public function test_sanitize_settings_unchecked_checkbox_on_active_tab_clears_it(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'enabled'          => true,
			'greeting_message' => 'Hi',
			'language'         => 'auto',
		) );

		$input = array(
			'active_tab'       => 'general',
			'greeting_message' => 'Hi',
			'language'         => 'auto',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $sanitized['enabled'] );
	}

	public function test_ajax_save_faqs_saves_qa_pairs(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$_POST = array(
			'faq_content' => "Q: What is your return policy?\nA: 30-day returns.\n\nQ: Do you ship internationally?\nA: Yes, to 50+ countries.",
		);

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_save_faqs();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertStringContainsString( '2 FAQ(s) saved', $e->data['message'] );
		}

		global $wpdb;
		$faqs = $wpdb->get_results( "SELECT question, answer FROM wp_wpaic_faqs ORDER BY id ASC" );

		$this->assertCount( 2, $faqs );
		$this->assertEquals( 'What is your return policy?', $faqs[0]->question );
		$this->assertEquals( '30-day returns.', $faqs[0]->answer );
		$this->assertEquals( 'Do you ship internationally?', $faqs[1]->question );
		$this->assertEquals( 'Yes, to 50+ countries.', $faqs[1]->answer );

		$_POST = array();
	}

	public function test_ajax_save_faqs_clears_on_empty_content(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		global $wpdb;
		$wpdb->insert( 'wp_wpaic_faqs', array( 'question' => 'Old Q', 'answer' => 'Old A' ) );

		$_POST = array( 'faq_content' => '' );

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_save_faqs();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertStringContainsString( 'cleared', $e->data['message'] );
		}

		$faqs = $wpdb->get_results( "SELECT * FROM wp_wpaic_faqs" );
		$this->assertCount( 0, $faqs );

		$_POST = array();
	}

	public function test_ajax_save_faqs_replaces_existing(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		global $wpdb;
		$wpdb->insert( 'wp_wpaic_faqs', array( 'question' => 'Old Q', 'answer' => 'Old A' ) );

		$_POST = array(
			'faq_content' => "Q: New question?\nA: New answer.",
		);

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_save_faqs();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertStringContainsString( '1 FAQ(s) saved', $e->data['message'] );
		}

		$faqs = $wpdb->get_results( "SELECT question, answer FROM wp_wpaic_faqs" );
		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'New question?', $faqs[0]->question );

		$_POST = array();
	}

	public function test_ajax_save_faqs_requires_permission(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', false );

		$_POST = array( 'faq_content' => "Q: Test?\nA: Yes." );

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_save_faqs();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertStringContainsString( 'Permission denied', $e->data['message'] );
		}

		$_POST = array();
	}

	public function test_ajax_upload_csv_returns_error_when_files_not_set(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$_POST = array(
			'source_name'        => 'testdata',
			'source_label'       => 'Test Data',
			'source_description' => 'Test description',
		);

		// No $_FILES at all
		$_FILES = array();

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_upload_csv();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertArrayHasKey( 'message', $e->data );
			$this->assertStringContainsString( 'select a CSV file', $e->data['message'] );
		}

		// Clean up
		$_POST  = array();
		$_FILES = array();
	}

	public function test_sanitize_settings_api_tab_includes_provider_fields(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => 'existing-key',
				'enabled'        => true,
			)
		);

		$admin = new WPAIC_Admin();
		$result = $admin->sanitize_settings( array(
			'active_tab'        => 'api',
			'openai_api_key'    => '',
			'model'             => 'gpt-4o-mini',
			'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
			'provider_site_key' => 'my-site-key-123',
		) );

		$this->assertEquals( 'https://provider.example.com/wp-json/wpaip/v1/chat', $result['provider_url'] );
		$this->assertEquals( 'my-site-key-123', $result['provider_site_key'] );
		$this->assertTrue( $result['enabled'] );
	}

	public function test_sanitize_settings_general_tab_preserves_provider_fields(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'my-site-key-123',
				'enabled'           => true,
			)
		);

		$admin = new WPAIC_Admin();
		$result = $admin->sanitize_settings( array(
			'active_tab'       => 'general',
			'enabled'          => true,
			'greeting_message' => 'Hi!',
			'language'         => 'en',
		) );

		$this->assertEquals( 'https://provider.example.com/wp-json/wpaip/v1/chat', $result['provider_url'] );
		$this->assertEquals( 'my-site-key-123', $result['provider_site_key'] );
	}

	public function test_sanitize_settings_provider_url_uses_esc_url_raw(): void {
		$admin = new WPAIC_Admin();
		$result = $admin->sanitize_settings( array(
			'active_tab'        => 'api',
			'openai_api_key'    => '',
			'model'             => 'gpt-4o-mini',
			'provider_url'      => 'https://valid-url.com/wp-json/wpaip/v1/chat',
			'provider_site_key' => 'key',
		) );

		$this->assertEquals( 'https://valid-url.com/wp-json/wpaip/v1/chat', $result['provider_url'] );
	}
}
