<?php
/**
 * Tests for WPAIC_Tools class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-search-index.php';
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-tools.php';

class WPAIC_ToolsTest extends TestCase {
	private WPAIC_Tools $tools;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		global $mock_wc, $mock_wc_products;
		$mock_wc          = new MockWooCommerce();
		$mock_wc_products = array();
		$this->tools      = new WPAIC_Tools();
	}

	protected function tearDown(): void {
		global $mock_wc, $mock_wc_products;
		$mock_wc          = null;
		$mock_wc_products = array();
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_get_cart_contents_returns_empty_cart(): void {
		$result = $this->tools->get_cart_contents();

		$this->assertFalse( isset( $result['error'] ) );
		$this->assertTrue( $result['is_empty'] );
		$this->assertSame( 0, $result['item_count'] );
		$this->assertSame( '$0.00', $result['subtotal'] );
		$this->assertSame( '$0.00', $result['total'] );
		$this->assertSame( array(), $result['items'] );
	}

	public function test_get_cart_contents_returns_items_and_totals(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Hat', '10.00' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 2 );
		$mock_wc->get_persisted_cart()->add_to_cart( 2, 1 );

		$result = $this->tools->get_cart_contents();

		$this->assertFalse( $result['is_empty'] );
		$this->assertSame( 3, $result['item_count'] );
		$this->assertSame( '$49.98', $result['subtotal'] );
		$this->assertSame( '$49.98', $result['total'] );
		$this->assertCount( 2, $result['items'] );
		$this->assertSame( 1, $result['items'][0]['product_id'] );
		$this->assertSame( 'Red Shirt', $result['items'][0]['name'] );
		$this->assertSame( 2, $result['items'][0]['quantity'] );
		$this->assertSame( '$39.98', $result['items'][0]['line_total'] );
		$this->assertSame( 'Blue Hat', $result['items'][1]['name'] );
		$this->assertSame( '$10.00', $result['items'][1]['line_total'] );
	}

	public function test_get_cart_contents_initializes_cart_when_missing(): void {
		global $mock_wc;

		$this->create_mock_product( 3, 'Delayed Cart Product', '15.00' );

		$mock_wc = new MockWooCommerce( false, true );
		$mock_wc->get_persisted_cart()->add_to_cart( 3, 2 );

		$result = $this->tools->get_cart_contents();

		$this->assertFalse( $result['is_empty'] );
		$this->assertSame( 2, $result['item_count'] );
		$this->assertSame( '$30.00', $result['total'] );
		$this->assertSame( 'Delayed Cart Product', $result['items'][0]['name'] );
	}

	public function test_get_cart_contents_strips_html_from_totals(): void {
		global $mock_wc;

		$this->create_mock_product( 4, 'HTML Total Product', '15.00' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 4, 2 );
		$mock_wc->get_persisted_cart()->set_return_html_totals( true );

		$result = $this->tools->get_cart_contents();

		$this->assertSame( '$30.00', $result['subtotal'] );
		$this->assertSame( '$30.00', $result['total'] );
		$this->assertSame( '$30.00', $result['items'][0]['line_total'] );
		$this->assertStringNotContainsString( '<', $result['subtotal'] );
		$this->assertStringNotContainsString( '<', $result['items'][0]['line_total'] );
	}

	public function test_get_cart_contents_returns_error_when_cart_unavailable(): void {
		global $mock_wc;

		$mock_wc = new MockWooCommerce( false, false );

		$result = $this->tools->get_cart_contents();

		$this->assertSame( 'Cart unavailable', $result['error'] );
	}

	public function test_get_checkout_action_returns_urls_when_cart_empty(): void {
		$result = $this->tools->get_checkout_action();

		$this->assertSame( 'http://example.com/checkout/', $result['checkout_url'] );
		$this->assertNotSame( '', $result['cart_url'] );
		$this->assertFalse( $result['has_cart'] );
		$this->assertSame( 0, $result['item_count'] );
	}

	public function test_get_checkout_action_reports_cart_state(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Shoes', '20.00' );
		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 3 );

		$result = $this->tools->get_checkout_action();

		$this->assertTrue( $result['has_cart'] );
		$this->assertSame( 3, $result['item_count'] );
		$this->assertSame( 'http://example.com/checkout/', $result['checkout_url'] );
	}

	public function test_get_order_status_returns_error_when_order_number_missing(): void {
		$result = $this->tools->get_order_status( array( 'email' => 'test@example.com' ) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	public function test_get_order_status_returns_error_when_email_missing(): void {
		$result = $this->tools->get_order_status( array( 'order_number' => '123' ) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'required', $result['error'] );
	}

	public function test_get_order_status_returns_error_for_nonexistent_order(): void {
		$result = $this->tools->get_order_status(
			array(
				'order_number' => '999',
				'email'        => 'test@example.com',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_get_order_status_returns_error_when_email_does_not_match(): void {
		$this->create_mock_order(
			'123',
			'customer@example.com',
			'processing',
			99.99,
			array()
		);

		$result = $this->tools->get_order_status(
			array(
				'order_number' => '123',
				'email'        => 'wrong@example.com',
			)
		);

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'not found', $result['error'] );
	}

	public function test_get_order_status_returns_order_info_on_success(): void {
		$items = array(
			new WC_Order_Item( array( 'name' => 'Test Product', 'quantity' => 2 ) ),
		);
		$this->create_mock_order(
			'456',
			'customer@example.com',
			'completed',
			49.99,
			$items,
			'Standard Shipping'
		);

		$result = $this->tools->get_order_status(
			array(
				'order_number' => '456',
				'email'        => 'customer@example.com',
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertEquals( '456', $result['order_number'] );
		$this->assertEquals( 'Completed', $result['status'] );
		$this->assertEquals( '$49.99', $result['total'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertEquals( 'Test Product', $result['items'][0]['name'] );
		$this->assertEquals( 2, $result['items'][0]['quantity'] );
		$this->assertEquals( 'Standard Shipping', $result['shipping_method'] );
	}

	public function test_get_order_status_email_comparison_is_case_insensitive(): void {
		$this->create_mock_order(
			'789',
			'Customer@Example.COM',
			'processing',
			29.99,
			array()
		);

		$result = $this->tools->get_order_status(
			array(
				'order_number' => '789',
				'email'        => 'customer@example.com',
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertEquals( '789', $result['order_number'] );
	}

	public function test_get_order_status_includes_tracking_when_available(): void {
		$this->create_mock_order(
			'111',
			'customer@example.com',
			'completed',
			99.99,
			array(),
			'Express',
			array(
				'_tracking_number' => 'TRACK123456',
				'_tracking_url'    => 'https://tracking.example.com/TRACK123456',
			)
		);

		$result = $this->tools->get_order_status(
			array(
				'order_number' => '111',
				'email'        => 'customer@example.com',
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertEquals( 'TRACK123456', $result['tracking_number'] );
		$this->assertEquals( 'https://tracking.example.com/TRACK123456', $result['tracking_url'] );
	}

	public function test_get_order_status_omits_tracking_when_not_available(): void {
		$this->create_mock_order(
			'222',
			'customer@example.com',
			'processing',
			59.99,
			array()
		);

		$result = $this->tools->get_order_status(
			array(
				'order_number' => '222',
				'email'        => 'customer@example.com',
			)
		);

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertArrayNotHasKey( 'tracking_number', $result );
		$this->assertArrayNotHasKey( 'tracking_url', $result );
	}

	// --- Handoff tests ---

	public function test_create_handoff_request_returns_error_when_name_missing(): void {
		$result = $this->tools->create_handoff_request( array(
			'customer_email'       => 'test@example.com',
			'conversation_summary' => 'Need help',
		) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'name', $result['error'] );
	}

	public function test_create_handoff_request_returns_error_when_email_missing(): void {
		$result = $this->tools->create_handoff_request( array(
			'customer_name'        => 'John',
			'conversation_summary' => 'Need help',
		) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'email', $result['error'] );
	}

	public function test_create_handoff_request_returns_error_for_invalid_email(): void {
		$result = $this->tools->create_handoff_request( array(
			'customer_name'        => 'John',
			'customer_email'       => 'not-an-email',
			'conversation_summary' => 'Need help',
		) );

		$this->assertArrayHasKey( 'error', $result );
		$this->assertStringContainsString( 'email', $result['error'] );
	}

	public function test_create_handoff_request_succeeds_with_valid_data(): void {
		$result = $this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Customer needs help with order',
		) );

		$this->assertArrayNotHasKey( 'error', $result );
		$this->assertTrue( $result['success'] );
		$this->assertArrayHasKey( 'request_id', $result );
		$this->assertStringContainsString( 'contact you shortly', $result['message'] );
	}

	public function test_create_handoff_request_inserts_row_in_db(): void {
		global $wpdb;

		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs product info',
		) );

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );

		$row = $rows[0];
		$this->assertEquals( 'Jane Doe', $row->customer_name );
		$this->assertEquals( 'jane@example.com', $row->customer_email );
		$this->assertEquals( 'Needs product info', $row->transcript );
		$this->assertEquals( 'new', $row->status );
	}

	public function test_create_handoff_request_sends_admin_email(): void {
		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help with shipping',
		) );

		$mail = WPAICTestHelper::get_option( 'test_last_mail' );
		$this->assertNotNull( $mail );
		$this->assertStringContainsString( 'Jane Doe', $mail['subject'] );
		$this->assertStringContainsString( 'jane@example.com', $mail['message'] );
		$this->assertStringContainsString( 'Needs help with shipping', $mail['message'] );
	}

	public function test_create_handoff_request_status_is_new(): void {
		global $wpdb;

		$result = $this->tools->create_handoff_request( array(
			'customer_name'        => 'Bob',
			'customer_email'       => 'bob@example.com',
			'conversation_summary' => 'Question about returns',
		) );

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 'new', $rows[0]->status );
		$this->assertEquals( $result['request_id'], $rows[0]->id );
	}

	public function test_create_handoff_request_stores_extra_fields_in_db(): void {
		global $wpdb;

		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help',
			'phone_number'         => '555-1234',
			'company'              => 'Acme Corp',
		) );

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );

		$extra = json_decode( $rows[0]->extra_fields, true );
		$this->assertEquals( '555-1234', $extra['phone_number'] );
		$this->assertEquals( 'Acme Corp', $extra['company'] );
		$this->assertArrayNotHasKey( 'order_number', $extra );
	}

	public function test_create_handoff_request_extra_fields_null_when_none_provided(): void {
		global $wpdb;

		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help',
		) );

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );
		$this->assertNull( $rows[0]->extra_fields );
	}

	public function test_create_handoff_request_email_includes_extra_fields(): void {
		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help',
			'phone_number'         => '555-1234',
			'order_number'         => 'ORD-789',
		) );

		$mail = WPAICTestHelper::get_option( 'test_last_mail' );
		$this->assertNotNull( $mail );
		$this->assertStringContainsString( '555-1234', $mail['message'] );
		$this->assertStringContainsString( 'ORD-789', $mail['message'] );
		$this->assertStringContainsString( 'Phone', $mail['message'] );
		$this->assertStringContainsString( 'Order Number', $mail['message'] );
	}

	public function test_create_handoff_request_stores_conversation_id(): void {
		global $wpdb;

		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help',
			'conversation_id'      => 42,
		) );

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );
		$this->assertEquals( 42, $rows[0]->conversation_id );
	}

	public function test_create_handoff_request_conversation_id_null_when_absent(): void {
		global $wpdb;

		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help',
		) );

		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$this->assertCount( 1, $rows );
		$this->assertNull( $rows[0]->conversation_id );
	}

	public function test_create_handoff_request_conversation_id_not_in_extra_fields(): void {
		global $wpdb;

		$this->tools->create_handoff_request( array(
			'customer_name'        => 'Jane Doe',
			'customer_email'       => 'jane@example.com',
			'conversation_summary' => 'Needs help',
			'conversation_id'      => 42,
			'phone_number'         => '555-1234',
		) );

		$rows  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpaic_support_requests" );
		$extra = json_decode( $rows[0]->extra_fields, true );
		$this->assertArrayNotHasKey( 'conversation_id', $extra );
	}

	// --- End handoff tests ---

	// --- Content index tool tests ---

	public function test_search_site_content_returns_empty_for_empty_query(): void {
		$result = $this->tools->search_site_content( array( 'query' => '' ) );

		$this->assertEmpty( $result );
	}

	public function test_search_site_content_returns_results(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 10,
				'post_title'   => 'Shipping Policy',
				'post_content' => 'We ship worldwide with free shipping on orders over $50.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$result = $this->tools->search_site_content( array( 'query' => 'shipping' ) );

		$this->assertNotEmpty( $result );
		$this->assertEquals( 10, $result[0]['post_id'] );
		$this->assertEquals( 'Shipping Policy', $result[0]['title'] );
	}

	public function test_get_page_content_returns_error_for_nonexistent_post(): void {
		$result = $this->tools->get_page_content( array( 'post_id' => 999 ) );

		$this->assertSame( array( 'error' => 'Page not found or not available' ), $result );
	}

	public function test_get_page_content_returns_content_for_valid_post(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 20,
				'post_title'   => 'Return Policy',
				'post_content' => 'You can return items within 30 days.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$result = $this->tools->get_page_content( array( 'post_id' => 20 ) );

		$this->assertNotNull( $result );
		$this->assertEquals( 20, $result['post_id'] );
		$this->assertEquals( 'Return Policy', $result['title'] );
		$this->assertStringContainsString( 'return items', $result['content'] );
	}

	// --- End content index tool tests ---

	public function test_get_shipping_info_returns_not_configured_when_no_zones(): void {
		WPAICTestHelper::set_option( 'test_shipping_zones', array() );
		WPAICTestHelper::set_option( 'test_shipping_rest_of_world_methods', array() );

		$result = $this->tools->get_shipping_info();

		$this->assertFalse( $result['has_shipping_configured'] );
		$this->assertArrayHasKey( 'message', $result );
		// Message must stay shopper-safe (no "configured" dev-speak) and the hint
		// must steer the model to the shipping policy page instead of a denial.
		$this->assertStringNotContainsString( 'configured', $result['message'] );
		$this->assertArrayHasKey( 'hint', $result );
		$this->assertStringContainsString( 'search_site_content', $result['hint'] );
		$this->assertStringContainsString( 'shipping policy page', $result['hint'] );
	}

	public function test_get_shipping_info_returns_zones_with_methods(): void {
		WPAICTestHelper::set_option( 'woocommerce_currency', 'USD' );
		WPAICTestHelper::set_option(
			'test_shipping_zones',
			array(
				array(
					'zone_id'                 => 1,
					'zone_name'               => 'United States',
					'formatted_zone_location' => 'United States',
					'zone_locations'          => array(
						(object) array( 'type' => 'country', 'code' => 'US' ),
					),
					'shipping_methods'        => array(
						new MockShippingMethod(
							array(
								'id'    => 'flat_rate',
								'title' => 'Flat rate',
								'cost'  => '5.00',
							)
						),
						new MockShippingMethod(
							array(
								'id'         => 'free_shipping',
								'title'      => 'Free shipping',
								'min_amount' => '50.00',
								'requires'   => 'min_amount',
							)
						),
					),
				),
			)
		);
		WPAICTestHelper::set_option( 'test_shipping_rest_of_world_methods', array() );

		$result = $this->tools->get_shipping_info();

		$this->assertTrue( $result['has_shipping_configured'] );
		$this->assertSame( 'USD', $result['currency'] );
		$this->assertCount( 1, $result['zones'] );

		$zone = $result['zones'][0];
		$this->assertSame( 'United States', $zone['zone_name'] );
		$this->assertSame( 'United States', $zone['formatted_location'] );
		$this->assertSame( array( array( 'type' => 'country', 'code' => 'US' ) ), $zone['locations'] );
		$this->assertCount( 2, $zone['methods'] );

		$this->assertSame( 'flat_rate', $zone['methods'][0]['method_id'] );
		$this->assertSame( 'Flat rate', $zone['methods'][0]['title'] );
		$this->assertSame( '5.00', $zone['methods'][0]['cost'] );

		$this->assertSame( 'free_shipping', $zone['methods'][1]['method_id'] );
		$this->assertSame( '50.00', $zone['methods'][1]['min_amount'] );
		$this->assertSame( 'min_amount', $zone['methods'][1]['requires'] );
	}

	public function test_get_shipping_info_skips_disabled_methods(): void {
		WPAICTestHelper::set_option(
			'test_shipping_zones',
			array(
				array(
					'zone_id'                 => 1,
					'zone_name'               => 'EU',
					'formatted_zone_location' => 'Europe',
					'zone_locations'          => array(),
					'shipping_methods'        => array(
						new MockShippingMethod(
							array(
								'id'      => 'flat_rate',
								'title'   => 'Flat rate',
								'cost'    => '10.00',
								'enabled' => 'no',
							)
						),
						new MockShippingMethod(
							array(
								'id'    => 'local_pickup',
								'title' => 'Local pickup',
								'cost'  => '0',
							)
						),
					),
				),
			)
		);
		WPAICTestHelper::set_option( 'test_shipping_rest_of_world_methods', array() );

		$result = $this->tools->get_shipping_info();

		$this->assertTrue( $result['has_shipping_configured'] );
		$this->assertCount( 1, $result['zones'][0]['methods'] );
		$this->assertSame( 'local_pickup', $result['zones'][0]['methods'][0]['method_id'] );
	}

	public function test_get_shipping_info_drops_zones_with_no_enabled_methods(): void {
		WPAICTestHelper::set_option(
			'test_shipping_zones',
			array(
				array(
					'zone_id'                 => 1,
					'zone_name'               => 'Empty Zone',
					'formatted_zone_location' => 'Empty',
					'zone_locations'          => array(),
					'shipping_methods'        => array(
						new MockShippingMethod(
							array(
								'id'      => 'flat_rate',
								'title'   => 'Flat rate',
								'enabled' => 'no',
							)
						),
					),
				),
			)
		);
		WPAICTestHelper::set_option( 'test_shipping_rest_of_world_methods', array() );

		$result = $this->tools->get_shipping_info();

		$this->assertFalse( $result['has_shipping_configured'] );
	}

	public function test_get_shipping_info_includes_rest_of_world_zone(): void {
		WPAICTestHelper::set_option( 'test_shipping_zones', array() );
		WPAICTestHelper::set_option(
			'test_shipping_rest_of_world_methods',
			array(
				new MockShippingMethod(
					array(
						'id'    => 'flat_rate',
						'title' => 'International flat rate',
						'cost'  => '25.00',
					)
				),
			)
		);

		$result = $this->tools->get_shipping_info();

		$this->assertTrue( $result['has_shipping_configured'] );
		$this->assertCount( 1, $result['zones'] );
		$this->assertSame( 0, $result['zones'][0]['zone_id'] );
		$this->assertSame( 'Everywhere else (all destinations not covered by the zones above)', $result['zones'][0]['zone_name'] );
		$this->assertSame( 'International flat rate', $result['zones'][0]['methods'][0]['title'] );
	}

	public function test_get_shipping_info_includes_grounding_note(): void {
		WPAICTestHelper::set_option(
			'test_shipping_zones',
			array(
				array(
					'zone_id'                 => 1,
					'zone_name'               => 'US',
					'formatted_zone_location' => 'United States',
					'zone_locations'          => array(),
					'shipping_methods'        => array(
						new MockShippingMethod( array( 'id' => 'flat_rate', 'title' => 'Flat rate', 'cost' => '5.00' ) ),
					),
				),
			)
		);
		WPAICTestHelper::set_option( 'test_shipping_rest_of_world_methods', array() );

		$result = $this->tools->get_shipping_info();

		$this->assertArrayHasKey( 'notes', $result );
		$this->assertNotEmpty( $result['notes'] );
		$this->assertStringContainsString( 'processing time', $result['notes'][0] );
	}

	public function test_get_shipping_info_notes_redirect_uncovered_destinations_to_policy_page(): void {
		WPAICTestHelper::set_option(
			'test_shipping_zones',
			array(
				array(
					'zone_id'                 => 1,
					'zone_name'               => 'US',
					'formatted_zone_location' => 'United States',
					'zone_locations'          => array(),
					'shipping_methods'        => array(
						new MockShippingMethod( array( 'id' => 'flat_rate', 'title' => 'Flat rate', 'cost' => '5.00' ) ),
					),
				),
			)
		);
		WPAICTestHelper::set_option( 'test_shipping_rest_of_world_methods', array() );

		$result = $this->tools->get_shipping_info();

		$notes_text = implode( ' ', $result['notes'] );
		$this->assertStringContainsString( 'destination not covered', $notes_text );
		$this->assertStringContainsString( 'search_site_content', $notes_text );
		$this->assertStringContainsString( 'do not say the store cannot ship there', $notes_text );
	}

	/**
	 * Creates a mock WooCommerce order.
	 *
	 * @param string $order_number
	 * @param string $email
	 * @param string $status
	 * @param float $total
	 * @param array<int, WC_Order_Item> $items
	 * @param string $shipping_method
	 * @param array<string, mixed> $meta
	 */
	private function create_mock_order(
		string $order_number,
		string $email,
		string $status,
		float $total,
		array $items,
		string $shipping_method = '',
		array $meta = array()
	): void {
		WPAICTestHelper::add_mock_order(
			array(
				'id'              => $order_number,
				'order_number'    => $order_number,
				'billing_email'   => $email,
				'status'          => $status,
				'total'           => $total,
				'items'           => $items,
				'shipping_method' => $shipping_method,
				'date_created'    => '2024-01-15 10:30:00',
				'meta'            => $meta,
			)
		);
	}

	public function test_add_to_cart_returns_intent_for_simple_product(): void {
		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'add_to_cart', $result['action'] );
		$this->assertEquals( 1, $result['product_id'] );
		$this->assertEquals( 1, $result['quantity'] );
		$this->assertEquals( 'Test Product', $result['name'] );
		$this->assertArrayNotHasKey( 'variation_id', $result );
	}

	public function test_add_to_cart_respects_quantity(): void {
		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$result = $this->tools->add_to_cart(
			array(
				'product_id' => 1,
				'quantity'   => 3,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 3, $result['quantity'] );
	}

	public function test_add_to_cart_clamps_invalid_quantity_to_one(): void {
		$this->create_mock_product( 1, 'Test Product', '19.99' );

		$result = $this->tools->add_to_cart(
			array(
				'product_id' => 1,
				'quantity'   => 0,
			)
		);

		$this->assertEquals( 1, $result['quantity'] );
	}

	public function test_add_to_cart_fails_when_woocommerce_inactive(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertFalse( $result['success'] );
	}

	public function test_add_to_cart_fails_when_product_not_found(): void {
		$result = $this->tools->add_to_cart( array( 'product_id' => 999 ) );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'not_found', $result['reason'] );
	}

	public function test_add_to_cart_fails_when_not_purchasable(): void {
		global $mock_wc_products;
		$this->create_mock_product( 1, 'Test Product', '19.99' );
		$mock_wc_products[1] = new MockWCProduct( 1, false, true );

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'not_purchasable', $result['reason'] );
	}

	public function test_add_to_cart_fails_when_out_of_stock(): void {
		global $mock_wc_products;
		$this->create_mock_product( 1, 'Test Product', '19.99' );
		$mock_wc_products[1] = new MockWCProduct( 1, true, false );

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'out_of_stock', $result['reason'] );
	}

	public function test_add_to_cart_needs_variation_for_variable_without_variation_id(): void {
		global $mock_wc_products;
		$this->create_mock_product( 10, 'Cool Shirt', '20.00' );
		$mock_wc_products[10] = new MockWCProduct( 10, true, true, 'variable' );

		$result = $this->tools->add_to_cart( array( 'product_id' => 10 ) );

		$this->assertFalse( $result['success'] );
		$this->assertTrue( $result['needs_variation'] );
		$this->assertEquals( 10, $result['product_id'] );
	}

	public function test_add_to_cart_returns_intent_for_valid_variation(): void {
		global $mock_wc_products;
		$this->create_mock_product( 10, 'Cool Shirt', '20.00' );
		$mock_wc_products[10]  = new MockWCProduct( 10, true, true, 'variable' );
		$mock_wc_products[101] = new MockWCProduct( 101, true, true, 'variation', 10 );

		$result = $this->tools->add_to_cart(
			array(
				'product_id'   => 10,
				'variation_id' => 101,
			)
		);

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 10, $result['product_id'] );
		$this->assertEquals( 101, $result['variation_id'] );
	}

	public function test_add_to_cart_fails_when_variation_parent_mismatch(): void {
		global $mock_wc_products;
		$this->create_mock_product( 10, 'Cool Shirt', '20.00' );
		$mock_wc_products[10]  = new MockWCProduct( 10, true, true, 'variable' );
		$mock_wc_products[202] = new MockWCProduct( 202, true, true, 'variation', 99 );

		$result = $this->tools->add_to_cart(
			array(
				'product_id'   => 10,
				'variation_id' => 202,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'invalid_variation', $result['reason'] );
	}

	public function test_add_to_cart_fails_when_variation_out_of_stock(): void {
		global $mock_wc_products;
		$this->create_mock_product( 10, 'Cool Shirt', '20.00' );
		$mock_wc_products[10]  = new MockWCProduct( 10, true, true, 'variable' );
		$mock_wc_products[101] = new MockWCProduct( 101, true, false, 'variation', 10 );

		$result = $this->tools->add_to_cart(
			array(
				'product_id'   => 10,
				'variation_id' => 101,
			)
		);

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'out_of_stock', $result['reason'] );
	}

	public function test_clear_cart_returns_clear_all_intent_with_items(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Hat', '10.00' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 2 );
		$mock_wc->get_persisted_cart()->add_to_cart( 2, 1 );

		$result = $this->tools->clear_cart( array() );

		$this->assertTrue( $result['success'] );
		$this->assertEquals( 'clear_cart', $result['action'] );
		$this->assertTrue( $result['clear_all'] );
		$this->assertCount( 2, $result['items'] );
		$this->assertSame( 1, $result['items'][0]['product_id'] );
		$this->assertSame( 'Red Shirt', $result['items'][0]['name'] );
		$this->assertSame( 2, $result['items'][0]['remove_quantity'] );
		$this->assertTrue( $result['items'][0]['remove_all'] );
	}

	public function test_clear_cart_returns_only_requested_items(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Hat', '10.00' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 2 );
		$mock_wc->get_persisted_cart()->add_to_cart( 2, 1 );

		$result = $this->tools->clear_cart( array( 'items' => array( array( 'product_id' => 2 ) ) ) );

		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['clear_all'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 2, $result['items'][0]['product_id'] );
		$this->assertSame( 'Blue Hat', $result['items'][0]['name'] );
		$this->assertSame( 1, $result['items'][0]['remove_quantity'] );
		$this->assertTrue( $result['items'][0]['remove_all'] );
	}

	public function test_clear_cart_partial_quantity_marks_remove_all_false(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Bottled Water', '1.50' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 5 );

		$result = $this->tools->clear_cart(
			array( 'items' => array( array( 'product_id' => 1, 'quantity' => 2 ) ) )
		);

		$this->assertTrue( $result['success'] );
		$this->assertFalse( $result['clear_all'] );
		$this->assertCount( 1, $result['items'] );
		$this->assertSame( 2, $result['items'][0]['remove_quantity'] );
		$this->assertFalse( $result['items'][0]['remove_all'] );
	}

	public function test_clear_cart_quantity_at_or_above_stock_removes_all(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Bottled Water', '1.50' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 3 );

		$result = $this->tools->clear_cart(
			array( 'items' => array( array( 'product_id' => 1, 'quantity' => 10 ) ) )
		);

		$this->assertTrue( $result['success'] );
		$this->assertSame( 3, $result['items'][0]['remove_quantity'] );
		$this->assertTrue( $result['items'][0]['remove_all'] );
	}

	public function test_clear_cart_fails_when_cart_empty(): void {
		global $mock_wc;
		$mock_wc = new MockWooCommerce();

		$result = $this->tools->clear_cart( array() );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'cart_empty', $result['reason'] );
	}

	public function test_clear_cart_fails_when_requested_items_not_in_cart(): void {
		global $mock_wc;

		$this->create_mock_product( 1, 'Red Shirt', '19.99' );

		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 1 );

		$result = $this->tools->clear_cart( array( 'items' => array( array( 'product_id' => 999 ) ) ) );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'not_in_cart', $result['reason'] );
	}

	public function test_clear_cart_fails_when_woocommerce_inactive(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );

		$result = $this->tools->clear_cart( array() );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'woocommerce_inactive', $result['reason'] );
	}

	// --- Add-to-cart related product suggestions ---

	public function test_add_to_cart_includes_cross_sell_related_products(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Coffee Maker', '79.99' );
		$this->create_mock_product( 2, 'Coffee Filters', '4.99' );
		$this->create_mock_product( 3, 'Descaling Kit', '12.50' );

		$product = new MockWCProduct( 1 );
		$product->set_cross_sell_ids( array( 2, 3 ) );
		$mock_wc_products[1] = $product;

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertTrue( $result['success'] );
		$this->assertSame(
			array(
				array(
					'id'    => 2,
					'name'  => 'Coffee Filters',
					'price' => '4.99',
				),
				array(
					'id'    => 3,
					'name'  => 'Descaling Kit',
					'price' => '12.50',
				),
			),
			$result['related_products']
		);
	}

	public function test_add_to_cart_caps_related_products_at_three_and_dedupes(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Camera', '499.00' );
		$this->create_mock_product( 2, 'Memory Card', '19.99' );
		$this->create_mock_product( 3, 'Camera Bag', '39.99' );
		$this->create_mock_product( 4, 'Tripod', '59.99' );
		$this->create_mock_product( 5, 'Lens Cleaner', '9.99' );

		$product = new MockWCProduct( 1 );
		$product->set_cross_sell_ids( array( 2, 3 ) );
		$product->set_upsell_ids( array( 2, 4, 5 ) );
		$mock_wc_products[1] = $product;

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertCount( 3, $result['related_products'] );
		$this->assertSame( array( 2, 3, 4 ), array_column( $result['related_products'], 'id' ) );
	}

	public function test_add_to_cart_omits_related_products_when_none_configured(): void {
		$this->create_mock_product( 1, 'Plain Product', '10.00' );

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'related_products', $result );
	}

	public function test_add_to_cart_skips_out_of_stock_and_missing_related_products(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Laptop', '999.00' );
		$this->create_mock_product( 2, 'Laptop Sleeve', '24.99' );

		$product = new MockWCProduct( 1 );
		$product->set_cross_sell_ids( array( 2, 777 ) );
		$mock_wc_products[1] = $product;
		$mock_wc_products[2] = new MockWCProduct( 2, true, false );

		$result = $this->tools->add_to_cart( array( 'product_id' => 1 ) );

		$this->assertTrue( $result['success'] );
		$this->assertArrayNotHasKey( 'related_products', $result );
	}

	/**
	 * Creates a mock WooCommerce product with metadata.
	 */
	private function create_mock_product(
		int $id,
		string $title,
		string $price,
		string $regular_price = '',
		string $sale_price = '',
		string $content = '',
		string $excerpt = '',
		string $sku = '',
		string $stock_status = 'instock',
		string $stock_quantity = ''
	): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_excerpt' => $excerpt,
				'post_type'    => 'product',
				'post_status'  => 'publish',
			)
		);

		WPAICTestHelper::set_post_meta( $id, '_price', $price );
		WPAICTestHelper::set_post_meta( $id, '_regular_price', $regular_price ?: $price );
		WPAICTestHelper::set_post_meta( $id, '_sale_price', $sale_price );
		WPAICTestHelper::set_post_meta( $id, '_sku', $sku );
		WPAICTestHelper::set_post_meta( $id, '_stock_status', $stock_status );
		WPAICTestHelper::set_post_meta( $id, '_stock', $stock_quantity );
	}
}
