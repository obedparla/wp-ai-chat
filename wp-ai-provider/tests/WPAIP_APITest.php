<?php

use PHPUnit\Framework\TestCase;

class WPAIP_APITest extends TestCase {

	private WPAIP_API $api;

	protected function setUp(): void {
		$GLOBALS['wp_options']     = array();
		$GLOBALS['wp_actions']     = array();
		$GLOBALS['wp_rest_routes'] = array();
		$GLOBALS['wp_filters']     = array();

		update_option( 'wpaip_settings', array(
			'openai_api_key'      => 'sk-test',
			'model'               => 'gpt-5-mini',
			'reasoning_effort'    => 'medium',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-api-token',
		) );

		$this->api = new WPAIP_API();
	}

	public function test_register_routes(): void {
		$this->api->register_routes();

		$this->assertArrayHasKey( 'wpaip/v1', $GLOBALS['wp_rest_routes'] );
		$this->assertArrayHasKey( '/chat', $GLOBALS['wp_rest_routes']['wpaip/v1'] );
		$this->assertSame( 'POST', $GLOBALS['wp_rest_routes']['wpaip/v1']['/chat']['methods'] );
	}

	public function test_init_registers_rest_api_init_action(): void {
		$this->api->init();

		$this->assertArrayHasKey( 'rest_api_init', $GLOBALS['wp_actions'] );
	}

	public function test_authenticate_request_returns_validator_error(): void {
		$this->api->set_license_validator(
			new class extends WPAIP_License_Validator {
				public function __construct() {}

				public function validate_request( WP_REST_Request $request ): array|WP_Error {
					return new WP_Error( 'rest_forbidden', 'Nope', array( 'status' => 403 ) );
				}
			}
		);

		$request = new WP_REST_Request();

		$result = $this->api->authenticate_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_authenticate_request_accepts_valid_validator_result(): void {
		$this->api->set_license_validator(
			new class extends WPAIP_License_Validator {
				public function __construct() {}

				public function validate_request( WP_REST_Request $request ): array|WP_Error {
					return array(
						'install_id'   => 99,
						'license_id'   => 55,
						'status'       => 'licensed',
						'is_grace'     => false,
						'usage_bucket' => 'fs_install_99',
					);
				}
			}
		);

		$request = new WP_REST_Request();

		$result = $this->api->authenticate_request( $request );

		$this->assertTrue( $result );
	}

	// --- Validation tests (via validate_chat_request) ---

	public function test_validate_rejects_missing_input(): void {
		$request = new WP_REST_Request();

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_validate_rejects_empty_input(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array() );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_non_array_input(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', 'not an array' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_malformed_input(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user' ), // missing content
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_accepts_valid_input(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'instructions', 'You are helpful.' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_accepts_function_call_output_items(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Search products' ),
			array(
				'type'    => 'function_call_output',
				'call_id' => 'call_123',
				'output'  => '{"results": []}',
			),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_accepts_function_call_items(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Search products' ),
			array(
				'type'      => 'function_call',
				'call_id'   => 'call_123',
				'name'      => 'search_products',
				'arguments' => '{}',
			),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_rejects_non_array_tools(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'tools', 'not an array' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_non_string_model(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'model', 123 );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_non_string_instructions(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'instructions', array( 'not', 'a', 'string' ) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_accepts_valid_tools_and_model(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'tools', array(
			array( 'type' => 'function', 'name' => 'test', 'parameters' => array( 'type' => 'object', 'properties' => new \stdClass() ) ),
		) );
		$request->set_param( 'model', 'gpt-5' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	// --- handle_chat integration tests ---

	public function test_handle_chat_returns_error_when_no_api_key(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => '',
			'model'               => 'gpt-5-mini',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-api-token',
		) );

		$streamer = new WPAIP_Streamer();
		$this->api->set_streamer( $streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		$result = $this->api->handle_chat( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'server_error', $result->get_error_code() );
		$this->assertSame( 500, $result->get_error_data()['status'] );
	}

	public function test_handle_chat_returns_validation_error(): void {
		$request = new WP_REST_Request();

		$result = $this->api->handle_chat( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_handle_chat_builds_correct_params_with_tools(): void {
		$mock_streamer = $this->make_capturing_streamer();

		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'instructions', 'You are helpful.' );
		$request->set_param( 'tools', array(
			array( 'type' => 'function', 'name' => 'search', 'parameters' => array( 'type' => 'object', 'properties' => array( 'q' => array( 'type' => 'string' ) ) ) ),
		) );

		// handle_chat calls exit after streaming — catch it
		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit throws in some test configurations
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'gpt-5-mini', $mock_streamer->captured_params['model'] );
		$this->assertSame( array( 'effort' => 'medium' ), $mock_streamer->captured_params['reasoning'] );
		$this->assertSame(
			array( array( 'role' => 'user', 'content' => 'Hello' ) ),
			$mock_streamer->captured_params['input']
		);
		$this->assertSame( 'You are helpful.', $mock_streamer->captured_params['instructions'] );
		$this->assertSame( 'search', $mock_streamer->captured_params['tools'][0]['name'] );
		$this->assertSame( 'function', $mock_streamer->captured_params['tools'][0]['type'] );
	}

	public function test_handle_chat_uses_provider_selected_model_and_effort(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => 'sk-test',
			'model'               => 'gpt-5.4-nano',
			'reasoning_effort'    => 'high',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-api-token',
		) );

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'gpt-5.4-nano', $mock_streamer->captured_params['model'] );
		$this->assertSame( array( 'effort' => 'high' ), $mock_streamer->captured_params['reasoning'] );
	}

	// Backwards compat: older chatbots still send model/reasoning_effort. The
	// provider must accept them (validation passes) but ignore their values.
	public function test_handle_chat_ignores_request_model_and_reasoning_effort(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => 'sk-test',
			'model'               => 'gpt-5.4-mini',
			'reasoning_effort'    => 'low',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-api-token',
		) );

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'model', 'gpt-5' );
		$request->set_param( 'reasoning_effort', 'high' );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'gpt-5.4-mini', $mock_streamer->captured_params['model'] );
		$this->assertSame( array( 'effort' => 'low' ), $mock_streamer->captured_params['reasoning'] );
	}

