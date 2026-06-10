<?php
/**
 * Tests for WPAIC_Search_Index against a real TNT index built from stubbed
 * product data. Regression coverage for the sneaker-recall bug: TNT only
 * fuzzy-expands a query term with NO exact wordlist match, so an unstemmed
 * index holding both "sneaker" (one product's description) and "sneakers"
 * (other products' titles) made the query "sneaker" exact-match only the
 * singular document and never surface the plural-titled products.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-singular-stemmer.php';
require_once __DIR__ . '/../includes/class-wpaic-search-index.php';

class WPAIC_SearchIndexTest extends TestCase {
	private WPAIC_Search_Index $index;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		global $mock_wc_products;
		$mock_wc_products = array();
		$this->index      = new WPAIC_Search_Index();
		$this->index->clear_index();
	}

	protected function tearDown(): void {
		// Other suites assume no index file exists (WP_Query fallback path).
		$this->index->clear_index();
		global $mock_wc_products;
		$mock_wc_products = array();
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	/**
	 * The dev-catalog shape that reproduced the bug: one product says
	 * "sneaker" (singular) in its description, two carry "Sneakers" (plural)
	 * in their titles, plus generic shoes.
	 */
	private function create_sneaker_catalog(): void {
		$this->create_mock_product(
			496,
			'Nike Air Jordan 1 Red And Black',
			'149.99',
			'The Nike Air Jordan 1 in Red and Black is an iconic basketball sneaker known for its stylish design.'
		);
		$this->create_mock_product(
			514,
			'Sports Sneakers Off White & Red',
			'114.03',
			'The Sports Sneakers in Off White and Red combine style and functionality.'
		);
		$this->create_mock_product(
			520,
			'Sports Sneakers Off White Red',
			'109.95',
			'Another variant of the Sports Sneakers in Off White Red. These sneakers offer style and comfort.'
		);
		$this->create_mock_product( 998, 'Red Shoes', '28.80', 'Stylish red shoes for casual occasions.' );
		$this->create_mock_product( 980, 'Calvin Klein Heel Shoes', '77.44', 'Classic heel shoes designed for elegance.' );
	}

	public function test_singular_query_recalls_plural_titled_products_from_index(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$result = $this->index->search( 'sneaker', array(), 6 );

		$this->assertContains( 514, $result );
		$this->assertContains( 520, $result );
	}

	public function test_running_shoes_query_recalls_sneaker_products(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$result = $this->index->search( 'running shoes', array(), 6 );

		$this->assertContains( 514, $result );
		$this->assertContains( 520, $result );
	}

	public function test_full_typo_sentence_recalls_sneaker_products(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$result = $this->index->search( 'do u hav running shoos?', array(), 6 );

		$this->assertContains( 514, $result );
		$this->assertContains( 520, $result );
	}

	public function test_plural_query_recalls_plural_titled_products_from_index(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$result = $this->index->search( 'sneakers', array(), 6 );

		$this->assertContains( 514, $result );
		$this->assertContains( 520, $result );
	}

	public function test_index_persists_singular_stemmer(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$upload_dir = wp_upload_dir();
		$pdo        = new PDO( 'sqlite:' . $upload_dir['basedir'] . '/wpaic/search/products.index' );

		$stemmer = $pdo->query( "SELECT value FROM info WHERE key = 'stemmer'" )->fetchColumn();
		$this->assertSame( WPAIC_Singular_Stemmer::class, $stemmer );

		// Plural title tokens are stored in canonical singular form.
		$sneaker_docs = (int) $pdo->query( "SELECT num_docs FROM wordlist WHERE term = 'sneaker'" )->fetchColumn();
		$this->assertSame( 3, $sneaker_docs );
		$this->assertFalse( $pdo->query( "SELECT id FROM wordlist WHERE term = 'sneakers'" )->fetchColumn() );
	}

	public function test_singular_stemmer_rules(): void {
		$this->assertSame( 'sneaker', WPAIC_Singular_Stemmer::stem( 'sneakers' ) );
		$this->assertSame( 'shoe', WPAIC_Singular_Stemmer::stem( 'shoes' ) );
		$this->assertSame( 'watch', WPAIC_Singular_Stemmer::stem( 'watches' ) );
		$this->assertSame( 'accessory', WPAIC_Singular_Stemmer::stem( 'accessories' ) );
		$this->assertSame( 'dress', WPAIC_Singular_Stemmer::stem( 'dress' ) );
		$this->assertSame( 'gas', WPAIC_Singular_Stemmer::stem( 'gas' ) );
		$this->assertSame( 'cactus', WPAIC_Singular_Stemmer::stem( 'cactus' ) );
		$this->assertSame( 'water', WPAIC_Singular_Stemmer::stem( 'water' ) );
	}

	private function create_mock_product( int $id, string $title, string $price, string $content = '' ): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => $id,
				'post_title'   => $title,
				'post_content' => $content,
				'post_type'    => 'product',
				'post_status'  => 'publish',
			)
		);

		WPAICTestHelper::set_post_meta( $id, '_price', $price );
		WPAICTestHelper::set_post_meta( $id, '_regular_price', $price );
		WPAICTestHelper::set_post_meta( $id, '_stock_status', 'instock' );
	}
}
