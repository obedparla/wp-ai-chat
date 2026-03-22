<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-page-context.php';

class WPAIC_PageContextTest extends TestCase {
	private WPAIC_Page_Context $page_context;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		$this->page_context = new WPAIC_Page_Context();
	}

	public function test_build_returns_product_page_context(): void {
		$product = WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 42,
				'post_title' => 'Blue Widget',
				'post_type'  => 'product',
			)
		);
		WPAICTestHelper::set_queried_object( $product );
		WPAICTestHelper::set_conditional( 'is_product', true );
		WPAICTestHelper::set_conditional( 'is_singular', true );

		$result = $this->page_context->build();

		$this->assertSame( 'product', $result['page_type'] );
		$this->assertSame( 'Blue Widget', $result['title'] );
		$this->assertSame( 'http://example.com/?p=42', $result['url'] );
		$this->assertSame( 42, $result['post_id'] );
		$this->assertSame( 'product', $result['post_type'] );
		$this->assertSame( 42, $result['product_id'] );
	}

	public function test_build_returns_product_category_context(): void {
		$term = WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 7,
				'name'     => 'Shirts',
				'slug'     => 'shirts',
				'taxonomy' => 'product_cat',
			)
		);
		WPAICTestHelper::set_queried_object( $term );
		WPAICTestHelper::set_conditional( 'is_product_category', true );

		$result = $this->page_context->build();

		$this->assertSame( 'product_category', $result['page_type'] );
		$this->assertSame( 'Shirts', $result['title'] );
		$this->assertSame( 'http://example.com/product-category/shirts/', $result['url'] );
		$this->assertSame( 7, $result['term_id'] );
		$this->assertSame( 'product_cat', $result['taxonomy'] );
		$this->assertSame( 'shirts', $result['term_slug'] );
	}

	public function test_build_returns_product_tag_context(): void {
		$term = WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 8,
				'name'     => 'Summer Sale',
				'slug'     => 'summer-sale',
				'taxonomy' => 'product_tag',
			)
		);
		WPAICTestHelper::set_queried_object( $term );
		WPAICTestHelper::set_conditional( 'is_product_tag', true );

		$result = $this->page_context->build();

		$this->assertSame( 'product_tag', $result['page_type'] );
		$this->assertSame( 'Summer Sale', $result['title'] );
		$this->assertSame( 'http://example.com/product-tag/summer-sale/', $result['url'] );
		$this->assertSame( 'product_tag', $result['taxonomy'] );
	}

	public function test_build_returns_shop_page_context(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 20,
				'post_title' => 'Shop',
				'post_type'  => 'page',
			)
		);
		WPAICTestHelper::set_wc_page_id( 'shop', 20 );
		WPAICTestHelper::set_conditional( 'is_shop', true );

		$result = $this->page_context->build();

		$this->assertSame( 'shop', $result['page_type'] );
		$this->assertSame( 'Shop', $result['title'] );
		$this->assertSame( 'http://example.com/?p=20', $result['url'] );
		$this->assertSame( 20, $result['post_id'] );
		$this->assertSame( 'page', $result['post_type'] );
	}

	public function test_build_returns_cart_page_context(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 21,
				'post_title' => 'Cart',
				'post_type'  => 'page',
			)
		);
		WPAICTestHelper::set_wc_page_id( 'cart', 21 );
		WPAICTestHelper::set_conditional( 'is_cart', true );

		$result = $this->page_context->build();

		$this->assertSame( 'cart', $result['page_type'] );
		$this->assertSame( 'Cart', $result['title'] );
		$this->assertSame( 'http://example.com/cart/', $result['url'] );
		$this->assertSame( 21, $result['post_id'] );
	}

	public function test_build_returns_checkout_page_context(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 22,
				'post_title' => 'Checkout',
				'post_type'  => 'page',
			)
		);
		WPAICTestHelper::set_wc_page_id( 'checkout', 22 );
		WPAICTestHelper::set_conditional( 'is_checkout', true );

		$result = $this->page_context->build();

		$this->assertSame( 'checkout', $result['page_type'] );
		$this->assertSame( 'Checkout', $result['title'] );
		$this->assertSame( 'http://example.com/checkout/', $result['url'] );
		$this->assertSame( 22, $result['post_id'] );
	}

	public function test_build_returns_generic_singular_page_context(): void {
		$page = WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 30,
				'post_title' => 'Refund Policy',
				'post_type'  => 'page',
			)
		);
		WPAICTestHelper::set_queried_object( $page );
		WPAICTestHelper::set_conditional( 'is_singular', true );

		$result = $this->page_context->build();

		$this->assertSame( 'singular', $result['page_type'] );
		$this->assertSame( 'Refund Policy', $result['title'] );
		$this->assertSame( 'http://example.com/?p=30', $result['url'] );
		$this->assertSame( 30, $result['post_id'] );
		$this->assertSame( 'page', $result['post_type'] );
	}

	public function test_build_returns_other_page_context_and_strips_query_args(): void {
		$_SERVER['REQUEST_URI'] = '/summer-sale/?utm_source=test';

		$result = $this->page_context->build();

		$this->assertSame( 'other', $result['page_type'] );
		$this->assertSame( 'Current page', $result['title'] );
		$this->assertSame( 'http://example.com/summer-sale/', $result['url'] );
	}
}
