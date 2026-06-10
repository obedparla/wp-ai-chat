<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TeamTNT\TNTSearch\TNTSearch;

require_once __DIR__ . '/class-wpaic-singular-stemmer.php';
require_once __DIR__ . '/class-wpaic-query-expander.php';

class WPAIC_Search_Index {

	/**
	 * Bump whenever index-time tokenization changes (e.g. the singularizing
	 * stemmer added in version 2): indexes built by an older plugin version
	 * are detected as stale via the wpaic_index_version option and rebuilt.
	 */
	public const INDEX_VERSION = 2;

	/**
	 * Hard cap on search passes (TNT or LIKE-fallback queries) per filter set
	 * within one search() call. The expansion can produce a dozen variants;
	 * without a cap a fully-missing query multiplies into dozens of
	 * index/database hits. The cap is per filter set (not shared across the
	 * up-to-three sets) so a miss-heavy expansion under a wrong category
	 * filter can never starve the parent-category/no-category rescue sets of
	 * passes; worst case is 3 sets x 4 = 12 passes.
	 */
	private const MAX_PASSES_PER_FILTER_SET = 4;

	private ?TNTSearch $tnt = null;
	private string $index_path;
	private string $index_name = 'products.index';
	private WPAIC_Query_Expander $query_expander;

	/**
	 * Per-request memo of get_product_data() results keyed by product ID:
	 * relevance filtering re-reads the same candidates on every variant pass.
	 * Reset at the start of each search().
	 *
	 * @var array<int, array<string, mixed>|null>
	 */
	private array $product_data_cache = array();

	/** Search passes executed for the filter set currently being searched. */
	private int $search_pass_count = 0;

	/** Whether the TNT index returned any raw candidate during this search(). */
	private bool $tnt_returned_candidates = false;

	public function __construct() {
		$upload_dir           = wp_upload_dir();
		$this->index_path     = $upload_dir['basedir'] . '/wpaic/search/';
		$this->query_expander = new WPAIC_Query_Expander();
	}

	public function is_enabled(): bool {
		$settings = get_option( 'wpaic_settings', array() );
		if ( ! is_array( $settings ) ) {
			return true;
		}

		if ( array_key_exists( 'product_index_enabled', $settings ) ) {
			return ! empty( $settings['product_index_enabled'] );
		}

		return true;
	}

	/**
	 * Get TNTSearch instance.
	 *
	 * @return TNTSearch
	 */
	private function get_tnt(): TNTSearch {
		if ( null === $this->tnt ) {
			$this->tnt = new TNTSearch();
			$this->tnt->loadConfig(
				array(
					'driver'    => 'filesystem',
					'storage'   => $this->index_path,
					'fuzziness' => true,
				)
			);
		}
		return $this->tnt;
	}

	/**
	 * Ensure the index directory exists.
	 *
	 * @return bool
	 */
	private function ensure_directory(): bool {
		if ( ! file_exists( $this->index_path ) ) {
			return wp_mkdir_p( $this->index_path );
		}
		return true;
	}

	/**
	 * Get all indexable products data.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_products_data(): array {
		$products = array();
		$args     = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		$query = new WP_Query( $args );

		foreach ( $query->posts as $product_id ) {
			$data = $this->get_product_data( (int) $product_id );
			if ( $data ) {
				$products[] = $data;
			}
		}

		return $products;
	}

	/**
	 * Get indexable data for a single product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|null
	 */
	private function get_product_data( int $product_id ): ?array {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		$categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
		$cat_string = is_array( $categories ) ? implode( ' ', $categories ) : '';

		$attrs_string = '';
		if ( $product->is_type( 'variable' ) && $product instanceof WC_Product_Variable ) {
			$attrs = $product->get_variation_attributes();
			foreach ( $attrs as $options ) {
				if ( is_array( $options ) ) {
					$attrs_string .= ' ' . implode( ' ', $options );
				}
			}
		}

		$sku = $product->get_sku();

		return array(
			'id'          => $product_id,
			'title'       => $product->get_name(),
			'description' => wp_strip_all_tags( $product->get_description() . ' ' . $product->get_short_description() ),
			'sku'         => $sku ? $sku : '',
			'categories'  => $cat_string,
			'attributes'  => trim( $attrs_string ),
		);
	}

