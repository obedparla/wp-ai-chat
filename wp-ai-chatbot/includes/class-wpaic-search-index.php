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
			return $this->fallback_search( $query, $filters, $limit );
		}

		$tnt = $this->get_tnt();
		$tnt->selectIndex( $this->index_name );
		$tnt->fuzziness = true;

		$results     = $tnt->search( $query, $limit * 2 );
		$product_ids = isset( $results['ids'] ) && is_array( $results['ids'] ) ? array_map( 'intval', $results['ids'] ) : array();

		if ( empty( $product_ids ) ) {
			return $this->fallback_search( $query, $filters, $limit );
		}

		if ( ! empty( $filters ) ) {
			$product_ids = $this->apply_filters( $product_ids, $filters );
		}

		return array_slice( $product_ids, 0, $limit );
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
