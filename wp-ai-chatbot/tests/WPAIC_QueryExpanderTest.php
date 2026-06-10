<?php
/**
 * Tests for WPAIC_Query_Expander: the single expansion pass producing the
 * ordered (variant query, tier) list WPAIC_Search_Index's search-and-merge
 * loop consumes. Variant queries and their ordering encode the live-verified
 * recall behavior previously spread across expand_query_variants,
 * phrase_synonym_variants and synonym_variants_for_unmatched_title_tokens.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-query-expander.php';

class WPAIC_QueryExpanderTest extends TestCase {
	private WPAIC_Query_Expander $expander;

	protected function setUp(): void {
		parent::setUp();
		$this->expander = new WPAIC_Query_Expander();
	}

	/**
	 * @param array<int, array{query:string, tier:string, source:?string}> $variants
	 * @return array<int, string>
	 */
	private function queries_for_tier( array $variants, string $tier ): array {
		$queries = array();
		foreach ( $variants as $variant ) {
			if ( $tier === $variant['tier'] ) {
				$queries[] = $variant['query'];
			}
		}
		return $queries;
	}

	/**
	 * @param array<int, array{query:string, tier:string, source:?string}> $variants
	 * @return array<int, array{0:string, 1:string}>
	 */
	private function query_tier_pairs( array $variants ): array {
		return array_map(
			static fn( array $variant ): array => array( $variant['query'], $variant['tier'] ),
			$variants
		);
	}

	// --- Tier ordering ---

	public function test_expand_orders_tiers_with_brand_token_fallback_last(): void {
		$variants = $this->expander->expand( 'chanel perfumes' );

		$this->assertSame(
			array(
				array( 'chanel perfumes', WPAIC_Query_Expander::TIER_EXACT ),
				array( 'chanel perfume', WPAIC_Query_Expander::TIER_SINGULAR ),
				array( 'chanel fragrance', WPAIC_Query_Expander::TIER_TOKEN_SYNONYM ),
				array( 'chanel', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
				array( 'perfumes', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
				array( 'perfume', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
				array( 'fragrance', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
			),
			$this->query_tier_pairs( $variants )
		);
	}

	public function test_expand_orders_phrase_synonyms_before_token_synonyms(): void {
		$variants = $this->expander->expand( 'running shoes' );

		$this->assertSame(
			array(
				array( 'running shoes', WPAIC_Query_Expander::TIER_EXACT ),
				array( 'running shoe', WPAIC_Query_Expander::TIER_SINGULAR ),
				array( 'sneaker', WPAIC_Query_Expander::TIER_PHRASE_SYNONYM ),
				array( 'running sneaker', WPAIC_Query_Expander::TIER_TOKEN_SYNONYM ),
				array( 'running trainer', WPAIC_Query_Expander::TIER_TOKEN_SYNONYM ),
				array( 'running', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
				array( 'shoes', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
				array( 'shoe', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
				array( 'trainer', WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ),
			),
			$this->query_tier_pairs( $variants )
		);
	}

	public function test_expand_dedupes_across_tiers_first_tier_wins(): void {
		// "shoe" appears as a phrase synonym AND a token synonym of "sneaker":
		// the earlier (phrase) tier keeps it.
		$variants = $this->expander->expand( 'sneakers' );

		$this->assertSame(
			array(
				array( 'sneakers', WPAIC_Query_Expander::TIER_EXACT ),
				array( 'sneaker', WPAIC_Query_Expander::TIER_SINGULAR ),
				array( 'shoe', WPAIC_Query_Expander::TIER_PHRASE_SYNONYM ),
				array( 'trainer', WPAIC_Query_Expander::TIER_TOKEN_SYNONYM ),
			),
			$this->query_tier_pairs( $variants )
		);
	}

	// --- Normalization (exact/normalized + singular tiers) ---

	public function test_expand_normalizes_hyphens_and_plurals(): void {
		$variants = $this->expander->expand( 'T-Shirts' );
		$queries  = array_column( $variants, 'query' );

		$this->assertSame( 'T-Shirts', $queries[0] );
		$this->assertContains( 't shirts', $queries );
		$this->assertContains( 't shirt', $queries );
		$this->assertContains( 'tshirt', $queries );
		$this->assertContains( 'tee', $queries );
		$this->assertNotContains( 't-shirts', $queries );
	}

	public function test_expand_skips_normalized_form_equal_to_original(): void {
		$variants = $this->expander->expand( 'Sneakers' );

		// "Sneakers" lowercases to its own normalized form, so the exact tier
		// holds a single entry.
		$this->assertSame(
			array( 'Sneakers' ),
			$this->queries_for_tier( $variants, WPAIC_Query_Expander::TIER_EXACT )
		);
	}

	public function test_expand_returns_empty_for_blank_query(): void {
		$this->assertSame( array(), $this->expander->expand( '' ) );
		$this->assertSame( array(), $this->expander->expand( '   ' ) );
	}

	// --- Phrase synonym tier ---

	public function test_phrase_tier_substitutes_matched_phrases(): void {
		$this->assertSame(
			array( 'sneaker' ),
			$this->queries_for_tier( $this->expander->expand( 'running shoos' ), WPAIC_Query_Expander::TIER_PHRASE_SYNONYM )
		);
		$this->assertSame(
			array( 'red sneaker' ),
			$this->queries_for_tier( $this->expander->expand( 'red trainers' ), WPAIC_Query_Expander::TIER_PHRASE_SYNONYM )
		);
		$this->assertSame(
			array( 'shoe' ),
			$this->queries_for_tier( $this->expander->expand( 'sneakers' ), WPAIC_Query_Expander::TIER_PHRASE_SYNONYM )
		);
		$this->assertSame(
			array(),
			$this->queries_for_tier( $this->expander->expand( 'shoes' ), WPAIC_Query_Expander::TIER_PHRASE_SYNONYM )
		);
		$this->assertSame(
			array(),
			$this->queries_for_tier( $this->expander->expand( 'heels' ), WPAIC_Query_Expander::TIER_PHRASE_SYNONYM )
		);
	}

	// --- Token synonym tier ---

	public function test_token_tier_tolerates_single_letter_typo_in_noun(): void {
		$variants = $this->expander->expand( 'running shoos' );

		$this->assertSame(
			array( 'running shoe', 'running sneaker', 'running trainer' ),
			$this->queries_for_tier( $variants, WPAIC_Query_Expander::TIER_TOKEN_SYNONYM )
		);
	}

	public function test_token_tier_variants_carry_the_substituted_token_as_source(): void {
		$sources = array();
		foreach ( $this->expander->expand( 'running shoos' ) as $variant ) {
			if ( WPAIC_Query_Expander::TIER_TOKEN_SYNONYM === $variant['tier'] ) {
				$sources[] = $variant['source'];
			}
		}

		$this->assertSame( array( 'shoo', 'shoo', 'shoo' ), $sources );
	}

	public function test_token_tier_multi_word_member_carries_phrase_source(): void {
		$sources = array();
		foreach ( $this->expander->expand( 't-shirts' ) as $variant ) {
			if ( WPAIC_Query_Expander::TIER_TOKEN_SYNONYM === $variant['tier'] ) {
				$sources[ $variant['query'] ] = $variant['source'];
			}
		}

		$this->assertSame(
			array(
				'tshirt' => 't shirt',
				'tee'    => 't shirt',
			),
			$sources
		);
	}

	public function test_no_synonym_variants_for_unrelated_query(): void {
		$variants = $this->expander->expand( 'unicorn saddle' );

		$this->assertSame( array(), $this->queries_for_tier( $variants, WPAIC_Query_Expander::TIER_PHRASE_SYNONYM ) );
		$this->assertSame( array(), $this->queries_for_tier( $variants, WPAIC_Query_Expander::TIER_TOKEN_SYNONYM ) );
		$this->assertSame(
			array( 'unicorn', 'saddle' ),
			$this->queries_for_tier( $variants, WPAIC_Query_Expander::TIER_TOKEN_FALLBACK )
		);
	}

	// --- Token fallback tier ---

	public function test_no_token_fallback_for_single_token_queries(): void {
		$variants = $this->expander->expand( 'perfume' );

		$this->assertSame( array(), $this->queries_for_tier( $variants, WPAIC_Query_Expander::TIER_TOKEN_FALLBACK ) );
	}
}
