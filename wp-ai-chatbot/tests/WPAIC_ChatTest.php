<?php
/**
 * Tests for WPAIC_Chat class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-tools.php';
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-page-context.php';
require_once __DIR__ . '/../includes/class-wpaic-chat.php';

class WPAIC_ChatTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		global $mock_wc;
		$mock_wc = new MockWooCommerce();
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		global $mock_wc;
		$mock_wc = null;
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5',
			)
		);

		$settings = get_option( 'wpaic_settings' );
		$this->assertEquals( 'gpt-5', $settings['model'] );
	}

	public function test_chat_defaults_to_gpt_5_mini(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => 'test-key',
			)
		);

		$settings = get_option( 'wpaic_settings' );
		$model    = $settings['model'] ?? 'gpt-5-mini';
		$this->assertEquals( 'gpt-5-mini', $model );
	}

	public function test_tool_definitions_structure(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools = $method->invoke( $chat );

		$this->assertCount( 10, $tools );

		$tool_names = array_map( fn( $t ) => $t['function']['name'], $tools );
		$this->assertContains( 'search_products', $tool_names );
		$this->assertContains( 'get_product_details', $tool_names );
		$this->assertContains( 'get_categories', $tool_names );
		$this->assertContains( 'get_cart_contents', $tool_names );
		$this->assertContains( 'get_checkout_action', $tool_names );
		$this->assertContains( 'compare_products', $tool_names );
		$this->assertContains( 'get_order_status', $tool_names );
		$this->assertContains( 'get_shipping_info', $tool_names );
		$this->assertContains( 'search_site_content', $tool_names );
		$this->assertContains( 'get_page_content', $tool_names );

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
				'model'          => 'gpt-5-mini',
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

	public function test_get_cart_contents_tool_uses_empty_object_properties_schema(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools     = $method->invoke( $chat );
		$cart_tool = array_values( array_filter( $tools, fn( $t ) => $t['function']['name'] === 'get_cart_contents' ) )[0];

		$this->assertSame( 'object', $cart_tool['function']['parameters']['type'] );
		$this->assertInstanceOf( stdClass::class, $cart_tool['function']['parameters']['properties'] );
	}

	public function test_get_checkout_action_tool_uses_empty_object_properties_schema(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools         = $method->invoke( $chat );
		$checkout_tool = array_values( array_filter( $tools, fn( $t ) => $t['function']['name'] === 'get_checkout_action' ) )[0];

		$this->assertSame( 'object', $checkout_tool['function']['parameters']['type'] );
		$this->assertInstanceOf( stdClass::class, $checkout_tool['function']['parameters']['properties'] );
	}

	public function test_execute_tool_get_checkout_action(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		global $mock_wc;
		$this->create_mock_product( 11, 'Hat', '5.00' );
		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 11, 2 );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_checkout_action', array() );

		$this->assertIsArray( $result );
		$this->assertSame( 'http://example.com/checkout/', $result['checkout_url'] );
		$this->assertTrue( $result['has_cart'] );
		$this->assertSame( 2, $result['item_count'] );
	}

	public function test_execute_tool_search_products(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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

	public function test_execute_tool_get_cart_contents(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		global $mock_wc;
		$mock_wc = new MockWooCommerce();

		$this->create_mock_product( 7, 'Cart Test Product', '12.50' );
		$mock_wc->get_persisted_cart()->add_to_cart( 7, 2 );

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_cart_contents', array() );

		$this->assertIsArray( $result );
		$this->assertFalse( $result['is_empty'] );
		$this->assertSame( 2, $result['item_count'] );
		$this->assertSame( '$25.00', $result['total'] );
		$this->assertSame( 'Cart Test Product', $result['items'][0]['name'] );
	}

	public function test_execute_tool_compare_products(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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

	public function test_get_system_prompt_mentions_cart_tool_for_cart_questions(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'get_cart_contents', $prompt );
		$this->assertStringContainsString( 'current cart questions', $prompt );
	}

	public function test_get_system_prompt_includes_product_page_context(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat(
			array(
				'page_type'  => 'product',
				'title'      => 'Blue Widget',
				'url'        => 'http://example.com/product/blue-widget/',
				'post_id'    => 42,
				'post_type'  => 'product',
				'product_id' => 42,
			)
		);
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'Current page context', $prompt );
		$this->assertStringContainsString( 'get_product_details', $prompt );
		$this->assertStringContainsString( '"product_id":42', $prompt );
	}

	public function test_get_system_prompt_includes_singular_page_context_guidance(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat(
			array(
				'page_type' => 'singular',
				'title'     => 'Refund Policy',
				'url'       => 'http://example.com/refund-policy/',
				'post_id'   => 15,
				'post_type' => 'page',
			)
		);
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'get_page_content', $prompt );
		$this->assertStringContainsString( '"post_id":15', $prompt );
	}

	public function test_get_system_prompt_includes_category_page_context_guidance(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat(
			array(
				'page_type' => 'product_category',
				'title'     => 'Shirts',
				'url'       => 'http://example.com/product-category/shirts/',
				'term_id'   => 7,
				'taxonomy'  => 'product_cat',
				'term_slug' => 'shirts',
				'term_name' => 'Shirts',
			)
		);
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'category filter', $prompt );
		$this->assertStringContainsString( '"term_slug":"shirts"', $prompt );
	}

	public function test_get_system_prompt_includes_cart_page_context_guidance(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat(
			array(
				'page_type' => 'cart',
				'title'     => 'Cart',
				'url'       => 'http://example.com/cart/',
				'post_id'   => 21,
				'post_type' => 'page',
			)
		);
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'Current page context', $prompt );
		$this->assertStringContainsString( 'get_cart_contents', $prompt );
		$this->assertStringContainsString( '"page_type":"cart"', $prompt );
	}

	public function test_get_system_prompt_uses_default_when_whitespace_only(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
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

	public function test_get_system_prompt_neutral_tone_is_no_op(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'  => '',
				'model'           => 'gpt-5-mini',
				'tone_of_voice'   => 'neutral',
				'system_prompt'   => '',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringNotContains( 'Adjust only tone and wording.', $prompt );
	}

	public function test_get_system_prompt_includes_friendly_tone_instruction(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
				'tone_of_voice'  => 'friendly',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'friendly, warm, conversational, approachable tone', $prompt );
	}

	public function test_get_system_prompt_places_tone_after_custom_prompt_and_before_operational_instructions(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
				'system_prompt'  => 'You are a custom bot for my store.',
				'tone_of_voice'  => 'professional',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$custom_prompt_pos = strpos( $prompt, 'You are a custom bot for my store.' );
		$tone_pos          = strpos( $prompt, 'Adjust only tone and wording.' );
		$tools_pos         = strpos( $prompt, 'When presenting product search or comparison results' );

		$this->assertNotFalse( $custom_prompt_pos );
		$this->assertNotFalse( $tone_pos );
		$this->assertNotFalse( $tools_pos );
		$this->assertGreaterThan( $custom_prompt_pos, $tone_pos );
		$this->assertGreaterThan( $tone_pos, $tools_pos );
	}

	public function test_get_system_prompt_enthusiastic_tone_stays_non_pushy(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
				'tone_of_voice'  => 'enthusiastic',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContains( 'do not become pushy or more proactive', $prompt );
	}

	public function test_format_messages_handles_tool_messages(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools = $method->invoke( $chat );

		$this->assertIsArray( $tools );
		$tool_names = array_map( fn( $t ) => $t['function']['name'], $tools );
		$this->assertNotContains( 'search_products', $tool_names );
		$this->assertNotContains( 'get_cart_contents', $tool_names );
		$this->assertContains( 'search_site_content', $tool_names );
		$this->assertContains( 'get_page_content', $tool_names );
	}

	public function test_execute_tool_returns_error_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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

	public function test_system_prompt_includes_guided_shopping_flow_for_generic_queries(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'For broad shopping-discovery asks', $prompt );
		$this->assertStringContainsString( 'call get_categories first', $prompt );
		$this->assertStringContainsString( 'top 3-5 categories sorted by highest count', $prompt );
		$this->assertStringContainsString( 'ask one short clarifying question', $prompt );
		$this->assertStringContainsString( 'Do not call search_products until the user gives direction', $prompt );
	}

	public function test_system_prompt_includes_off_topic_redirection_clause(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'OFF-TOPIC REDIRECTION', $prompt );
		$this->assertStringContainsString( 'After politely answering or declining any non-shopping question', $prompt );
		$this->assertStringContainsString( 'ALWAYS end with a short, natural shopping-related follow-up', $prompt );
		$this->assertStringContainsString( 'not pushy or templated', $prompt );
	}

	public function test_system_prompt_pairs_categories_with_products_for_gift_queries(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'GIFT AND RECOMMENDATION QUERIES', $prompt );
		$this->assertStringContainsString( 'do not stop at category names', $prompt );
		$this->assertStringContainsString( 'call search_products once per category', $prompt );
	}

	public function test_system_prompt_includes_what_do_you_sell_context_rules(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'call get_categories first', $prompt );
		$this->assertStringContainsString( 'For "what do you sell?", after category guidance you may use search_site_content and get_page_content', $prompt );
		$this->assertStringContainsString( 'If context is missing, say so and do not invent claims', $prompt );
	}

	public function test_system_prompt_enforces_strict_product_grounding(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'STRICT PRODUCT GROUNDING', $prompt );
		$this->assertStringContainsString( 'state ONLY facts present in the tool output', $prompt );
		$this->assertStringContainsString( 'say explicitly that you do not have that information', $prompt );
		$this->assertStringContainsString( 'NEVER fill gaps using general or brand knowledge', $prompt );
		$this->assertStringContainsString( '"typically"', $prompt );
	}

	public function test_system_prompt_no_products_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
				'system_prompt'  => '',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringNotContains( 'Help customers find products and answer questions.', $prompt );
		$this->assertStringNotContains( 'Use tools to search products when asked.', $prompt );
		$this->assertStringContains( 'helpful assistant', $prompt );
		$this->assertStringContainsString( 'WooCommerce product tools are unavailable', $prompt );
		$this->assertStringNotContainsString( 'For broad shopping requests like "show me products"', $prompt );
	}

	public function test_system_prompt_with_custom_prompt_still_uses_non_woocommerce_fallback(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
				'system_prompt'  => 'You are a custom assistant for this website.',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'You are a custom assistant for this website.', $prompt );
		$this->assertStringContainsString( 'WooCommerce product tools are unavailable', $prompt );
		$this->assertStringNotContainsString( 'For broad shopping requests like "show me products"', $prompt );
	}

	public function test_handle_tool_calls_stream_emits_tool_input_available(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
				'model'          => 'gpt-5-mini',
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
		$chat = new WPAIC_Chat( array(), $this->create_provider_license_manager() );
		$this->assertTrue( $chat->is_provider_mode() );
	}

	public function test_is_provider_mode_false_when_url_missing(): void {
		$chat = new WPAIC_Chat( array(), $this->create_provider_license_manager( '', true ) );
		$this->assertFalse( $chat->is_provider_mode() );
	}

	public function test_is_provider_mode_false_when_site_key_missing(): void {
		$chat = new WPAIC_Chat( array(), $this->create_provider_license_manager( 'https://provider.example.com/wp-json/wpaip/v1/chat', false ) );
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
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'openai_api_key' => '' ) );

		$chat   = new WPAIC_Chat( array(), $this->create_provider_license_manager() );
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
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'openai_api_key' => '' ) );

		$chat          = new WPAIC_Chat( array(), $this->create_provider_license_manager() );
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

		// Create a temporary SSE stream file (Responses API events).
		$sse_content  = "data: " . json_encode( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hello' ) ) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => ' world' ) ) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array( 'event' => 'response.completed', 'data' => array() ) ) . "\n\n";
		$sse_content .= "data: [DONE]\n\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $temp_file, $sse_content );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'file://' . $temp_file,
			'test-key',
			array( 'input' => array() ),
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

	public function test_stream_from_provider_keeps_zero_only_text_chunks(): void {
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

		$sse_content  = "data: " . json_encode( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Founded in 201' ) ) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => '0' ) ) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array( 'event' => 'response.completed', 'data' => array() ) ) . "\n\n";
		$sse_content .= "data: [DONE]\n\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $temp_file, $sse_content );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'file://' . $temp_file,
			'test-key',
			array( 'input' => array() ),
			function ( $data ) use ( &$chunks ) {
				$chunks[] = $data;
			}
		);

		unlink( $temp_file );

		$this->assertCount( 2, $chunks );
		$this->assertEquals( array( 'content' => 'Founded in 201' ), $chunks[0] );
		$this->assertEquals( array( 'content' => '0' ), $chunks[1] );
		$this->assertEmpty( $result );
	}

	public function test_to_responses_tools_flattens_function_shape(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_responses_tools' );
		$method->setAccessible( true );

		$nested = array(
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'search_products',
					'description' => 'Search products',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array( 'search' => array( 'type' => 'string' ) ),
					),
				),
			),
			array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_categories',
					'description' => 'Get all product categories',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			),
		);

		$flat = $method->invoke( $chat, $nested );

		$this->assertSame( 'function', $flat[0]['type'] );
		$this->assertSame( 'search_products', $flat[0]['name'] );
		$this->assertSame( 'Search products', $flat[0]['description'] );
		$this->assertArrayHasKey( 'parameters', $flat[0] );
		$this->assertArrayNotHasKey( 'function', $flat[0] );
		$this->assertFalse( $flat[0]['strict'] );
		// No-arg tool keeps its empty stdClass properties (so it serializes to {}).
		$this->assertInstanceOf( \stdClass::class, $flat[1]['parameters']['properties'] );
	}

	public function test_build_responses_input_maps_roles_and_content(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'build_responses_input' );
		$method->setAccessible( true );

		$input = $method->invoke(
			$chat,
			array(
				array( 'role' => 'user', 'content' => 'Hi' ),
				array( 'role' => 'assistant', 'content' => 'Hello!' ),
				array( 'role' => 'weird', 'content' => 'coerced' ),
			)
		);

		$this->assertSame( array( 'role' => 'user', 'content' => 'Hi' ), $input[0] );
		$this->assertSame( array( 'role' => 'assistant', 'content' => 'Hello!' ), $input[1] );
		$this->assertSame( 'user', $input[2]['role'] );
	}

	public function test_should_emit_stream_content_keeps_zero_string(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'should_emit_stream_content' );
		$method->setAccessible( true );

		$this->assertTrue( $method->invoke( $chat, '0' ) );
		$this->assertTrue( $method->invoke( $chat, 'Hello' ) );
		$this->assertFalse( $method->invoke( $chat, '' ) );
		$this->assertFalse( $method->invoke( $chat, null ) );
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
			'event' => 'response.output_item.added',
			'data'  => array(
				'output_index' => 0,
				'item'         => array(
					'type'      => 'function_call',
					'id'        => 'fc_item_1',
					'call_id'   => 'call_abc123',
					'name'      => 'search_products',
					'arguments' => '',
				),
			),
		) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array(
			'event' => 'response.function_call_arguments.delta',
			'data'  => array(
				'item_id' => 'fc_item_1',
				'delta'   => '{"search":"shoes"}',
			),
		) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array(
			'event' => 'response.function_call_arguments.done',
			'data'  => array(
				'item_id'   => 'fc_item_1',
				'arguments' => '{"search":"shoes"}',
			),
		) ) . "\n\n";
		$sse_content .= "data: " . json_encode( array( 'event' => 'response.completed', 'data' => array() ) ) . "\n\n";
		$sse_content .= "data: [DONE]\n\n";

		$temp_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $temp_file, $sse_content );

		$chunks = array();
		$result = $method->invoke(
			$chat,
			'file://' . $temp_file,
			'test-key',
			array( 'input' => array() ),
			function ( $data ) use ( &$chunks ) {
				$chunks[] = $data;
			}
		);

		unlink( $temp_file );

		$this->assertArrayHasKey( 'tool_calls', $result );
		$this->assertCount( 1, $result['tool_calls'] );
		$this->assertEquals( 'call_abc123', $result['tool_calls'][0]['call_id'] );
		$this->assertEquals( 'search_products', $result['tool_calls'][0]['name'] );
		$this->assertEquals( '{"search":"shoes"}', $result['tool_calls'][0]['arguments'] );

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
		$this->assertStringStartsWith( 'Failed to connect to provider', $result['error'] );
	}

	public function test_get_provider_http_error_message_uses_rest_error_message(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_provider_http_error_message' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$chat,
			403,
			wp_json_encode(
				array(
					'code'    => 'rest_forbidden',
					'message' => 'License is expired, cancelled, or blocked.',
					'data'    => array( 'status' => 403 ),
				)
			)
		);

		$this->assertSame( 'License is expired, cancelled, or blocked.', $result );
	}

	public function test_provider_completion_loop_stops_at_max_iterations(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'provider_completion_loop' );
		$method->setAccessible( true );

		$max_const = $reflection->getReflectionConstant( 'MAX_PROVIDER_ITERATIONS' );
		$max_value = $max_const->getValue();

		$collected_events = array();
		$callback         = function ( $data ) use ( &$collected_events ) {
			$collected_events[] = $data;
		};

		// Call with iteration = max to trigger the guard immediately
		$method->invoke( $chat, array(), array(), 'gpt-5-mini', $callback, $max_value );

		$this->assertCount( 1, $collected_events );
		$this->assertArrayHasKey( 'error', $collected_events[0] );
		$this->assertStringContainsString( 'too many processing steps', $collected_events[0]['error'] );
	}

	public function test_provider_completion_loop_allows_normal_iterations(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'provider_url'      => 'https://provider.example.com/wp-json/wpaip/v1/chat',
				'provider_site_key' => 'test-key',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );

		$max_const = $reflection->getReflectionConstant( 'MAX_PROVIDER_ITERATIONS' );
		$max_value = $max_const->getValue();
		$this->assertEquals( 10, $max_value );

		// Calling at iteration < max should NOT trigger the guard (it will attempt provider connection and fail)
		$method = $reflection->getMethod( 'provider_completion_loop' );
		$method->setAccessible( true );

		$collected_events = array();
		$callback         = function ( $data ) use ( &$collected_events ) {
			$collected_events[] = $data;
		};

		$method->invoke( $chat, array(), array(), 'gpt-5-mini', $callback, 0 );

		// Should get a connection error (not max iterations error)
		$has_max_iterations_error = false;
		foreach ( $collected_events as $event ) {
			if ( isset( $event['error'] ) && str_contains( $event['error'], 'too many processing steps' ) ) {
				$has_max_iterations_error = true;
			}
		}
		$this->assertFalse( $has_max_iterations_error, 'iteration 0 should not trigger max iterations guard' );
	}

	public function test_provider_mode_client_not_initialized(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'openai_api_key' => 'this-key-should-be-ignored' ) );

		$chat       = new WPAIC_Chat( array(), $this->create_provider_license_manager() );
		$reflection = new ReflectionClass( $chat );
		$property   = $reflection->getProperty( 'client' );
		$property->setAccessible( true );

		// In provider mode, the OpenAI client should NOT be initialized
		$this->assertNull( $property->getValue( $chat ) );
	}

	public function test_provider_request_body_omits_model_and_reasoning_effort(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key' => '',
				'model'          => 'gpt-5-mini',
			)
		);

		$license_manager = new class extends WPAIC_License_Manager {
			/** @var array<string, mixed>|null */
			public ?array $captured_body = null;

			public function __construct() {}

			public function is_provider_url_configured(): bool {
				return true;
			}

			public function has_provider_auth(): bool {
				return true;
			}

			public function get_provider_url(): string {
				return 'https://provider.example.invalid/wp-json/wpaip/v1/chat';
			}

			public function get_provider_request_headers( array $body ): array {
				$this->captured_body = $body;
				return array( 'X-WPAIC-Test' => '1' );
			}
		};

		$chat = new WPAIC_Chat( array(), $license_manager );
		$chat->send_stream(
			array( array( 'role' => 'user', 'content' => 'Hello' ) ),
			function () {}
		);

		$this->assertIsArray( $license_manager->captured_body );
		$this->assertArrayHasKey( 'input', $license_manager->captured_body );
		$this->assertArrayHasKey( 'instructions', $license_manager->captured_body );
		$this->assertSame( 'user', $license_manager->captured_body['input'][0]['role'] );
		$this->assertSame( 'Hello', $license_manager->captured_body['input'][0]['content'] );
		$this->assertArrayNotHasKey( 'messages', $license_manager->captured_body );
		$this->assertArrayNotHasKey( 'model', $license_manager->captured_body );
		$this->assertArrayNotHasKey( 'reasoning_effort', $license_manager->captured_body );
	}

	private function create_provider_license_manager( string $provider_url = 'https://provider.example.com/wp-json/wpaip/v1/chat', bool $has_auth = true ): WPAIC_License_Manager {
		return new class( $provider_url, $has_auth ) extends WPAIC_License_Manager {
			public function __construct( private string $provider_url, private bool $has_auth ) {}

			public function is_provider_url_configured(): bool {
				return '' !== $this->provider_url;
			}

			public function has_provider_auth(): bool {
				return $this->has_auth;
			}

			public function get_provider_url(): string {
				return $this->provider_url;
			}

			public function get_provider_request_headers( array $body ): array {
				if ( ! $this->has_auth ) {
					return array();
				}

				return array(
					'Content-Type'                  => 'application/json',
					'X-WPAIC-FS-Install-Id'         => '123',
					'X-WPAIC-FS-Install-Public-Key' => 'pk_test',
					'X-WPAIC-Timestamp'             => (string) time(),
					'X-WPAIC-Signature'             => 'sig_test',
				);
			}
		};
	}

	public function test_handoff_tool_includes_optional_fields_when_configured(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'  => '',
				'model'           => 'gpt-5-mini',
				'handoff_enabled' => true,
				'handoff_fields'  => array( 'phone_number', 'company' ),
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools      = $method->invoke( $chat );
		$tool_names = array_map( fn( $t ) => $t['function']['name'], $tools );
		$this->assertContains( 'create_handoff_request', $tool_names );

		$handoff_tool = null;
		foreach ( $tools as $tool ) {
			if ( 'create_handoff_request' === $tool['function']['name'] ) {
				$handoff_tool = $tool;
				break;
			}
		}

		$properties = $handoff_tool['function']['parameters']['properties'];
		$required   = $handoff_tool['function']['parameters']['required'];

		$this->assertArrayHasKey( 'phone_number', $properties );
		$this->assertArrayHasKey( 'company', $properties );
		$this->assertArrayNotHasKey( 'order_number', $properties );
		$this->assertArrayNotHasKey( 'request_message', $properties );
		$this->assertContains( 'phone_number', $required );
		$this->assertContains( 'company', $required );
	}

	public function test_handoff_tool_has_no_optional_fields_by_default(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'  => '',
				'model'           => 'gpt-5-mini',
				'handoff_enabled' => true,
				'handoff_fields'  => array(),
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools        = $method->invoke( $chat );
		$handoff_tool = null;
		foreach ( $tools as $tool ) {
			if ( 'create_handoff_request' === $tool['function']['name'] ) {
				$handoff_tool = $tool;
				break;
			}
		}

		$properties = $handoff_tool['function']['parameters']['properties'];

		$this->assertArrayHasKey( 'customer_name', $properties );
		$this->assertArrayHasKey( 'customer_email', $properties );
		$this->assertArrayHasKey( 'conversation_summary', $properties );
		$this->assertArrayNotHasKey( 'phone_number', $properties );
		$this->assertArrayNotHasKey( 'company', $properties );
		$this->assertArrayNotHasKey( 'order_number', $properties );
		$this->assertArrayNotHasKey( 'request_message', $properties );
	}

	public function test_handoff_instruction_includes_selected_optional_fields(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'  => '',
				'model'           => 'gpt-5-mini',
				'handoff_enabled' => true,
				'handoff_fields'  => array( 'phone_number', 'request_message' ),
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_handoff_instruction' );
		$method->setAccessible( true );

		$instruction = $method->invoke( $chat );

		$this->assertStringContainsString( 'phone number', $instruction );
		$this->assertStringContainsString( 'describing their issue', $instruction );
		$this->assertStringNotContainsString( 'company', $instruction );
		$this->assertStringNotContainsString( 'order number', $instruction );
	}

	public function test_handoff_instruction_only_name_email_when_no_optional_fields(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'openai_api_key'  => '',
				'model'           => 'gpt-5-mini',
				'handoff_enabled' => true,
				'handoff_fields'  => array(),
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_handoff_instruction' );
		$method->setAccessible( true );

		$instruction = $method->invoke( $chat );

		$this->assertStringContainsString( 'name', $instruction );
		$this->assertStringContainsString( 'email', $instruction );
		$this->assertStringNotContainsString( 'phone', $instruction );
		$this->assertStringNotContainsString( 'company', $instruction );
	}
}
