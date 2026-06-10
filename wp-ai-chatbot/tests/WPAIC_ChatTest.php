<?php
/**
 * Tests for WPAIC_Chat class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-tools.php';
require_once __DIR__ . '/../includes/class-wpaic-product-tools.php';
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-page-context.php';
require_once __DIR__ . '/../includes/class-wpaic-system-prompt.php';
require_once __DIR__ . '/../includes/class-wpaic-events.php';
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

	public function test_send_stream_errors_when_provider_auth_missing(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model' => 'gpt-5-mini',
			)
		);

		$chat           = new WPAIC_Chat( array(), $this->create_provider_license_manager( 'https://provider.example.com/wp-json/wpaip/v1/chat', false ) );
		$collected_data = array();

		$chat->send_stream(
			array( array( 'role' => 'user', 'content' => 'Hello' ) ),
			function ( $data ) use ( &$collected_data ) {
				$collected_data[] = $data;
			}
		);

		$this->assertCount( 1, $collected_data );
		$this->assertArrayHasKey( 'error', $collected_data[0] );
		$this->assertEquals( 'Provider authentication is not available for this site.', $collected_data[0]['error'] );
	}

	public function test_tool_definitions_structure(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools = $method->invoke( $chat );

		$this->assertCount( 14, $tools );

		$tool_names = array_map( fn( $t ) => $t['function']['name'], $tools );
		$this->assertContains( 'search_products', $tool_names );
		$this->assertContains( 'add_to_cart', $tool_names );
		$this->assertContains( 'clear_cart', $tool_names );
		$this->assertContains( 'get_popular_products', $tool_names );
		$this->assertContains( 'get_product_details', $tool_names );
		$this->assertContains( 'get_categories', $tool_names );
		$this->assertContains( 'get_cart_contents', $tool_names );
		$this->assertContains( 'get_checkout_action', $tool_names );
		$this->assertContains( 'compare_products', $tool_names );
		$this->assertContains( 'get_order_status', $tool_names );
		$this->assertContains( 'get_shipping_info', $tool_names );
		$this->assertContains( 'get_active_promotions', $tool_names );
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
		$this->assertArrayHasKey( 'on_sale', $search_properties );
		$this->assertSame( 'boolean', $search_properties['on_sale']['type'] );
		$this->assertArrayHasKey( 'limit', $search_properties );
	}

	public function test_get_active_promotions_tool_uses_empty_object_properties_schema(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();

		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_tool_definitions' );
		$method->setAccessible( true );

		$tools           = $method->invoke( $chat );
		$promotions_tool = array_values( array_filter( $tools, fn( $t ) => $t['function']['name'] === 'get_active_promotions' ) )[0];

		$this->assertSame( 'object', $promotions_tool['function']['parameters']['type'] );
		$this->assertInstanceOf( stdClass::class, $promotions_tool['function']['parameters']['properties'] );
	}

	public function test_get_cart_contents_tool_uses_empty_object_properties_schema(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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

	public function test_execute_tool_get_product_details_returns_error_for_nonexistent(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$result = $method->invoke( $chat, 'get_product_details', array( 'product_id' => 999 ) );

		$this->assertSame( array( 'error' => 'Product not found' ), $result );
	}

	public function test_execute_tool_converts_throwable_into_error_result(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );

		// Simulate a third-party-plugin fatal inside a WooCommerce tool call.
		$throwing_tools = new class() extends WPAIC_Tools {
			public function get_cart_contents(): array {
				throw new Error( 'Call to undefined function third_party_plugin_hook()' );
			}
		};
		$tools_property = $reflection->getProperty( 'tools' );
		$tools_property->setAccessible( true );
		$tools_property->setValue( $chat, $throwing_tools );

		$method = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		$previous_error_log = ini_set( 'error_log', '/dev/null' );
		try {
			$result = $method->invoke( $chat, 'get_cart_contents', array() );
		} finally {
			ini_set( 'error_log', (string) $previous_error_log );
		}

		$this->assertSame( array( 'error' => 'Tool execution failed unexpectedly.' ), $result );
	}

	public function test_get_system_prompt_uses_custom_when_set(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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

	public function test_get_system_prompt_includes_ordinal_and_comparison_rules(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'ORDINAL AND POSITIONAL REFERENCES', $prompt );
		$this->assertStringContainsString( 'Products shown (display order)', $prompt );
		$this->assertStringContainsString( 'COMPARISON ACCURACY', $prompt );
		$this->assertStringContainsString( 'differences summary', $prompt );
	}

	public function test_get_system_prompt_includes_leak_guard_count_alignment_and_disambiguation_rules(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'INTERNAL ONLY', $prompt );
		$this->assertStringContainsString( 'never write "Products shown"', $prompt );
		$this->assertStringContainsString( 'CARD AND TEXT COUNT ALIGNMENT', $prompt );
		$this->assertStringContainsString( 'at most 6 product cards', $prompt );
		$this->assertStringContainsString( 'DISAMBIGUATION WITH CARDS', $prompt );
		$this->assertStringContainsString( 'SAME reply as your one short clarifying question', $prompt );
	}

	public function test_get_system_prompt_includes_product_page_context(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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
		$tools_pos         = strpos( $prompt, 'When presenting product search, recommendation, or comparison results' );

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

	public function test_tool_definitions_empty_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'BROAD DISCOVERY ONLY WHEN GENUINELY VAGUE', $prompt );
		$this->assertStringContainsString( 'call get_categories first', $prompt );
		$this->assertStringContainsString( 'mention the top 3-5 categories by their highest count', $prompt );
		$this->assertStringContainsString( 'Do NOT use this broad path when the message already names a concrete product', $prompt );
	}

	public function test_system_prompt_includes_off_topic_redirection_clause(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'OFF-TOPIC REDIRECTION', $prompt );
		$this->assertStringContainsString( 'After politely answering or declining a non-shopping question', $prompt );
		$this->assertStringContainsString( 'you MAY add one short, natural shopping-related follow-up', $prompt );
		$this->assertStringContainsString( 'never pushy, templated, or forced', $prompt );
	}

	public function test_system_prompt_pairs_categories_with_products_for_gift_queries(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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
		$this->assertStringContainsString( 'call search_products once per chosen category', $prompt );
	}

	public function test_system_prompt_includes_what_do_you_sell_context_rules(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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

	public function test_system_prompt_routes_best_sellers_to_popular_products_tool(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'BEST SELLER AND POPULARITY QUERIES', $prompt );
		$this->assertStringContainsString( 'call get_popular_products', $prompt );
		$this->assertStringContainsString( 'Do NOT answer these with a category list', $prompt );
		// Tie-breaker: popularity + concrete product type prefers get_popular_products with a
		// category slug, but get_popular_products has no free-text keyword, so fall back to search.
		$this->assertStringContainsString( 'TIE-BREAKER WHEN POPULARITY MEETS A PRODUCT TYPE', $prompt );
		$this->assertStringContainsString( 'has NO free-text keyword', $prompt );
		$this->assertStringContainsString( 'fall back to search_products with that product type as the search keyword', $prompt );
	}

	public function test_system_prompt_enforces_strict_category_grounding(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'STRICT CATEGORY GROUNDING', $prompt );
		$this->assertStringContainsString( 'Only ever name categories that appear in the get_categories output', $prompt );
		$this->assertStringContainsString( 'NEVER by its slug', $prompt );
		$this->assertStringContainsString( 'do not claim a "clothing" category exists', $prompt );
	}

	public function test_system_prompt_maps_budget_to_price_filters(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'BUDGET AND PRICE FILTERS', $prompt );
		$this->assertStringContainsString( 'pass it to search_products using min_price and/or max_price', $prompt );
	}

	public function test_system_prompt_searches_immediately_for_concrete_requests(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'SEARCH IMMEDIATELY FOR CONCRETE REQUESTS', $prompt );
		$this->assertStringContainsString( 'call search_products right away with that as the search keyword', $prompt );
	}

	public function test_system_prompt_instructs_translating_search_keywords(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'SEARCH KEYWORD LANGUAGE', $prompt );
		$this->assertStringContainsString( 'translate the search keyword', $prompt );
		// Translate-before-search must be imperative and up-front, never translate brands,
		// and carry the concrete Spanish running-shoes example.
		$this->assertStringContainsString( 'on the FIRST search', $prompt );
		$this->assertStringContainsString( 'NEVER translate brand names, model numbers, or SKUs', $prompt );
		$this->assertStringContainsString( '"zapatos para correr" should search the keyword "running shoes"', $prompt );
	}

	public function test_system_prompt_includes_catalog_language_instruction(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option( 'test_locale', 'es_ES' );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( "The store's product catalog is written primarily in Spanish", $prompt );
		$this->assertStringContainsString( 'follow the SEARCH KEYWORD LANGUAGE rule above', $prompt );
		$this->assertStringContainsString( 'translate generic keywords into Spanish', $prompt );
	}

	public function test_system_prompt_branches_checkout_on_empty_cart(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'If has_cart is true (item_count is 1 or more)', $prompt );
		$this->assertStringContainsString( 'If has_cart is false or item_count is 0, do NOT say checkout is ready', $prompt );
		$this->assertStringContainsString( 'let the user know their cart is empty', $prompt );
		$this->assertStringContainsString( 'offer to help them find something to add first', $prompt );
	}

	public function test_system_prompt_instructs_add_to_cart_via_tool(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'ADD-TO-CART INTENT', $prompt );
		$this->assertStringContainsString( 'use the add_to_cart tool', $prompt );
		$this->assertStringContainsString( 'you MUST pass the chosen variation_id', $prompt );
		$this->assertStringContainsString( 'do NOT guess and do NOT call add_to_cart', $prompt );
		$this->assertStringContainsString( 'never type out any add-to-cart or cart URL', $prompt );
	}

	public function test_system_prompt_instructs_clear_cart_via_tool(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'CLEAR-CART AND REMOVE-ITEM INTENT', $prompt );
		$this->assertStringContainsString( 'use the clear_cart tool', $prompt );
		$this->assertStringContainsString( 'do NOT claim the cart was cleared', $prompt );
	}

	public function test_system_prompt_preserves_strict_shipping_grounding(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'STRICT SHIPPING GROUNDING', $prompt );
		$this->assertStringContainsString( 'first call get_shipping_info for site-wide policy', $prompt );
		$this->assertStringContainsString( 'NEVER invent delivery durations like "3 to 7 business days"', $prompt );
		$this->assertStringContainsString( 'has_shipping_configured=false', $prompt );
		// Uncovered destinations must route to the shipping policy page, never to
		// "isn't configured" dev-speak or a flat denial.
		$this->assertStringContainsString( 'no listed zone covers the shopper\'s destination', $prompt );
		$this->assertStringContainsString( 'call search_site_content with "shipping"', $prompt );
		// The model must FETCH the policy page and quote real rates, not just
		// reference the page while claiming costs are unavailable.
		$this->assertStringContainsString( 'call get_page_content on the best matching result', $prompt );
		$this->assertStringContainsString( 'quote the concrete rates, thresholds, and conditions', $prompt );
	}

	public function test_system_prompt_routes_discount_questions_to_get_active_promotions(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'DISCOUNTS AND PROMOTIONS', $prompt );
		$this->assertStringContainsString( 'call get_active_promotions', $prompt );
		$this->assertStringContainsString( 'tell the shopper honestly that there are no current promotions', $prompt );
		$this->assertStringContainsString( 'NEVER invent, guess, or hint at codes', $prompt );
		$this->assertStringContainsString( 'call search_products with on_sale set to true', $prompt );
	}

	public function test_system_prompt_allows_single_related_suggestion_after_add(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'If the add_to_cart result includes related_products', $prompt );
		$this->assertStringContainsString( 'never more than one suggestion, never pushy', $prompt );
	}

	public function test_system_prompt_grounds_pivot_suggestions_in_real_categories(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'The same applies to pivots and alternative suggestions', $prompt );
		$this->assertStringContainsString( 'categories or product types that get_categories actually returned', $prompt );
		$this->assertStringContainsString( 'do not offer pet items unless such a category exists', $prompt );
	}

	public function test_system_prompt_forbids_asserting_catalog_absence_on_zero_results(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'ZERO-RESULT HANDLING', $prompt );
		$this->assertStringContainsString( 'NEVER tell the shopper the store does not carry', $prompt );
		$this->assertStringContainsString( 'closest matches', $prompt );
		$this->assertStringContainsString( 'one keyword miss is not proof of absence', $prompt );
	}

	public function test_system_prompt_enforces_strict_product_grounding(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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

	/**
	 * Provider tool loop end-to-end through send_stream: the first provider
	 * response carries a function_call, the chatbot executes it locally and
	 * emits the frontend tool events (card URLs intact), then the second
	 * response streams the final text.
	 */
	public function test_send_stream_executes_provider_tool_calls_and_emits_events(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'model' => 'gpt-5-mini' ) );

		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$tool_call_sse  = 'data: ' . json_encode(
			array(
				'event' => 'response.output_item.added',
				'data'  => array(
					'output_index' => 0,
					'item'         => array(
						'type'      => 'function_call',
						'id'        => 'fc_item_1',
						'call_id'   => 'call_123',
						'name'      => 'search_products',
						'arguments' => '',
					),
				),
			)
		) . "\n\n";
		$tool_call_sse .= 'data: ' . json_encode(
			array(
				'event' => 'response.function_call_arguments.done',
				'data'  => array(
					'item_id'   => 'fc_item_1',
					'arguments' => '{}',
				),
			)
		) . "\n\n";
		$tool_call_sse .= "data: [DONE]\n\n";

		$final_sse  = 'data: ' . json_encode(
			array(
				'event' => 'response.output_text.delta',
				'data'  => array( 'delta' => 'Here you go' ),
			)
		) . "\n\n";
		$final_sse .= "data: [DONE]\n\n";

		$first_file  = tempnam( sys_get_temp_dir(), 'sse_test_' );
		$second_file = tempnam( sys_get_temp_dir(), 'sse_test_' );
		file_put_contents( $first_file, $tool_call_sse );
		file_put_contents( $second_file, $final_sse );

		// First loop iteration streams the tool call, the follow-up iteration streams the final text.
		$license_manager = new class( 'file://' . $first_file, 'file://' . $second_file ) extends WPAIC_License_Manager {
			private int $request_count = 0;

			public function __construct( private string $first_url, private string $second_url ) {}

			public function is_provider_url_configured(): bool {
				return true;
			}

			public function has_provider_auth(): bool {
				return true;
			}

			public function get_provider_url(): string {
				++$this->request_count;
				return 1 === $this->request_count ? $this->first_url : $this->second_url;
			}

			public function get_provider_request_headers( array $body ): array {
				return array( 'X-WPAIC-Test' => '1' );
			}
		};

		$chat             = new WPAIC_Chat( array(), $license_manager );
		$collected_events = array();

		$chat->send_stream(
			array( array( 'role' => 'user', 'content' => 'Show products' ) ),
			function ( $data ) use ( &$collected_events ) {
				$collected_events[] = $data;
			}
		);

		unlink( $first_file );
		unlink( $second_file );

		$event_types = array();
		foreach ( $collected_events as $event ) {
			$event_types[] = array_key_first( $event );
		}

		$input_index  = array_search( 'tool_input_available', $event_types, true );
		$output_index = array_search( 'tool_output_available', $event_types, true );

		$this->assertContains( 'tool_input_start', $event_types );
		$this->assertNotFalse( $input_index );
		$this->assertNotFalse( $output_index );
		$this->assertLessThan( $output_index, $input_index, 'tool_input_available should come before tool_output_available' );

		$input_event = $collected_events[ $input_index ]['tool_input_available'];
		$this->assertEquals( 'call_123', $input_event['toolCallId'] );
		$this->assertEquals( 'search_products', $input_event['toolName'] );

		// The frontend copy keeps the card URLs; only the model copy is slimmed.
		$output_event = $collected_events[ $output_index ]['tool_output_available'];
		$this->assertEquals( 'call_123', $output_event['toolCallId'] );
		$this->assertIsArray( $output_event['output'] );
		$this->assertCount( 1, $output_event['output'] );
		$this->assertEquals( 'Test Product', $output_event['output'][0]['name'] );
		$this->assertArrayHasKey( 'url', $output_event['output'][0] );
		$this->assertArrayHasKey( 'add_to_cart_url', $output_event['output'][0] );

		$this->assertContains( array( 'content' => 'Here you go' ), $collected_events );
		$this->assertContains( array( 'done' => true ), $collected_events );
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

	public function test_build_responses_input_emits_product_context_as_system_item(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'build_responses_input' );
		$method->setAccessible( true );

		$input = $method->invoke(
			$chat,
			array(
				array( 'role' => 'user', 'content' => 'Show me tongs' ),
				array(
					'role'            => 'assistant',
					'content'         => 'Here are a few options:',
					'product_context' => 'Products shown (display order): 1. Red Tongs (id 417, price 5.98)',
				),
			)
		);

		$this->assertCount( 3, $input );
		// Assistant text must stay free of the product context summary.
		$this->assertSame( array( 'role' => 'assistant', 'content' => 'Here are a few options:' ), $input[1] );
		$this->assertSame( 'system', $input[2]['role'] );
		$this->assertStringContainsString( 'Internal context', $input[2]['content'] );
		$this->assertStringContainsString( 'Products shown (display order): 1. Red Tongs (id 417, price 5.98)', $input[2]['content'] );
	}

	public function test_build_responses_input_skips_empty_product_context(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'build_responses_input' );
		$method->setAccessible( true );

		$input = $method->invoke(
			$chat,
			array(
				array(
					'role'            => 'assistant',
					'content'         => 'Hello!',
					'product_context' => '',
				),
			)
		);

		$this->assertCount( 1, $input );
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

	public function test_provider_request_body_omits_model_and_reasoning_effort(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
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
		$settings = array(
			'model'           => 'gpt-5-mini',
			'handoff_enabled' => true,
			'handoff_fields'  => array( 'phone_number', 'request_message' ),
		);

		$system_prompt = new WPAIC_System_Prompt( $settings );
		$reflection    = new ReflectionClass( $system_prompt );
		$method        = $reflection->getMethod( 'get_handoff_instruction' );
		$method->setAccessible( true );

		$instruction = $method->invoke( $system_prompt );

		$this->assertStringContainsString( 'phone number', $instruction );
		$this->assertStringContainsString( 'describing their issue', $instruction );
		$this->assertStringNotContainsString( 'company', $instruction );
		$this->assertStringNotContainsString( 'order number', $instruction );
	}

	public function test_handoff_instruction_only_name_email_when_no_optional_fields(): void {
		$settings = array(
			'model'           => 'gpt-5-mini',
			'handoff_enabled' => true,
			'handoff_fields'  => array(),
		);

		$system_prompt = new WPAIC_System_Prompt( $settings );
		$reflection    = new ReflectionClass( $system_prompt );
		$method        = $reflection->getMethod( 'get_handoff_instruction' );
		$method->setAccessible( true );

		$instruction = $method->invoke( $system_prompt );

		$this->assertStringContainsString( 'name', $instruction );
		$this->assertStringContainsString( 'email', $instruction );
		$this->assertStringNotContainsString( 'phone', $instruction );
		$this->assertStringNotContainsString( 'company', $instruction );
	}

	public function test_build_responses_input_trims_to_last_twenty_messages(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'build_responses_input' );
		$method->setAccessible( true );

		$messages = array();
		for ( $i = 1; $i <= 25; $i++ ) {
			$messages[] = array( 'role' => 'user', 'content' => "Message {$i}" );
		}

		$input = $method->invoke( $chat, $messages );

		$this->assertCount( 20, $input );
		$this->assertSame( 'Message 6', $input[0]['content'] );
		$this->assertSame( 'Message 25', $input[19]['content'] );
	}

	public function test_to_model_payload_strips_urls_and_image_from_product_lists(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			array(
				'id'              => 1,
				'name'            => 'Test Product',
				'url'             => 'http://example.com/product/test/',
				'add_to_cart_url' => 'http://example.com/cart/?add-to-cart=1',
				'image'           => 'http://example.com/image.jpg',
				'external_url'    => 'http://other-site.example.com/buy',
				'price'           => '19.99',
			),
		);

		$model_payload = $method->invoke( $chat, 'search_products', $tool_result );

		$this->assertArrayNotHasKey( 'url', $model_payload[0] );
		$this->assertArrayNotHasKey( 'add_to_cart_url', $model_payload[0] );
		$this->assertArrayNotHasKey( 'image', $model_payload[0] );
		$this->assertArrayNotHasKey( 'external_url', $model_payload[0] );
		$this->assertSame( 'Test Product', $model_payload[0]['name'] );
		$this->assertSame( '19.99', $model_payload[0]['price'] );
	}

	public function test_to_model_payload_leaves_frontend_tool_result_untouched(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			array(
				'id'    => 1,
				'name'  => 'Test Product',
				'url'   => 'http://example.com/product/test/',
				'image' => 'http://example.com/image.jpg',
			),
		);

		$frontend_json = wp_json_encode( $tool_result );

		$method->invoke( $chat, 'search_products', $tool_result );

		$this->assertSame( $frontend_json, wp_json_encode( $tool_result ) );
	}

	public function test_to_model_payload_collapses_variations_for_product_details(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			'id'         => 5,
			'name'       => 'Hoodie',
			'url'        => 'http://example.com/product/hoodie/',
			'variations' => array(
				array(
					'variation_id'  => 51,
					'attributes'    => array(
						'attribute_pa_color' => 'blue',
						'attribute_size'     => 'L',
					),
					'price'         => 45.0,
					'regular_price' => 50.0,
					'is_in_stock'   => true,
					'image'         => 'http://example.com/hoodie-blue.jpg',
				),
			),
		);

		$model_payload = $method->invoke( $chat, 'get_product_details', $tool_result );

		$this->assertArrayNotHasKey( 'url', $model_payload );
		$this->assertSame(
			array(
				'variation_id' => 51,
				'attributes'   => 'color: blue, size: L',
				'price'        => 45.0,
				'is_in_stock'  => true,
			),
			$model_payload['variations'][0]
		);
	}

	public function test_to_model_payload_prefers_variation_attribute_labels(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			'id'         => 5,
			'name'       => 'Hoodie',
			'variations' => array(
				array(
					'variation_id'     => 51,
					'attributes'       => array( 'attribute_pa_color' => 'navy-blue' ),
					'attribute_labels' => array( 'attribute_pa_color' => 'Navy Blue' ),
					'price'            => 45.0,
					'is_in_stock'      => true,
				),
			),
		);

		$model_payload = $method->invoke( $chat, 'get_product_details', $tool_result );

		$this->assertSame( 'color: Navy Blue', $model_payload['variations'][0]['attributes'] );
	}

	public function test_to_model_payload_humanizes_attribute_options_and_drops_labels_map(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			'id'         => 5,
			'name'       => 'Hoodie',
			'attributes' => array(
				array(
					'name'          => 'pa_color',
					'label'         => 'Color',
					'options'       => array( 'navy-blue', 'red' ),
					'option_labels' => array(
						'navy-blue' => 'Navy Blue',
						'red'       => 'Red',
					),
				),
			),
		);

		$model_payload = $method->invoke( $chat, 'get_product_details', $tool_result );

		$this->assertSame( array( 'Navy Blue', 'Red' ), $model_payload['attributes'][0]['options'] );
		$this->assertArrayNotHasKey( 'option_labels', $model_payload['attributes'][0] );
		// Frontend copy keeps the slug options and labels map untouched.
		$this->assertSame( array( 'navy-blue', 'red' ), $tool_result['attributes'][0]['options'] );
		$this->assertArrayHasKey( 'option_labels', $tool_result['attributes'][0] );
	}

	public function test_to_model_payload_slims_compare_products_entries(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			'products'   => array(
				array(
					'id'              => 1,
					'name'            => 'Product A',
					'url'             => 'http://example.com/product/a/',
					'add_to_cart_url' => 'http://example.com/cart/?add-to-cart=1',
					'image'           => 'http://example.com/a.jpg',
					'rating'          => 4.0,
				),
			),
			'attributes' => array( 'price' ),
		);

		$model_payload = $method->invoke( $chat, 'compare_products', $tool_result );

		$this->assertArrayNotHasKey( 'url', $model_payload['products'][0] );
		$this->assertArrayNotHasKey( 'add_to_cart_url', $model_payload['products'][0] );
		$this->assertArrayNotHasKey( 'image', $model_payload['products'][0] );
		$this->assertSame( 4.0, $model_payload['products'][0]['rating'] );
		$this->assertSame( array( 'price' ), $model_payload['attributes'] );
	}

	public function test_to_model_payload_keeps_compare_differences_and_attributes(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			'products'    => array(
				array(
					'id'         => 1,
					'name'       => 'Product A',
					'url'        => 'http://example.com/product/a/',
					'image'      => 'http://example.com/a.jpg',
					'price'      => '19.99',
					'attributes' => array(
						'Color'    => 'Blue, Red',
						'Warranty' => '3 years',
					),
					'weight'     => '1.5 kg',
					'dimensions' => '10 x 5 cm',
				),
				array(
					'id'    => 2,
					'name'  => 'Product B',
					'url'   => 'http://example.com/product/b/',
					'price' => '29.99',
				),
			),
			'attributes'  => array( 'price', 'rating' ),
			'differences' => array(
				'Price: Product A is cheapest at 19.99; Product B is most expensive at 29.99 (10.00 difference).',
			),
		);

		$model_payload = $method->invoke( $chat, 'compare_products', $tool_result );

		$this->assertSame( $tool_result['differences'], $model_payload['differences'] );
		$this->assertSame(
			array(
				'Color'    => 'Blue, Red',
				'Warranty' => '3 years',
			),
			$model_payload['products'][0]['attributes']
		);
		$this->assertSame( '1.5 kg', $model_payload['products'][0]['weight'] );
		$this->assertSame( '10 x 5 cm', $model_payload['products'][0]['dimensions'] );
		$this->assertArrayNotHasKey( 'url', $model_payload['products'][0] );
		$this->assertArrayNotHasKey( 'image', $model_payload['products'][0] );
		$this->assertArrayNotHasKey( 'url', $model_payload['products'][1] );
	}

	public function test_to_model_payload_strips_checkout_and_cart_urls(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			'checkout_url' => 'http://example.com/checkout/',
			'cart_url'     => 'http://example.com/cart/',
			'has_cart'     => true,
			'item_count'   => 2,
		);

		$model_payload = $method->invoke( $chat, 'get_checkout_action', $tool_result );

		$this->assertArrayNotHasKey( 'checkout_url', $model_payload );
		$this->assertArrayNotHasKey( 'cart_url', $model_payload );
		$this->assertTrue( $model_payload['has_cart'] );
		$this->assertSame( 2, $model_payload['item_count'] );
	}

	public function test_to_model_payload_keeps_site_content_urls_for_citation(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$tool_result = array(
			array(
				'post_id' => 3,
				'title'   => 'Shipping Policy',
				'url'     => 'http://example.com/shipping/',
				'snippet' => 'We ship worldwide.',
			),
		);

		$model_payload = $method->invoke( $chat, 'search_site_content', $tool_result );

		$this->assertSame( $tool_result, $model_payload );
	}

	public function test_to_model_payload_passes_null_through(): void {
		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'to_model_payload' );
		$method->setAccessible( true );

		$this->assertNull( $method->invoke( $chat, 'get_product_details', null ) );
	}

	public function test_system_prompt_caps_faq_injection_at_thirty_pairs(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		global $wpdb;
		for ( $i = 1; $i <= 35; $i++ ) {
			$wpdb->insert(
				'wp_wpaic_faqs',
				array(
					'question' => "Question {$i}?",
					'answer'   => "Answer {$i}.",
				)
			);
		}

		$chat       = new WPAIC_Chat();
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'get_system_prompt' );
		$method->setAccessible( true );

		$prompt = $method->invoke( $chat );

		$this->assertStringContainsString( 'Question 1?', $prompt );
		$this->assertStringContainsString( 'Question 30?', $prompt );
		$this->assertStringNotContainsString( 'Question 31?', $prompt );
		$this->assertStringNotContainsString( 'Question 35?', $prompt );
	}

	// --- Tool event recording tests (P1-14) ---

	private function execute_tool_on( WPAIC_Chat $chat, string $name, array $arguments ): mixed {
		$reflection = new ReflectionClass( $chat );
		$method     = $reflection->getMethod( 'execute_tool' );
		$method->setAccessible( true );

		return $method->invoke( $chat, $name, $arguments );
	}

	public function test_execute_tool_search_products_records_search_and_products_shown_events(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);
		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$chat = new WPAIC_Chat();
		$chat->set_conversation_id( 3 );
		$this->execute_tool_on( $chat, 'search_products', array( 'search' => 'test' ) );

		$events = WPAIC_Events::get_for_conversation( 3 );

		$this->assertCount( 2, $events );
		$this->assertEquals( WPAIC_Events::SEARCH_PERFORMED, $events[0]->event_type );
		$this->assertEquals( 'test', $events[0]->event_data['query'] );
		$this->assertEquals( 1, $events[0]->event_data['result_count'] );
		$this->assertEquals( WPAIC_Events::PRODUCTS_SHOWN, $events[1]->event_type );
		$this->assertEquals( array( 1 ), $events[1]->event_data['ids'] );
		$this->assertEquals( array( 'Test Product' ), $events[1]->event_data['names'] );
	}

	public function test_execute_tool_search_products_records_zero_result_search_without_products_shown(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();
		$chat->set_conversation_id( 3 );
		$this->execute_tool_on( $chat, 'search_products', array( 'search' => 'nothing matches' ) );

		$events = WPAIC_Events::get_for_conversation( 3 );

		$this->assertCount( 1, $events );
		$this->assertEquals( WPAIC_Events::SEARCH_PERFORMED, $events[0]->event_type );
		$this->assertEquals( 0, $events[0]->event_data['result_count'] );
	}

	public function test_execute_tool_records_no_events_without_conversation_id(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);
		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$chat = new WPAIC_Chat();
		$this->execute_tool_on( $chat, 'search_products', array( 'search' => 'test' ) );

		global $wpdb;
		$this->assertSame( array(), $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_events WHERE conversation_id = 0" ) );
		$this->assertSame( array(), WPAIC_Events::get_for_conversation( 3 ) );
	}

	public function test_execute_tool_add_to_cart_records_product_added_event_with_price(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);
		$this->create_mock_product( 11, 'Hat', '5.00' );

		$chat = new WPAIC_Chat();
		$chat->set_conversation_id( 4 );
		$result = $this->execute_tool_on( $chat, 'add_to_cart', array( 'product_id' => 11 ) );

		$this->assertTrue( $result['success'] );

		$events = WPAIC_Events::get_for_conversation( 4 );

		$this->assertCount( 1, $events );
		$this->assertEquals( WPAIC_Events::PRODUCT_ADDED_TO_CART, $events[0]->event_type );
		$this->assertEquals( 11, $events[0]->event_data['id'] );
		$this->assertEquals( 'Hat', $events[0]->event_data['name'] );
		$this->assertEquals( '5.00', $events[0]->event_data['price'] );
	}

	public function test_execute_tool_add_to_cart_failure_records_no_event(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();
		$chat->set_conversation_id( 4 );
		$result = $this->execute_tool_on( $chat, 'add_to_cart', array( 'product_id' => 999 ) );

		$this->assertFalse( $result['success'] );
		$this->assertSame( array(), WPAIC_Events::get_for_conversation( 4 ) );
	}

	public function test_execute_tool_get_checkout_action_records_checkout_started(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();
		$chat->set_conversation_id( 5 );
		$this->execute_tool_on( $chat, 'get_checkout_action', array() );

		$events = WPAIC_Events::get_for_conversation( 5 );

		$this->assertCount( 1, $events );
		$this->assertEquals( WPAIC_Events::CHECKOUT_STARTED, $events[0]->event_type );
	}

	public function test_execute_tool_create_handoff_request_links_conversation_and_records_event(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		$chat = new WPAIC_Chat();
		$chat->set_conversation_id( 6 );
		$result = $this->execute_tool_on(
			$chat,
			'create_handoff_request',
			array(
				'customer_name'        => 'Jane Doe',
				'customer_email'       => 'jane@example.com',
				'conversation_summary' => 'Needs a human',
			)
		);

		$this->assertTrue( $result['success'] );

		global $wpdb;
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 6, $rows[0]->conversation_id );

		$events = WPAIC_Events::get_for_conversation( 6 );
		$this->assertCount( 1, $events );
		$this->assertEquals( WPAIC_Events::HANDOFF_CREATED, $events[0]->event_type );
		$this->assertEquals( $result['request_id'], $events[0]->event_data['request_id'] );
	}

	public function test_execute_tool_get_active_promotions_returns_coupons(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array(
				'model'          => 'gpt-5-mini',
			)
		);

		WPAICTestHelper::add_mock_post(
			array(
				'ID'          => 41,
				'post_title'  => 'SAVE10',
				'post_type'   => 'shop_coupon',
				'post_status' => 'publish',
			)
		);
		WPAICTestHelper::set_post_meta( 41, 'discount_type', 'percent' );
		WPAICTestHelper::set_post_meta( 41, 'coupon_amount', '10' );

		$chat   = new WPAIC_Chat();
		$result = $this->execute_tool_on( $chat, 'get_active_promotions', array() );

		$this->assertTrue( $result['has_promotions'] );
		$this->assertSame( 'SAVE10', $result['promotions'][0]['code'] );
	}
}
