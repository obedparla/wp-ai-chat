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

	public function test_search_products_drops_results_missing_query_tokens(): void {
		$this->create_mock_product( 1, 'Mens Watches Classic', '199.00' );
		$this->create_mock_product( 2, 'Womens Watches Pearl', '149.00' );

		$result = $this->tools->search_products( array( 'search' => 'mens watches' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Mens Watches Classic', $result[0]['name'] );
	}

	public function test_relevance_filter_keeps_only_products_matching_all_significant_tokens(): void {
		$this->create_mock_product( 1, 'Rolex Mens Watches Submariner', '5000.00' );
		$this->create_mock_product( 2, 'Vaseline Lotion Beauty', '5.00' );
		$this->create_mock_product( 3, 'Womens Heels Shoes', '60.00' );

		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'filter_by_relevance' );
		$reflect->setAccessible( true );

		$kept = $reflect->invoke( $index, array( 1, 2, 3 ), 'mens watches' );
		$this->assertSame( array( 1 ), $kept );

		$kept = $reflect->invoke( $index, array( 1, 2, 3 ), 'coffee beans' );
		$this->assertSame( array(), $kept );

		$kept = $reflect->invoke( $index, array( 1, 2, 3 ), 'running shoes' );
		$this->assertSame( array(), $kept );
	}

	public function test_search_products_excludes_description_only_matches_when_strong_match_exists(): void {
		$this->create_mock_product( 1, 'Water', '0.99' );
		$this->create_mock_product(
			2,
			'Rolex Submariner Watch',
			'13999.99',
			'',
			'',
			'Known for its durability and water resistance, a symbol of luxury.'
		);

		$result = $this->tools->search_products( array( 'search' => 'water' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Water', $result[0]['name'] );
	}

	public function test_search_products_keeps_description_only_match_when_no_strong_match(): void {
		$this->create_mock_product(
			1,
			'Trench Coat',
			'120.00',
			'',
			'',
			'fully waterproof outer shell'
		);

		$result = $this->tools->search_products( array( 'search' => 'waterproof' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Trench Coat', $result[0]['name'] );
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

	public function test_get_popular_products_returns_products_with_sales(): void {
		$this->create_mock_product( 1, 'No Sales', '19.99' );
		$this->create_mock_product( 2, 'Best Seller', '29.99' );
		$this->create_mock_product( 3, 'Some Sales', '9.99' );

		WPAICTestHelper::set_post_meta( 1, 'total_sales', '0' );
		WPAICTestHelper::set_post_meta( 2, 'total_sales', '50' );
		WPAICTestHelper::set_post_meta( 3, 'total_sales', '10' );

		$result = $this->tools->get_popular_products( array() );

		$this->assertCount( 2, $result );
		foreach ( $result as $product ) {
			$this->assertArrayHasKey( 'name', $product );
			$this->assertArrayHasKey( 'price', $product );
		}

		// The popularity query must order by the named total_sales clause (the stub
		// ignores orderby for results, so assert the query args the plugin built).
		$query_vars = WPAICTestHelper::get_last_query_vars();
		$this->assertNotNull( $query_vars );
		$this->assertSame( array( 'sales_clause' => 'DESC' ), $query_vars['orderby'] );
		$this->assertArrayHasKey( 'sales_clause', $query_vars['meta_query'] );
		$this->assertSame( 'total_sales', $query_vars['meta_query']['sales_clause']['key'] );
	}

	public function test_get_popular_products_falls_back_to_rating_when_no_sales(): void {
		$this->create_mock_product( 1, 'Rated Product', '19.99' );
		$this->create_mock_product( 2, 'Another Rated', '29.99' );

		WPAICTestHelper::set_post_meta( 1, '_wc_average_rating', '4.5' );
		WPAICTestHelper::set_post_meta( 2, '_wc_average_rating', '3.0' );

		$result = $this->tools->get_popular_products( array() );

		$this->assertCount( 2, $result );

		// Rating tier is the last query run, so it must build the named rating clause.
		$query_vars = WPAICTestHelper::get_last_query_vars();
		$this->assertNotNull( $query_vars );
		$this->assertSame( array( 'rating_clause' => 'DESC' ), $query_vars['orderby'] );
		$this->assertArrayHasKey( 'rating_clause', $query_vars['meta_query'] );
		$this->assertSame( '_wc_average_rating', $query_vars['meta_query']['rating_clause']['key'] );
	}

	public function test_get_popular_products_falls_back_to_newest_when_no_signal(): void {
		$this->create_mock_product( 1, 'Plain Product', '19.99' );
		$this->create_mock_product( 2, 'Another Plain', '29.99' );

		$result = $this->tools->get_popular_products( array() );

		$this->assertCount( 2, $result );
	}

	public function test_get_popular_products_filters_by_category(): void {
		$this->create_mock_product( 1, 'Shirt Seller', '19.99' );
		$this->create_mock_product( 2, 'Pants Seller', '29.99' );

		WPAICTestHelper::set_post_meta( 1, 'total_sales', '50' );
		WPAICTestHelper::set_post_meta( 2, 'total_sales', '40' );

		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'shirts' ) );
		WPAICTestHelper::set_post_terms( 2, 'product_cat', array( 'pants' ) );

		$result = $this->tools->get_popular_products( array( 'category' => 'shirts' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Shirt Seller', $result[0]['name'] );
	}

	public function test_get_popular_products_clamps_limit(): void {
		$this->create_mock_product( 1, 'Seller One', '19.99' );
		$this->create_mock_product( 2, 'Seller Two', '29.99' );
		$this->create_mock_product( 3, 'Seller Three', '9.99' );

		WPAICTestHelper::set_post_meta( 1, 'total_sales', '50' );
		WPAICTestHelper::set_post_meta( 2, 'total_sales', '40' );
		WPAICTestHelper::set_post_meta( 3, 'total_sales', '30' );

		$result = $this->tools->get_popular_products( array( 'limit' => 1 ) );

		$this->assertCount( 1, $result );
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

	public function test_search_products_includes_stock_status_and_purchasable(): void {
		$this->create_mock_product( 1, 'In Stock Product', '19.99' );
		global $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, true, true, 'simple' );

		$result = $this->tools->search_products( array() );

		$this->assertArrayHasKey( 'stock_status', $result[0] );
		$this->assertArrayHasKey( 'is_purchasable', $result[0] );
		$this->assertEquals( 'instock', $result[0]['stock_status'] );
		$this->assertTrue( $result[0]['is_purchasable'] );
	}

	public function test_search_products_marks_out_of_stock(): void {
		$this->create_mock_product( 1, 'Sold Out', '19.99' );
		global $mock_wc_products;
		$mock_wc_products[1] = new MockWCProduct( 1, true, false, 'simple' );

		$result = $this->tools->search_products( array() );

		$this->assertEquals( 'outofstock', $result[0]['stock_status'] );
	}

	public function test_search_products_includes_external_url_and_button_text(): void {
		$this->create_mock_product( 1, 'Affiliate Product', '99.00' );
		global $mock_wc_products;
		$mock = new MockWCProduct( 1, true, true, 'external' );
		$mock->set_external( 'https://amazon.com/dp/ABC', 'Buy on Amazon' );
		$mock_wc_products[1] = $mock;

		$result = $this->tools->search_products( array() );

		$this->assertEquals( 'external', $result[0]['product_type'] );
		$this->assertEquals( 'https://amazon.com/dp/ABC', $result[0]['external_url'] );
		$this->assertEquals( 'Buy on Amazon', $result[0]['button_text'] );
	}

	public function test_search_products_omits_external_fields_for_simple_products(): void {
		$this->create_mock_product( 1, 'Simple Product', '19.99' );

		$result = $this->tools->search_products( array() );

		$this->assertArrayNotHasKey( 'external_url', $result[0] );
		$this->assertArrayNotHasKey( 'button_text', $result[0] );
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

	public function test_get_page_content_returns_null_for_nonexistent_post(): void {
		$result = $this->tools->get_page_content( array( 'post_id' => 999 ) );

		$this->assertNull( $result );
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
