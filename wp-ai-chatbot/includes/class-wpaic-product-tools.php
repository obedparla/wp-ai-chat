<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Product_Tools {
	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function search_products( array $args ): array {
		$limit = isset( $args['limit'] ) && is_numeric( $args['limit'] ) ? (int) $args['limit'] : 10;

		// Use TNTSearch fuzzy matching when search term provided
		if ( ! empty( $args['search'] ) && is_string( $args['search'] ) ) {
			$filters = array();
			if ( ! empty( $args['category'] ) && is_string( $args['category'] ) ) {
				$filters['category'] = $args['category'];
			}
			if ( ! empty( $args['min_price'] ) && is_numeric( $args['min_price'] ) ) {
				$filters['min_price'] = $args['min_price'];
			}
			if ( ! empty( $args['max_price'] ) && is_numeric( $args['max_price'] ) ) {
				$filters['max_price'] = $args['max_price'];
			}

			$search_index = new WPAIC_Search_Index();
			$product_ids  = $search_index->search( $args['search'], $filters, $limit );

			$products = array();
			foreach ( $product_ids as $product_id ) {
				$post = get_post( $product_id );
				if ( $post instanceof WP_Post ) {
					$card = $this->format_product( $post );
					if ( ! empty( $card ) ) {
						$products[] = $card;
					}
				}
			}
			return $products;
		}

		// Category/price-only queries: use WP_Query
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
		);

		if ( ! empty( $args['category'] ) && is_string( $args['category'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $args['category'],
				),
			);
		}

		if ( ! empty( $args['min_price'] ) || ! empty( $args['max_price'] ) ) {
			$query_args['meta_query'] = array( 'relation' => 'AND' );

			if ( ! empty( $args['min_price'] ) && is_numeric( $args['min_price'] ) ) {
				$query_args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => (float) $args['min_price'],
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
			}

			if ( ! empty( $args['max_price'] ) && is_numeric( $args['max_price'] ) ) {
				$query_args['meta_query'][] = array(
					'key'     => '_price',
					'value'   => (float) $args['max_price'],
					'compare' => '<=',
					'type'    => 'NUMERIC',
				);
			}
		}

		$query    = new WP_Query( $query_args );
		$products = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$card = $this->format_product( $post );
				if ( ! empty( $card ) ) {
					$products[] = $card;
				}
			}
		}

		return $products;
	}

	/**
	 * Get the store's best-selling / most popular products.
	 *
	 * Returns best-sellers ordered by total_sales (WooCommerce-standard popularity
	 * ordering). When no products carry a sales signal, falls back to top-rated by
	 * average rating, then to the newest products so a request always yields cards.
	 *
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function get_popular_products( array $args ): array {
		$limit = isset( $args['limit'] ) && is_numeric( $args['limit'] ) ? (int) $args['limit'] : 10;
		$limit = max( 1, min( 24, $limit ) );

		$base_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'order'          => 'DESC',
		);

		if ( ! empty( $args['category'] ) && is_string( $args['category'] ) ) {
			$base_args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $args['category'],
				),
			);
		}

		// Tier 1: real best-sellers by total sales.
		$products = $this->query_popular_products(
			$this->meta_ordered_args( $base_args, 'sales_clause', 'total_sales' )
		);
		if ( ! empty( $products ) ) {
			return $products;
		}

		// Tier 2: top-rated fallback when no sales signal exists.
		$products = $this->query_popular_products(
			$this->meta_ordered_args( $base_args, 'rating_clause', '_wc_average_rating' )
		);
		if ( ! empty( $products ) ) {
			return $products;
		}

		// Tier 3: newest products, always returns something.
		return $this->query_popular_products(
			array_merge(
				$base_args,
				array(
					'orderby' => 'date',
				)
			)
		);
	}

	/**
	 * Merge a named numeric meta_query clause and a matching orderby onto the base
	 * query args. Used by the popularity tiers, which differ only by clause name
	 * and meta key (both order DESC by a "> 0" numeric value).
	 *
	 * @param array<string, mixed> $base
	 * @param string $clause   Named meta_query clause key, also referenced by orderby.
	 * @param string $meta_key Post meta key to order by.
	 * @return array<string, mixed>
	 */
	private function meta_ordered_args( array $base, string $clause, string $meta_key ): array {
		return array_merge(
			$base,
			array(
				'meta_query' => array(
					$clause => array(
						'key'     => $meta_key,
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
				'orderby'    => array( $clause => 'DESC' ),
			)
		);
	}

	/**
	 * Run a product WP_Query and format the matching posts.
	 *
	 * @param array<string, mixed> $query_args
	 * @return array<int, array<string, mixed>>
	 */
	private function query_popular_products( array $query_args ): array {
		$query    = new WP_Query( $query_args );
		$products = array();

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post ) {
				$card = $this->format_product( $post );
				if ( ! empty( $card ) ) {
					$products[] = $card;
				}
			}
		}

		return $products;
	}

	/**
	 * @param int $product_id
	 * @return array<string, mixed>|null
	 */
	public function get_product_details( int $product_id ): ?array {
		$post = get_post( $product_id );

		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return null;
		}

		return $this->format_product( $post, true );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_categories(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		$result = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$result[] = array(
					'id'    => $term->term_id,
					'name'  => $term->name,
					'slug'  => $term->slug,
					'count' => $term->count,
				);
			}
		}
		return $result;
	}

	/**
	 * Compare multiple products side by side.
	 *
	 * @param array<int> $product_ids Array of product IDs to compare.
	 * @return array<string, mixed> Comparison data with products and attributes.
	 */
	public function compare_products( array $product_ids ): array {
		if ( empty( $product_ids ) ) {
			return array(
				'products'   => array(),
				'attributes' => array(),
			);
		}

		$product_ids = array_slice( array_map( 'intval', $product_ids ), 0, 4 );
		$products    = array();

		foreach ( $product_ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
				continue;
			}
			$products[] = $this->format_comparison_product( $post );
		}

		if ( empty( $products ) ) {
			return array(
				'products'   => array(),
				'attributes' => array(),
			);
		}

		$attributes = array( 'price', 'regular_price', 'stock_status', 'rating', 'categories' );

		return array(
			'products'   => $products,
			'attributes' => $attributes,
		);
	}

	/**
	 * Format product for comparison view.
	 *
	 * @param WP_Post $post Product post.
	 * @return array<string, mixed> Formatted product data.
	 */
	private function format_comparison_product( WP_Post $post ): array {
		$rating = get_post_meta( $post->ID, '_wc_average_rating', true );

		$product_data = array(
			'id'              => $post->ID,
			'name'            => $post->post_title,
			'url'             => get_permalink( $post->ID ),
			'price'           => get_post_meta( $post->ID, '_price', true ),
			'regular_price'   => get_post_meta( $post->ID, '_regular_price', true ),
			'sale_price'      => get_post_meta( $post->ID, '_sale_price', true ),
			'stock_status'    => get_post_meta( $post->ID, '_stock_status', true ),
			'rating'          => '' !== $rating ? (float) $rating : null,
			'add_to_cart_url' => add_query_arg( 'add-to-cart', $post->ID, wc_get_cart_url() ),
		);

		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$image_url = wp_get_attachment_url( $thumbnail_id );
			if ( false !== $image_url ) {
				$product_data['image'] = $image_url;
			}
		}

		$categories                 = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
		$product_data['categories'] = is_array( $categories ) ? $categories : array();

		return $product_data;
	}

	/**
	 * @param WP_Post $post
	 * @param bool $detailed
	 * @return array<string, mixed>
	 */
	private function format_product( WP_Post $post, bool $detailed = false ): array {
		$wc_product = wc_get_product( $post->ID );
		if ( ! $wc_product ) {
			return array();
		}

		$product_type = $wc_product->get_type();

		$product_data = array(
			'id'                => $post->ID,
			'name'              => $post->post_title,
			'url'               => get_permalink( $post->ID ),
			'price'             => get_post_meta( $post->ID, '_price', true ),
			'regular_price'     => get_post_meta( $post->ID, '_regular_price', true ),
			'sale_price'        => get_post_meta( $post->ID, '_sale_price', true ),
			'short_description' => wp_strip_all_tags( $post->post_excerpt ),
			'add_to_cart_url'   => add_query_arg( 'add-to-cart', $post->ID, wc_get_cart_url() ),
			'product_type'      => $product_type,
			'stock_status'      => $wc_product->get_stock_status(),
			'is_purchasable'    => $wc_product->is_purchasable(),
		);

		if ( 'external' === $product_type && method_exists( $wc_product, 'get_product_url' ) ) {
			$external_url = $wc_product->get_product_url();
			$button_text  = method_exists( $wc_product, 'get_button_text' ) ? $wc_product->get_button_text() : '';
			if ( is_string( $external_url ) && '' !== $external_url ) {
				$product_data['external_url'] = $external_url;
			}
			if ( is_string( $button_text ) && '' !== $button_text ) {
				$product_data['button_text'] = $button_text;
			}
		}

		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$image_url = wp_get_attachment_url( $thumbnail_id );
			if ( false !== $image_url ) {
				$product_data['image'] = $image_url;
			}
		}

		$categories                 = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
		$product_data['categories'] = is_array( $categories ) ? $categories : array();

		// Add variable product data
		if ( 'variable' === $product_type && $wc_product instanceof WC_Product_Variable ) {
			$product_data = array_merge( $product_data, $this->get_variable_product_data( $wc_product ) );
		}

		if ( $detailed ) {
			$product_data['description']       = $post->post_content;
			$product_data['short_description'] = $post->post_excerpt;
			$product_data['sku']               = get_post_meta( $post->ID, '_sku', true );
			$product_data['stock_quantity']    = get_post_meta( $post->ID, '_stock', true );
		}

		return $product_data;
	}

	/**
	 * Get variable product attributes and variations data.
	 *
	 * @param WC_Product_Variable $product Variable product.
	 * @return array<string, mixed> Variable product data.
	 */
	private function get_variable_product_data( WC_Product_Variable $product ): array {
		$attributes       = array();
		$variation_attrs  = $product->get_variation_attributes();
		$available_vars   = $product->get_available_variations();
		$variation_count  = count( $available_vars );
		$attribute_count  = count( $variation_attrs );

		// Determine complexity: simple if <=2 attrs and <30 variations
		$is_complex = $attribute_count > 2 || $variation_count >= 30;

		foreach ( $variation_attrs as $attr_name => $options ) {
			$attr_label = wc_attribute_label( $attr_name, $product );
			$attributes[] = array(
				'name'    => $attr_name,
				'label'   => $attr_label,
				'options' => array_values( $options ),
			);
		}

		$data = array(
			'attributes'      => $attributes,
			'is_complex'      => $is_complex,
			'variation_count' => $variation_count,
		);

		// Only include variations for simple variable products
		if ( ! $is_complex ) {
			$variations = array();
			foreach ( $available_vars as $var ) {
				$variations[] = array(
					'variation_id' => $var['variation_id'],
					'attributes'   => $var['attributes'],
					'price'        => $var['display_price'],
					'regular_price' => $var['display_regular_price'],
					'is_in_stock'  => $var['is_in_stock'],
					'image'        => isset( $var['image']['url'] ) ? $var['image']['url'] : null,
				);
			}
			$data['variations'] = $variations;
		}

		return $data;
	}
}
