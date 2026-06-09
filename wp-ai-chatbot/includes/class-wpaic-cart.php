<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Cart {
	public function init(): void {
		add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_wpaic_clear_cart', array( $this, 'ajax_clear_cart' ) );
		add_action( 'wp_ajax_nopriv_wpaic_clear_cart', array( $this, 'ajax_clear_cart' ) );
	}

	public function ajax_add_to_cart(): void {
		if ( ! wpaic_is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for add to cart
		$product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for add to cart
		$quantity = isset( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for add to cart
		$variation_id = isset( $_REQUEST['variation_id'] ) ? absint( $_REQUEST['variation_id'] ) : 0;

		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid product ID' ) );
		}

		// For a variable product, validate and add the chosen variation, not the parent.
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Product not found' ) );
		}

		if ( ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => 'Product cannot be purchased' ) );
		}

		if ( ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => 'Product is out of stock' ) );
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $this->get_request_variation_attributes() );

		if ( $cart_item_key ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );

			wp_send_json_success(
				array(
					'message'       => 'Product added to cart',
					'cart_item_key' => $cart_item_key,
					'cart_count'    => WC()->cart->get_cart_contents_count(),
					'cart_total'    => WC()->cart->get_cart_total(),
					'cart_hash'     => method_exists( WC()->cart, 'get_cart_hash' ) ? WC()->cart->get_cart_hash() : '',
					'fragments'     => $this->get_cart_fragments(),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to add product to cart' ) );
		}
	}

	/**
	 * Remove items from the cart, or empty it entirely. Mirrors ajax_add_to_cart: the
	 * actual cart mutation happens here (after the shopper confirmed in the chat UI),
	 * then mini-cart fragments are returned so the page updates. Pass an `items` JSON
	 * array of {id, qty} to remove qty units of each product (lines are reduced, only
	 * fully removed when qty reaches the line total); omit `items` to clear everything.
	 */
	public function ajax_clear_cart(): void {
		if ( ! wpaic_is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		$cart = WC()->cart;
		if ( ! is_object( $cart ) ) {
			wp_send_json_error( array( 'message' => 'Cart unavailable' ) );
		}

		$remaining = $this->get_request_clear_items();

		if ( count( $remaining ) > 0 ) {
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
				if ( ! isset( $remaining[ $product_id ] ) || $remaining[ $product_id ] <= 0 ) {
					continue;
				}

				$line_quantity = isset( $cart_item['quantity'] ) ? absint( $cart_item['quantity'] ) : 0;
				if ( $remaining[ $product_id ] >= $line_quantity ) {
					$cart->remove_cart_item( $cart_item_key );
					$remaining[ $product_id ] -= $line_quantity;
				} else {
					$cart->set_quantity( $cart_item_key, $line_quantity - $remaining[ $product_id ] );
					$remaining[ $product_id ] = 0;
				}
			}
		} else {
			$cart->empty_cart();
		}

		wp_send_json_success(
			array(
				'message'    => 'Cart updated',
				'cart_count' => $cart->get_cart_contents_count(),
				'cart_total' => $cart->get_cart_total(),
				'cart_hash'  => method_exists( $cart, 'get_cart_hash' ) ? $cart->get_cart_hash() : '',
				'fragments'  => $this->get_cart_fragments(),
			)
		);
	}

	/**
	 * Parse the `items` request param (JSON array of {id, qty}) into a map of
	 * product_id => units to remove. Empty means clear the whole cart.
	 *
	 * @return array<int, int>
	 */
	private function get_request_clear_items(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for the shopper's own session cart
		$raw = isset( $_REQUEST['items'] ) ? wp_unslash( $_REQUEST['items'] ) : '';
		if ( ! is_string( $raw ) || '' === $raw ) {
			return array();
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$items = array();
		foreach ( $decoded as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$product_id = isset( $entry['id'] ) ? absint( $entry['id'] ) : 0;
			$quantity   = isset( $entry['qty'] ) ? absint( $entry['qty'] ) : 0;
			if ( $product_id > 0 && $quantity > 0 ) {
				$items[ $product_id ] = ( $items[ $product_id ] ?? 0 ) + $quantity;
			}
		}

		return $items;
	}

	/**
	 * Collect attribute_* variation selections from the request.
	 *
	 * @return array<string, string>
	 */
	private function get_request_variation_attributes(): array {
		$attributes = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for add to cart
		foreach ( $_REQUEST as $key => $value ) {
			if ( is_string( $key ) && str_starts_with( $key, 'attribute_' ) && is_string( $value ) ) {
				$attributes[ sanitize_key( $key ) ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}
		return $attributes;
	}

	/**
	 * Get cart fragments for updating mini-cart.
	 *
	 * @return array<string, string>
	 */
	private function get_cart_fragments(): array {
		$fragments = array();

		ob_start();
		woocommerce_mini_cart();
		$mini_cart = ob_get_clean();

		$fragments['div.widget_shopping_cart_content'] = '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard filter
		return apply_filters( 'woocommerce_add_to_cart_fragments', $fragments );
	}
}
