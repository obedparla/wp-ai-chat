<?php
/**
 * Tests for WPAIC_Admin class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-logs.php';
require_once __DIR__ . '/../includes/class-wpaic-events.php';
require_once __DIR__ . '/../includes/class-wpaic-transcript-renderer.php';
require_once __DIR__ . '/../includes/class-wpaic-tools.php';
require_once __DIR__ . '/../includes/class-wpaic-search-index.php';
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-system-prompt.php';
require_once __DIR__ . '/../includes/class-wpaic-admin.php';

class WPAIC_AdminTest extends TestCase {
	private WPAIC_Admin $admin;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		$this->cleanup_search_index_files();
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		$this->admin = new WPAIC_Admin();
	}

	protected function tearDown(): void {
		$this->cleanup_search_index_files();
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	private function cleanup_search_index_files(): void {
		$upload_dir = wp_upload_dir();
		$search_dir = $upload_dir['basedir'] . '/wpaic/search';
		if ( ! is_dir( $search_dir ) ) {
			return;
		}

		$files = glob( $search_dir . '/*' );
		if ( false !== $files ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}

		@rmdir( $search_dir );
		@rmdir( dirname( $search_dir ) );
	}

	private function create_license_manager_stub( array $overrides = array() ): WPAIC_License_Manager {
		$defaults = array(
			'provider_url'                  => 'https://provider.example.com/wp-json/wpaip/v1/chat',
			'provider_url_configured'       => true,
			'license_status_label'          => 'License required',
			'has_valid_chat_license'        => false,
			'can_render_chat'               => false,
			'activation_url'                => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot',
			'account_url'                   => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot-account',
			'pricing_url'                   => 'https://example.com/wp-admin/admin.php?page=wp-ai-chatbot-pricing',
			'provider_url_override_allowed' => false,
		);

		return new class( array_merge( $defaults, $overrides ) ) extends WPAIC_License_Manager {
			/** @var array<string, mixed> */
			private array $config;

			/**
			 * @param array<string, mixed> $config
			 */
			public function __construct( array $config ) {
				$this->config = $config;
			}

			public function get_provider_url(): string {
				return (string) $this->config['provider_url'];
			}

			public function is_provider_url_configured(): bool {
				return (bool) $this->config['provider_url_configured'];
			}

			public function get_license_status_label(): string {
				return (string) $this->config['license_status_label'];
			}

			public function has_valid_chat_license(): bool {
				return (bool) $this->config['has_valid_chat_license'];
			}

			public function can_render_chat(): bool {
				return (bool) $this->config['can_render_chat'];
			}

			public function get_activation_url(): string {
				return (string) $this->config['activation_url'];
			}

			public function get_account_url(): string {
				return (string) $this->config['account_url'];
			}

			public function get_pricing_url(): string {
				return (string) $this->config['pricing_url'];
			}

			public function is_provider_url_override_allowed(): bool {
				return (bool) $this->config['provider_url_override_allowed'];
			}
		};
	}

	public function test_sanitize_settings_drops_openai_api_key(): void {
		$input = array(
			'openai_api_key'   => 'sk-test-key-12345',
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertArrayNotHasKey( 'openai_api_key', $sanitized );
	}

	public function test_sanitize_settings_forces_model_ignoring_input(): void {
		$input = array(
			'model'            => 'gpt-5',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'gpt-5-mini', $sanitized['model'] );
	}

	public function test_sanitize_settings_sets_model_when_missing(): void {
		$input = array(
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'gpt-5-mini', $sanitized['model'] );
	}

	public function test_sanitize_settings_sanitizes_greeting_message(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => "  Hello!\nHow can I help?  ",
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( "Hello!\nHow can I help?", $sanitized['greeting_message'] );
	}

	public function test_sanitize_settings_enabled_is_true_when_set(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $sanitized['enabled'] );
	}

	public function test_sanitize_settings_enabled_is_false_when_not_set(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $sanitized['enabled'] );
	}

	public function test_sanitize_settings_enabled_is_false_when_empty(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '',
			'system_prompt'    => '',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $sanitized['enabled'] );
	}

	public function test_sanitize_settings_general_tab_saves_retention_and_anonymize_ip(): void {
		$input = array(
			'active_tab'     => 'general',
			'enabled'        => '1',
			'retention_days' => '30',
			'anonymize_ip'   => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 30, $sanitized['retention_days'] );
		$this->assertTrue( $sanitized['anonymize_ip'] );
	}

	public function test_sanitize_settings_general_tab_disables_anonymize_ip(): void {
		$input = array(
			'active_tab'     => 'general',
			'enabled'        => '1',
			'retention_days' => '0',
			'anonymize_ip'   => '0',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 0, $sanitized['retention_days'] );
		$this->assertFalse( $sanitized['anonymize_ip'] );
	}

	public function test_sanitize_settings_clamps_negative_retention_days(): void {
		$input = array(
			'active_tab'     => 'general',
			'enabled'        => '1',
			'retention_days' => '-7',
			'anonymize_ip'   => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 0, $sanitized['retention_days'] );
	}

	public function test_sanitize_settings_defaults_anonymize_ip_on_when_never_saved(): void {
		// Save from a different tab: privacy fields come from (empty) existing settings.
		$input = array(
			'active_tab'   => 'appearance',
			'chatbot_name' => 'Bot',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $sanitized['anonymize_ip'] );
		$this->assertSame( 0, $sanitized['retention_days'] );
	}

	public function test_sanitize_settings_preserves_privacy_settings_on_other_tab_saves(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'retention_days' => 90,
				'anonymize_ip'   => false,
			)
		);

		$input = array(
			'active_tab'   => 'appearance',
			'chatbot_name' => 'Bot',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertSame( 90, $sanitized['retention_days'] );
		$this->assertFalse( $sanitized['anonymize_ip'] );
	}

	public function test_sanitize_settings_sanitizes_system_prompt(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'system_prompt'    => "  You are a helpful bot.\nBe nice.  ",
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( "You are a helpful bot.\nBe nice.", $sanitized['system_prompt'] );
	}

	public function test_sanitize_settings_sanitizes_theme_color(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'theme_color'      => '#ff5500',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '#ff5500', $sanitized['theme_color'] );
	}

	public function test_sanitize_settings_defaults_theme_color_when_invalid(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'theme_color'      => 'not-a-color',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '#2545B8', $sanitized['theme_color'] );
	}

	public function test_sanitize_settings_defaults_theme_color_when_missing(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( '#2545B8', $sanitized['theme_color'] );
	}

	public function test_settings_persist_after_sanitization(): void {
		$input = array(
			'model'            => 'gpt-5',
			'greeting_message' => 'Hello, welcome!',
			'enabled'          => '1',
			'system_prompt'    => 'You are helpful.',
		);

		$sanitized = $this->admin->sanitize_settings( $input );
		update_option( 'wpaic_settings', $sanitized );

		$retrieved = get_option( 'wpaic_settings' );

		$this->assertEquals( 'gpt-5-mini', $retrieved['model'] );
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

	public function test_render_settings_page_license_tab_includes_activation_button(): void {
		$_GET['tab'] = 'api';
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$this->admin = new WPAIC_Admin(
			$this->create_license_manager_stub()
		);

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Activate License', $output );
		$this->assertStringContainsString( 'page=wp-ai-chatbot', $output );
		$this->assertStringNotContainsString( 'Manage Billing', $output );
		$this->assertStringContainsString( 'See Plans', $output );
		unset( $_GET['tab'] );
	}

	public function test_render_settings_page_license_tab_shows_manage_billing_when_licensed(): void {
		$_GET['tab'] = 'api';
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$this->admin = new WPAIC_Admin(
			$this->create_license_manager_stub(
				array(
					'has_valid_chat_license' => true,
					'license_status_label'   => 'Active license',
				)
			)
		);

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Manage Billing', $output );
		$this->assertStringContainsString( 'See Plans', $output );
		$this->assertStringNotContainsString( 'Activate License', $output );
		unset( $_GET['tab'] );
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
		$this->assertStringContainsString( 'wpaic-card', $output );
	}

	public function test_render_logs_page_hides_id_user_session_columns(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( '>ID<', $output );
		$this->assertStringNotContainsString( '>Session<', $output );
		$this->assertStringNotContainsString( '>User<', $output );
		$this->assertStringContainsString( 'Chat Logs', $output );
		$this->assertStringContainsString( 'No conversations found', $output );
	}

	public function test_render_logs_page_shows_insights_summary_cards(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$logs            = new WPAIC_Logs();
		$conversation_id = $logs->create_conversation( 'insights-session' );
		WPAIC_Events::record( $conversation_id, WPAIC_Events::PRODUCT_ADDED_TO_CART, array( 'id' => 5, 'name' => 'Mug' ) );
		WPAIC_Events::record( $conversation_id, WPAIC_Events::CHECKOUT_STARTED );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Conversations', $output );
		$this->assertStringContainsString( 'Added to cart', $output );
		$this->assertStringContainsString( 'Checkouts started', $output );
		$this->assertStringContainsString( 'Handoffs', $output );
		$this->assertStringContainsString( 'vs previous week', $output );
	}

	public function test_render_logs_page_outputs_filter_controls(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'name="s"', $output );
		$this->assertStringContainsString( 'name="date_from"', $output );
		$this->assertStringContainsString( 'name="date_to"', $output );
	}

	public function test_render_logs_page_shows_first_user_message_preview_instead_of_total_text(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$logs            = new WPAIC_Logs();
		$conversation_id = $logs->create_conversation( 'preview-session' );
		$logs->log_message( $conversation_id, 'user', str_repeat( 'a', 90 ) );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Total Text', $output );
		$this->assertStringContainsString( 'First message', $output );
		$this->assertStringContainsString( str_repeat( 'a', 80 ) . '…', $output );
		$this->assertStringNotContainsString( str_repeat( 'a', 81 ), $output );
	}

	public function test_render_logs_page_missed_searches_card_links_to_add_faq(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		WPAIC_Events::record( 1, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'vegan leather bag', 'result_count' => 0 ) );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Missed searches', $output );
		$this->assertStringContainsString( 'vegan leather bag', $output );
		$this->assertStringContainsString( 'Add as FAQ', $output );
		$this->assertStringContainsString( 'wpaic_faq_question', $output );
	}

	public function test_render_logs_page_hides_missed_searches_card_without_zero_result_searches(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		WPAIC_Events::record( 1, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'mug', 'result_count' => 3 ) );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_logs_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Missed searches', $output );
	}

	public function test_knowledge_tab_prefills_faq_question_from_query_param(): void {
		global $wpdb;
		$wpdb = new MockWpdb();

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$_GET['tab']                = 'knowledge';
		$_GET['wpaic_faq_question'] = 'vegan leather bag';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( "Q: vegan leather bag\nA: ", $output );

		unset( $_GET['tab'], $_GET['wpaic_faq_question'] );
	}

	public function test_sanitize_settings_sanitizes_language(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'language'         => '  es  ',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'es', $sanitized['language'] );
	}

	public function test_sanitize_settings_defaults_language_to_auto(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'auto', $sanitized['language'] );
	}

	public function test_sanitize_settings_sanitizes_tone_of_voice(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'tone_of_voice'    => '  professional  ',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'professional', $sanitized['tone_of_voice'] );
	}

	public function test_sanitize_settings_defaults_tone_of_voice_to_neutral(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'neutral', $sanitized['tone_of_voice'] );
	}

	public function test_sanitize_settings_rejects_invalid_tone_of_voice(): void {
		$input = array(
			'model'            => 'gpt-5-mini',
			'greeting_message' => 'Hello',
			'enabled'          => '1',
			'tone_of_voice'    => 'luxury',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'neutral', $sanitized['tone_of_voice'] );
	}

	public function test_render_settings_page_general_tab_includes_tone_of_voice_field(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'tone_of_voice' => 'professional',
			)
		);
		$_GET['tab'] = 'general';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Tone of voice', $output );
		$this->assertStringContainsString( 'name="wpaic_settings[tone_of_voice]"', $output );
		$this->assertStringContainsString( 'Neutral', $output );
		$this->assertStringContainsString( 'Friendly', $output );
		$this->assertStringContainsString( 'Professional', $output );
		$this->assertStringContainsString( 'Enthusiastic', $output );
		$this->assertMatchesRegularExpression( '/value="professional"[^>]*checked/', $output );
		$this->assertStringContainsString( 'overrides tone preset', $output );

		unset( $_GET['tab'] );
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
			'model'            => 'gpt-5-mini',
		) );

		$input = array(
			'active_tab'            => 'api',
			'provider_url_override' => 'https://override.example.com/wp-json/wpaip/v1/chat',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( 'https://override.example.com/wp-json/wpaip/v1/chat', $sanitized['provider_url_override'] );
		$this->assertEquals( 'gpt-5-mini', $sanitized['model'] );
		$this->assertTrue( $sanitized['enabled'] );
		$this->assertEquals( 'Hi there!', $sanitized['greeting_message'] );
		$this->assertEquals( 'es', $sanitized['language'] );
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

	public function test_sanitize_settings_promotions_enabled_defaults_to_false(): void {
		$sanitized = $this->admin->sanitize_settings( array() );

		$this->assertFalse( $sanitized['promotions_enabled'] );
	}

	public function test_sanitize_settings_promotions_enabled_saved_from_engagement_tab(): void {
		$input = array(
			'active_tab'         => 'engagement',
			'promotions_enabled' => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertTrue( $sanitized['promotions_enabled'] );
	}

	public function test_sanitize_settings_engagement_tab_clears_unchecked_promotions_enabled(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'promotions_enabled' => true ) );

		$input = array(
			'active_tab' => 'engagement',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertFalse( $sanitized['promotions_enabled'] );
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

	public function test_sanitize_settings_defaults_product_index_enabled_to_true(): void {
		$sanitized = $this->admin->sanitize_settings( array() );

		$this->assertTrue( $sanitized['product_index_enabled'] );
		$this->assertEquals( array( 'page', 'post' ), $sanitized['content_index_post_types'] );
	}

	public function test_sanitize_settings_search_tab_allows_empty_content_selection_and_disabled_products(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'product_index_enabled'    => true,
			'content_index_post_types' => array( 'page', 'post' ),
		) );

		$sanitized = $this->admin->sanitize_settings(
			array(
				'active_tab' => 'search',
			)
		);

		$this->assertFalse( $sanitized['product_index_enabled'] );
		$this->assertSame( array(), $sanitized['content_index_post_types'] );
	}

	public function test_knowledge_tab_renders_indexed_content_controls(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'product_index_enabled'    => true,
				'content_index_post_types' => array( 'page', 'post' ),
			)
		);
		$_GET['tab'] = 'knowledge';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Indexed site content', $output );
		$this->assertStringContainsString( 'Products', $output );
		$this->assertStringContainsString( 'wpaic-update-search-indexes', $output );

		unset( $_GET['tab'] );
	}

	public function test_ajax_update_search_indexes_clears_disabled_indexes_and_persists_settings(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'product_index_enabled'    => true,
				'content_index_post_types' => array( 'page', 'post' ),
			)
		);
		WPAICTestHelper::set_option(
			'wpaic_search_index_meta',
			array(
				'product_count' => 4,
				'last_updated'  => '2026-03-20 10:00:00',
			)
		);
		WPAICTestHelper::set_option(
			'wpaic_content_index_meta',
			array(
				'post_count'   => 7,
				'last_updated' => '2026-03-20 10:00:00',
				'post_types'   => array( 'page', 'post' ),
			)
		);

		$upload_dir = wp_upload_dir();
		$search_dir = $upload_dir['basedir'] . '/wpaic/search';
		wp_mkdir_p( $search_dir );
		file_put_contents( $search_dir . '/products.index', 'products' );
		file_put_contents( $search_dir . '/content.index', 'content' );

			$_POST = array(
				'_wpnonce'              => 'test_nonce_wpaic_update_search_indexes',
				'product_index_enabled' => '',
				'content_index_post_types' => array(),
			);

		try {
			$this->admin->ajax_update_search_indexes();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertSame( 'Search indexes updated successfully.', $e->data['message'] );
			$this->assertFalse( $e->data['product']['enabled'] );
			$this->assertFalse( $e->data['product']['exists'] );
			$this->assertFalse( $e->data['content']['enabled'] );
			$this->assertFalse( $e->data['content']['exists'] );
			$this->assertSame( array(), $e->data['content']['post_types'] );
		}

		$settings = get_option( 'wpaic_settings', array() );
		$this->assertFalse( $settings['product_index_enabled'] );
		$this->assertSame( array(), $settings['content_index_post_types'] );
		$this->assertFileDoesNotExist( $search_dir . '/products.index' );
		$this->assertFileDoesNotExist( $search_dir . '/content.index' );

		$search_meta = get_option( 'wpaic_search_index_meta', array() );
		$this->assertSame( 0, $search_meta['product_count'] );
		$this->assertNull( $search_meta['last_updated'] );

		$content_meta = get_option( 'wpaic_content_index_meta', array() );
		$this->assertSame( 0, $content_meta['post_count'] );
		$this->assertNull( $content_meta['last_updated'] );
		$this->assertSame( array(), $content_meta['post_types'] );
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

	public function test_ajax_save_faqs_preserves_existing_faqs_on_malformed_content(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		global $wpdb;
		$wpdb->insert( 'wp_wpaic_faqs', array( 'question' => 'Old Q', 'answer' => 'Old A' ) );

		$_POST = array( 'faq_content' => 'This paste has no question or answer markers at all.' );

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_save_faqs();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertStringContainsString( 'left unchanged', $e->data['message'] );
		}

		$faqs = $wpdb->get_results( "SELECT question, answer FROM wp_wpaic_faqs" );
		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'Old Q', $faqs[0]->question );
		$this->assertEquals( 'Old A', $faqs[0]->answer );

		$_POST = array();
	}

	public function test_ajax_save_faqs_reports_skipped_entries(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$_POST = array(
			'faq_content' => "Q: Valid question?\nA: Valid answer.\n\nQ: Question separated from its answer by a blank line\n\nA: Orphaned answer.",
		);

		$this->admin = new WPAIC_Admin();

		try {
			$this->admin->ajax_save_faqs();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertStringContainsString( '1 FAQ(s) saved', $e->data['message'] );
			$this->assertStringContainsString( 'could not be parsed', $e->data['message'] );
			$this->assertStringContainsString( 'Orphaned answer', $e->data['message'] );
		}

		global $wpdb;
		$faqs = $wpdb->get_results( "SELECT question, answer FROM wp_wpaic_faqs" );
		$this->assertCount( 1, $faqs );
		$this->assertEquals( 'Valid question?', $faqs[0]->question );

		$_POST = array();
	}

	public function test_parse_faq_content_parses_valid_pairs(): void {
		$parsed = WPAIC_Admin::parse_faq_content( "Q: First?\nA: One.\n\nQ: Second?\nA: Two\nspanning lines." );

		$this->assertSame( array(), $parsed['failed'] );
		$this->assertCount( 2, $parsed['pairs'] );
		$this->assertSame( 'First?', $parsed['pairs'][0]['question'] );
		$this->assertSame( 'One.', $parsed['pairs'][0]['answer'] );
		$this->assertSame( 'Second?', $parsed['pairs'][1]['question'] );
		$this->assertSame( "Two\nspanning lines.", $parsed['pairs'][1]['answer'] );
	}

	public function test_parse_faq_content_reports_blank_line_separated_answer(): void {
		$parsed = WPAIC_Admin::parse_faq_content( "Q: Where is my order?\n\nA: Check your email." );

		$this->assertCount( 0, $parsed['pairs'] );
		$this->assertCount( 2, $parsed['failed'] );
		$this->assertSame( 'Q: Where is my order?', $parsed['failed'][0] );
		$this->assertSame( 'A: Check your email.', $parsed['failed'][1] );
	}

	public function test_parse_faq_content_truncates_long_failed_previews(): void {
		$long_line = 'X' . str_repeat( 'y', 100 );
		$parsed    = WPAIC_Admin::parse_faq_content( $long_line );

		$this->assertCount( 0, $parsed['pairs'] );
		$this->assertCount( 1, $parsed['failed'] );
		$this->assertSame( 60, mb_strlen( $parsed['failed'][0] ) );
		$this->assertStringEndsWith( '...', $parsed['failed'][0] );
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

	public function test_sanitize_settings_api_tab_includes_provider_override_field(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'enabled' => true,
			)
		);

		$admin = new WPAIC_Admin();
		$result = $admin->sanitize_settings( array(
			'active_tab'            => 'api',
			'model'                 => 'gpt-5-mini',
			'provider_url_override' => 'https://provider.example.com/wp-json/wpaip/v1/chat',
		) );

		$this->assertEquals( 'https://provider.example.com/wp-json/wpaip/v1/chat', $result['provider_url_override'] );
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
			'tone_of_voice'    => 'enthusiastic',
		) );

		$this->assertEquals( 'https://provider.example.com/wp-json/wpaip/v1/chat', $result['provider_url'] );
		$this->assertEquals( 'my-site-key-123', $result['provider_site_key'] );
		$this->assertEquals( 'enthusiastic', $result['tone_of_voice'] );
	}

	public function test_sanitize_settings_provider_override_uses_esc_url_raw(): void {
		$admin = new WPAIC_Admin();
		$result = $admin->sanitize_settings( array(
			'active_tab'            => 'api',
			'model'                 => 'gpt-5-mini',
			'provider_url_override' => 'https://valid-url.com/wp-json/wpaip/v1/chat',
		) );

		$this->assertEquals( 'https://valid-url.com/wp-json/wpaip/v1/chat', $result['provider_url_override'] );
	}

	public function test_appearance_tab_renders_media_uploader_for_logo(): void {
		$_GET['tab'] = 'appearance';
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'chatbot_logo' => 'https://example.com/logo.png',
		) );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wpaic_logo_upload"', $output );
		$this->assertStringContainsString( 'id="wpaic_logo_remove"', $output );
		$this->assertStringContainsString( 'id="wpaic_logo_preview"', $output );
		$this->assertStringContainsString( 'type="hidden" id="wpaic_chatbot_logo"', $output );
		$this->assertStringContainsString( 'https://example.com/logo.png', $output );
		unset( $_GET['tab'] );
	}

	public function test_appearance_tab_hides_preview_when_no_logo(): void {
		$_GET['tab'] = 'appearance';
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'chatbot_logo' => '',
		) );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wpaic_logo_upload"', $output );
		$this->assertMatchesRegularExpression( '/id="wpaic_logo_remove"[^>]*style="display:none"/', $output );
		$this->assertMatchesRegularExpression( '/id="wpaic_logo_preview"[^>]*style="display:none;?"/', $output );
		unset( $_GET['tab'] );
	}

	public function test_logo_preview_shows_image_when_logo_set(): void {
		$_GET['tab'] = 'appearance';
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'chatbot_logo' => 'https://example.com/big-logo.png',
		) );

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wpaic_logo_preview"', $output );
		$this->assertStringContainsString( 'https://example.com/big-logo.png', $output );
		unset( $_GET['tab'] );
	}

