<?php
/**
 * Tests for WPAIC_Product_Tools class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-search-index.php';
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-product-tools.php';

class WPAIC_ProductToolsTest extends TestCase {
	private WPAIC_Product_Tools $tools;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		global $mock_wc, $mock_wc_products;
		$mock_wc          = new MockWooCommerce();
		$mock_wc_products = array();
		$this->tools      = new WPAIC_Product_Tools();
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

		// Best-sellers must be ordered by the named total_sales clause, highest first.
		$this->assertSame( 'Best Seller', $result[0]['name'] );
		$this->assertSame( 'Some Sales', $result[1]['name'] );

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

		// Rating tier orders by the named rating clause, highest first.
		$this->assertSame( 'Rated Product', $result[0]['name'] );
		$this->assertSame( 'Another Rated', $result[1]['name'] );

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
