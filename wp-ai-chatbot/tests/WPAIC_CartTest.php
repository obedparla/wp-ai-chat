<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-cart.php';
require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-logs.php';
require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-events.php';

class WPAIC_CartTest extends TestCase {
	private WPAIC_Cart $cart;

	protected function setUp(): void {
		parent::setUp();
		$this->cart = new WPAIC_Cart();
		WPAICTestHelper::reset();
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		global $mock_wc, $mock_wc_products;
		$mock_wc          = new MockWooCommerce();
		$mock_wc_products = array();
	}

	public function test_init_registers_ajax_actions(): void {
		$this->cart->init();
		$this->assertTrue( true );
	}

	public function test_ajax_add_to_cart_fails_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );

		$_REQUEST['product_id'] = 1;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'WooCommerce not active', $e->data['message'] );
		}
	}

	public function test_ajax_add_to_cart_fails_with_invalid_product_id(): void {
		$_REQUEST['product_id'] = 0;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'Invalid product ID', $e->data['message'] );
		}
	}

	public function test_ajax_add_to_cart_fails_when_product_not_found(): void {
		$_REQUEST['product_id'] = 999;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'Product not found', $e->data['message'] );
		}
	}

	public function test_ajax_add_to_cart_fails_when_product_not_purchasable(): void {
		global $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, false, true );

		$_REQUEST['product_id'] = 1;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'Product cannot be purchased', $e->data['message'] );
		}
	}

	public function test_ajax_add_to_cart_fails_when_product_out_of_stock(): void {
		global $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, true, false );

		$_REQUEST['product_id'] = 1;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'Product is out of stock', $e->data['message'] );
		}
	}

	public function test_ajax_add_to_cart_succeeds_with_valid_product(): void {
		global $mock_wc_products, $mock_wc;
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		$mock_wc             = new MockWooCommerce();

		$_REQUEST['product_id'] = 1;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertEquals( 'Product added to cart', $e->data['message'] );
			$this->assertNotEmpty( $e->data['cart_item_key'] );
			$this->assertEquals( 1, $e->data['cart_count'] );
			$this->assertNotEmpty( $e->data['cart_hash'] );
		}
	}

	public function test_ajax_add_to_cart_uses_default_quantity(): void {
		global $mock_wc_products, $mock_wc;
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		$mock_wc             = new MockWooCommerce();

		$_REQUEST['product_id'] = 1;
		unset( $_REQUEST['quantity'] );

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertEquals( 1, $e->data['cart_count'] );
		}
	}

	public function test_ajax_add_to_cart_validates_variation_stock(): void {
		global $mock_wc_products, $mock_wc;
		$mock_wc               = new MockWooCommerce();
		$mock_wc_products[10]  = new MockWCProduct( 10, true, true, 'variable' );
		$mock_wc_products[101] = new MockWCProduct( 101, true, false, 'variation', 10 );

		$_REQUEST['product_id']   = 10;
		$_REQUEST['variation_id'] = 101;
		$_REQUEST['quantity']     = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'Product is out of stock', $e->data['message'] );
		}
	}

	public function test_ajax_add_to_cart_adds_selected_variation(): void {
		global $mock_wc_products, $mock_wc;
		$mock_wc               = new MockWooCommerce();
		$mock_wc_products[10]  = new MockWCProduct( 10, true, true, 'variable' );
		$mock_wc_products[101] = new MockWCProduct( 101, true, true, 'variation', 10 );

		$_REQUEST['product_id']   = 10;
		$_REQUEST['variation_id'] = 101;
		$_REQUEST['quantity']     = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertEquals( 1, $e->data['cart_count'] );
			$cart_items = $mock_wc->get_persisted_cart()->get_cart();
			$item       = array_values( $cart_items )[0];
			$this->assertEquals( 101, $item['variation_id'] );
		}
	}

	public function test_ajax_clear_cart_fails_when_woocommerce_not_active(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );

		try {
			$this->cart->ajax_clear_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
			$this->assertEquals( 'WooCommerce not active', $e->data['message'] );
		}
	}

	public function test_ajax_clear_cart_empties_entire_cart_when_no_items(): void {
		global $mock_wc, $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		$mock_wc_products[2] = new MockWCProduct( 2, true, true );
		$mock_wc             = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 1 );
		$mock_wc->get_persisted_cart()->add_to_cart( 2, 2 );

		unset( $_REQUEST['items'] );

		try {
			$this->cart->ajax_clear_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$this->assertSame( 0, $e->data['cart_count'] );
			$this->assertSame( array(), $mock_wc->get_persisted_cart()->get_cart() );
		}
	}

	public function test_ajax_clear_cart_removes_only_requested_items(): void {
		global $mock_wc, $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		$mock_wc_products[2] = new MockWCProduct( 2, true, true );
		$mock_wc             = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 1 );
		$mock_wc->get_persisted_cart()->add_to_cart( 2, 2 );

		$_REQUEST['items'] = wp_json_encode( array( array( 'product_id' => 2, 'quantity' => 2 ) ) );

		try {
			$this->cart->ajax_clear_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$cart_items = $mock_wc->get_persisted_cart()->get_cart();
			$this->assertCount( 1, $cart_items );
			$this->assertSame( 1, array_values( $cart_items )[0]['product_id'] );
		}
	}

	public function test_ajax_clear_cart_reduces_quantity_for_partial_removal(): void {
		global $mock_wc, $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		$mock_wc             = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 5 );

		$_REQUEST['items'] = wp_json_encode( array( array( 'product_id' => 1, 'quantity' => 2 ) ) );

		try {
			$this->cart->ajax_clear_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
			$cart_items = $mock_wc->get_persisted_cart()->get_cart();
			$this->assertCount( 1, $cart_items );
			$this->assertSame( 3, array_values( $cart_items )[0]['quantity'] );
			$this->assertSame( 3, $e->data['cart_count'] );
		}
	}

	// ---- Cart-confirmation outcome events (P2-27d) ----

	private const SESSION_UUID = 'b1c0c7e2-1a2b-4c3d-8e4f-5a6b7c8d9e0f';

	private function create_conversation_for_session(): int {
		$logs = new WPAIC_Logs();
		return (int) $logs->create_conversation( self::SESSION_UUID );
	}

	/**
	 * @return array<int, object>
	 */
	private function get_cart_confirmation_events( int $conversation_id ): array {
		return array_values(
			array_filter(
				WPAIC_Events::get_for_conversation( $conversation_id ),
				static fn ( object $event ): bool => WPAIC_Events::CART_CONFIRMATION === $event->event_type
			)
		);
	}

	public function test_ajax_add_to_cart_records_completed_confirmation_event(): void {
		global $mock_wc_products;
		$conversation_id     = $this->create_conversation_for_session();
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 1,
				'post_title' => 'Classic Tee',
			)
		);

		$_REQUEST['product_id']       = 1;
		$_REQUEST['quantity']         = 1;
		$_REQUEST['wpaic_session_id'] = self::SESSION_UUID;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
		}

		$events = $this->get_cart_confirmation_events( $conversation_id );
		$this->assertCount( 1, $events );
		$this->assertSame( 'add', $events[0]->event_data['action'] );
		$this->assertSame( 'completed', $events[0]->event_data['outcome'] );
		$this->assertSame( 'Classic Tee', $events[0]->event_data['name'] );
	}

	public function test_ajax_add_to_cart_records_failed_confirmation_event_when_out_of_stock(): void {
		global $mock_wc_products;
		$conversation_id     = $this->create_conversation_for_session();
		$mock_wc_products[1] = new MockWCProduct( 1, true, false );

		$_REQUEST['product_id']       = 1;
		$_REQUEST['quantity']         = 1;
		$_REQUEST['wpaic_session_id'] = self::SESSION_UUID;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
		}

		$events = $this->get_cart_confirmation_events( $conversation_id );
		$this->assertCount( 1, $events );
		$this->assertSame( 'add', $events[0]->event_data['action'] );
		$this->assertSame( 'failed', $events[0]->event_data['outcome'] );
	}

	public function test_ajax_add_to_cart_records_no_event_without_session_id(): void {
		global $mock_wc_products;
		$conversation_id     = $this->create_conversation_for_session();
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );

		$_REQUEST['product_id'] = 1;
		$_REQUEST['quantity']   = 1;

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
		}

		$this->assertCount( 0, $this->get_cart_confirmation_events( $conversation_id ) );
	}

	public function test_ajax_add_to_cart_records_no_event_for_non_uuid_session_id(): void {
		global $mock_wc_products;
		$conversation_id     = $this->create_conversation_for_session();
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );

		$_REQUEST['product_id']       = 1;
		$_REQUEST['quantity']         = 1;
		$_REQUEST['wpaic_session_id'] = 'not-a-uuid';

		try {
			$this->cart->ajax_add_to_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
		}

		$this->assertCount( 0, $this->get_cart_confirmation_events( $conversation_id ) );
	}

	public function test_ajax_clear_cart_records_clear_confirmation_event(): void {
		global $mock_wc, $mock_wc_products;
		$conversation_id     = $this->create_conversation_for_session();
		$mock_wc_products[1] = new MockWCProduct( 1, true, true );
		$mock_wc             = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 1, 2 );

		unset( $_REQUEST['items'] );
		$_REQUEST['wpaic_session_id'] = self::SESSION_UUID;

		try {
			$this->cart->ajax_clear_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
		}

		$events = $this->get_cart_confirmation_events( $conversation_id );
		$this->assertCount( 1, $events );
		$this->assertSame( 'clear', $events[0]->event_data['action'] );
		$this->assertSame( 'completed', $events[0]->event_data['outcome'] );
	}

	public function test_ajax_clear_cart_records_remove_confirmation_event_with_names(): void {
		global $mock_wc, $mock_wc_products;
		$conversation_id     = $this->create_conversation_for_session();
		$mock_wc_products[2] = new MockWCProduct( 2, true, true );
		WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 2,
				'post_title' => 'Sparkling Water',
			)
		);
		$mock_wc = new MockWooCommerce();
		$mock_wc->get_persisted_cart()->add_to_cart( 2, 2 );

		$_REQUEST['items']            = wp_json_encode( array( array( 'product_id' => 2, 'quantity' => 2 ) ) );
		$_REQUEST['wpaic_session_id'] = self::SESSION_UUID;

		try {
			$this->cart->ajax_clear_cart();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
		}

		$events = $this->get_cart_confirmation_events( $conversation_id );
		$this->assertCount( 1, $events );
		$this->assertSame( 'remove', $events[0]->event_data['action'] );
		$this->assertSame( 'completed', $events[0]->event_data['outcome'] );
		$this->assertSame( 'Sparkling Water', $events[0]->event_data['name'] );
	}

	public function test_ajax_cart_cancelled_records_cancelled_confirmation_event(): void {
		$conversation_id = $this->create_conversation_for_session();

		$_REQUEST['cart_action']      = 'clear';
		$_REQUEST['wpaic_session_id'] = self::SESSION_UUID;

		try {
			$this->cart->ajax_cart_cancelled();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertTrue( $e->success );
		}

		$events = $this->get_cart_confirmation_events( $conversation_id );
		$this->assertCount( 1, $events );
		$this->assertSame( 'clear', $events[0]->event_data['action'] );
		$this->assertSame( 'cancelled', $events[0]->event_data['outcome'] );
	}

	public function test_ajax_cart_cancelled_rejects_invalid_action(): void {
		$conversation_id = $this->create_conversation_for_session();

		$_REQUEST['cart_action']      = 'bogus';
		$_REQUEST['wpaic_session_id'] = self::SESSION_UUID;

		try {
			$this->cart->ajax_cart_cancelled();
			$this->fail( 'Expected WPAICJsonResponseException' );
		} catch ( WPAICJsonResponseException $e ) {
			$this->assertFalse( $e->success );
		}

		$this->assertCount( 0, $this->get_cart_confirmation_events( $conversation_id ) );
	}

	protected function tearDown(): void {
		parent::tearDown();
		unset( $_REQUEST['product_id'], $_REQUEST['quantity'], $_REQUEST['variation_id'], $_REQUEST['items'], $_REQUEST['wpaic_session_id'], $_REQUEST['cart_action'] );
	}
}