	public function test_handle_chat_falls_back_to_defaults_when_settings_invalid(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => 'sk-test',
			'model'               => 'bogus-model',
			'reasoning_effort'    => 'turbo',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-api-token',
		) );

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'gpt-5-mini', $mock_streamer->captured_params['model'] );
		$this->assertSame( array( 'effort' => 'low' ), $mock_streamer->captured_params['reasoning'] );
		$this->assertArrayNotHasKey( 'tools', $mock_streamer->captured_params );
	}

	// 'none' has no Responses API equivalent — omitting the param would make
	// GPT-5 silently default to medium, so the provider maps it to 'minimal'.
	public function test_handle_chat_maps_none_reasoning_effort_to_minimal(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'      => 'sk-test',
			'model'               => 'gpt-5-mini',
			'reasoning_effort'    => 'none',
			'freemius_product_id' => 1234,
			'freemius_api_token'  => 'fs-api-token',
		) );

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( array( 'effort' => 'minimal' ), $mock_streamer->captured_params['reasoning'] );
	}

	public function test_handle_chat_disables_openai_conversation_storage(): void {
		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertFalse( $mock_streamer->captured_params['store'] );
	}

	public function test_handle_chat_sets_prompt_cache_key_from_usage_bucket(): void {
		$this->api->set_license_validator(
			new class extends WPAIP_License_Validator {
				public function __construct() {}

				public function validate_request( WP_REST_Request $request ): array|WP_Error {
					return array(
						'install_id'   => 99,
						'license_id'   => 55,
						'status'       => 'licensed',
						'is_grace'     => false,
						'usage_bucket' => 'fs_install_99',
					);
				}
			}
		);

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		$this->assertTrue( $this->api->authenticate_request( $request ) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'fs_install_99', $mock_streamer->captured_params['prompt_cache_key'] );
	}

	public function test_handle_chat_omits_prompt_cache_key_without_request_context(): void {
		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertArrayNotHasKey( 'prompt_cache_key', $mock_streamer->captured_params );
	}

	// --- Request caps (P0-4) ---

	public function test_validate_rejects_too_many_input_items(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array_fill( 0, 101, array( 'role' => 'user', 'content' => 'Hello' ) ) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_validate_accepts_input_at_item_limit(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array_fill( 0, 100, array( 'role' => 'user', 'content' => 'Hello' ) ) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_rejects_oversized_instructions(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'instructions', str_repeat( 'a', 32769 ) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_validate_rejects_oversized_input_item(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => str_repeat( 'a', 70000 ) ),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_oversized_function_call_output_item(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Search products' ),
			array(
				'type'    => 'function_call_output',
				'call_id' => 'call_123',
				'output'  => str_repeat( 'a', 70000 ),
			),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	// --- Output token cap (P0-4) ---

	public function test_handle_chat_sets_max_output_tokens(): void {
		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 2048, $mock_streamer->captured_params['max_output_tokens'] );
	}

	public function test_max_output_tokens_is_filterable(): void {
		add_filter( 'wpaip_max_output_tokens', static function ( int $max_output_tokens ): int {
			return 512;
		} );

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 512, $mock_streamer->captured_params['max_output_tokens'] );
	}

	// --- Daily budget enforcement (P0-4) ---

	public function test_handle_chat_returns_429_when_over_daily_budget(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key'       => 'sk-test',
			'model'                => 'gpt-5-mini',
			'reasoning_effort'     => 'low',
			'freemius_product_id'  => 1234,
			'freemius_api_token'   => 'fs-api-token',
			'daily_message_budget' => 1,
			'daily_token_budget'   => 0,
		) );
		update_option( 'wpaip_usage_daily', array(
			gmdate( 'Y-m-d' ) => array(
				'fs_install_99' => array( 'messages' => 1 ),
			),
		) );

		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = $this->make_authenticated_request();

		$result = $this->api->handle_chat( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rate_limited', $result->get_error_code() );
		$this->assertSame( 429, $result->get_error_data()['status'] );
		$this->assertStringContainsString( 'daily limit', $result->get_error_message() );
		$this->assertNull( $mock_streamer->captured_params );
	}

	public function test_handle_chat_records_message_for_usage_bucket(): void {
		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = $this->make_authenticated_request();

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$usage_tracker = new WPAIP_Usage_Tracker();

		$this->assertSame( 1, $usage_tracker->get_daily_usage( 'fs_install_99' )['messages'] );
	}

	public function test_handle_chat_records_no_usage_without_auth_context(): void {
		$mock_streamer = $this->make_capturing_streamer();
		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( array(), get_option( 'wpaip_usage_daily', array() ) );
	}

	public function test_completed_stream_event_records_tokens_for_usage_bucket(): void {
		// Streamer that simulates OpenAI finishing with a response.completed event.
		$mock_streamer = new class extends WPAIP_Streamer {
			public function __construct() {}

			public function has_client(): bool {
				return true;
			}

			public function stream( array $params ): void {
				$this->maybe_notify_response_completed( array(
					'event' => 'response.completed',
					'data'  => array(
						'type'     => 'response.completed',
						'response' => array(
							'usage' => array(
								'input_tokens'         => 1000,
								'input_tokens_details' => array( 'cached_tokens' => 900 ),
								'output_tokens'        => 150,
								'total_tokens'         => 1150,
							),
						),
					),
				) );
			}
		};
		$this->api->set_streamer( $mock_streamer );

		$request = $this->make_authenticated_request();

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$usage_tracker = new WPAIP_Usage_Tracker();
		$usage         = $usage_tracker->get_daily_usage( 'fs_install_99' );

		$this->assertSame( 1, $usage['messages'] );
		$this->assertSame( 1000, $usage['input_tokens'] );
		$this->assertSame( 900, $usage['cached_input_tokens'] );
		$this->assertSame( 150, $usage['output_tokens'] );
		$this->assertSame( 1150, $usage['total_tokens'] );
	}

	/**
	 * Build a valid request authenticated as install 99 (usage bucket fs_install_99).
	 */
	private function make_authenticated_request(): WP_REST_Request {
		$this->api->set_license_validator(
			new class extends WPAIP_License_Validator {
				public function __construct() {}

				public function validate_request( WP_REST_Request $request ): array|WP_Error {
					return array(
						'install_id'   => 99,
						'license_id'   => 55,
						'status'       => 'licensed',
						'is_grace'     => false,
						'usage_bucket' => 'fs_install_99',
					);
				}
			}
		);

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		$this->assertTrue( $this->api->authenticate_request( $request ) );

		return $request;
	}

	private function make_capturing_streamer(): WPAIP_Streamer {
		return new class extends WPAIP_Streamer {
			/** @var array<string, mixed>|null */
			public ?array $captured_params = null;

			public function __construct() {
				// Skip parent constructor (no OpenAI client needed).
			}

			public function has_client(): bool {
				return true;
			}

			public function stream( array $params ): void {
				$this->captured_params = $params;
			}
		};
	}

	public function test_handle_chat_omits_empty_tools(): void {
		$mock_streamer = new class extends WPAIP_Streamer {
			public ?array $captured_params = null;

			public function __construct() {}

			public function has_client(): bool {
				return true;
			}

			public function stream( array $params ): void {
				$this->captured_params = $params;
			}
		};

		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'input', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'tools', array() );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertArrayNotHasKey( 'tools', $mock_streamer->captured_params );
	}
}