	/**
	 * Per-request memoized variant of get_product_data() for the relevance
	 * filter and title-token gating, which re-read the same products for
	 * every variant pass of a single search().
	 *
	 * @return array<string, mixed>|null
	 */
	private function get_product_data_cached( int $product_id ): ?array {
		if ( ! array_key_exists( $product_id, $this->product_data_cache ) ) {
			$this->product_data_cache[ $product_id ] = $this->get_product_data( $product_id );
		}
		return $this->product_data_cache[ $product_id ];
	}

	/**
	 * Build/rebuild the full search index.
	 *
	 * @return bool True on success.
	 */
	public function build_index(): bool {
		if ( ! $this->is_enabled() ) {
			return $this->clear_index();
		}

		if ( ! $this->ensure_directory() ) {
			return false;
		}

		$index_file = $this->index_path . $this->index_name;
		if ( file_exists( $index_file ) ) {
			wp_delete_file( $index_file );
		}

		$tnt     = $this->get_tnt();
		$indexer = $tnt->createIndex( $this->index_name );
		// Singularize tokens at index time; TNT persists the stemmer class in the
		// index and applies it to query tokens too, so plural product titles
		// ("Sports Sneakers") and singular queries ("sneaker") meet on the same
		// canonical term. Without this, TNT's fuzzy expansion — which only runs
		// when a query term has NO exact wordlist match — never reaches the
		// plural form once any product carries the singular one.
		$indexer->setStemmer( new WPAIC_Singular_Stemmer() );

		$products = $this->get_products_data();

		foreach ( $products as $product ) {
			$document = implode(
				' ',
				array(
					$product['title'],
					$product['description'],
					$product['sku'],
					$product['categories'],
					$product['attributes'],
				)
			);
			$indexer->insert(
				array(
					'id'   => $product['id'],
					'text' => $document,
				)
			);
		}

		$this->update_index_meta( count( $products ) );
		update_option( 'wpaic_index_version', self::INDEX_VERSION );

		return true;
	}

	public function clear_index(): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( file_exists( $index_file ) ) {
			wp_delete_file( $index_file );
		}

		$this->clear_index_meta();
		delete_option( 'wpaic_index_version' );

