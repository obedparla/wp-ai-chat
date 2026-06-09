<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TeamTNT\TNTSearch\TNTSearch;

class WPAIC_Search_Index {

	private ?TNTSearch $tnt = null;
	private string $index_path;
	private string $index_name = 'products.index';

	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->index_path = $upload_dir['basedir'] . '/wpaic/search/';
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
		$indexer->setLanguage( 'no' );

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

		return true;
	}

	public function clear_index(): bool {
		$index_file = $this->index_path . $this->index_name;
		if ( file_exists( $index_file ) ) {
			wp_delete_file( $index_file );
		}

		$this->clear_index_meta();

		return true;
	}

	/**
	 * Search products using fuzzy matching.
	 *
	 * @param string              $query Search query.
	 * @param array<string,mixed> $filters Optional filters (category, min_price, max_price).
	 * @param int                 $limit Max results.
	 * @return array<int> Array of product IDs.
	 */
	public function search( string $query, array $filters = array(), int $limit = 20 ): array {
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

		if ( empty( $product_ids ) ) {
			$product_ids = $this->fallback_search( $query, $filters, $limit * 2 );
		}

		if ( ! empty( $filters ) ) {
			$product_ids = $this->apply_filters( $product_ids, $filters );
		}

		$product_ids = $this->filter_by_relevance( $product_ids, $query );

		return array_slice( $product_ids, 0, $limit );
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

		$tokens = $this->significant_query_tokens( $query );
		if ( empty( $tokens ) ) {
			return $product_ids;
		}

		$strong_matches = array();
		$weak_matches   = array();

		foreach ( $product_ids as $product_id ) {
			$data = $this->get_product_data( (int) $product_id );
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
			$in_strong = isset( $strong_tokens[ $token ] );
			if ( $in_strong ) {
				++$matched_strong;
			}
			if ( $in_strong || isset( $weak_tokens[ $token ] ) ) {
				++$matched_any;
			}
		}

		return array(
			'matched_strong' => $matched_strong,
			'matched_any'    => $matched_any,
		);
	}

	/**
	 * Normalize text to a set of lowercased alphanumeric tokens.
	 * Mirrors the normalization used by significant_query_tokens().
	 *
	 * @return array<string,int> token => 1 map for O(1) lookups.
	 */
	private function tokenize_haystack( string $text ): array {
		$normalized = preg_replace( '/[^a-z0-9]+/', ' ', strtolower( $text ) );
		if ( ! is_string( $normalized ) ) {
			return array();
		}
		return array_flip( preg_split( '/\s+/', trim( $normalized ) ) ?: array() );
	}

	/**
	 * Extract lowercased query tokens worth filtering against. Drops short
	 * fragments and generic stopwords that would otherwise reject relevant
	 * products.
	 *
	 * @return array<string>
	 */
	private function significant_query_tokens( string $query ): array {
		$normalized = strtolower( $query );
		$normalized = preg_replace( '/[^a-z0-9]+/', ' ', $normalized );
		if ( ! is_string( $normalized ) ) {
			return array();
		}

		$stopwords = array(
			'a', 'an', 'the', 'and', 'or', 'but', 'of', 'for', 'with', 'to', 'in', 'on',
			'at', 'by', 'is', 'are', 'be', 'this', 'that', 'these', 'those', 'i', 'me',
			'my', 'we', 'our', 'you', 'your', 'show', 'find', 'list', 'give', 'me',
			'some', 'any', 'please', 'looking', 'need', 'want', 'have', 'has', 'do',
			'does', 'can', 'could', 'would', 'should', 'product', 'products', 'item',
			'items', 'price', 'prices', 'picture', 'pictures', 'image', 'images',
			'actual', 'really', 'real',
		);

		$tokens = array();
		foreach ( preg_split( '/\s+/', trim( $normalized ) ) ?: array() as $token ) {
			if ( '' === $token ) {
				continue;
			}
			if ( strlen( $token ) < 3 ) {
				continue;
			}
			if ( in_array( $token, $stopwords, true ) ) {
				continue;
			}
			$tokens[ $token ] = true;
		}

		return array_keys( $tokens );
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
