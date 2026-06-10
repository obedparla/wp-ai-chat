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

	public function test_search_skips_like_fallback_once_index_returned_candidates(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		// "sneaker unicornium" makes TNT return candidates (sneaker docs) that
		// relevance filtering rejects, then later variants ("trainer
		// unicornium") return zero TNT candidates before the single-token
		// fallback "sneaker" finally matches.
		$result = $this->index->search( 'sneaker unicornium', array(), 6 );

		$this->assertContains( 514, $result );
		$this->assertContains( 520, $result );

		// Since the index produced candidates, no empty variant pass may fall
		// back to a WP_Query LIKE search: the last WP_Query issued must be
		// build_index's product fetch, which carries no 's' parameter.
		$query_vars = WPAICTestHelper::get_last_query_vars();
		$this->assertNotNull( $query_vars );
		$this->assertArrayNotHasKey( 's', $query_vars );
	}

	// --- Index version staleness (stemmer rollout) ---

	public function test_build_index_stores_current_index_version(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$this->assertSame( WPAIC_Search_Index::INDEX_VERSION, (int) get_option( 'wpaic_index_version', 0 ) );
		$this->assertFalse( $this->index->needs_version_rebuild() );

		$this->index->clear_index();
		$this->assertSame( 0, (int) get_option( 'wpaic_index_version', 0 ) );
	}

	public function test_needs_version_rebuild_detects_stale_index(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		update_option( 'wpaic_index_version', WPAIC_Search_Index::INDEX_VERSION - 1 );

		$this->assertTrue( $this->index->needs_version_rebuild() );
	}

	public function test_needs_version_rebuild_false_without_index_file(): void {
		update_option( 'wpaic_index_version', WPAIC_Search_Index::INDEX_VERSION - 1 );

		$this->assertFalse( $this->index->needs_version_rebuild() );
	}

	public function test_maybe_schedule_version_rebuild_schedules_cron_event_when_stale(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );
		update_option( 'wpaic_index_version', WPAIC_Search_Index::INDEX_VERSION - 1 );

		$this->index->maybe_schedule_version_rebuild();

		$this->assertNotFalse( wp_next_scheduled( 'wpaic_rebuild_product_index' ) );
	}

	public function test_maybe_schedule_version_rebuild_noops_when_version_current(): void {
		$this->create_sneaker_catalog();
		$this->assertTrue( $this->index->build_index() );

		$this->index->maybe_schedule_version_rebuild();

		$this->assertFalse( wp_next_scheduled( 'wpaic_rebuild_product_index' ) );
	}

	// --- End index version staleness tests ---

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