public function test_sanitize_settings_handoff_fields_filters_invalid_values(): void {
		$input = array(
			'active_tab'      => 'engagement',
			'handoff_enabled' => '1',
			'handoff_fields'  => array( 'phone_number', 'invalid_field', 'company', 'xss_attempt' ),
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( array( 'phone_number', 'company' ), $sanitized['handoff_fields'] );
	}

	public function test_sanitize_settings_handoff_fields_defaults_to_empty(): void {
		$input = array(
			'active_tab'      => 'engagement',
			'handoff_enabled' => '1',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( array(), $sanitized['handoff_fields'] );
	}

	public function test_sanitize_settings_handoff_fields_accepts_all_valid_fields(): void {
		$input = array(
			'active_tab'      => 'engagement',
			'handoff_enabled' => '1',
			'handoff_fields'  => array( 'phone_number', 'company', 'order_number', 'request_message' ),
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertCount( 4, $sanitized['handoff_fields'] );
		$this->assertContains( 'phone_number', $sanitized['handoff_fields'] );
		$this->assertContains( 'company', $sanitized['handoff_fields'] );
		$this->assertContains( 'order_number', $sanitized['handoff_fields'] );
		$this->assertContains( 'request_message', $sanitized['handoff_fields'] );
	}

	public function test_engagement_tab_renders_handoff_field_chips(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'handoff_enabled' => true,
			'handoff_fields'  => array( 'phone_number' ),
		) );
		$_GET['tab'] = 'engagement';

		$this->admin = new WPAIC_Admin();

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collected fields', $output );
		$this->assertStringContainsString( 'Phone Number', $output );
		$this->assertStringContainsString( 'Company', $output );
		$this->assertStringContainsString( 'Order Number', $output );
		$this->assertStringContainsString( 'Request Message', $output );
		$this->assertStringContainsString( 'wpaic-handoff-field-checkbox', $output );
		unset( $_GET['tab'] );
	}

	public function test_sanitize_settings_handoff_fields_preserved_across_tabs(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'handoff_enabled' => true,
			'handoff_fields'  => array( 'phone_number', 'company' ),
		) );

		$input = array(
			'active_tab'     => 'general',
			'enabled'        => '1',
			'greeting_message' => 'Hi',
			'language'       => 'auto',
		);

		$sanitized = $this->admin->sanitize_settings( $input );

		$this->assertEquals( array( 'phone_number', 'company' ), $sanitized['handoff_fields'] );
		$this->assertTrue( $sanitized['handoff_enabled'] );
	}

	public function test_sanitize_settings_conversation_starters_trims_deduplicates_and_caps_at_five(): void {
		$sanitized = $this->admin->sanitize_settings(
			array(
				'active_tab'             => 'engagement',
				'conversation_starters'  => array(
					'  Find a product  ',
					'<b>Track my order</b>',
					'Find a product',
					'',
					'Shipping info',
					'Need support',
					'Compare items',
					'Extra starter',
				),
			)
		);

		$this->assertSame(
			array(
				'Find a product',
				'Track my order',
				'Shipping info',
				'Need support',
				'Compare items',
			),
			$sanitized['conversation_starters']
		);
	}

	public function test_sanitize_settings_conversation_starters_preserved_across_other_tabs(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'conversation_starters' => array( 'Find a product', 'Track my order' ),
			)
		);

		$sanitized = $this->admin->sanitize_settings(
			array(
				'active_tab'       => 'general',
				'enabled'          => '1',
				'greeting_message' => 'Hi',
				'language'         => 'auto',
			)
		);

		$this->assertSame( array( 'Find a product', 'Track my order' ), $sanitized['conversation_starters'] );
	}

	public function test_engagement_tab_renders_conversation_starter_inputs(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'conversation_starters' => array( 'Find a product', 'Track my order' ),
			)
		);
		$_GET['tab'] = 'engagement';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Conversation starters', $output );
		$this->assertStringContainsString( 'wpaic_settings[conversation_starters][]', $output );
		$this->assertStringContainsString( 'Find a product', $output );
		$this->assertStringContainsString( 'Track my order', $output );
		unset( $_GET['tab'] );
	}

	// --- Transcript items (messages + event chips) tests (P1-14) ---

	private function reset_mock_wpdb(): void {
		global $wpdb;
		if ( ! $wpdb instanceof MockWpdb ) {
			$wpdb = new MockWpdb();
		}
		$wpdb->reset();
	}

	public function test_ajax_get_conversation_merges_messages_and_event_chips(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$logs            = new WPAIC_Logs();
		$conversation_id = $logs->create_conversation( 'merged-session' );
		$logs->log_message( $conversation_id, 'user', 'Show me mugs' );
		WPAIC_Events::record(
			$conversation_id,
			WPAIC_Events::PRODUCTS_SHOWN,
			array(
				'ids'   => array( 1, 2 ),
				'names' => array( 'Mug A', 'Mug B' ),
			)
		);
		$logs->log_message( $conversation_id, 'assistant', 'Here are some mugs.' );

		$_POST['conversation_id'] = (string) $conversation_id;

		try {
			$this->admin->ajax_get_conversation();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
			$items = $response->data;

			$this->assertCount( 3, $items );
			$this->assertEquals( 'message', $items[0]['type'] );
			$this->assertEquals( 'user', $items[0]['role'] );
			$this->assertEquals( 'event', $items[1]['type'] );
			$this->assertStringContainsString( 'Mug A, Mug B', $items[1]['label'] );
			$this->assertEquals( 'message', $items[2]['type'] );
			$this->assertEquals( 'assistant', $items[2]['role'] );
		} finally {
			unset( $_POST['conversation_id'] );
		}
	}

	public function test_ajax_get_support_transcript_includes_linked_conversation(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$logs            = new WPAIC_Logs();
		$conversation_id = $logs->create_conversation( 'handoff-session' );
		$logs->log_message( $conversation_id, 'user', 'I need a human' );
		$logs->log_message( $conversation_id, 'assistant', 'Connecting you now.' );

		$tools  = new WPAIC_Tools();
		$result = $tools->create_handoff_request(
			array(
				'customer_name'        => 'Jane Doe',
				'customer_email'       => 'jane@example.com',
				'conversation_summary' => 'Customer needs help',
				'conversation_id'      => $conversation_id,
			)
		);

		$_POST['request_id'] = (string) $result['request_id'];

		try {
			$this->admin->ajax_get_support_transcript();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
			$this->assertArrayHasKey( 'conversation', $response->data );
			$conversation = $response->data['conversation'];

			$this->assertCount( 2, $conversation );
			$this->assertEquals( 'I need a human', $conversation[0]['content'] );
			$this->assertEquals( 'Connecting you now.', $conversation[1]['content'] );
		} finally {
			unset( $_POST['request_id'] );
		}
	}

	public function test_ajax_get_support_transcript_omits_conversation_when_not_linked(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$tools  = new WPAIC_Tools();
		$result = $tools->create_handoff_request(
			array(
				'customer_name'        => 'Jane Doe',
				'customer_email'       => 'jane@example.com',
				'conversation_summary' => 'User: hi',
			)
		);

		$_POST['request_id'] = (string) $result['request_id'];

		try {
			$this->admin->ajax_get_support_transcript();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
			$this->assertArrayNotHasKey( 'conversation', $response->data );
			$this->assertEquals( 'User: hi', $response->data['transcript'] );
		} finally {
			unset( $_POST['request_id'] );
		}
	}

	// ---- Header status pill (P1-22b) ----

	public function test_header_pill_shows_live_when_enabled_and_chat_can_render(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub( array( 'can_render_chat' => true ) ) );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Live on site', $output );
		$this->assertStringNotContainsString( 'Hidden —', $output );
	}

	public function test_header_pill_shows_hidden_reason_when_enabled_but_license_gate_blocks(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );

		$this->admin = new WPAIC_Admin(
			$this->create_license_manager_stub(
				array(
					'can_render_chat'        => false,
					'has_valid_chat_license' => false,
				)
			)
		);

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Hidden — license required', $output );
		$this->assertStringNotContainsString( 'Live on site', $output );
	}

	public function test_header_pill_shows_paused_when_disabled(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => false ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub( array( 'can_render_chat' => true ) ) );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Paused', $output );
		$this->assertStringNotContainsString( 'Live on site', $output );
	}

	// ---- Onboarding checklist (P1-22a) ----

	public function test_general_tab_renders_onboarding_checklist(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub() );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wpaic-onboarding"', $output );
		$this->assertStringContainsString( 'Start your trial or activate your license', $output );
		$this->assertStringContainsString( 'Name and brand your chatbot', $output );
		$this->assertStringContainsString( 'Add knowledge', $output );
		$this->assertStringContainsString( 'Open your store and try it', $output );
		$this->assertStringContainsString( 'id="wpaic-onboarding-dismiss"', $output );
	}

	public function test_onboarding_checklist_hidden_when_dismissed(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );
		WPAICTestHelper::set_option( 'wpaic_onboarding', array( 'dismissed' => true ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub() );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'id="wpaic-onboarding"', $output );
	}

	public function test_onboarding_license_step_done_when_chat_can_render(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub( array( 'can_render_chat' => true ) ) );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-onboarding-step="1" data-onboarding-done="1"', $output );
	}

	public function test_onboarding_license_step_pending_when_chat_cannot_render(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub( array( 'can_render_chat' => false ) ) );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-onboarding-step="1" data-onboarding-done="0"', $output );
	}

	public function test_onboarding_brand_step_done_when_chatbot_named(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'enabled'      => true,
				'chatbot_name' => 'Astra',
			)
		);

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub() );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-onboarding-step="2" data-onboarding-done="1"', $output );
	}

	public function test_onboarding_knowledge_step_done_when_faqs_exist(): void {
		$this->reset_mock_wpdb();
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'What is your return policy?',
				'answer'   => '30 days.',
			)
		);

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub() );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-onboarding-step="3" data-onboarding-done="1"', $output );
	}

	public function test_onboarding_try_it_step_done_when_persisted(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'enabled' => true ) );
		WPAICTestHelper::set_option( 'wpaic_onboarding', array( 'steps' => array( 'try_it' ) ) );

		$this->admin = new WPAIC_Admin( $this->create_license_manager_stub() );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'data-onboarding-step="4" data-onboarding-done="1"', $output );
	}

	public function test_ajax_update_onboarding_persists_dismissal(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$_POST['dismissed'] = '1';

		try {
			$this->admin->ajax_update_onboarding();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
		} finally {
			unset( $_POST['dismissed'] );
		}

		$onboarding = get_option( 'wpaic_onboarding' );
		$this->assertTrue( $onboarding['dismissed'] );
	}

	public function test_ajax_update_onboarding_persists_try_it_step(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$_POST['step'] = 'try_it';

		try {
			$this->admin->ajax_update_onboarding();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
		} finally {
			unset( $_POST['step'] );
		}

		$onboarding = get_option( 'wpaic_onboarding' );
		$this->assertContains( 'try_it', $onboarding['steps'] );
		$this->assertArrayNotHasKey( 'dismissed', $onboarding );
	}

	public function test_ajax_update_onboarding_ignores_unknown_step(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$_POST['step'] = 'bogus_step';

		try {
			$this->admin->ajax_update_onboarding();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
		} finally {
			unset( $_POST['step'] );
		}

		$onboarding = get_option( 'wpaic_onboarding' );
		$this->assertArrayNotHasKey( 'steps', $onboarding );
	}

	public function test_ajax_update_onboarding_requires_permission(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', false );
		$_POST['dismissed'] = '1';

		try {
			$this->admin->ajax_update_onboarding();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertFalse( $response->success );
		} finally {
			unset( $_POST['dismissed'] );
		}

		$this->assertFalse( get_option( 'wpaic_onboarding', false ) );
	}

	// ---- Activation redirect (P1-22a) ----

	public function test_maybe_redirect_after_activation_redirects_and_clears_transient(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		set_transient( 'wpaic_activation_redirect', true, 60 );

		try {
			$this->admin->maybe_redirect_after_activation();
			$this->fail( 'Expected WPAICRedirectException' );
		} catch ( WPAICRedirectException $redirect ) {
			$this->assertStringContainsString( 'page=wp-ai-chatbot', $redirect->location );
		}

		$this->assertFalse( get_transient( 'wpaic_activation_redirect' ) );
	}

	public function test_maybe_redirect_after_activation_noop_without_transient(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$this->admin->maybe_redirect_after_activation();

		$this->assertFalse( get_transient( 'wpaic_activation_redirect' ) );
	}

	public function test_maybe_redirect_after_activation_skips_bulk_activation(): void {
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		set_transient( 'wpaic_activation_redirect', true, 60 );
		$_GET['activate-multi'] = '1';

		try {
			$this->admin->maybe_redirect_after_activation();
		} finally {
			unset( $_GET['activate-multi'] );
		}

		// Transient consumed but no redirect thrown.
		$this->assertFalse( get_transient( 'wpaic_activation_redirect' ) );
	}

	// ---- FAQ prompt-cap note on Knowledge tab (P0-6 leftover) ----

	public function test_knowledge_tab_warns_when_faq_pairs_exceed_prompt_cap(): void {
		$this->reset_mock_wpdb();
		global $wpdb;
		for ( $i = 1; $i <= 31; $i++ ) {
			$wpdb->insert(
				'wp_wpaic_faqs',
				array(
					'question' => "Question {$i}?",
					'answer'   => "Answer {$i}.",
				)
			);
		}

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$_GET['tab'] = 'knowledge';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Using 30 of 31', $output );
		$this->assertStringContainsString( 'only the first 30 FAQ pairs are included in answers', $output );
		$this->assertStringContainsString( '31 pairs', $output );

		unset( $_GET['tab'] );
	}

	public function test_knowledge_tab_shows_count_without_warning_at_or_below_cap(): void {
		$this->reset_mock_wpdb();
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'Do you ship internationally?',
				'answer'   => 'Yes.',
			)
		);

		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		$_GET['tab'] = 'knowledge';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( '1 pair', $output );
		$this->assertStringNotContainsString( 'only the first 30 FAQ pairs', $output );

		unset( $_GET['tab'] );
	}

	// ---- Markdown-lite transcript rendering (P2-27d) ----

	public function test_render_markdown_lite_renders_bold_italic_code_and_links(): void {
		$html = WPAIC_Transcript_Renderer::render_markdown_lite( 'Try **bold**, *italic*, `code`, and [our shop](https://example.com/shop).' );

		$this->assertStringContainsString( '<strong>bold</strong>', $html );
		$this->assertStringContainsString( '<em>italic</em>', $html );
		$this->assertStringContainsString( '<code>code</code>', $html );
		$this->assertStringContainsString( '<a href="https://example.com/shop" target="_blank" rel="noopener">our shop</a>', $html );
		$this->assertStringNotContainsString( '**', $html );
	}

	public function test_render_markdown_lite_renders_bullet_and_numbered_lists(): void {
		$html = WPAIC_Transcript_Renderer::render_markdown_lite( "Top picks:\n- Mug A\n- Mug B\n\n1. First\n2. Second" );

		$this->assertStringContainsString( '<ul><li>Mug A</li><li>Mug B</li></ul>', $html );
		$this->assertStringContainsString( '<ol><li>First</li><li>Second</li></ol>', $html );
		$this->assertStringContainsString( '<p>Top picks:</p>', $html );
	}

	public function test_render_markdown_lite_escapes_html(): void {
		$html = WPAIC_Transcript_Renderer::render_markdown_lite( '<script>alert("xss")</script> & **<b>bold</b>**' );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringNotContainsString( '<b>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '<strong>&lt;b&gt;bold&lt;/b&gt;</strong>', $html );
	}

	public function test_render_markdown_lite_joins_paragraph_lines_with_breaks(): void {
		$html = WPAIC_Transcript_Renderer::render_markdown_lite( "Line one\nLine two\n\nNext paragraph" );

		$this->assertStringContainsString( '<p>Line one<br>Line two</p>', $html );
		$this->assertStringContainsString( '<p>Next paragraph</p>', $html );
	}

	public function test_render_markdown_lite_renders_headings_as_bold_paragraphs(): void {
		$html = WPAIC_Transcript_Renderer::render_markdown_lite( '## Shipping options' );

		$this->assertStringContainsString( '<p><strong>Shipping options</strong></p>', $html );
	}

	public function test_ajax_get_conversation_adds_content_html_for_assistant_messages_only(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		$logs            = new WPAIC_Logs();
		$conversation_id = $logs->create_conversation( 'markdown-session' );
		$logs->log_message( $conversation_id, 'user', 'I typed **stars** myself' );
		$logs->log_message( $conversation_id, 'assistant', 'Here is a **great** pick.' );

		$_POST['conversation_id'] = (string) $conversation_id;

		try {
			$this->admin->ajax_get_conversation();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $response ) {
			$this->assertTrue( $response->success );
			$items = $response->data;

			$this->assertArrayNotHasKey( 'content_html', $items[0] );
			$this->assertArrayHasKey( 'content_html', $items[1] );
			$this->assertStringContainsString( '<strong>great</strong>', $items[1]['content_html'] );
			$this->assertSame( 'Here is a **great** pick.', $items[1]['content'] );
		} finally {
			unset( $_POST['conversation_id'] );
		}
	}

	// ---- New-handoff count bubble on the admin menu (P2-27c) ----

	public function test_admin_menu_shows_awaiting_mod_bubble_for_new_support_requests(): void {
		$this->reset_mock_wpdb();
		global $wpdb;
		$wpdb->insert( 'wp_wpaic_support_requests', array( 'customer_name' => 'A', 'customer_email' => 'a@example.com', 'status' => 'new' ) );
		$wpdb->insert( 'wp_wpaic_support_requests', array( 'customer_name' => 'B', 'customer_email' => 'b@example.com', 'status' => 'new' ) );
		$wpdb->insert( 'wp_wpaic_support_requests', array( 'customer_name' => 'C', 'customer_email' => 'c@example.com', 'status' => 'resolved' ) );

		$GLOBALS['wpaic_test_menu_pages']    = array();
		$GLOBALS['wpaic_test_submenu_pages'] = array();

		$this->admin->add_admin_menu();

		$this->assertStringContainsString( 'awaiting-mod count-2', $GLOBALS['wpaic_test_menu_pages'][0]['menu_title'] );

		$support_submenu = null;
		foreach ( $GLOBALS['wpaic_test_submenu_pages'] as $submenu ) {
			if ( 'wp-ai-chatbot-support' === $submenu['menu_slug'] ) {
				$support_submenu = $submenu;
			}
		}
		$this->assertNotNull( $support_submenu );
		$this->assertStringContainsString( 'awaiting-mod count-2', $support_submenu['menu_title'] );
		$this->assertStringContainsString( '2 new support requests', $support_submenu['menu_title'] );
	}

	public function test_admin_menu_has_no_bubble_without_new_support_requests(): void {
		$this->reset_mock_wpdb();
		global $wpdb;
		$wpdb->insert( 'wp_wpaic_support_requests', array( 'customer_name' => 'C', 'customer_email' => 'c@example.com', 'status' => 'resolved' ) );

		$GLOBALS['wpaic_test_menu_pages']    = array();
		$GLOBALS['wpaic_test_submenu_pages'] = array();

		$this->admin->add_admin_menu();

		$this->assertStringNotContainsString( 'awaiting-mod', $GLOBALS['wpaic_test_menu_pages'][0]['menu_title'] );
		foreach ( $GLOBALS['wpaic_test_submenu_pages'] as $submenu ) {
			$this->assertStringNotContainsString( 'awaiting-mod', $submenu['menu_title'] );
		}
	}

	// ---- Unsaved-changes guard on settings tabs (P2-27a) ----
	// The guard logic lives in the enqueued assets/admin.js; the page only
	// renders the indicator markup the script toggles.

	private function get_admin_js_asset(): string {
		$admin_js = file_get_contents( WPAIC_PLUGIN_DIR . 'assets/admin.js' );
		$this->assertNotFalse( $admin_js );
		return (string) $admin_js;
	}

	public function test_settings_page_includes_unsaved_changes_guard(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option( 'wpaic_settings', array() );

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'id="wpaic-unsaved-indicator"', $output );
		// No inline guard script anymore; logic moved to assets/admin.js.
		$this->assertStringNotContainsString( 'beforeunload', $output );

		$admin_js = $this->get_admin_js_asset();
		$this->assertStringContainsString( 'beforeunload', $admin_js );
		$this->assertStringContainsString( 'hasUnsavedChanges', $admin_js );
		$this->assertStringContainsString( '#wpaic-unsaved-indicator', $admin_js );
	}

	public function test_unsaved_changes_guard_is_value_based(): void {
		$admin_js = $this->get_admin_js_asset();

		// Snapshot of saved values taken on load, compared on every edit so
		// reverting to the saved values clears the dirty state again.
		$this->assertStringContainsString( 'var savedSnapshot = $form.serialize();', $admin_js );
		$this->assertStringContainsString( "hasUnsavedChanges = \$form.serialize() !== savedSnapshot;", $admin_js );
		$this->assertStringContainsString( "toggleClass('hidden', !hasUnsavedChanges)", $admin_js );
		// Snapshot resets on submit so a save without a reload starts clean:
		// one serialize() snapshot on load, one inside the submit handler.
		$this->assertSame( 2, substr_count( $admin_js, 'savedSnapshot = $form.serialize();' ) );
	}

	// ---- Enqueued admin JS asset (onboarding + transcript modal handlers) ----

	public function test_admin_js_asset_contains_onboarding_and_transcript_handlers(): void {
		$admin_js = $this->get_admin_js_asset();

		// Onboarding checklist: dismissal and "try it" step persistence.
		$this->assertStringContainsString( 'wpaic_update_onboarding', $admin_js );
		$this->assertStringContainsString( 'wpaicAdmin.onboardingNonce', $admin_js );
		$this->assertStringContainsString( '#wpaic-onboarding-dismiss', $admin_js );
		$this->assertStringContainsString( '#wpaic-onboarding-try-it', $admin_js );

		// Chat Logs transcript modal: view, delete, and close handlers.
		$this->assertStringContainsString( 'wpaic_get_conversation', $admin_js );
		$this->assertStringContainsString( 'wpaic_delete_conversation', $admin_js );
		$this->assertStringContainsString( 'wpaicAdmin.adminNonce', $admin_js );
		$this->assertStringContainsString( 'wpaicAdmin.deleteConversationConfirm', $admin_js );
		$this->assertStringContainsString( '#wpaic-conversation-modal', $admin_js );
	}

	public function test_enqueue_admin_scripts_registers_admin_js_with_localized_data(): void {
		$GLOBALS['wpaic_test_enqueued_scripts']  = array();
		$GLOBALS['wpaic_test_localized_scripts'] = array();

		$this->admin->enqueue_admin_scripts( 'toplevel_page_wp-ai-chatbot' );

		$this->assertArrayHasKey( 'wpaic-admin', $GLOBALS['wpaic_test_enqueued_scripts'] );
		$this->assertSame( WPAIC_PLUGIN_URL . 'assets/admin.js', $GLOBALS['wpaic_test_enqueued_scripts']['wpaic-admin']['src'] );
		$this->assertContains( 'jquery', $GLOBALS['wpaic_test_enqueued_scripts']['wpaic-admin']['deps'] );

		$this->assertArrayHasKey( 'wpaic-admin', $GLOBALS['wpaic_test_localized_scripts'] );
		$localized = $GLOBALS['wpaic_test_localized_scripts']['wpaic-admin']['wpaicAdmin'];
		$this->assertSame( 'test_nonce_wpaic_admin', $localized['adminNonce'] );
		$this->assertSame( 'test_nonce_wpaic_onboarding', $localized['onboardingNonce'] );
		$this->assertSame( 'Are you sure you want to delete this conversation?', $localized['deleteConversationConfirm'] );
	}

	public function test_enqueue_admin_scripts_skips_unrelated_admin_pages(): void {
		$GLOBALS['wpaic_test_enqueued_scripts'] = array();

		$this->admin->enqueue_admin_scripts( 'edit.php' );

		$this->assertArrayNotHasKey( 'wpaic-admin', $GLOBALS['wpaic_test_enqueued_scripts'] );
	}

	// ---- Honest index freshness wording (P2-27b) ----

	public function test_knowledge_tab_describes_index_freshness_honestly(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'product_index_enabled'    => true,
				'content_index_post_types' => array( 'page', 'post' ),
			)
		);
		WPAICTestHelper::set_option(
			'wpaic_content_index_meta',
			array(
				'post_count'   => 7,
				'last_updated' => '2026-05-06 10:00:00',
				'post_types'   => array( 'page', 'post' ),
			)
		);

		$upload_dir = wp_upload_dir();
		$search_dir = $upload_dir['basedir'] . '/wpaic/search';
		wp_mkdir_p( $search_dir );
		file_put_contents( $search_dir . '/content.index', 'content' );

		$_GET['tab'] = 'knowledge';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Index is fresh', $output );
		$this->assertStringContainsString( 'Up to date.', $output );
		$this->assertStringContainsString( 'content changes are indexed automatically', $output );
		$this->assertStringContainsString( 'last full rebuild', $output );
		// Products enabled but never indexed (activation only builds the content index).
		$this->assertStringContainsString( 'Products are not indexed yet', $output );

		unset( $_GET['tab'] );
	}

	public function test_knowledge_tab_hides_products_unindexed_note_when_product_index_exists(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'product_index_enabled'    => true,
				'content_index_post_types' => array( 'page', 'post' ),
			)
		);

		$upload_dir = wp_upload_dir();
		$search_dir = $upload_dir['basedir'] . '/wpaic/search';
		wp_mkdir_p( $search_dir );
		file_put_contents( $search_dir . '/products.index', 'products' );
		file_put_contents( $search_dir . '/content.index', 'content' );

		$_GET['tab'] = 'knowledge';

		ob_start();
		$this->admin->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringNotContainsString( 'Products are not indexed yet', $output );

		unset( $_GET['tab'] );
	}

	// ---- Legacy handoff fallback role chip (FIX-5) ----

	public function test_support_page_text_fallback_hides_empty_role_chip(): void {
		$this->reset_mock_wpdb();
		WPAICTestHelper::set_option( 'test_user_can_manage_options', true );

		ob_start();
		$this->admin->render_support_page();
		$output = ob_get_clean();

		// The flush() helper only emits the role chip when a role was parsed.
		$this->assertStringContainsString( 'if (currentRole) {', $output );
	}
}
