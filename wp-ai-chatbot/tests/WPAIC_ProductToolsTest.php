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

	public function test_search_products_defaults_to_six_results(): void {
		for ( $i = 1; $i <= 8; $i++ ) {
			$this->create_mock_product( $i, "Product {$i}", '10' );
		}

		$result = $this->tools->search_products( array() );

		$this->assertCount( 6, $result );
	}

	public function test_search_products_clamps_limit_to_ten(): void {
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->create_mock_product( $i, "Product {$i}", '10' );
		}

		$result = $this->tools->search_products( array( 'limit' => 15 ) );

		$this->assertCount( 10, $result );
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

	public function test_search_products_max_price_drops_item_whose_sale_price_exceeds_cap(): void {
		// Stale `_price` meta (9.50) slips through the meta query, but the
		// effective (sale) price 10.82 is over the $10 cap and must not ship.
		$this->create_mock_product( 1, 'Kitchen Rolling Pin', '9.50', '12.99', '10.82' );
		$this->create_mock_product( 2, 'Kitchen Spoon', '4.91', '4.99', '4.91' );

		$result = $this->tools->search_products(
			array(
				'search'    => 'kitchen',
				'max_price' => 10,
			)
		);

		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertNotContains( 'Kitchen Rolling Pin', $names );
		$this->assertContains( 'Kitchen Spoon', $names );
	}

	public function test_search_products_category_path_enforces_effective_price_cap(): void {
		$this->create_mock_product( 1, 'Rolling Pin', '9.50', '12.99', '10.82' );
		$this->create_mock_product( 2, 'Peeler', '5.24', '5.99', '5.24' );

		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'kitchen-accessories' ) );
		WPAICTestHelper::set_post_terms( 2, 'product_cat', array( 'kitchen-accessories' ) );

		$result = $this->tools->search_products(
			array(
				'category'  => 'kitchen-accessories',
				'max_price' => 10,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Peeler', $result[0]['name'] );
	}

	public function test_search_products_min_price_enforced_against_effective_price(): void {
		// Stale `_price` 9.00 passes the >= 8 meta query, but the shopper pays
		// the sale price 5.00, which is below the requested minimum.
		$this->create_mock_product( 1, 'Discounted Mug', '9.00', '9.00', '5.00' );
		$this->create_mock_product( 2, 'Premium Mug', '15.00' );

		$result = $this->tools->search_products(
			array(
				'search'    => 'mug',
				'min_price' => 8,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Premium Mug', $result[0]['name'] );
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

	public function test_get_popular_products_defaults_to_six_results(): void {
		for ( $i = 1; $i <= 8; $i++ ) {
			$this->create_mock_product( $i, "Seller {$i}", '10' );
			WPAICTestHelper::set_post_meta( $i, 'total_sales', (string) ( 100 - $i ) );
		}

		$result = $this->tools->get_popular_products( array() );

		$this->assertCount( 6, $result );
	}

	public function test_get_popular_products_clamps_limit_to_ten(): void {
		for ( $i = 1; $i <= 12; $i++ ) {
			$this->create_mock_product( $i, "Seller {$i}", '10' );
			WPAICTestHelper::set_post_meta( $i, 'total_sales', (string) ( 100 - $i ) );
		}

		$result = $this->tools->get_popular_products( array( 'limit' => 24 ) );

		$this->assertCount( 10, $result );
	}

	public function test_get_product_details_returns_error_for_nonexistent_product(): void {
		$result = $this->tools->get_product_details( 999 );

		$this->assertSame( array( 'error' => 'Product not found' ), $result );
	}

	public function test_get_product_details_returns_error_for_non_product_post(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'          => 1,
				'post_title'  => 'Blog Post',
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$result = $this->tools->get_product_details( 1 );

		$this->assertSame( array( 'error' => 'Product not found' ), $result );
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

	public function test_get_product_details_strips_html_and_shortcodes_from_description(): void {
		$this->create_mock_product(
			1,
			'Formatted Product',
			'10.00',
			'10.00',
			'',
			'<p>Great <strong>fit</strong></p>[gallery] and comfort',
			'<em>Soft</em> tee',
			'SKU1',
			'instock',
			'5'
		);

		$result = $this->tools->get_product_details( 1 );

		$this->assertNotNull( $result );
		$this->assertStringNotContainsString( '<p>', $result['description'] );
		$this->assertStringNotContainsString( '<strong>', $result['description'] );
		$this->assertStringNotContainsString( '[gallery]', $result['description'] );
		$this->assertStringContainsString( 'Great', $result['description'] );
		$this->assertStringContainsString( 'comfort', $result['description'] );
		$this->assertStringNotContainsString( '<em>', $result['short_description'] );
		$this->assertStringContainsString( 'Soft', $result['short_description'] );
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

	// --- Slug-like category name humanization (FIX-2) ---

	public function test_get_categories_humanizes_slug_like_names(): void {
		WPAICTestHelper::add_mock_term(
			array(
				'term_id' => 1,
				'name'    => 'kitchen-accessories',
				'slug'    => 'kitchen-accessories',
				'count'   => 7,
			)
		);

		$result = $this->tools->get_categories();

		$this->assertEquals( 'Kitchen Accessories', $result[0]['name'] );
		// Tool parameters keep using the real slug.
		$this->assertEquals( 'kitchen-accessories', $result[0]['slug'] );
	}

	public function test_get_categories_keeps_curated_and_hyphenless_names_verbatim(): void {
		WPAICTestHelper::add_mock_term(
			array(
				'term_id' => 1,
				'name'    => 'Mens Shoes',
				'slug'    => 'mens-shoes',
				'count'   => 3,
			)
		);
		WPAICTestHelper::add_mock_term(
			array(
				'term_id' => 2,
				'name'    => 'beauty',
				'slug'    => 'beauty',
				'count'   => 2,
			)
		);

		$result = $this->tools->get_categories();

		$this->assertEquals( 'Mens Shoes', $result[0]['name'] );
		$this->assertEquals( 'beauty', $result[1]['name'] );
	}

	public function test_search_products_card_categories_humanize_slug_like_names(): void {
		$this->create_mock_product( 1, 'Bamboo Spatula', '7.99' );
		WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 1,
				'name'     => 'kitchen-accessories',
				'slug'     => 'kitchen-accessories',
				'taxonomy' => 'product_cat',
			)
		);

		$result = $this->tools->search_products( array() );

		$this->assertSame( array( 'Kitchen Accessories' ), $result[0]['categories'] );
	}

	public function test_compare_products_categories_humanize_slug_like_names(): void {
		$this->create_mock_product( 1, 'Bamboo Spatula', '7.99' );
		$this->create_mock_product( 2, 'Steel Whisk', '5.99' );
		WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 1,
				'name'     => 'kitchen-accessories',
				'slug'     => 'kitchen-accessories',
				'taxonomy' => 'product_cat',
			)
		);

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertSame( array( 'Kitchen Accessories' ), $result['products'][0]['categories'] );
	}

	// --- End slug-like category name humanization tests ---

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

	public function test_compare_products_differences_describe_price_gap(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertContains(
			'Price: Product A is cheapest at 19.99; Product B is most expensive at 29.99 (10.00 difference).',
			$result['differences']
		);
	}

	public function test_compare_products_differences_describe_equal_prices(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '19.99' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertContains( 'Price: all compared products cost 19.99.', $result['differences'] );
	}

	public function test_compare_products_differences_describe_rating_delta(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );
		WPAICTestHelper::set_post_meta( 1, '_wc_average_rating', '4.0' );
		WPAICTestHelper::set_post_meta( 2, '_wc_average_rating', '3.0' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertContains(
			'Rating: Product A 4.0, Product B 3.0 — Product A is rated highest.',
			$result['differences']
		);
	}

	public function test_compare_products_differences_note_unrated_products(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );
		$this->create_mock_product( 3, 'Product C', '39.99' );
		WPAICTestHelper::set_post_meta( 1, '_wc_average_rating', '4.0' );
		WPAICTestHelper::set_post_meta( 2, '_wc_average_rating', '3.0' );

		$result = $this->tools->compare_products( array( 1, 2, 3 ) );

		$this->assertContains(
			'Rating: Product A 4.0, Product B 3.0 — Product A is rated highest; no rating: Product C.',
			$result['differences']
		);
	}

	public function test_compare_products_differences_skip_rating_when_fewer_than_two_rated(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );
		WPAICTestHelper::set_post_meta( 1, '_wc_average_rating', '4.0' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		foreach ( $result['differences'] as $difference ) {
			$this->assertStringNotContainsString( 'Rating:', $difference );
		}
	}

	public function test_compare_products_differences_describe_stock_mismatch(): void {
		$this->create_mock_product( 1, 'In Stock Item', '19.99', '', '', '', '', '', 'instock' );
		$this->create_mock_product( 2, 'Out of Stock Item', '29.99', '', '', '', '', '', 'outofstock' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertContains(
			'Stock: In Stock Item: in stock; Out of Stock Item: out of stock.',
			$result['differences']
		);
	}

	public function test_compare_products_differences_omit_stock_when_statuses_match(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$result = $this->tools->compare_products( array( 1, 2 ) );

		foreach ( $result['differences'] as $difference ) {
			$this->assertStringNotContainsString( 'Stock:', $difference );
		}
	}

	public function test_compare_products_differences_empty_for_single_product(): void {
		$this->create_mock_product( 1, 'Product A', '19.99' );

		$result = $this->tools->compare_products( array( 1 ) );

		$this->assertSame( array(), $result['differences'] );
	}

	public function test_compare_products_includes_attributes_with_human_labels(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$product = new MockWCProduct( 1 );
		$product->set_attribute_values(
			array(
				'pa_color' => 'Blue, Red',
				'Warranty' => '3 years',
			)
		);
		$mock_wc_products[1] = $product;

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertSame(
			array(
				'Color'    => 'Blue, Red',
				'Warranty' => '3 years',
			),
			$result['products'][0]['attributes']
		);
		$this->assertSame( array(), $result['products'][1]['attributes'] );
	}

	public function test_compare_products_includes_weight_and_dimensions(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Product A', '19.99' );
		$this->create_mock_product( 2, 'Product B', '29.99' );

		$product = new MockWCProduct( 1 );
		$product->set_weight( '1.5' );
		$product->set_dimensions(
			array(
				'length' => '10',
				'width'  => '5',
				'height' => '',
			)
		);
		$mock_wc_products[1] = $product;

		$result = $this->tools->compare_products( array( 1, 2 ) );

		$this->assertSame( '1.5 kg', $result['products'][0]['weight'] );
		$this->assertSame( '10 x 5 cm', $result['products'][0]['dimensions'] );
		$this->assertArrayNotHasKey( 'weight', $result['products'][1] );
		$this->assertArrayNotHasKey( 'dimensions', $result['products'][1] );
	}

	/**
	 * Creates a mock WooCommerce product with metadata.
	 */
	// --- Zero-result search retry tests ---

	public function test_search_products_plural_hyphen_query_matches_variable_product(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'V-Neck T-Shirt', '24.99' );
		$mock_wc_products[1] = new MockWCProduct( 1, true, true, 'variable' );

		$result = $this->tools->search_products( array( 'search' => 't-shirts' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'V-Neck T-Shirt', $result[0]['name'] );
		$this->assertEquals( 'variable', $result[0]['product_type'] );
	}

	public function test_variable_product_attribute_names_match_variation_attribute_keys(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Hoodie', '42.00' );

		// Custom (non-taxonomy) attribute "Logo": WooCommerce keys the variation
		// payload by the sanitized name (attribute_logo), so the payload's
		// attribute names must be sanitized too or the frontend never matches.
		$variable = new WC_Product_Variable( 1 );
		$variable->set_variation_attributes(
			array(
				'pa_color' => array( 'blue', 'green' ),
				'Logo'     => array( 'Yes', 'No' ),
			)
		);
		$variable->set_available_variations(
			array(
				array(
					'variation_id'          => 201,
					'attributes'            => array(
						'attribute_pa_color' => 'blue',
						'attribute_logo'     => 'Yes',
					),
					'display_price'         => 45.0,
					'display_regular_price' => 45.0,
					'is_in_stock'           => true,
					'image'                 => array( 'url' => 'http://example.com/hoodie-blue.jpg' ),
				),
			)
		);
		$mock_wc_products[1] = $variable;

		$result = $this->tools->search_products( array( 'search' => 'hoodie' ) );

		$this->assertCount( 1, $result );
		$product = $result[0];
		$this->assertEquals( 'variable', $product['product_type'] );

		$attribute_names = array_column( $product['attributes'], 'name' );
		$this->assertEquals( array( 'pa_color', 'logo' ), $attribute_names );

		foreach ( $attribute_names as $attribute_name ) {
			$this->assertArrayHasKey( 'attribute_' . $attribute_name, $product['variations'][0]['attributes'] );
		}

		$this->assertEquals( 201, $product['variations'][0]['variation_id'] );
		$this->assertEquals( 45.0, $product['variations'][0]['price'] );
	}

	public function test_variable_product_payload_carries_human_option_labels(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Hoodie', '42.00' );

		WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 7,
				'name'     => 'Navy Blue',
				'slug'     => 'navy-blue',
				'taxonomy' => 'pa_color',
			)
		);

		$variable = new WC_Product_Variable( 1 );
		$variable->set_variation_attributes(
			array(
				'pa_color' => array( 'navy-blue' ),
				'Logo'     => array( 'Yes' ),
			)
		);
		$variable->set_available_variations(
			array(
				array(
					'variation_id'          => 201,
					'attributes'            => array(
						'attribute_pa_color' => 'navy-blue',
						'attribute_logo'     => 'Yes',
					),
					'display_price'         => 45.0,
					'display_regular_price' => 45.0,
					'is_in_stock'           => true,
					'image'                 => array( 'url' => 'http://example.com/hoodie-navy.jpg' ),
				),
			)
		);
		$mock_wc_products[1] = $variable;

		$result  = $this->tools->search_products( array( 'search' => 'hoodie' ) );
		$product = $result[0];

		// Taxonomy attribute: slug options stay intact for WC AJAX adds, with a
		// term-name label map alongside for display.
		$color_attribute = $product['attributes'][0];
		$this->assertSame( array( 'navy-blue' ), $color_attribute['options'] );
		$this->assertSame( array( 'navy-blue' => 'Navy Blue' ), $color_attribute['option_labels'] );

		// Custom attribute: options are already display values, labels map to themselves.
		$logo_attribute = $product['attributes'][1];
		$this->assertSame( array( 'Yes' ), $logo_attribute['options'] );
		$this->assertSame( array( 'Yes' => 'Yes' ), $logo_attribute['option_labels'] );

		// Variation: slug attributes intact, human labels keyed identically.
		$variation = $product['variations'][0];
		$this->assertSame( 'navy-blue', $variation['attributes']['attribute_pa_color'] );
		$this->assertSame(
			array(
				'attribute_pa_color' => 'Navy Blue',
				'attribute_logo'     => 'Yes',
			),
			$variation['attribute_labels']
		);
	}

	public function test_variable_product_option_label_falls_back_to_slug_for_unknown_term(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Hoodie', '42.00' );

		$variable = new WC_Product_Variable( 1 );
		$variable->set_variation_attributes(
			array(
				'pa_color' => array( 'blue' ),
			)
		);
		$variable->set_available_variations( array() );
		$mock_wc_products[1] = $variable;

		$result  = $this->tools->search_products( array( 'search' => 'hoodie' ) );
		$product = $result[0];

		$this->assertSame( array( 'blue' => 'blue' ), $product['attributes'][0]['option_labels'] );
	}

	public function test_search_products_retries_brand_token_alone(): void {
		$this->create_mock_product( 1, 'Chanel Coco Noir Eau De', '129.99' );
		$this->create_mock_product( 2, 'Dior Lipstick', '39.99' );

		$result = $this->tools->search_products( array( 'search' => 'chanel perfume' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Chanel Coco Noir Eau De', $result[0]['name'] );
	}

	public function test_search_products_retries_perfume_fragrance_synonym(): void {
		$this->create_mock_product( 1, 'Elegant Fragrance Spray', '59.99' );

		$result = $this->tools->search_products( array( 'search' => 'perfume' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Elegant Fragrance Spray', $result[0]['name'] );
	}

	public function test_search_products_retries_sneakers_shoes_synonym(): void {
		$this->create_mock_product( 1, 'Running Shoes', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'sneakers' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Running Shoes', $result[0]['name'] );
	}

	public function test_search_products_retries_tee_tshirt_synonym(): void {
		$this->create_mock_product( 1, 'Classic Cotton Tee', '14.99' );

		$result = $this->tools->search_products( array( 'search' => 't-shirt' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Classic Cotton Tee', $result[0]['name'] );
	}

	public function test_search_products_falls_back_to_parent_category(): void {
		$this->create_mock_product( 1, 'Gourmet Coffee Beans', '12.99' );
		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'kitchen' ) );
		WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 9,
				'name'     => 'Kitchen',
				'slug'     => 'kitchen',
				'taxonomy' => 'product_cat',
			)
		);
		WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 10,
				'name'     => 'Kitchen Gadgets',
				'slug'     => 'kitchen-gadgets',
				'taxonomy' => 'product_cat',
				'parent'   => 9,
			)
		);

		$result = $this->tools->search_products(
			array(
				'search'   => 'coffee',
				'category' => 'kitchen-gadgets',
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Gourmet Coffee Beans', $result[0]['name'] );
	}

	public function test_search_products_drops_category_filter_when_nothing_matches(): void {
		$this->create_mock_product( 1, 'Chanel Perfume Spray', '129.99' );
		WPAICTestHelper::set_post_terms( 1, 'product_cat', array( 'fragrances' ) );
		WPAICTestHelper::add_mock_term(
			array(
				'term_id'  => 11,
				'name'     => 'Beauty',
				'slug'     => 'beauty',
				'taxonomy' => 'product_cat',
			)
		);

		$result = $this->tools->search_products(
			array(
				'search'   => 'chanel',
				'category' => 'beauty',
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Chanel Perfume Spray', $result[0]['name'] );
	}

	public function test_search_products_returns_empty_when_nothing_close(): void {
		$this->create_mock_product( 1, 'Kitchen Sieve', '8.99' );
		$this->create_mock_product( 2, 'Garden Hose', '19.99' );

		$result = $this->tools->search_products( array( 'search' => 'unicorn saddle' ) );

		$this->assertEmpty( $result );
	}

	public function test_expand_query_variants_orders_brand_token_before_generic_terms(): void {
		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'expand_query_variants' );
		$reflect->setAccessible( true );

		$variants = $reflect->invoke( $index, 'chanel perfumes' );

		$this->assertSame(
			array( 'chanel perfume', 'chanel fragrance', 'chanel', 'perfumes', 'perfume', 'fragrance' ),
			$variants
		);
	}

	public function test_expand_query_variants_normalizes_hyphens_and_plurals(): void {
		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'expand_query_variants' );
		$reflect->setAccessible( true );

		$variants = $reflect->invoke( $index, 'T-Shirts' );

		$this->assertContains( 't shirts', $variants );
		$this->assertContains( 't shirt', $variants );
		$this->assertContains( 'tshirt', $variants );
		$this->assertContains( 'tee', $variants );
		$this->assertNotContains( 't-shirts', $variants );
	}

	public function test_relevance_filter_matches_plural_query_against_singular_title(): void {
		$this->create_mock_product( 1, 'V-Neck T-Shirt', '24.99' );

		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'filter_by_relevance' );
		$reflect->setAccessible( true );

		$this->assertSame( array( 1 ), $reflect->invoke( $index, array( 1 ), 't-shirts' ) );
		$this->assertSame( array( 1 ), $reflect->invoke( $index, array( 1 ), 'shirts' ) );
	}

	public function test_search_index_includes_variable_products(): void {
		global $mock_wc_products;

		$this->create_mock_product( 1, 'Hoodie', '45.00' );
		$mock_wc_products[1] = new MockWCProduct( 1, true, true, 'variable' );

		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'get_products_data' );
		$reflect->setAccessible( true );

		$products = $reflect->invoke( $index );

		$this->assertCount( 1, $products );
		$this->assertSame( 1, $products[0]['id'] );
		$this->assertSame( 'Hoodie', $products[0]['title'] );
	}

	// --- End zero-result search retry tests ---

	// --- on_sale filter ---

	public function test_search_products_on_sale_filters_keyword_results(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Shirt', '29.99' );
		WPAICTestHelper::set_option( 'test_product_ids_on_sale', array( 1 ) );

		$result = $this->tools->search_products(
			array(
				'search'  => 'shirt',
				'on_sale' => true,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Red Shirt', $result[0]['name'] );
	}

	public function test_search_products_on_sale_filters_query_without_keyword(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_product( 2, 'Blue Shirt', '29.99' );
		WPAICTestHelper::set_option( 'test_product_ids_on_sale', array( 2 ) );

		$result = $this->tools->search_products( array( 'on_sale' => true ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Blue Shirt', $result[0]['name'] );
	}

	public function test_search_products_on_sale_returns_empty_when_nothing_on_sale(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		WPAICTestHelper::set_option( 'test_product_ids_on_sale', array() );

		$result = $this->tools->search_products( array( 'on_sale' => true ) );

		$this->assertEmpty( $result );
	}

	// --- Zero-priced down-ranking ---

	public function test_search_products_down_ranks_zero_priced_keyword_results(): void {
		$this->create_mock_product( 1, 'Sample Shirt', '0' );
		$this->create_mock_product( 2, 'Red Shirt', '19.99' );

		$result = $this->tools->search_products( array( 'search' => 'shirt' ) );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'Red Shirt', $result[0]['name'] );
		$this->assertEquals( 'Sample Shirt', $result[1]['name'] );
	}

	public function test_search_products_down_ranks_priceless_results_without_keyword(): void {
		$this->create_mock_product( 1, 'Priceless Hoodie', '' );
		$this->create_mock_product( 2, 'Priced Hoodie', '45.00' );

		$result = $this->tools->search_products( array() );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'Priced Hoodie', $result[0]['name'] );
		$this->assertEquals( 'Priceless Hoodie', $result[1]['name'] );
	}

	// --- Weak-result synonym merge (NEW-A) ---

	public function test_search_products_merges_synonym_results_when_no_title_match(): void {
		$this->create_mock_product(
			1,
			'Elegant Heels',
			'59.99',
			'',
			'',
			'classy shoes for evening parties'
		);
		$this->create_mock_product( 2, 'Sports Sneakers Off White Red', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'shoes' ) );

		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertContains( 'Elegant Heels', $names );
		$this->assertContains( 'Sports Sneakers Off White Red', $names );
	}

	public function test_search_products_skips_synonym_merge_when_title_matches(): void {
		$this->create_mock_product( 1, 'Running Shoes', '79.99' );
		$this->create_mock_product( 2, 'Sports Sneakers', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'shoes' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Running Shoes', $result[0]['name'] );
	}

	public function test_synonym_variants_tolerate_single_letter_typo_in_noun(): void {
		$this->create_mock_product( 1, 'Elegant Heels', '59.99' );

		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'synonym_variants_for_unmatched_title_tokens' );
		$reflect->setAccessible( true );

		$variants = $reflect->invoke( $index, array( 1 ), 'running shoos' );

		$this->assertSame( array( 'running shoe', 'running sneaker', 'running trainer' ), $variants );
	}

	public function test_synonym_variants_empty_when_noun_already_in_a_title(): void {
		$this->create_mock_product( 1, 'Running Shoes', '79.99' );

		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'synonym_variants_for_unmatched_title_tokens' );
		$reflect->setAccessible( true );

		$variants = $reflect->invoke( $index, array( 1 ), 'running shoes' );

		$this->assertSame( array(), $variants );
	}

	// --- End weak-result synonym merge tests ---

	// --- Phrase-level synonym merge (FIX-3) ---

	public function test_search_products_running_shoes_always_merges_sneakers_despite_title_match(): void {
		// "shoes" carries a title match (heels), which used to suppress the
		// synonym merge entirely; phrase synonyms must merge regardless.
		$this->create_mock_product(
			1,
			'Elegant Heels Shoes',
			'59.99',
			'',
			'',
			'great for running errands'
		);
		$this->create_mock_product( 2, 'Sports Sneakers Off White Red', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'running shoes' ) );

		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertContains( 'Elegant Heels Shoes', $names );
		$this->assertContains( 'Sports Sneakers Off White Red', $names );
	}

	public function test_search_products_typo_running_shoos_surfaces_sneakers(): void {
		$this->create_mock_product( 1, 'Sports Sneakers Off White Red', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'running shoos' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Sports Sneakers Off White Red', $result[0]['name'] );
	}

	public function test_search_products_kicks_surfaces_sneakers(): void {
		$this->create_mock_product( 1, 'Sports Sneakers Off White & Red', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'kicks' ) );

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Sports Sneakers Off White & Red', $result[0]['name'] );
	}

	public function test_search_products_trainers_merge_sneakers_despite_title_match(): void {
		$this->create_mock_product( 1, 'Gym Trainers', '49.99' );
		$this->create_mock_product( 2, 'Sports Sneakers', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'trainers' ) );

		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertContains( 'Gym Trainers', $names );
		$this->assertContains( 'Sports Sneakers', $names );
	}

	public function test_search_products_sneakers_merge_shoes_as_fallback(): void {
		$this->create_mock_product( 1, 'Casual Sneakers', '69.99' );
		$this->create_mock_product( 2, 'Leather Shoes', '99.99' );

		$result = $this->tools->search_products( array( 'search' => 'sneakers' ) );

		$names = array_map( fn( $p ) => $p['name'], $result );
		$this->assertContains( 'Casual Sneakers', $names );
		$this->assertContains( 'Leather Shoes', $names );
	}

	public function test_search_products_phrase_merge_capped_at_limit_with_primary_first(): void {
		$this->create_mock_product( 1, 'Running Shoes', '79.99' );
		$this->create_mock_product( 2, 'Sports Sneakers', '89.99' );

		$result = $this->tools->search_products(
			array(
				'search' => 'running shoes',
				'limit'  => 1,
			)
		);

		$this->assertCount( 1, $result );
		$this->assertEquals( 'Running Shoes', $result[0]['name'] );
	}

	public function test_search_products_phrase_merged_results_still_down_rank_zero_priced(): void {
		$this->create_mock_product( 1, 'Running Shoes', '0' );
		$this->create_mock_product( 2, 'Sports Sneakers', '89.99' );

		$result = $this->tools->search_products( array( 'search' => 'running shoes' ) );

		$this->assertCount( 2, $result );
		$this->assertEquals( 'Sports Sneakers', $result[0]['name'] );
		$this->assertEquals( 'Running Shoes', $result[1]['name'] );
	}

	public function test_phrase_synonym_variants_substitute_matched_phrases(): void {
		$index   = new WPAIC_Search_Index();
		$reflect = new ReflectionMethod( WPAIC_Search_Index::class, 'phrase_synonym_variants' );
		$reflect->setAccessible( true );

		$this->assertSame( array( 'sneaker' ), $reflect->invoke( $index, 'running shoos' ) );
		$this->assertSame( array( 'red sneaker' ), $reflect->invoke( $index, 'red trainers' ) );
		$this->assertSame( array( 'shoe' ), $reflect->invoke( $index, 'sneakers' ) );
		$this->assertSame( array(), $reflect->invoke( $index, 'shoes' ) );
		$this->assertSame( array(), $reflect->invoke( $index, 'heels' ) );
	}

	// --- End phrase-level synonym merge tests ---

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