		return true;
	}

	/**
	 * Whether the on-disk index was built by an older plugin version whose
	 * tokenization rules no longer match INDEX_VERSION (e.g. pre-stemmer
	 * indexes store plural title tokens the singularizing query path can no
	 * longer reach).
	 */
	public function needs_version_rebuild(): bool {
		if ( ! $this->is_enabled() ) {
			return false;
		}
		if ( ! file_exists( $this->index_path . $this->index_name ) ) {
			return false;
		}
		return (int) get_option( 'wpaic_index_version', 0 ) !== self::INDEX_VERSION;
	}

	/**
	 * Rebuild a version-stale index: asynchronously via a single cron event
	 * when WP-Cron runs, inline (callers hook this on admin_init) when cron
	 * is disabled.
	 */
	public function maybe_schedule_version_rebuild(): void {
		if ( ! $this->needs_version_rebuild() ) {
			return;
		}

		if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
			if ( ! wp_next_scheduled( 'wpaic_rebuild_product_index' ) ) {
				wp_schedule_single_event( time(), 'wpaic_rebuild_product_index' );
			}
			return;
		}

		$this->build_index();
	}

	/**
	 * Search products using fuzzy matching over the expansion produced by
	 * WPAIC_Query_Expander: one ordered pass of (variant query, tier) pairs —
	 * exact/normalized, singularized, phrase-synonym, token-synonym, then
	 * single-token fallback — merged so earlier tiers rank first, deduped and
	 * capped at limit. A single keyword miss therefore never reads as "the
	 * store does not sell this". Filter fallbacks (parent category, then no
	 * category) re-run the same pass when a filter set yields nothing.
	 *
	 * @param string              $query Search query.
	 * @param array<string,mixed> $filters Optional filters (category, min_price, max_price).
	 * @param int                 $limit Max results.
	 * @return array<int> Array of product IDs.
	 */
	public function search( string $query, array $filters = array(), int $limit = 20 ): array {
		// Per-request state: product-data memo for relevance checks and
		// whether TNT has produced candidates yet. The pass counter is reset
		// per filter set inside search_variants().
		$this->product_data_cache      = array();
		$this->tnt_returned_candidates = false;

		$variants = $this->query_expander->expand( $query );

		foreach ( $this->filter_fallbacks( $filters ) as $candidate_filters ) {
			$product_ids = $this->search_variants( $variants, $candidate_filters, $limit );
			if ( ! empty( $product_ids ) ) {
				return $product_ids;
			}
		}

		return array();
	}

	/**
	 * Run the tiered search-and-merge loop for one filter set: variants are
	 * searched in tier order, results merged first-tier-first and deduped.
	 * While results are empty every tier acts as a broader retry; once
	 * results exist only the merge-capable tiers (see should_search_variant)
	 * still run.
	 *
	 * @param array<int, array{query:string, tier:string, source:?string}> $variants
	 * @param array<string,mixed>                                          $filters
	 * @return array<int>
	 */
	private function search_variants( array $variants, array $filters, int $limit ): array {
		$merged = array();

		$this->search_pass_count = 0;

		foreach ( $variants as $variant ) {
			// MAX_PASSES_PER_FILTER_SET caps passes per filter set, so every
			// filter fallback gets passes — see the constant's doc comment.
			if ( $this->search_pass_count >= self::MAX_PASSES_PER_FILTER_SET ) {
				break;
			}
			if ( ! $this->should_search_variant( $variant, $merged ) ) {
				continue;
			}

			++$this->search_pass_count;
			foreach ( $this->search_single( $variant['query'], $filters, $limit ) as $product_id ) {
				if ( ! in_array( $product_id, $merged, true ) ) {
					$merged[] = $product_id;
				}
			}
		}

		return array_slice( $merged, 0, $limit );
	}

	/**
	 * Tier gating for the merge loop. While results are empty, every tier is
	 * a broader zero-result retry. Once results exist:
	 * - phrase synonyms always merge ("running shoes" always has SOME title
	 *   match like heels, which must not stop sneakers from surfacing);
	 * - token synonyms merge only when the substituted token has no
	 *   result-title match (e.g. "shoos" fuzzy-matched heels via their
	 *   description but no result names a shoe), and multi-word sources
	 *   ("t shirt") only ever broaden zero-result searches;
	 * - exact/singular/single-token tiers never merge into existing results.
	 *
	 * @param array{query:string, tier:string, source:?string} $variant
	 * @param array<int>                                       $merged
	 */
	private function should_search_variant( array $variant, array $merged ): bool {
		if ( empty( $merged ) ) {
			return true;
		}
		if ( WPAIC_Query_Expander::TIER_PHRASE_SYNONYM === $variant['tier'] ) {
			return true;
		}
		if ( WPAIC_Query_Expander::TIER_TOKEN_SYNONYM === $variant['tier'] ) {
			$source = $variant['source'];
			if ( ! is_string( $source ) || str_contains( $source, ' ' ) ) {
				return false;
			}
			return ! $this->token_in_result_titles( $merged, $source );
		}
		return false;
	}

	/**
	 * Whether the (singular) token appears in any result product title.
	 * tokenize_haystack() indexes both raw and singularized title words, so a
	 * singular token also matches plural-titled products.
	 *
	 * @param array<int> $product_ids
	 */
	private function token_in_result_titles( array $product_ids, string $token ): bool {
		foreach ( $product_ids as $product_id ) {
			$data = $this->get_product_data_cached( (int) $product_id );
			if ( null === $data ) {
				continue;
			}
			$title_tokens = $this->tokenize_haystack( (string) $data['title'] );
			if ( isset( $title_tokens[ $token ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run one search pass (TNT index when available, WP_Query fallback otherwise)
	 * for an exact query and filter set, with relevance filtering.
	 *
	 * @param string              $query Search query.
	 * @param array<string,mixed> $filters Optional filters (category, min_price, max_price).
	 * @param int                 $limit Max results.
	 * @return array<int> Array of product IDs.
	 */
	private function search_single( string $query, array $filters, int $limit ): array {
		$index_file = $this->index_path . $this->index_name;

		if ( ! file_exists( $index_file ) ) {
			$product_ids = $this->fallback_search( $query, $filters, $limit * 2 );
			$product_ids = $this->filter_by_relevance( $product_ids, $query );
			return array_slice( $product_ids, 0, $limit );
		}

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$tnt->fuzziness = true;

		$results     = $tnt->search( $query, $limit * 4 );
		$product_ids = isset( $results['ids'] ) && is_array( $results['ids'] ) ? array_map( 'intval', $results['ids'] ) : array();

		if ( ! empty( $product_ids ) ) {
			$this->tnt_returned_candidates = true;
		} elseif ( ! $this->tnt_returned_candidates ) {
			// The index exists but has not produced a single candidate this
			// request (possibly empty or stale), so allow the LIKE fallback.
			// Once TNT has returned candidates the index is authoritative:
			// re-querying via WP_Query LIKE on every empty variant pass would
			// only multiply slow queries.
			$product_ids = $this->fallback_search( $query, $filters, $limit * 2 );
		}

		if ( ! empty( $filters ) ) {
			$product_ids = $this->apply_filters( $product_ids, $filters );
		}

		$product_ids = $this->filter_by_relevance( $product_ids, $query );

		return array_slice( $product_ids, 0, $limit );
	}

	/**
	 * Filter sets to try in order: as given; with the category swapped for its
	 * parent (when one exists); with no category at all so close matches from
	 * sibling categories still surface. Price filters are always preserved.
	 *
	 * @param array<string,mixed> $filters
	 * @return array<int, array<string,mixed>>
	 */
	private function filter_fallbacks( array $filters ): array {
		$fallbacks = array( $filters );

		if ( empty( $filters['category'] ) || ! is_string( $filters['category'] ) ) {
			return $fallbacks;
		}

		$parent_slug = $this->get_parent_category_slug( $filters['category'] );
		if ( null !== $parent_slug && $parent_slug !== $filters['category'] ) {
			$parent_filters             = $filters;
			$parent_filters['category'] = $parent_slug;
			$fallbacks[]                = $parent_filters;
		}

		$no_category_filters = $filters;
		unset( $no_category_filters['category'] );
		$fallbacks[] = $no_category_filters;

		return $fallbacks;
	}

	private function get_parent_category_slug( string $slug ): ?string {
		if ( ! function_exists( 'get_term_by' ) || ! function_exists( 'get_term' ) ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term instanceof WP_Term || empty( $term->parent ) ) {
			return null;
		}

		$parent = get_term( (int) $term->parent, 'product_cat' );
		if ( ! $parent instanceof WP_Term || '' === $parent->slug ) {
			return null;
		}

		return $parent->slug;
	}

	/**
	 * Keep candidates that match the query, preferring field-aware relevance.
	 * A token hit in title/sku/categories/attributes is STRONG; a hit found
	 * only in the description is WEAK. When any candidate has a strong match,
	 * description-only (weak) candidates are dropped so high-signal title/category
	 * matches win (e.g. 'water' returns the 'Water' product, not a watch whose
	 * description says 'water resistant'). When NO candidate has a strong match,
	 * weak matches are returned so legitimately description-based queries still
	 * resolve and we never empty results the fuzzy/LIKE pass produced.
	 *
	 * @param array<int> $product_ids
	 * @return array<int>
	 */
	private function filter_by_relevance( array $product_ids, string $query ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$tokens = $this->query_expander->significant_query_tokens( $query );
		if ( empty( $tokens ) ) {
			return $product_ids;
		}

		$strong_matches = array();
		$weak_matches   = array();

		foreach ( $product_ids as $product_id ) {
			$data = $this->get_product_data_cached( (int) $product_id );
			if ( null === $data ) {
				continue;
			}

			$match = $this->classify_match_strength( $data, $tokens );

			// Preserve the original AND semantics: every significant token must
			// appear somewhere on the product, otherwise it is not a match.
			if ( $match['matched_any'] < count( $tokens ) ) {
				continue;
			}

			if ( $match['matched_strong'] >= 1 ) {
				$strong_matches[] = (int) $product_id;
			} else {
				$weak_matches[] = (int) $product_id;
			}
		}

		return ! empty( $strong_matches ) ? $strong_matches : $weak_matches;
	}

	/**
	 * Classify how a product's already-fetched fields match the query tokens.
	 * Title, SKU, categories and attributes are strong-signal fields; the
	 * description is weak-signal. Tokenization mirrors significant_query_tokens()
	 * so matching is consistent with the rest of the relevance path.
	 *
	 * @param array<string,mixed> $data   Product data from get_product_data().
	 * @param array<string>       $tokens Significant query tokens.
	 * @return array{matched_strong:int,matched_any:int}
	 */
	private function classify_match_strength( array $data, array $tokens ): array {
		$strong_tokens = $this->tokenize_haystack(
			(string) $data['title']
			. ' ' . (string) $data['sku']
			. ' ' . (string) $data['categories']
			. ' ' . (string) $data['attributes']
		);
		$weak_tokens = $this->tokenize_haystack( (string) $data['description'] );

		$matched_strong = 0;
		$matched_any    = 0;
		foreach ( $tokens as $token ) {
			$singular  = $this->singularize_token( $token );
			$in_strong = isset( $strong_tokens[ $token ] ) || isset( $strong_tokens[ $singular ] );
			$in_weak   = isset( $weak_tokens[ $token ] ) || isset( $weak_tokens[ $singular ] );
			if ( $in_strong ) {
				++$matched_strong;
			}
			if ( $in_strong || $in_weak ) {
				++$matched_any;
			}
		}

		return array(
			'matched_strong' => $matched_strong,
			'matched_any'    => $matched_any,
		);
	}

	/**
	 * Normalize text to a set of lowercased alphanumeric tokens, including each
	 * token's singular form so plural queries match singular product fields
	 * ("t-shirts" matches "V-Neck T-Shirt") and vice versa.
	 * Mirrors the normalization used by significant_query_tokens().
	 *
	 * @return array<string,int> token => 1 map for O(1) lookups.
	 */
	private function tokenize_haystack( string $text ): array {
		$normalized = preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $text ) );
		if ( ! is_string( $normalized ) ) {
			return array();
		}

		$tokens = array();
		foreach ( preg_split( '/\s+/', trim( $normalized ) ) ?: array() as $token ) {
			if ( '' === $token ) {
				continue;
			}
			$tokens[ $token ]                             = 1;
			$tokens[ $this->singularize_token( $token ) ] = 1;
		}
		return $tokens;
	}

	/**
	 * Reduce a simple English plural to its singular form ("shirts" → "shirt",
	 * "watches" → "watch", "accessories" → "accessory"). Delegates to the TNT
	 * stemmer so query/haystack tokenization and the index share one rule set.
	 */
	private function singularize_token( string $token ): string {
		return WPAIC_Singular_Stemmer::stem( $token );
	}

	/**
	 * Apply price/category filters to product IDs.
	 *
	 * @param array<int>          $product_ids Product IDs.
	 * @param array<string,mixed> $filters Filters.
	 * @return array<int>
	 */
	private function apply_filters( array $product_ids, array $filters ): array {
		if ( empty( $product_ids ) ) {
			return array();
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'post__in'       => $product_ids,
			'posts_per_page' => count( $product_ids ),
			'orderby'        => 'post__in',
			'fields'         => 'ids',
		);

		if ( ! empty( $filters['category'] ) && is_string( $filters['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $filters['category'],
				),
			);
		}

		if ( ! empty( $filters['min_price'] ) || ! empty( $filters['max_price'] ) ) {
			$args['meta_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $filters['min_price'] ) && is_numeric( $filters['min_price'] ) ) {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => (float) $filters['min_price'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}

			if ( ! empty( $filters['max_price'] ) && is_numeric( $filters['max_price'] ) ) {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => (float) $filters['max_price'],
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
		}

		$query = new WP_Query( $args );

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Fallback to WP_Query when index unavailable.
	 *
	 * @param string              $query Search query.
	 * @param array<string,mixed> $filters Filters.
	 * @param int                 $limit Limit.
	 * @return array<int>
	 */
	private function fallback_search( string $query, array $filters, int $limit ): array {
		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			's'              => $query,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		);

		if ( ! empty( $filters['category'] ) && is_string( $filters['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $filters['category'],
				),
			);
		}

		if ( ! empty( $filters['min_price'] ) || ! empty( $filters['max_price'] ) ) {
			$args['meta_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $filters['min_price'] ) && is_numeric( $filters['min_price'] ) ) {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => (float) $filters['min_price'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}

			if ( ! empty( $filters['max_price'] ) && is_numeric( $filters['max_price'] ) ) {
				$args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => (float) $filters['max_price'],
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
		}

		$query_obj = new WP_Query( $args );

		return array_map( 'intval', $query_obj->posts );
	}

	/**
	 * Index a single product (add or update).
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function index_product( int $product_id ): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( ! file_exists( $index_file ) ) {
			return $this->build_index();
		}

		$data = $this->get_product_data( $product_id );
		if ( ! $data ) {
			return false;
		}

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$indexer = $tnt->getIndex();

		$document = implode(
			' ',
			array(
				$data['title'],
				$data['description'],
				$data['sku'],
				$data['categories'],
				$data['attributes'],
			)
		);

		$indexer->update(
			$product_id,
			array(
				'id'   => $product_id,
				'text' => $document,
			)
		);

		return true;
	}

	/**
	 * Remove a product from the index.
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public function remove_product( int $product_id ): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( ! file_exists( $index_file ) ) {
			return true;
		}

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$indexer = $tnt->getIndex();
		$indexer->delete( $product_id );

		return true;
	}

	/**
	 * Update index metadata (count, timestamp).
	 *
	 * @param int $count Product count.
	 */
	private function update_index_meta( int $count ): void {
		update_option(
			'wpaic_search_index_meta',
			array(
				'product_count' => $count,
				'last_updated'  => current_time( 'mysql' ),
			)
		);
	}

	private function clear_index_meta(): void {
		update_option(
			'wpaic_search_index_meta',
			array(
				'product_count' => 0,
				'last_updated'  => null,
			)
		);
	}

	/**
	 * Get index status info.
	 *
	 * @return array<string,mixed>
	 */
	public function get_index_status(): array {
		$index_file = $this->index_path . $this->index_name;
		$meta       = get_option( 'wpaic_search_index_meta', array() );

		return array(
			'exists'        => file_exists( $index_file ),
			'product_count' => isset( $meta['product_count'] ) ? (int) $meta['product_count'] : 0,
			'last_updated'  => isset( $meta['last_updated'] ) ? $meta['last_updated'] : null,
		);
	}
}
