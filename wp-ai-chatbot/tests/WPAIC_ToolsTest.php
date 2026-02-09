<?php
/**
 * Tests for WPAIC_Tools class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-search-index.php';
require_once __DIR__ . '/../includes/class-wpaic-tools.php';

class WPAIC_ToolsTest extends TestCase {
	private WPAIC_Tools $tools;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		$this->tools = new WPAIC_Tools();
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_search_products_returns_empty_when_no_products(): void {
		$result = $this->tools->search_products( array() );

		$this->assertEmpty( $result );
	}

	public function test_search_products_returns_products(): void {
		$this->create_mock_product( 1, 'Test Product', '19.99' );
		$this->create_mock_product( 2, 'Another Product', '29.99' );

		$result = $this->tools->search_products( array() );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'Test Product', $result[0]['name'] );
		$this->assertEquals( 'Another Product', $result[1]['name'] );
	}

	public function test_search_products_filters_by_keyword(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Pants', '29.99' );

		$result = $this->tools->search_products( array( 'search' => 'shirt' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Red Shirt', $result[0]['name'] );
	}

	public function test_search_products_respects_limit(): void {
		$this->create_mock_product( 1, 'Product 1', '10' );
		$this->create_mock_product( 2, 'Product 2', '20' );
		$this->create_mock_product( 3, 'Product 3', '30' );

		$result = $this->tools->search_products( array( 'limit' => 2 ) );

		$this->assertCount( 2, $result );
	}

	public function test_search_products_filters_by_category(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Pants', '29.99' );
		$this->create_mock_product( 3, 'Green Hat', '9.99' );

		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'clothing', 'shirts' ) );
		WPAICTestHelper::set_post_terms( 2, 'product_cat', array( 'clothing', 'pants' ) );
		WPAICTestHelper::set_post_terms( 3, 'product_cat', array( 'accessories' ) );

		$result = $this->tools->search_products( array( 'category' => 'shirts' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Red Shirt', $result[0]['name'] );
	}

	public function test_search_products_filters_by_category_returns_multiple(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Shirt', '29.99' );
		$this->create_mock_product( 3, 'Green Hat', '9.99' );

		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'clothing', 'shirts' ) );
		WPAICTestHelper::set_post_terms( 2, 'product_cat', array( 'clothing', 'shirts' ) );
		WPAICTestHelper::set_post_terms( 3, 'product_cat', array( 'accessories' ) );

		$result = $this->tools->search_products( array( 'category' => 'shirts' ) );

		$this->assertCount( 2, $result );
	}

	public function test_search_products_filters_by_min_price(): void {
		$this->create_mock_product( 1, 'Cheap Item', '10' );
		$this->create_mock_product( 2, 'Mid Item', '50' );
		$this->create_mock_product( 3, 'Expensive Item', '100' );

		$result = $this->tools->search_products( array( 'min_price' => 50 ) );

		$this->assertCount( 2, $result );
		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertContains( 'Mid Item', $names );
		$this->assertContains( 'Expensive Item', $names );
	}

	public function test_search_products_filters_by_max_price(): void {
		$this->create_mock_product( 1, 'Cheap Item', '10' );
		$this->create_mock_product( 2, 'Mid Item', '50' );
		$this->create_mock_product( 3, 'Expensive Item', '100' );

		$result = $this->tools->search_products( array( 'max_price' => 50 ) );

		$this->assertCount( 2, $result );
		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertContains( 'Cheap Item', $names );
		$this->assertContains( 'Mid Item', $names );
	}

	public function test_search_products_filters_by_price_range(): void {
		$this->create_mock_product( 1, 'Cheap Item', '10' );
		$this->create_mock_product( 2, 'Mid Item', '50' );
		$this->create_mock_product( 3, 'Expensive Item', '100' );

		$result = $this->tools->search_products(
			array(
				'min_price' => 20,
				'max_price' => 80,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Mid Item', $result[0]['name'] );
	}

	public function test_search_products_combines_category_and_price_filter(): void {
		$this->create_mock_product( 1, 'Cheap Shirt', '10' );
		$this->create_mock_product( 2, 'Expensive Shirt', '100' );
		$this->create_mock_product( 3, 'Cheap Pants', '15' );

		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'shirts' ) );
		WPAICTestHelper::set_post_terms( 2, 'product_cat', array( 'shirts' ) );
		WPAICTestHelper::set_post_terms( 3, 'product_cat', array( 'pants' ) );

		$result = $this->tools->search_products(
			array(
				'category'  => 'shirts',
				'max_price' => 50,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Cheap Shirt', $result[0]['name'] );
	}

	public function test_search_products_returns_correct_fields(): void {
		$this->create_mock_product( 1, 'Test Product', '19.99', '24.99', '19.99' );

		$result = $this->tools->search_products( array() );

		$this->assertArrayHasKey( 'id', $result[0] );
		$this->assertArrayHasKey( 'name', $result[0] );
		$this->assertArrayHasKey( 'url', $result[0] );
		$this->assertArrayHasKey( 'price', $result[0] );
		$this->assertArrayHasKey( 'regular_price', $result[0] );
		$this->assertArrayHasKey( 'sale_price', $result[0] );

		$this->assertEquals( 1, $result[0]['id'] );
		$this->assertEquals( 'Test Product', $result[0]['name'] );
		$this->assertEquals( '19.99', $result[0]['price'] );
		$this->assertEquals( '24.99', $result[0]['regular_price'] );
		$this->assertEquals( '19.99', $result[0]['sale_price'] );
	}

	public function test_get_product_details_returns_null_for_nonexistent_product(): void {
		$result = $this->tools->get_product_details( 999 );

		$this->assertNull( $result );
	}

	public function test_get_product_details_returns_null_for_non_product_post(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'          => 1,
				'post_title'  => 'Blog Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$result = $this->tools->get_product_details( 1 );

		$this->assertNull( $result );
	}

	public function test_get_product_details_returns_detailed_product_info(): void {
		$this->create_mock_product(
			1,
			'Detailed Product',
			'49.99',
			'59.99',
			'49.99',
			'A detailed description.',
			'Short desc',
			'SKU123',
			'instock',
			'10'
		);

		$result = $this->tools->get_product_details( 1 );

		$this->assertNotNull( $result );
		$this->assertEquals( 1, $result['id'] );
		$this->assertEquals( 'Detailed Product', $result['name'] );
		$this->assertEquals( '49.99', $result['price'] );
		$this->assertEquals( 'A detailed description.', $result['description'] );
		$this->assertEquals( 'Short desc', $result['short_description'] );
		$this->assertEquals( 'SKU123', $result['sku'] );
		$this->assertEquals( 'instock', $result['stock_status'] );
		$this->assertEquals( '10', $result['stock_quantity'] );
		$this->assertArrayHasKey( 'categories', $result );
	}

	public function test_get_categories_returns_empty_when_no_categories(): void {
		$result = $this->tools->get_categories();

		$this->assertEmpty( $result );
	}

	public function test_get_categories_returns_categories(): void {
		WPAICTestHelper::add_mock_term(
			array(
				'term_id' => 1,
				'name'    => 'Clothing',
				'slug'    => 'clothing',
				'count'   => 5,
			)
		);
		WPAICTestHelper::add_mock_term(
			array(
				'term_id' => 2,
				'name'    => 'Electronics',
				'slug'    => 'electronics',
				'count'   => 10,
			)
		);

		$result = $this->tools->get_categories();

		$this->assertCount( 2, $result );
		$this->assertEquals( 'Clothing', $result[0]['name'] );
		$this->assertEquals( 'clothing', $result[0]['slug'] );
		$this->assertEquals( 5, $result[0]['count'] );
		$this->assertEquals( 'Electronics', $result[1]['name'] );
	}

	public function test_compare_products_returns_empty_when_no_ids(): void {
		$result = $this->tools->compare_products( array() );

		$this->assertArrayHasKey( 'products', $result );
		$this->assertArrayHasKey( 'attributes', $result );
		$this->assertEmpty( $result['products'] );
		$this->assertEmpty( $result['attributes'] );
	}

	public function test_compare_products_returns_empty_when_products_not_found(): void {
		$result = $this->tools->compare_products( array( 999, 888 ) );

		$this->assertArrayHasKey( 'products', $result );
		$this->assertEmpty( $result['products'] );
	}

	public function test_compare_products_returns_comparison_data(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertArrayHasKey( 'products', $result );
		$this->assertArrayHasKey( 'attributes', $result );
		$this->assertCount( 2, $result['products'] );
		$this->assertEquals( 'Product A', $result['products'][0]['name'] );
		$this->assertEquals( 'Product B', $result['products'][1]['name'] );
	}

	public function test_compare_products_includes_expected_attributes(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertContains( 'price', $result['attributes'] );
		$this->assertContains( 'stock_status', $result['attributes'] );
		$this->assertContains( 'rating', $result['attributes'] );
		$this->assertContains( 'categories', $result['attributes'] );
	}

	public function test_compare_products_limits_to_4_products(): void {
		$this->create_mock_product( 1, 'Product 1', '10' );
		$this->create_mock_product( 2, 'Product 2', '20' );
		$this->create_mock_product( 3, 'Product 3', '30' );
		$this->create_mock_product( 4, 'Product 4', '40' );
		$this->create_mock_product( 5, 'Product 5', '50' );

		$result = $this->tools->compare_products( array( 1, 2, 3, 4, 5 ) );

		$this->assertCount( 4, $result['products'] );
	}

	public function test_compare_products_includes_stock_status(): void {
		$this->create_mock_product( 1, 'In Stock Item', '19.99', '', '', '', '', '', 'instock' );
		$this->create_mock_product( 2, 'Out of Stock Item', '29.99', '', '', '', '', '', 'outofstock' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertEquals( 'instock', $result['products'][0]['stock_status'] );
		$this->assertEquals( 'outofstock', $result['products'][1]['stock_status'] );
	}

	public function test_compare_products_includes_rating(): void {
		$this->create_mock_product( 1, 'Rated Product', '19.99' );
		$this->create_mock_product( 2, 'Another Product', '29.99' );
		WPAICTestHelper::set_post_meta( 1, '_wc_average_rating', '4.5' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertCount( 2, $result['products'] );
		$this->assertEquals( 4.5, $result['products'][0]['rating'] );
	}

	public function test_compare_products_returns_product_fields(): void {
		$this->create_mock_product( 1, 'Test Product', '19.99', '24.99', '19.99' );

		$this->create_mock_product( 2, 'Another Product', '29.99' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$product = $result['products'][0];
		$this->assertArrayHasKey( 'id', $product );
		$this->assertArrayHasKey( 'name', $product );
		$this->assertArrayHasKey( 'url', $product );
		$this->assertArrayHasKey( 'price', $product );
		$this->assertArrayHasKey( 'regular_price', $product );
		$this->assertArrayHasKey( 'sale_price', $product );
		$this->assertArrayHasKey( 'stock_status', $product );
		$this->assertArrayHasKey( 'rating', $product );
		$this->assertArrayHasKey( 'categories', $product );
		$this->assertArrayHasKey( 'add_to_cart_url', $product );
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
