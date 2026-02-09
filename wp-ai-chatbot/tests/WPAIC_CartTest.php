<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-cart.php';

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

	protected function tearDown(): void {
		parent::tearDown();
		unset( $_REQUEST['product_id'], $_REQUEST['quantity'] );
	}
}
