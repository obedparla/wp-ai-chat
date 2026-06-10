<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Product_Tools {
	/**
	 * Default and maximum result counts for product-list tools. The widget
	 * renders at most 6 cards per message, so defaulting to 6 keeps the bot's
	 * text aligned with what the shopper actually sees; the model may request
	 * up to MAX_PRODUCT_LIMIT via the limit param.
	 */
	public const DEFAULT_PRODUCT_LIMIT = 6;
	public const MAX_PRODUCT_LIMIT     = 10;

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function search_products( array $args ): array {
		$limit = isset( $args['limit'] ) && is_numeric( $args['limit'] ) ? (int) $args['limit'] : self::DEFAULT_PRODUCT_LIMIT;
		$limit = max( 1, min( self::MAX_PRODUCT_LIMIT, $limit ) );

		// on_sale filter: restrict results to products WooCommerce reports on sale.
		$on_sale_ids = null;
		if ( ! empty( $args['on_sale'] ) ) {
			$on_sale_ids = function_exists( 'wc_get_product_ids_on_sale' )
				? array_map( 'intval', (array) wc_get_product_ids_on_sale() )
				: array();
			if ( empty( $on_sale_ids ) ) {
				return array();
			}
		}

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

			if ( null !== $on_sale_ids ) {
				$product_ids = array_values( array_intersect( $product_ids, $on_sale_ids ) );
			}

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
			$products = $this->filter_within_price_bounds( $products, $args );
			return $this->down_rank_zero_priced( $products );
		}

		// Category/price-only queries: use WP_Query
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
		);

		if ( null !== $on_sale_ids ) {
			$query_args['post__in'] = $on_sale_ids;
		}

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

		$products = $this->filter_within_price_bounds( $products, $args );
		return $this->down_rank_zero_priced( $products );
	}

	/**
	 * Enforce min_price/max_price against each card's EFFECTIVE price — the
	 * sale price when one is set — so a shopper asking "under $10" never sees
	 * a $10.82 sale item. The upstream `_price` meta queries usually handle
	 * this, but stale meta and variable-product min/max rows can leak
	 * out-of-budget products through; this is the final gate on what ships.
	 *
	 * @param array<int, array<string, mixed>> $products Formatted product cards.
	 * @param array<string, mixed>             $args Tool arguments.
	 * @return array<int, array<string, mixed>>
	 */
	private function filter_within_price_bounds( array $products, array $args ): array {
		$min_price = ! empty( $args['min_price'] ) && is_numeric( $args['min_price'] ) ? (float) $args['min_price'] : null;
		$max_price = ! empty( $args['max_price'] ) && is_numeric( $args['max_price'] ) ? (float) $args['max_price'] : null;

		if ( null === $min_price && null === $max_price ) {
			return $products;
		}

		$within_bounds = array();
		foreach ( $products as $product ) {
			$effective_price = $this->effective_price( $product );
			if ( null === $effective_price ) {
				continue;
			}
			if ( null !== $min_price && $effective_price < $min_price ) {
				continue;
			}
			if ( null !== $max_price && $effective_price > $max_price ) {
				continue;
			}
			$within_bounds[] = $product;
		}

		return $within_bounds;
	}

	/**
	 * The price the shopper actually pays: the sale price when one is set,
	 * otherwise the current price, otherwise the regular price.
	 *
	 * @param array<string, mixed> $product Formatted product card.
	 */
	private function effective_price( array $product ): ?float {
		foreach ( array( 'sale_price', 'price', 'regular_price' ) as $field ) {
			$value = $product[ $field ] ?? '';
			if ( '' !== $value && is_numeric( $value ) ) {
				return (float) $value;
			}
		}
		return null;
	}

	/**
	 * Zero-priced products (common in sample data) read as broken cards and
	 * should never lead recommendations: keep them, but after every priced
	 * result, preserving relative order within each group.
	 *
	 * @param array<int, array<string, mixed>> $products Formatted product cards.
	 * @return array<int, array<string, mixed>>
	 */
	private function down_rank_zero_priced( array $products ): array {
		$priced   = array();
		$unpriced = array();

		foreach ( $products as $product ) {
			$price = $product['price'] ?? '';
			if ( is_numeric( $price ) && (float) $price > 0 ) {
				$priced[] = $product;
			} else {
				$unpriced[] = $product;
			}
		}

		return array_merge( $priced, $unpriced );
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
		$limit = isset( $args['limit'] ) && is_numeric( $args['limit'] ) ? (int) $args['limit'] : self::DEFAULT_PRODUCT_LIMIT;
		$limit = max( 1, min( self::MAX_PRODUCT_LIMIT, $limit ) );

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
	 * @return array<string, mixed> Product data, or array('error' => ...) when not found
	 *                              (null would serialize as bare "null" to the model).
	 */
	public function get_product_details( int $product_id ): array {
		$post = get_post( $product_id );

		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return array( 'error' => 'Product not found' );
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
					'name'  => $this->category_display_name( $term ),
					'slug'  => $term->slug,
					'count' => $term->count,
				);
			}
		}
		return $result;
	}

	/**
	 * Display name for a category term. Demo catalogs (e.g. DummyJSON imports)
	 * store the name equal to the slug ("kitchen-accessories"); card captions
	 * and comparison rows would render that verbatim, so slug-like names are
	 * humanized for display. Tool parameters keep using the real slug.
	 */
	private function category_display_name( WP_Term $term ): string {
		if ( $term->name === $term->slug && str_contains( $term->name, '-' ) ) {
			return ucwords( str_replace( '-', ' ', $term->name ) );
		}
		return $term->name;
	}

	/**
	 * Humanized category names for a product's payload `categories` field.
	 *
	 * @param int $post_id
	 * @return array<int, string>
	 */
	private function get_category_display_names( int $post_id ): array {
		$terms = wp_get_post_terms( $post_id, 'product_cat' );
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$names = array();
		foreach ( $terms as $term ) {
			if ( $term instanceof WP_Term ) {
				$names[] = $this->category_display_name( $term );
			}
		}
		return $names;
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
				'products'    => array(),
				'attributes'  => array(),
				'differences' => array(),
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
				'products'    => array(),
				'attributes'  => array(),
				'differences' => array(),
			);
		}

		$attributes = array( 'price', 'regular_price', 'stock_status', 'rating', 'categories' );

		return array(
			'products'    => $products,
			'attributes'  => $attributes,
			'differences' => $this->compute_comparison_differences( $products ),
		);
	}

	/**
	 * Pre-computed, human-readable differences (price, rating, stock) so the
	 * model paraphrases server-verified facts instead of re-deriving — and
	 * potentially inverting — them at the purchase-decision moment.
	 *
	 * @param array<int, array<string, mixed>> $products Formatted comparison products.
	 * @return array<int, string>
	 */
	private function compute_comparison_differences( array $products ): array {
		if ( count( $products ) < 2 ) {
			return array();
		}

		$differences = array();

		$price_difference = $this->describe_price_difference( $products );
		if ( null !== $price_difference ) {
			$differences[] = $price_difference;
		}

		$rating_difference = $this->describe_rating_difference( $products );
		if ( null !== $rating_difference ) {
			$differences[] = $rating_difference;
		}

		$stock_difference = $this->describe_stock_difference( $products );
		if ( null !== $stock_difference ) {
			$differences[] = $stock_difference;
		}

		return $differences;
	}

	/**
	 * @param array<int, array<string, mixed>> $products
	 */
	private function describe_price_difference( array $products ): ?string {
		$priced = array_values(
			array_filter( $products, static fn( $product ) => is_numeric( $product['price'] ?? null ) )
		);
		if ( count( $priced ) < 2 ) {
			return null;
		}

		$cheapest       = $priced[0];
		$most_expensive = $priced[0];
		foreach ( $priced as $product ) {
			if ( (float) $product['price'] < (float) $cheapest['price'] ) {
				$cheapest = $product;
			}
			if ( (float) $product['price'] > (float) $most_expensive['price'] ) {
				$most_expensive = $product;
			}
		}

		$gap = (float) $most_expensive['price'] - (float) $cheapest['price'];
		if ( $gap <= 0 ) {
			return 'Price: all compared products cost ' . number_format( (float) $cheapest['price'], 2 ) . '.';
		}

		return sprintf(
			'Price: %s is cheapest at %s; %s is most expensive at %s (%s difference).',
			$cheapest['name'],
			number_format( (float) $cheapest['price'], 2 ),
			$most_expensive['name'],
			number_format( (float) $most_expensive['price'], 2 ),
			number_format( $gap, 2 )
		);
	}

	/**
	 * @param array<int, array<string, mixed>> $products
	 */
	private function describe_rating_difference( array $products ): ?string {
		$rated = array_values(
			array_filter( $products, static fn( $product ) => is_numeric( $product['rating'] ?? null ) )
		);
		if ( count( $rated ) < 2 ) {
			return null;
		}

		$parts   = array();
		$highest = $rated[0];
		$lowest  = $rated[0];
		foreach ( $rated as $product ) {
			$parts[] = $product['name'] . ' ' . number_format( (float) $product['rating'], 1 );
			if ( (float) $product['rating'] > (float) $highest['rating'] ) {
				$highest = $product;
			}
			if ( (float) $product['rating'] < (float) $lowest['rating'] ) {
				$lowest = $product;
			}
		}

		$summary = 'Rating: ' . implode( ', ', $parts );
		if ( (float) $highest['rating'] > (float) $lowest['rating'] ) {
			$summary .= ' — ' . $highest['name'] . ' is rated highest';
		} else {
			$summary .= ' — same rating';
		}

		$unrated = array_filter( $products, static fn( $product ) => ! is_numeric( $product['rating'] ?? null ) );
		if ( ! empty( $unrated ) ) {
			$summary .= '; no rating: ' . implode( ', ', array_column( $unrated, 'name' ) );
		}

		return $summary . '.';
	}

	/**
	 * @param array<int, array<string, mixed>> $products
	 */
	private function describe_stock_difference( array $products ): ?string {
		$statuses = array();
		foreach ( $products as $product ) {
			$status = (string) ( $product['stock_status'] ?? '' );
			if ( '' === $status ) {
				continue;
			}
			$statuses[ $status ][] = $product['name'];
		}

		if ( count( $statuses ) < 2 ) {
			return null;
		}

		$labels = array(
			'instock'     => 'in stock',
			'outofstock'  => 'out of stock',
			'onbackorder' => 'on backorder',
		);

		$parts = array();
		foreach ( $statuses as $status => $names ) {
			$parts[] = implode( ', ', $names ) . ': ' . ( $labels[ $status ] ?? $status );
		}

		return 'Stock: ' . implode( '; ', $parts ) . '.';
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

		$product_data['categories'] = $this->get_category_display_names( $post->ID );

		$wc_product = wc_get_product( $post->ID );
		if ( $wc_product ) {
			$product_data['attributes'] = $this->get_attribute_values( $wc_product );

			$weight = $wc_product->get_weight();
			if ( '' !== (string) $weight ) {
				$product_data['weight'] = $weight . ' ' . get_option( 'woocommerce_weight_unit', 'kg' );
			}

			$dimensions = array_filter(
				$wc_product->get_dimensions( false ),
				static fn( $dimension ) => '' !== (string) $dimension
			);
			if ( ! empty( $dimensions ) ) {
				$product_data['dimensions'] = implode( ' x ', $dimensions ) . ' ' . get_option( 'woocommerce_dimension_unit', 'cm' );
			}
		}

		return $product_data;
	}

	/**
	 * Human-labeled attribute values (taxonomy pa_* and custom attributes) for a
	 * product, e.g. array( 'Color' => 'Blue, Red', 'Warranty' => '3 years' ).
	 * get_attribute() returns term names for taxonomy attributes, so values are
	 * already shopper-readable.
	 *
	 * @param WC_Product $wc_product
	 * @return array<string, string>
	 */
	private function get_attribute_values( $wc_product ): array {
		$values = array();
		foreach ( $wc_product->get_attributes() as $attribute_key => $attribute ) {
			$attribute_name = is_object( $attribute ) && method_exists( $attribute, 'get_name' )
				? $attribute->get_name()
				: (string) $attribute_key;

			$value = $wc_product->get_attribute( $attribute_name );
			if ( '' === $value ) {
				continue;
			}

			$values[ wc_attribute_label( $attribute_name, $wc_product ) ] = $value;
		}
		return $values;
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

		$product_data['categories'] = $this->get_category_display_names( $post->ID );

		// Add variable product data
		if ( 'variable' === $product_type && $wc_product instanceof WC_Product_Variable ) {
			$product_data = array_merge( $product_data, $this->get_variable_product_data( $wc_product ) );
		}

		if ( $detailed ) {
			$product_data['description']    = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
			$product_data['sku']            = get_post_meta( $post->ID, '_sku', true );
			$product_data['stock_quantity'] = get_post_meta( $post->ID, '_stock', true );
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
			$options    = array_values( $options );
			$attributes[] = array(
				// sanitize_title() matches WooCommerce's attribute_* keys in
				// get_available_variations() and add-to-cart requests — custom
				// attribute names like "Logo" otherwise never match attribute_logo.
				'name'          => sanitize_title( $attr_name ),
				'label'         => $attr_label,
				'options'       => $options,
				// Slug => human label map for display ("blue" => "Blue"); the
				// slugs in `options` stay intact because WC AJAX add-to-cart
				// requests must send the slug values.
				'option_labels' => $this->get_attribute_option_labels( (string) $attr_name, $options ),
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
				$variation_attributes = is_array( $var['attributes'] ) ? $var['attributes'] : array();
				$variations[] = array(
					'variation_id'     => $var['variation_id'],
					'attributes'       => $variation_attributes,
					// Human labels keyed by the same attribute_* keys; the slug
					// values in `attributes` stay intact for WC AJAX adds.
					'attribute_labels' => $this->humanize_variation_attributes( $variation_attributes ),
					'price'            => $var['display_price'],
					'regular_price'    => $var['display_regular_price'],
					'is_in_stock'      => $var['is_in_stock'],
					'image'            => isset( $var['image']['url'] ) ? $var['image']['url'] : null,
				);
			}
			$data['variations'] = $variations;
		}

		return $data;
	}

	/**
	 * Map attribute option slugs to human display labels. Taxonomy attributes
	 * (pa_*) store term slugs as options ("blue"), so resolve the term name
	 * ("Blue"); custom attribute options are already display values.
	 *
	 * @param string $attribute_name Raw attribute name / taxonomy, e.g. "pa_color" or "Logo".
	 * @param array<int, string> $options Option slugs or values.
	 * @return array<string, string> Option => display label.
	 */
	private function get_attribute_option_labels( string $attribute_name, array $options ): array {
		$labels = array();
		foreach ( $options as $option ) {
			$labels[ (string) $option ] = $this->humanize_attribute_option( $attribute_name, (string) $option );
		}
		return $labels;
	}

	/**
	 * Human labels for one variation's attribute_* => slug map, e.g.
	 * array( 'attribute_pa_color' => 'Blue' ) for array( 'attribute_pa_color' => 'blue' ).
	 *
	 * @param array<string, string> $variation_attributes
	 * @return array<string, string>
	 */
	private function humanize_variation_attributes( array $variation_attributes ): array {
		$labels = array();
		foreach ( $variation_attributes as $attribute_key => $option ) {
			$taxonomy                          = (string) preg_replace( '/^attribute_/', '', (string) $attribute_key );
			$labels[ (string) $attribute_key ] = $this->humanize_attribute_option( $taxonomy, (string) $option );
		}
		return $labels;
	}

	private function humanize_attribute_option( string $attribute_name, string $option ): string {
		if ( '' !== $option && taxonomy_exists( $attribute_name ) ) {
			$term = get_term_by( 'slug', $option, $attribute_name );
			if ( $term instanceof WP_Term && '' !== $term->name ) {
				return $term->name;
			}
		}
		return $option;
	}
}
