<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Tools {
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
	 * Get site-level shipping info from WooCommerce shipping zones and methods.
	 *
	 * Reads only what WooCommerce reliably exposes: zones, their locations, and
	 * the enabled shipping methods on each zone (flat rate, free shipping with
	 * minimum, local pickup, etc.). Does NOT invent processing times or
	 * delivery estimates — WooCommerce core does not store those.
	 *
	 * @return array<string, mixed> Shipping info or a notice that none is configured.
	 */
	public function get_shipping_info(): array {
		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return array(
				'error' => 'Shipping settings could not be read.',
				'hint'  => 'Do not tell the shopper shipping is unavailable or misconfigured. Call search_site_content with "shipping", then call get_page_content on the best matching result and quote the concrete rates and conditions the shipping policy page lists; only if nothing is found, say you do not have shipping details and offer to connect them with the team.',
			);
		}

		$raw_zones = WC_Shipping_Zones::get_zones();
		if ( ! is_array( $raw_zones ) ) {
			$raw_zones = array();
		}

		$currency = function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : '';

		$zones = array();
		foreach ( $raw_zones as $raw_zone ) {
			$zone = $this->format_shipping_zone( $raw_zone );
			if ( null !== $zone ) {
				$zones[] = $zone;
			}
		}

		// "Rest of the World" zone (id 0) is implicit — fetch it explicitly.
		$rest_of_world = WC_Shipping_Zones::get_zone( 0 );
		if ( $rest_of_world ) {
			$methods = $this->format_shipping_methods( $rest_of_world->get_shipping_methods( true, 'admin' ) );
			if ( ! empty( $methods ) ) {
				$zones[] = array(
					'zone_id'   => 0,
					'zone_name' => 'Everywhere else (all destinations not covered by the zones above)',
					'locations' => array(),
					'methods'   => $methods,
				);
			}
		}

		if ( empty( $zones ) ) {
			return array(
				'has_shipping_configured' => false,
				'message'                 => 'No shipping details are available from the store settings.',
				'hint'                    => 'Do not tell the shopper shipping is unavailable or not configured. Call search_site_content with "shipping", then call get_page_content on the best matching result and quote the concrete rates and conditions the shipping policy page lists (never say costs are not shown while the page lists them); only if nothing is found, say you do not have shipping details and offer to connect them with the team.',
			);
		}

		return array(
			'has_shipping_configured' => true,
			'currency'                => $currency,
			'zones'                   => $zones,
			'notes'                   => array(
				'WooCommerce core does not store processing time or delivery estimates. Only the configured zones, methods, and costs are reported. Do not invent durations.',
				'If the shopper asks about a destination not covered by any zone listed here, do not say the store cannot ship there. Call search_site_content with "shipping", then call get_page_content on the shipping policy page and quote the concrete rates it lists for that destination before answering.',
			),
		);
	}

	/**
	 * Format a single shipping zone array (as returned by WC_Shipping_Zones::get_zones()).
	 *
	 * @param array<string, mixed> $raw_zone Zone data from WC.
	 * @return array<string, mixed>|null Formatted zone, or null if it has no usable methods.
	 */
	private function format_shipping_zone( array $raw_zone ): ?array {
		$zone_id   = isset( $raw_zone['zone_id'] ) ? (int) $raw_zone['zone_id'] : 0;
		$zone_name = isset( $raw_zone['zone_name'] ) && is_string( $raw_zone['zone_name'] ) ? $raw_zone['zone_name'] : '';

		$locations = array();
		if ( isset( $raw_zone['zone_locations'] ) && is_array( $raw_zone['zone_locations'] ) ) {
			foreach ( $raw_zone['zone_locations'] as $location ) {
				if ( is_object( $location ) && isset( $location->code, $location->type ) ) {
					$locations[] = array(
						'type' => (string) $location->type,
						'code' => (string) $location->code,
					);
				}
			}
		}

		$formatted_location = isset( $raw_zone['formatted_zone_location'] ) && is_string( $raw_zone['formatted_zone_location'] )
			? $raw_zone['formatted_zone_location']
			: '';

		$raw_methods = isset( $raw_zone['shipping_methods'] ) && is_array( $raw_zone['shipping_methods'] ) ? $raw_zone['shipping_methods'] : array();
		$methods     = $this->format_shipping_methods( $raw_methods );

		if ( empty( $methods ) ) {
			return null;
		}

		return array(
			'zone_id'            => $zone_id,
			'zone_name'          => $zone_name,
			'formatted_location' => $formatted_location,
			'locations'          => $locations,
			'methods'            => $methods,
		);
	}

	/**
	 * Format a list of WC shipping method objects into a simple array shape.
	 *
	 * @param array<int|string, object> $raw_methods Shipping method instances from WC.
	 * @return array<int, array<string, mixed>>
	 */
	private function format_shipping_methods( array $raw_methods ): array {
		$methods = array();
		foreach ( $raw_methods as $method ) {
			if ( ! is_object( $method ) ) {
				continue;
			}

			$enabled = isset( $method->enabled ) ? ( 'yes' === $method->enabled ) : true;
			if ( ! $enabled ) {
				continue;
			}

			$method_id = isset( $method->id ) && is_string( $method->id ) ? $method->id : '';
			$title     = isset( $method->title ) && is_string( $method->title ) ? $method->title : '';
			if ( '' === $title && method_exists( $method, 'get_method_title' ) ) {
				$maybe_title = $method->get_method_title();
				if ( is_string( $maybe_title ) ) {
					$title = $maybe_title;
				}
			}

			$entry = array(
				'method_id' => $method_id,
				'title'     => $title,
			);

			$cost = isset( $method->cost ) ? (string) $method->cost : '';
			if ( '' !== $cost ) {
				$entry['cost'] = $cost;
			}

			if ( 'free_shipping' === $method_id ) {
				$min_amount = isset( $method->min_amount ) ? (string) $method->min_amount : '';
				$requires   = isset( $method->requires ) && is_string( $method->requires ) ? $method->requires : '';
				if ( '' !== $min_amount ) {
					$entry['min_amount'] = $min_amount;
				}
				if ( '' !== $requires ) {
					$entry['requires'] = $requires;
				}
			}

			$methods[] = $entry;
		}
		return $methods;
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
	 * Get checkout/cart URLs and current cart state so the frontend can render a CTA button.
	 *
	 * @return array<string, mixed>
	 */
	public function get_checkout_action(): array {
		$checkout_url = function_exists( 'wc_get_checkout_url' ) ? (string) wc_get_checkout_url() : '';
		$cart_url     = function_exists( 'wc_get_cart_url' ) ? (string) wc_get_cart_url() : '';

		$item_count = 0;
		$cart       = $this->get_initialized_cart();
		if ( null !== $cart && method_exists( $cart, 'get_cart_contents_count' ) ) {
			$item_count = (int) $cart->get_cart_contents_count();
		}

		return array(
			'checkout_url' => $checkout_url,
			'cart_url'     => $cart_url,
			'has_cart'     => $item_count > 0,
			'item_count'   => $item_count,
		);
	}

	/**
	 * Validate a product (and variation) and return the add-to-cart intent for the
	 * frontend to execute via AJAX. The cart is intentionally not mutated here: the
	 * streaming response has already flushed headers, so the WooCommerce session
	 * cookie could not be set mid-stream for a first-time visitor.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function add_to_cart( array $args ): array {
		if ( ! wpaic_is_woocommerce_active() ) {
			return array(
				'success' => false,
				'reason'  => 'woocommerce_inactive',
				'message' => 'WooCommerce is not available.',
			);
		}

		$product_id   = isset( $args['product_id'] ) && is_numeric( $args['product_id'] ) ? (int) $args['product_id'] : 0;
		$variation_id = isset( $args['variation_id'] ) && is_numeric( $args['variation_id'] ) ? (int) $args['variation_id'] : 0;
		$quantity     = isset( $args['quantity'] ) && is_numeric( $args['quantity'] ) ? (int) $args['quantity'] : 1;
		if ( $quantity < 1 ) {
			$quantity = 1;
		}

		$product = $product_id > 0 && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		if ( ! $product ) {
			return array(
				'success' => false,
				'reason'  => 'not_found',
				'message' => 'Product not found.',
			);
		}

		$name = method_exists( $product, 'get_name' ) ? (string) $product->get_name() : '';

		// Variable products require a specific variation. Never guess one.
		if ( method_exists( $product, 'is_type' ) && $product->is_type( 'variable' ) ) {
			if ( $variation_id <= 0 ) {
				return array(
					'success'         => false,
					'needs_variation' => true,
					'product_id'      => $product_id,
					'name'            => $name,
					'message'         => 'This product has options (e.g. size or color). Ask the shopper which variation they want before adding.',
				);
			}

			$variation = wc_get_product( $variation_id );
			if ( ! $variation || ! method_exists( $variation, 'get_parent_id' ) || (int) $variation->get_parent_id() !== $product_id ) {
				return array(
					'success' => false,
					'reason'  => 'invalid_variation',
					'message' => 'That variation does not belong to this product.',
				);
			}

			$purchasable = $variation;
		} else {
			$variation_id = 0;
			$purchasable  = $product;
		}

		if ( method_exists( $purchasable, 'is_purchasable' ) && ! $purchasable->is_purchasable() ) {
			return array(
				'success' => false,
				'reason'  => 'not_purchasable',
				'message' => 'This product cannot be purchased.',
			);
		}

		if ( method_exists( $purchasable, 'is_in_stock' ) && ! $purchasable->is_in_stock() ) {
			return array(
				'success' => false,
				'reason'  => 'out_of_stock',
				'message' => 'This product is out of stock.',
			);
		}

		$intent = array(
			'success'    => true,
			'action'     => 'add_to_cart',
			'product_id' => $product_id,
			'quantity'   => $quantity,
			'name'       => $name,
		);
		if ( $variation_id > 0 ) {
			$intent['variation_id'] = $variation_id;
		}

		$related_products = $this->get_related_product_suggestions( $product );
		if ( ! empty( $related_products ) ) {
			$intent['related_products'] = $related_products;
		}

		return $intent;
	}

	/**
	 * Up to 3 cross-sell/upsell products (id/name/price only) configured on the
	 * added product, so the model can offer a single genuinely related suggestion
	 * after a successful add. Cross-sells come first (the WooCommerce-intended
	 * "goes well with" relation); upsells fill remaining slots.
	 *
	 * @param object $product The product just added to the cart.
	 * @return array<int, array<string, mixed>>
	 */
	private function get_related_product_suggestions( object $product ): array {
		$related_ids = array();
		foreach ( array( 'get_cross_sell_ids', 'get_upsell_ids' ) as $method ) {
			if ( ! method_exists( $product, $method ) ) {
				continue;
			}
			$ids = $product->$method();
			if ( is_array( $ids ) ) {
				$related_ids = array_merge( $related_ids, array_map( 'intval', $ids ) );
			}
		}

		$suggestions = array();
		foreach ( array_unique( $related_ids ) as $related_id ) {
			if ( count( $suggestions ) >= 3 ) {
				break;
			}
			if ( $related_id <= 0 ) {
				continue;
			}

			$related_product = wc_get_product( $related_id );
			if ( ! is_object( $related_product ) || ! method_exists( $related_product, 'get_name' ) ) {
				continue;
			}
			if ( method_exists( $related_product, 'is_purchasable' ) && ! $related_product->is_purchasable() ) {
				continue;
			}
			if ( method_exists( $related_product, 'is_in_stock' ) && ! $related_product->is_in_stock() ) {
				continue;
			}

			$suggestions[] = array(
				'id'    => $related_id,
				'name'  => (string) $related_product->get_name(),
				'price' => method_exists( $related_product, 'get_price' ) ? (string) $related_product->get_price() : '',
			);
		}

		return $suggestions;
	}

	/**
	 * Resolve which cart items (and how many units of each) to remove and return the
	 * clear-cart intent for the frontend to execute via AJAX after the shopper
	 * confirms. The cart is read but never mutated here (the streaming response has
	 * already flushed headers, like add_to_cart). Pass `items` ([{product_id,
	 * quantity}]) to remove specific items — quantity is how many units to remove,
	 * omit it to remove all units of that product — or omit `items` to clear the whole
	 * cart. Each returned item carries remove_quantity and remove_all so the UI can
	 * describe exactly what will be removed and the AJAX call knows how much to take.
	 *
	 * Cart lines are keyed by product_id only: variations of the same product are
	 * collapsed into one aggregate quantity, so a partial removal takes units from those
	 * lines in cart order rather than targeting a specific variation.
	 *
	 * @param array<string, mixed> $args
	 * @return array<string, mixed>
	 */
	public function clear_cart( array $args ): array {
		if ( ! wpaic_is_woocommerce_active() ) {
			return array(
				'success' => false,
				'reason'  => 'woocommerce_inactive',
				'message' => 'WooCommerce is not available.',
			);
		}

		$cart = $this->get_initialized_cart();
		if ( null === $cart ) {
			return array(
				'success' => false,
				'reason'  => 'cart_unavailable',
				'message' => 'Cart unavailable.',
			);
		}

		$cart_items = method_exists( $cart, 'get_cart' ) ? $cart->get_cart() : array();
		if ( ! is_array( $cart_items ) ) {
			$cart_items = array();
		}

		if ( 0 === count( $cart_items ) ) {
			return array(
				'success' => false,
				'reason'  => 'cart_empty',
				'message' => 'The cart is already empty.',
			);
		}

		// Map of requested product_id => units to remove (0 means "all units").
		$requested = $this->parse_clear_cart_items( $args );
		$clear_all = 0 === count( $requested );

		// Aggregate current quantity and name per matching product across cart lines.
		$current_quantity = array();
		$names            = array();
		foreach ( $cart_items as $cart_item ) {
			if ( ! is_array( $cart_item ) ) {
				continue;
			}

			$product_id = isset( $cart_item['product_id'] ) && is_numeric( $cart_item['product_id'] ) ? (int) $cart_item['product_id'] : 0;
			if ( ! $clear_all && ! array_key_exists( $product_id, $requested ) ) {
				continue;
			}

			$quantity                        = isset( $cart_item['quantity'] ) && is_numeric( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 0;
			$current_quantity[ $product_id ] = ( $current_quantity[ $product_id ] ?? 0 ) + $quantity;
			if ( ! isset( $names[ $product_id ] ) ) {
				$product            = $this->get_cart_item_product( $cart_item, $product_id );
				$names[ $product_id ] = $this->get_cart_item_name( $cart_item, $product );
			}
		}

		if ( ! $clear_all && 0 === count( $current_quantity ) ) {
			return array(
				'success' => false,
				'reason'  => 'not_in_cart',
				'message' => 'None of those items are in the cart.',
			);
		}

		$items = array();
		foreach ( $current_quantity as $product_id => $in_cart ) {
			$wanted     = $clear_all ? 0 : ( $requested[ $product_id ] ?? 0 );
			$remove_all = $wanted <= 0 || $wanted >= $in_cart;
			$items[]    = array(
				'product_id'      => $product_id,
				'name'            => $names[ $product_id ] ?? '',
				'remove_quantity' => $remove_all ? $in_cart : $wanted,
				'remove_all'      => $remove_all,
			);
		}

		return array(
			'success'   => true,
			'action'    => 'clear_cart',
			'clear_all' => $clear_all,
			'items'     => $items,
		);
	}

	/**
	 * Parse the clear_cart `items` argument into a map of product_id => units to
	 * remove (0 means remove all units of that product). Last entry wins per product.
	 *
	 * @param array<string, mixed> $args
	 * @return array<int, int>
	 */
	private function parse_clear_cart_items( array $args ): array {
		$requested = array();
		if ( ! isset( $args['items'] ) || ! is_array( $args['items'] ) ) {
			return $requested;
		}

		foreach ( $args['items'] as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$product_id = isset( $entry['product_id'] ) && is_numeric( $entry['product_id'] ) ? (int) $entry['product_id'] : 0;
			if ( $product_id <= 0 ) {
				continue;
			}
			$quantity                = isset( $entry['quantity'] ) && is_numeric( $entry['quantity'] ) ? (int) $entry['quantity'] : 0;
			$requested[ $product_id ] = max( 0, $quantity );
		}

		return $requested;
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
		// Injected server-side by WPAIC_Chat::execute_tool, never part of the model-facing schema.
		$conversation_id = isset( $args['conversation_id'] ) && is_numeric( $args['conversation_id'] ) ? (int) $args['conversation_id'] : 0;

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
			'customer_name'   => $customer_name,
			'customer_email'  => $customer_email,
			'conversation_id' => $conversation_id > 0 ? $conversation_id : null,
			'transcript'      => $transcript,
			'extra_fields'    => $extra_fields_json,
			'status'          => 'new',
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		);
		$insert_formats = array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' );

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
	 * @return array<string, mixed> Page content, or array('error' => ...) when unavailable
	 *                              (null would serialize as bare "null" to the model).
	 */
	public function get_page_content( array $args ): array {
		$post_id       = isset( $args['post_id'] ) && is_numeric( $args['post_id'] ) ? (int) $args['post_id'] : 0;
		$content_index = new WPAIC_Content_Index();
		return $content_index->get_page_content( $post_id ) ?? array( 'error' => 'Page not found or not available' );
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
}
