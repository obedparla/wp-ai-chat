<?php

use PHPUnit\Framework\TestCase;

class WPAIP_APITest extends TestCase {

	private WPAIP_API $api;
	private string $site_key;

	protected function setUp(): void {
		$GLOBALS['wp_options']     = array();
		$GLOBALS['wp_actions']     = array();
		$GLOBALS['wp_rest_routes'] = array();

		$this->site_key = 'test-site-key-abc123';
		update_option( 'wpaip_settings', array(
			'openai_api_key' => 'sk-test',
			'model'          => 'gpt-4o-mini',
			'site_key'       => $this->site_key,
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

	public function test_authenticate_request_rejects_missing_key(): void {
		$request = new WP_REST_Request();

		$result = $this->api->authenticate_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_authenticate_request_rejects_invalid_key(): void {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WPAIP-Site-Key', 'wrong-key' );

		$result = $this->api->authenticate_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_authenticate_request_accepts_valid_key(): void {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WPAIP-Site-Key', $this->site_key );

		$result = $this->api->authenticate_request( $request );

		$this->assertTrue( $result );
	}

	public function test_authenticate_request_rejects_empty_stored_key(): void {
		update_option( 'wpaip_settings', array(
			'site_key' => '',
		) );

		$request = new WP_REST_Request();
		$request->set_header( 'X-WPAIP-Site-Key', 'any-key' );

		$result = $this->api->authenticate_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
	}

	// --- Validation tests (via validate_chat_request) ---

	public function test_validate_rejects_missing_messages(): void {
		$request = new WP_REST_Request();

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	public function test_validate_rejects_empty_messages(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array() );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_non_array_messages(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', 'not an array' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_malformed_messages(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user' ), // missing content
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_accepts_valid_messages(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'system', 'content' => 'You are helpful.' ),
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_accepts_tool_role_messages(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Search products' ),
			array(
				'role'         => 'tool',
				'tool_call_id' => 'call_123',
				'content'      => '{"results": []}',
			),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_accepts_assistant_tool_calls_messages(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Search products' ),
			array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => array(
					array(
						'id'       => 'call_123',
						'type'     => 'function',
						'function' => array( 'name' => 'search_products', 'arguments' => '{}' ),
					),
				),
			),
		) );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	public function test_validate_rejects_non_array_tools(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'tools', 'not an array' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_rejects_non_string_model(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'model', 123 );

		$result = $this->api->validate_chat_request( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_request', $result->get_error_code() );
	}

	public function test_validate_accepts_valid_tools_and_model(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'tools', array(
			array( 'type' => 'function', 'function' => array( 'name' => 'test' ) ),
		) );
		$request->set_param( 'model', 'gpt-4o' );

		$result = $this->api->validate_chat_request( $request );

		$this->assertNull( $result );
	}

	// --- handle_chat integration tests ---

	public function test_handle_chat_returns_error_when_no_api_key(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key' => '',
			'model'          => 'gpt-4o-mini',
			'site_key'       => $this->site_key,
		) );

		$streamer = new WPAIP_Streamer();
		$this->api->set_streamer( $streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
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
		$captured_params = null;
		$mock_streamer   = new class extends WPAIP_Streamer {
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

		$this->api->set_streamer( $mock_streamer );

		$request = new WP_REST_Request();
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );
		$request->set_param( 'tools', array(
			array( 'type' => 'function', 'function' => array( 'name' => 'search' ) ),
		) );
		$request->set_param( 'model', 'gpt-4o' );

		// handle_chat calls exit after streaming — catch it
		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit throws in some test configurations
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'gpt-4o', $mock_streamer->captured_params['model'] );
		$this->assertSame(
			array( array( 'role' => 'user', 'content' => 'Hello' ) ),
			$mock_streamer->captured_params['messages']
		);
		$this->assertSame(
			array( array( 'type' => 'function', 'function' => array( 'name' => 'search' ) ) ),
			$mock_streamer->captured_params['tools']
		);
	}

	public function test_handle_chat_uses_default_model_when_not_provided(): void {
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
		$request->set_param( 'messages', array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		) );

		try {
			$this->api->handle_chat( $request );
		} catch ( \Throwable $e ) {
			// exit throws in some test configurations
		}

		$this->assertNotNull( $mock_streamer->captured_params );
		$this->assertSame( 'gpt-4o-mini', $mock_streamer->captured_params['model'] );
		$this->assertArrayNotHasKey( 'tools', $mock_streamer->captured_params );
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
		$request->set_param( 'messages', array(
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
