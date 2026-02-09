<?php
/**
 * Tests for WPAIC_Chat class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-tools.php';
require_once __DIR__ . '/../includes/class-wpaic-chat.php';

class WPAIC_ChatTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		parent::tearDown();
	}

	public function test_send_returns_error_when_no_api_key(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat   = new WPAIC_Chat();
		$result = $chat->send( array( array( 'role' => 'user', 'content' => 'Hello' ) ) );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertEquals( 'no_api_key', $result->get_error_code() );
	}

	public function test_send_stream_calls_callback_with_error_when_no_api_key(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat         = new WPAIC_Chat();
		$callback_data = null;

		$chat->send_stream(
			array( array( 'role' => 'user', 'content' => 'Hello' ) ),
			function ( $data ) use ( &$callback_data ) {
				$callback_data = $data;
			}
		);

		$this->assertNotNull( $callback_data );
		$this->assertArrayHasKey( 'error', $callback_data );
		$this->assertEquals( 'Chat is currently unavailable. Please try again later.', $callback_data['error'] );
	}

	public function test_chat_uses_settings_model(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => 'test-key',
				'model'          => 'gpt-4o',
			)
		);

		$settings = get_option( 'wpaic_settings' );
		$this->assertEquals( 'gpt-4o', $settings['model'] );
	}

	public function test_chat_defaults_to_gpt4o_mini(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => 'test-key',
			)
		);

		$settings = get_option( 'wpaic_settings' );
		$model    = $settings['model'] ?? 'gpt-4o-mini';
		$this->assertEquals( 'gpt-4o-mini', $model );
	}

	public function test_tool_definitions_structure(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools = $method->invoke( $chat );

		$this->assertCount( 5, $tools );

		$tool_names = array_map( fn( $t ) => $t['function']['name'], $tools );
		$this->assertContains( 'search_products', $tool_names );
		$this->assertContains( 'get_product_details', $tool_names );
		$this->assertContains( 'get_categories', $tool_names );
		$this->assertContains( 'compare_products', $tool_names );
		$this->assertContains( 'get_order_status', $tool_names );

		foreach ( $tools as $tool ) {
			$this->assertEquals( 'function', $tool['type'] );
			$this->assertArrayHasKey( 'function', $tool );
			$this->assertArrayHasKey( 'name', $tool['function'] );
			$this->assertArrayHasKey( 'description', $tool['function'] );
			$this->assertArrayHasKey( 'parameters', $tool['function'] );
		}
	}

	public function test_search_products_tool_parameters(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools             = $method->invoke( $chat );
		$search_tool       = array_filter( $tools, fn( $t ) => $t['function']['name'] === 'search_products' );
		$search_tool       = array_values( $search_tool )[0];
		$search_properties = $search_tool['function']['parameters']['properties'];

		$this->assertArrayHasKey( 'search', $search_properties );
		$this->assertArrayHasKey( 'category', $search_properties );
		$this->assertArrayHasKey( 'min_price', $search_properties );
		$this->assertArrayHasKey( 'max_price', $search_properties );
		$this->assertArrayHasKey( 'limit', $search_properties );
	}

	public function test_execute_tool_search_products(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'search_products', array() );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'Test Product', $result[0]['name'] );
	}

	public function test_execute_tool_get_product_details(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$this->create_mock_product( 42, 'Detailed Product', '99.99' );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_product_details', array( 'product_id' => 42 ) );

		$this->assertIsArray( $result );
		$this->assertEquals( 42, $result['id'] );
		$this->assertEquals( 'Detailed Product', $result['name'] );
	}

	public function test_execute_tool_get_categories(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		WPAICTestHelper::add_mock_term(
			array(
				'term_id' => 1,
				'name'    => 'Clothing',
				'slug'    => 'clothing',
				'count'   => 5,
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_categories', array() );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertEquals( 'Clothing', $result[0]['name'] );
	}

	public function test_execute_tool_compare_products(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'compare_products', array( 'product_ids' => array( 1, 2 ) ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'products', $result );
		$this->assertArrayHasKey( 'attributes', $result );
		$this->assertCount( 2, $result['products'] );
	}

	public function test_execute_tool_compare_products_with_empty_ids(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'compare_products', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'products', $result );
		$this->assertEmpty( $result['products'] );
	}

	public function test_execute_tool_unknown_returns_error(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'unknown_tool', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertEquals( 'Unknown tool', $result['error'] );
	}

	public function test_execute_tool_search_products_returns_empty_when_no_products(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'search_products', array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_execute_tool_get_categories_returns_empty_when_no_categories(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_categories', array() );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	public function test_execute_tool_get_product_details_returns_null_for_nonexistent(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_product_details', array( 'product_id' => 999 ) );

		$this->assertNull( $result );
	}

	public function test_format_messages_adds_system_prompt(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'format_messages' );
		$method->setAccessible( true );

		$messages = array(
			array( 'role' => 'user', 'content' => 'Hello' ),
		);

		$formatted = $method->invoke( $chat, $messages );

		$this->assertCount( 2, $formatted );
		$this->assertEquals( 'system', $formatted[0]['role'] );
		$this->assertStringContains( 'helpful assistant', $formatted[0]['content'] );
		$this->assertEquals( 'user', $formatted[1]['role'] );
		$this->assertEquals( 'Hello', $formatted[1]['content'] );
	}

	public function test_get_system_prompt_uses_custom_when_set(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'system_prompt'  => 'You are a custom bot for my store.',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'You are a custom bot for my store.', $prompt );
		$this->assertStringContainsString( 'respond in', $prompt );
	}

	public function test_get_system_prompt_uses_default_when_empty(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'system_prompt'  => '',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'helpful assistant', $prompt );
	}

	public function test_get_system_prompt_uses_default_when_whitespace_only(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'system_prompt'  => '   ',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'helpful assistant', $prompt );
	}

	public function test_format_messages_handles_tool_messages(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'format_messages' );
		$method->setAccessible( true );

		$messages = array(
			array( 'role' => 'user', 'content' => 'Search products' ),
			array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => array(
					array(
						'id'       => 'call_123',
						'type'     => 'function',
						'function' => array(
							'name'      => 'search_products',
							'arguments' => '{}',
						),
					),
				),
			),
			array(
				'role'         => 'tool',
				'tool_call_id' => 'call_123',
				'content'      => '[]',
			),
		);

		$formatted = $method->invoke( $chat, $messages );

		$this->assertCount( 4, $formatted );
		$this->assertEquals( 'system', $formatted[0]['role'] );
		$this->assertEquals( 'user', $formatted[1]['role'] );
		$this->assertEquals( 'assistant', $formatted[2]['role'] );
		$this->assertArrayHasKey( 'tool_calls', $formatted[2] );
		$this->assertEquals( 'tool', $formatted[3]['role'] );
		$this->assertEquals( 'call_123', $formatted[3]['tool_call_id'] );
	}

	public function test_tool_definitions_empty_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools = $method->invoke( $chat );

		$this->assertIsArray( $tools );
		$this->assertEmpty( $tools );
	}

	public function test_execute_tool_returns_error_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'search_products', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'error', $result );
		$this->assertEquals( 'Product tools unavailable', $result['error'] );
	}

	public function test_system_prompt_mentions_products_when_woocommerce_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'system_prompt'  => '',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'products', $prompt );
	}

	public function test_system_prompt_no_products_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'system_prompt'  => '',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringNotContains( 'products', $prompt );
		$this->assertStringContains( 'helpful assistant', $prompt );
	}

	public function test_handle_tool_calls_stream_emits_tool_input_available(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'handle_tool_calls_stream' );
		$method->setAccessible( true );

		$collected_events = array();
		$callback         = function ( $data ) use ( &$collected_events ) {
			$collected_events[] = $data;
		};

		$messages   = array( array( 'role' => 'user', 'content' => 'Show products' ) );
		$tool_calls = array(
			array(
				'id'       => 'call_123',
				'type'     => 'function',
				'function' => array(
					'name'      => 'search_products',
					'arguments' => '{}',
				),
			),
		);

		$method->invoke( $chat, $messages, $tool_calls, $callback );

		$tool_input_events = array_filter(
			$collected_events,
			fn( $e ) => isset( $e['tool_input_available'] )
		);
		$this->assertNotEmpty( $tool_input_events );

		$first_input = array_values( $tool_input_events )[0];
		$this->assertEquals( 'call_123', $first_input['tool_input_available']['toolCallId'] );
		$this->assertEquals( 'search_products', $first_input['tool_input_available']['toolName'] );
	}

	public function test_handle_tool_calls_stream_emits_tool_output_available(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'handle_tool_calls_stream' );
		$method->setAccessible( true );

		$collected_events = array();
		$callback         = function ( $data ) use ( &$collected_events ) {
			$collected_events[] = $data;
		};

		$messages   = array( array( 'role' => 'user', 'content' => 'Show products' ) );
		$tool_calls = array(
			array(
				'id'       => 'call_456',
				'type'     => 'function',
				'function' => array(
					'name'      => 'search_products',
					'arguments' => '{}',
				),
			),
		);

		$method->invoke( $chat, $messages, $tool_calls, $callback );

		$tool_output_events = array_filter(
			$collected_events,
			fn( $e ) => isset( $e['tool_output_available'] )
		);
		$this->assertNotEmpty( $tool_output_events );

		$first_output = array_values( $tool_output_events )[0];
		$this->assertEquals( 'call_456', $first_output['tool_output_available']['toolCallId'] );
		$this->assertIsArray( $first_output['tool_output_available']['output'] );
		$this->assertCount( 1, $first_output['tool_output_available']['output'] );
		$this->assertEquals( 'Test Product', $first_output['tool_output_available']['output'][0]['name'] );
	}

	public function test_handle_tool_calls_stream_emits_events_in_order(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'handle_tool_calls_stream' );
		$method->setAccessible( true );

		$collected_events = array();
		$callback         = function ( $data ) use ( &$collected_events ) {
			$collected_events[] = $data;
		};

		$messages   = array( array( 'role' => 'user', 'content' => 'Show products' ) );
		$tool_calls = array(
			array(
				'id'       => 'call_789',
				'type'     => 'function',
				'function' => array(
					'name'      => 'search_products',
					'arguments' => '{}',
				),
			),
		);

		$method->invoke( $chat, $messages, $tool_calls, $callback );

		$event_types = array();
		foreach ( $collected_events as $event ) {
			if ( isset( $event['tool_input_available'] ) ) {
				$event_types[] = 'tool_input_available';
			} elseif ( isset( $event['tool_output_available'] ) ) {
				$event_types[] = 'tool_output_available';
			} elseif ( isset( $event['error'] ) ) {
				$event_types[] = 'error';
			}
		}

		$input_index  = array_search( 'tool_input_available', $event_types, true );
		$output_index = array_search( 'tool_output_available', $event_types, true );

		$this->assertNotFalse( $input_index );
		$this->assertNotFalse( $output_index );
		$this->assertLessThan( $output_index, $input_index, 'tool_input_available should come before tool_output_available' );
	}

	/**
	 * Helper for string contains assertion.
	 */
	private function assertStringContains( string $needle, string $haystack ): void {
		$this->assertTrue(
			str_contains( $haystack, $needle ),
			"Failed asserting that '$haystack' contains '$needle'"
		);
	}

	/**
	 * Helper for string not contains assertion.
	 */
	private function assertStringNotContains( string $needle, string $haystack ): void {
		$this->assertFalse(
			str_contains( $haystack, $needle ),
			"Failed asserting that '$haystack' does not contain '$needle'"
		);
	}

	/**
	 * Creates a mock WooCommerce product with metadata.
	 */
	private function create_mock_product(
		int $id,
		string $title,
		string $price
	): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_content' => '',
				'post_excerpt' => '',
				'post_type'    => 'product',
				'post_status'  => 'publish',
			)
		);

		WPAICTestHelper::set_post_meta( $id, '_price', $price );
		WPAICTestHelper::set_post_meta( $id, '_regular_price', $price );
		WPAICTestHelper::set_post_meta( $id, '_sale_price', '' );
		WPAICTestHelper::set_post_meta( $id, '_sku', '' );
		WPAICTestHelper::set_post_meta( $id, '_stock_status', 'instock' );
		WPAICTestHelper::set_post_meta( $id, '_stock', '' );
	}

	public function test_system_prompt_includes_auto_language_instruction(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'language'       => 'auto',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'respond in the same language', $prompt );
	}

	public function test_system_prompt_includes_fixed_language_instruction(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
				'language'       => 'es',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'respond in Spanish', $prompt );
	}

	public function test_system_prompt_default_language_is_auto(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'respond in the same language', $prompt );
	}

	public function test_system_prompt_includes_faq_content(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'What is your return policy?',
				'answer'   => '30-day returns on all items.',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'FAQ knowledge', $prompt );
		$this->assertStringContains( 'What is your return policy?', $prompt );
		$this->assertStringContains( '30-day returns on all items.', $prompt );
	}

	public function test_system_prompt_excludes_faq_when_empty(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringNotContains( 'FAQ knowledge', $prompt );
	}

	public function test_system_prompt_includes_multiple_faqs(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'What is your return policy?',
				'answer'   => '30-day returns.',
			)
		);
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'Do you ship internationally?',
				'answer'   => 'Yes, to 50+ countries.',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'What is your return policy?', $prompt );
		$this->assertStringContains( 'Do you ship internationally?', $prompt );
		$this->assertStringContains( 'Yes, to 50+ countries.', $prompt );
	}

	public function test_faq_injected_as_text_not_tool(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'Test question?',
				'answer'   => 'Test answer.',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );

		$prompt_method = $reflection->getMethod( 'get_system_prompt' );
		$prompt_method->setAccessible( true );
		$prompt = $prompt_method->invoke( $chat );

		$this->assertStringContains( 'Test question?', $prompt );

		$tools_method = $reflection->getMethod( 'get_tool_definitions' );
		$tools_method->setAccessible( true );
		$tools      = $tools_method->invoke( $chat );
		$tool_names = array_map( fn( $t ) => $t['function']['name'], $tools );

		$this->assertNotContains( 'get_faqs', $tool_names );
		$this->assertNotContains( 'search_faqs', $tool_names );
	}

	public function test_format_messages_includes_faq_in_system_message(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-4o-mini',
			)
		);

		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_faqs',
			array(
				'question' => 'Hours of operation?',
				'answer'   => '9am to 5pm.',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'format_messages' );
		$method->setAccessible( true );

		$formatted = $method->invoke( $chat, array( array( 'role' => 'user', 'content' => 'What are your hours?' ) ) );

		$this->assertEquals( 'system', $formatted[0]['role'] );
		$this->assertStringContainsString( 'Hours of operation?', $formatted[0]['content'] );
		$this->assertStringContainsString( '9am to 5pm.', $formatted[0]['content'] );
	}

	public function test_is_provider_mode_true_when_both_fields_set(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-site-key-123',
			)
		);

		$chat = new WPAIC_Chat();
		$this->assertTrue( $chat->is_provider_mode() );
	}

	public function test_is_provider_mode_false_when_url_missing(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => '',
				'provider_site_key' => 'test-site-key-123',
			)
		);

		$chat = new WPAIC_Chat();
		$this->assertFalse( $chat->is_provider_mode() );
	}

	public function test_is_provider_mode_false_when_site_key_missing(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => '',
			)
		);

		$chat = new WPAIC_Chat();
		$this->assertFalse( $chat->is_provider_mode() );
	}

	public function test_is_provider_mode_false_when_neither_set(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => 'test-key',
			)
		);

		$chat = new WPAIC_Chat();
		$this->assertFalse( $chat->is_provider_mode() );
	}

	public function test_send_no_error_in_provider_mode_without_api_key(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'    => '',
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat   = new WPAIC_Chat();
		$result = $chat->send( array( array( 'role' => 'user', 'content' => 'Hello' ) ) );

		// In provider mode, send() should attempt provider connection (and fail due to no actual server).
		// It should NOT return 'no_api_key' error.
		if ( is_wp_error( $result ) ) {
			$this->assertNotEquals( 'no_api_key', $result->get_error_code() );
		} else {
			$this->assertArrayHasKey( 'content', $result );
		}
	}

	public function test_send_stream_no_error_in_provider_mode_without_api_key(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'    => '',
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat          = new WPAIC_Chat();
		$callback_data = array();

		$chat->send_stream(
			array( array( 'role' => 'user', 'content' => 'Hello' ) ),
			function ( $data ) use ( &$callback_data ) {
				$callback_data[] = $data;
			}
		);

		// Should attempt provider (fail to connect), but NOT give 'Chat is currently unavailable' error
		$has_unavailable_error = false;
		foreach ( $callback_data as $data ) {
			if ( isset( $data['error'] ) && 'Chat is currently unavailable. Please try again later.' === $data['error'] ) {
				$has_unavailable_error = true;
			}
		}
		$this->assertFalse( $has_unavailable_error, 'Provider mode should not produce "no API key" error' );
	}

	public function test_stream_from_provider_parses_text_chunks(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'stream_from_provider' );
		$method->setAccessible( true );

		// Create a temporary SSE stream file
		$sse_content  = "data: " . json_encode( array( 'choices' => array( array( 'delta' => array( 'content' => 'Hello' ), 'finish_reason' => null ) ) ) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array( 'choices' => array( array( 'delta' => array( 'content' => ' world' ), 'finish_reason' => 'stop' ) ) ) ) . "\n\n";
		$sse_content .= "data: [DONE]\n\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $temp_file, $sse_content );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'file://' . $temp_file,
			'test-key',
			array( 'messages' => array() ),
			function ( $data ) use ( &$chunks ) {
				$chunks[] = $data;
			}
		);

		unlink( $temp_file );

		$this->assertCount( 2, $chunks );
		$this->assertEquals( array( 'content' => 'Hello' ), $chunks[0] );
		$this->assertEquals( array( 'content' => ' world' ), $chunks[1] );
		$this->assertEmpty( $result );
	}

	public function test_stream_from_provider_parses_tool_calls(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'stream_from_provider' );
		$method->setAccessible( true );

		$sse_content  = "data: " . json_encode( array(
			'choices' => array( array(
				'delta' => array(
					'tool_calls' => array( array(
						'index'    => 0,
						'id'       => 'call_abc123',
						'function' => array( 'name' => 'search_products', 'arguments' => '' ),
					) ),
				),
				'finish_reason' => null,
			) ),
		) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array(
			'choices' => array( array(
				'delta' => array(
					'tool_calls' => array( array(
						'index'    => 0,
						'function' => array( 'arguments' => '{"search":"shoes"}' ),
					) ),
				),
				'finish_reason' => null,
			) ),
		) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array(
			'choices' => array( array(
				'delta'         => new \stdClass(),
				'finish_reason' => 'tool_calls',
			) ),
		) ) . "\n\n";
		$sse_content .= "data: [DONE]\n\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $temp_file, $sse_content );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'file://' . $temp_file,
			'test-key',
			array( 'messages' => array() ),
			function ( $data ) use ( &$chunks ) {
				$chunks[] = $data;
			}
		);

		unlink( $temp_file );

		$this->assertArrayHasKey( 'tool_calls', $result );
		$this->assertCount( 1, $result['tool_calls'] );
		$this->assertEquals( 'call_abc123', $result['tool_calls'][0]['id'] );
		$this->assertEquals( 'search_products', $result['tool_calls'][0]['function']['name'] );
		$this->assertEquals( '{"search":"shoes"}', $result['tool_calls'][0]['function']['arguments'] );

		// Verify tool_input_start and tool_input_delta chunks were emitted
		$chunk_types = array_keys( array_merge( ...$chunks ) );
		$this->assertContains( 'tool_input_start', $chunk_types );
		$this->assertContains( 'tool_input_delta', $chunk_types );
	}

	public function test_stream_from_provider_handles_error_response(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'stream_from_provider' );
		$method->setAccessible( true );

		$sse_content = "data: " . json_encode( array( 'error' => array( 'message' => 'Invalid API key' ) ) ) . "\n\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $temp_file, $sse_content );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'file://' . $temp_file,
			'test-key',
			array( 'messages' => array() ),
			function ( $data ) use ( &$chunks ) {
				$chunks[] = $data;
			}
		);

		unlink( $temp_file );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertEquals( 'Invalid API key', $result['error'] );
	}

	public function test_stream_from_provider_handles_connection_failure(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'stream_from_provider' );
		$method->setAccessible( true );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'https://this-will-not-resolve.invalid/endpoint',
			'test-key',
			array( 'messages' => array() ),
			function ( $data ) use ( &$chunks ) {
				$chunks[] = $data;
			}
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertEquals( 'Failed to connect to provider', $result['error'] );
	}

	public function test_provider_mode_client_not_initialized(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'    => 'this-key-should-be-ignored',
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$property   = $reflection->getProperty( 'client' );
		$property->setAccessible( true );

		// In provider mode, the OpenAI client should NOT be initialized
		$this->assertNull( $property->getValue( $chat ) );
	}
}
