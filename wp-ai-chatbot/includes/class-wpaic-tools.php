<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Tools {
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
					$products[] = $this->format_product( $post );
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
				$products[] = $this->format_product( $post );
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
	 * Get order status by order number and email for verification.
	 *
	 * @param array{order_number: string, email: string} $args Order number and email.
	 * @return array<string, mixed> Order status info or error.
	 */
	public function get_order_status( array $args ): array {
		$order_number = isset( $args['order_number'] ) && is_string( $args['order_number'] ) ? sanitize_text_field( $args['order_number'] ) : '';
		$email        = isset( $args['email'] ) && is_string( $args['email'] ) ? sanitize_email( $args['email'] ) : '';

		if ( '' === $order_number || '' === $email ) {
			return array( 'error' => 'Order number and email are required.' );
		}

		$order = wc_get_order( $order_number );

		if ( ! $order ) {
			return array( 'error' => 'Order not found. Please check the order number and try again.' );
		}

		$order_email = $order->get_billing_email();
		if ( strtolower( $order_email ) !== strtolower( $email ) ) {
			return array( 'error' => 'Order not found. Please check the order number and email match.' );
		}

		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array(
				'name'     => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'total'    => $order->get_formatted_line_subtotal( $item ),
			);
		}

		$result = array(
			'order_number'    => $order->get_order_number(),
			'status'          => wc_get_order_status_name( $order->get_status() ),
			'date_created'    => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : null,
			'total'           => $order->get_formatted_order_total(),
			'items'           => $items,
			'shipping_method' => $order->get_shipping_method(),
		);

		$tracking = $order->get_meta( '_tracking_number' );
		if ( $tracking ) {
			$result['tracking_number'] = $tracking;
		}

		$tracking_url = $order->get_meta( '_tracking_url' );
		if ( $tracking_url ) {
			$result['tracking_url'] = $tracking_url;
		}

		return $result;
	}

	/**
	 * Get the current cart contents and totals for the active WooCommerce session.
	 *
	 * @return array<string, mixed> Cart state or error payload.
	 */
	public function get_cart_contents(): array {
		$cart = $this->get_initialized_cart();
		if ( null === $cart ) {
			return array( 'error' => 'Cart unavailable' );
		}

		$cart_items = method_exists( $cart, 'get_cart' ) ? $cart->get_cart() : array();
		if ( ! is_array( $cart_items ) ) {
			$cart_items = array();
		}

		$items = array();
		foreach ( $cart_items as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product_id = isset( $cart_item['product_id'] ) && is_numeric( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			$quantity   = isset( $cart_item['quantity'] ) && is_numeric( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
			$product    = $this->get_cart_item_product( $cart_item, $product_id );
			$name       = $this->get_cart_item_name( $cart_item, $product );

			$items[] = array(
				'product_id' => $product_id,
				'name'       => $name,
				'quantity'   => $quantity,
				'line_total' => $this->get_cart_item_total_text( $cart, $cart_item, $product, $quantity ),
			);
		}

		$item_count = method_exists( $cart, 'get_cart_contents_count' ) ? (int) $cart->get_cart_contents_count() : count( $items );

		return array(
			'is_empty'   => 0 === $item_count,
			'item_count' => $item_count,
			'subtotal'   => $this->get_cart_total_text( $cart, 'get_cart_subtotal' ),
			'total'      => $this->get_cart_total_text( $cart, 'get_cart_total' ),
			'items'      => $items,
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
		);

		$thumbnail_id = get_post_thumbnail_id( $post->ID );
		if ( $thumbnail_id ) {
			$image_url = wp_get_attachment_url( $thumbnail_id );
			if ( false !== $image_url ) {
				$product_data['image'] = $image_url;
			}
		}

		// Add variable product data
		if ( 'variable' === $product_type && $wc_product instanceof WC_Product_Variable ) {
			$product_data = array_merge( $product_data, $this->get_variable_product_data( $wc_product ) );
		}

		if ( $detailed ) {
			$product_data['description']       = $post->post_content;
			$product_data['short_description'] = $post->post_excerpt;
			$product_data['sku']               = get_post_meta( $post->ID, '_sku', true );
			$product_data['stock_status']      = get_post_meta( $post->ID, '_stock_status', true );
			$product_data['stock_quantity']    = get_post_meta( $post->ID, '_stock', true );

			$categories                 = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'names' ) );
			$product_data['categories'] = is_array( $categories ) ? $categories : array();
		}

		return $product_data;
	}

	/**
	 * Create a handoff support request.
	 *
	 * @param array<string, mixed> $args Handoff data.
	 * @return array<string, mixed> Success response or error.
	 */
	public function create_handoff_request( array $args ): array {
		global $wpdb;

		$customer_name  = isset( $args['customer_name'] ) && is_string( $args['customer_name'] ) ? sanitize_text_field( $args['customer_name'] ) : '';
		$customer_email = isset( $args['customer_email'] ) && is_string( $args['customer_email'] ) ? sanitize_email( $args['customer_email'] ) : '';
		$transcript     = isset( $args['conversation_summary'] ) && is_string( $args['conversation_summary'] ) ? sanitize_textarea_field( $args['conversation_summary'] ) : '';

		if ( '' === $customer_name ) {
			return array( 'error' => 'Customer name is required.' );
		}

		if ( '' === $customer_email || ! is_email( $customer_email ) ) {
			return array( 'error' => 'Valid email address is required.' );
		}

		$extra_fields     = array();
		$optional_keys    = array( 'phone_number', 'company', 'order_number', 'request_message' );
		foreach ( $optional_keys as $key ) {
			if ( isset( $args[ $key ] ) && is_string( $args[ $key ] ) && '' !== $args[ $key ] ) {
				$extra_fields[ $key ] = sanitize_text_field( $args[ $key ] );
			}
		}
		$extra_fields_json = ! empty( $extra_fields ) ? wp_json_encode( $extra_fields ) : null;

		$table_name = $wpdb->prefix . 'wpaic_support_requests';

		$insert_data    = array(
			'customer_name'  => $customer_name,
			'customer_email' => $customer_email,
			'transcript'     => $transcript,
			'extra_fields'   => $extra_fields_json,
			'status'         => 'new',
			'created_at'     => current_time( 'mysql' ),
			'updated_at'     => current_time( 'mysql' ),
		);
		$insert_formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		$inserted = $wpdb->insert( $table_name, $insert_data, $insert_formats );

		if ( false === $inserted ) {
			return array( 'error' => 'Failed to create support request. Please try again.' );
		}

		$request_id = $wpdb->insert_id;

		$this->send_handoff_email( $request_id, $customer_name, $customer_email, $transcript, $extra_fields );

		return array(
			'success'    => true,
			'request_id' => $request_id,
			'message'    => 'Support request submitted. Our team will contact you shortly.',
		);
	}

	/**
	 * Send handoff notification email to admin.
	 *
	 * @param int                   $request_id     Support request ID.
	 * @param string                $customer_name  Customer name.
	 * @param string                $customer_email Customer email.
	 * @param string                $transcript     Conversation transcript.
	 * @param array<string, string> $extra_fields   Optional extra contact fields.
	 */
	private function send_handoff_email( int $request_id, string $customer_name, string $customer_email, string $transcript, array $extra_fields = array() ): void {
		$admin_email = get_option( 'admin_email' );
		$site_name   = get_bloginfo( 'name' );

		$support_url = add_query_arg(
			array(
				'page'       => 'wp-ai-chatbot-support',
				'request_id' => $request_id,
			),
			admin_url( 'admin.php' )
		);

		$subject = sprintf(
			/* translators: 1: customer name, 2: site name */
			__( '[%2$s] New Support Request from %1$s', 'wp-ai-chatbot' ),
			$customer_name,
			$site_name
		);

		$message = sprintf(
			/* translators: 1: customer name, 2: customer email */
			__( "A customer has requested human support.\n\nCustomer: %1\$s\nEmail: %2\$s\n", 'wp-ai-chatbot' ),
			$customer_name,
			$customer_email
		);

		$field_labels = array(
			'phone_number'    => __( 'Phone', 'wp-ai-chatbot' ),
			'company'         => __( 'Company', 'wp-ai-chatbot' ),
			'order_number'    => __( 'Order Number', 'wp-ai-chatbot' ),
			'request_message' => __( 'Message', 'wp-ai-chatbot' ),
		);
		foreach ( $extra_fields as $key => $value ) {
			$label   = $field_labels[ $key ] ?? $key;
			$message .= "{$label}: {$value}\n";
		}

		$message .= "\n" . __( "Conversation Summary:\n", 'wp-ai-chatbot' );
		$message .= "---\n" . $transcript . "\n---\n\n";
		$message .= sprintf(
			/* translators: %s: support page URL */
			__( "View full details: %s\n", 'wp-ai-chatbot' ),
			$support_url
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $admin_email, $subject, $message, $headers );
	}

	/**
	 * Query custom training data.
	 *
	 * @param array{source_name: string, query: string} $args Source name and query.
	 * @return array<string, mixed> Matching data or error.
	 */
	public function query_custom_data( array $args ): array {
		global $wpdb;

		$source_name = isset( $args['source_name'] ) && is_string( $args['source_name'] ) ? sanitize_key( $args['source_name'] ) : '';
		$query       = isset( $args['query'] ) && is_string( $args['query'] ) ? sanitize_text_field( $args['query'] ) : '';

		if ( '' === $source_name ) {
			return array( 'error' => 'Source name is required.' );
		}

		$sources_table = $wpdb->prefix . 'wpaic_data_sources';
		$data_table    = $wpdb->prefix . 'wpaic_training_data';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$source = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, label, description, columns FROM $sources_table WHERE name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_name
			)
		);

		if ( ! $source ) {
			return array( 'error' => "Data source '$source_name' not found." );
		}

		$columns = json_decode( $source->columns, true );
		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		// Get all rows for this source
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT row_data FROM $data_table WHERE source_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source->id
			)
		);

		$data = array();
		foreach ( $rows as $row ) {
			$row_data = json_decode( $row->row_data, true );
			if ( is_array( $row_data ) ) {
				// If query provided, filter rows (simple text matching)
				if ( '' !== $query ) {
					$row_text = strtolower( implode( ' ', array_values( $row_data ) ) );
					if ( str_contains( $row_text, strtolower( $query ) ) ) {
						$data[] = $row_data;
					}
				} else {
					$data[] = $row_data;
				}
			}
		}

		// Limit results to avoid overwhelming context
		$data = array_slice( $data, 0, 20 );

		return array(
			'source'      => $source_name,
			'label'       => $source->label,
			'description' => $source->description,
			'columns'     => $columns,
			'results'     => $data,
			'count'       => count( $data ),
		);
	}

	/**
	 * Get available data sources for tool definition.
	 *
	 * @return array<int, array{name: string, label: string, description: string, columns: array<string>}>
	 */
	public static function get_data_sources(): array {
		global $wpdb;

		$sources_table = $wpdb->prefix . 'wpaic_data_sources';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$sources = $wpdb->get_results( "SELECT name, label, description, columns FROM $sources_table" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$result = array();
		foreach ( $sources as $source ) {
			$columns = json_decode( $source->columns, true );
			$result[] = array(
				'name'        => $source->name,
				'label'       => $source->label,
				'description' => $source->description,
				'columns'     => is_array( $columns ) ? $columns : array(),
			);
		}

		return $result;
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<int, array<string, mixed>>
	 */
	public function search_site_content( array $args ): array {
		$query         = isset( $args['query'] ) && is_string( $args['query'] ) ? sanitize_text_field( $args['query'] ) : '';
		$content_index = new WPAIC_Content_Index();
		return $content_index->search( $query );
	}

	/**
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>|null
	 */
	public function get_page_content( array $args ): ?array {
		$post_id       = isset( $args['post_id'] ) && is_numeric( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$content_index = new WPAIC_Content_Index();
		return $content_index->get_page_content( $post_id );
	}

	/**
	 * WooCommerce does not auto-load the cart for REST requests, so bootstrap it on demand.
	 *
	 * @return object|null
	 */
	private function get_initialized_cart(): ?object {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}

		$woocommerce = WC();
		if ( $this->has_usable_cart( $woocommerce ) ) {
			return $woocommerce->cart;
		}

		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'did_action' ) || did_action( 'woocommerce_init' ) > 0 ) ) {
			wc_load_cart();
		} else {
			$woocommerce->initialize_session();
			$woocommerce->initialize_cart();
		}

		$woocommerce = WC();
		if ( ! $this->has_usable_cart( $woocommerce ) ) {
			return null;
		}

		return $woocommerce->cart;
	}

	private function has_usable_cart( object $woocommerce ): bool {
		return isset( $woocommerce->cart ) && is_object( $woocommerce->cart );
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @return object|false|null
	 */
	private function get_cart_item_product( array $cart_item, int $product_id ): object|false|null {
		if ( isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) ) {
			return $cart_item['data'];
		}

		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		return wc_get_product( $product_id );
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @param object|false|null $product
	 */
	private function get_cart_item_name( array $cart_item, object|false|null $product ): string {
		if ( is_object( $product ) && method_exists( $product, 'get_name' ) ) {
			$name = $product->get_name();
			if ( is_string( $name ) ) {
				return $name;
			}
		}

		return isset( $cart_item['name'] ) && is_string( $cart_item['name'] ) ? $cart_item['name'] : '';
	}

	/**
	 * @param array<string, mixed> $cart_item
	 * @param object|false|null $product
	 */
	private function get_cart_item_total_text( object $cart, array $cart_item, object|false|null $product, int $quantity ): string {
		if ( is_object( $product ) && method_exists( $cart, 'get_product_subtotal' ) ) {
			return $this->normalize_price_text( (string) $cart->get_product_subtotal( $product, $quantity ) );
		}

		if ( isset( $cart_item['line_total'] ) ) {
			return $this->normalize_price_text( (string) $cart_item['line_total'] );
		}

		return '';
	}

	private function get_cart_total_text( object $cart, string $method ): string {
		if ( ! method_exists( $cart, $method ) ) {
			return '';
		}

		return $this->normalize_price_text( (string) $cart->$method() );
	}

	private function normalize_price_text( string $price_text ): string {
		$decoded = html_entity_decode( $price_text, ENT_QUOTES, 'UTF-8' );
		$stripped = wp_strip_all_tags( $decoded, true );
		$normalized = preg_replace( '/\s+/u', ' ', $stripped );

		return trim( is_string( $normalized ) ? $normalized : $stripped );
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
