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
		add_action( 'wp_ajax_wpaic_cart_cancelled', array( $this, 'ajax_cart_cancelled' ) );
		add_action( 'wp_ajax_nopriv_wpaic_cart_cancelled', array( $this, 'ajax_cart_cancelled' ) );
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
			$this->record_cart_confirmation( 'add', 'failed', $product->get_name() );
			wp_send_json_error( array( 'message' => 'Product cannot be purchased' ) );
		}

		if ( ! $product->is_in_stock() ) {
			$this->record_cart_confirmation( 'add', 'failed', $product->get_name() );
			wp_send_json_error( array( 'message' => 'Product is out of stock' ) );
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $this->get_request_variation_attributes() );

		if ( $cart_item_key ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );

			$this->record_cart_confirmation( 'add', 'completed', $product->get_name() );

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
			$this->record_cart_confirmation( 'add', 'failed', $product->get_name() );
			wp_send_json_error( array( 'message' => 'Failed to add product to cart' ) );
		}
	}

	/**
	 * Remove items from the cart, or empty it entirely. Mirrors ajax_add_to_cart: the
	 * actual cart mutation happens here (after the shopper confirmed in the chat UI),
	 * then mini-cart fragments are returned so the page updates. Pass an `items` JSON
	 * array of {product_id, quantity} to remove that many units of each product (lines
	 * are reduced, only fully removed when quantity reaches the line total); omit
	 * `items` to clear everything.
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
			$removed_names = array();
			foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
				$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
				if ( ! isset( $remaining[ $product_id ] ) || $remaining[ $product_id ] <= 0 ) {
					continue;
				}

				$line_product = isset( $cart_item['data'] ) && is_object( $cart_item['data'] ) && method_exists( $cart_item['data'], 'get_name' ) ? $cart_item['data'] : null;
				if ( $line_product && '' !== (string) $line_product->get_name() ) {
					$removed_names[ $product_id ] = (string) $line_product->get_name();
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
			$this->record_cart_confirmation( 'remove', 'completed', implode( ', ', $removed_names ) );
		} else {
			$cart->empty_cart();
			$this->record_cart_confirmation( 'clear', 'completed' );
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
	 * Record that the shopper dismissed the clear/remove confirmation popup
	 * without confirming, so the conversation transcript shows the outcome.
	 */
	public function ajax_cart_cancelled(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for the shopper's own session cart
		$action = isset( $_REQUEST['cart_action'] ) ? sanitize_key( wp_unslash( $_REQUEST['cart_action'] ) ) : '';
		if ( ! in_array( $action, array( 'clear', 'remove' ), true ) ) {
			wp_send_json_error( array( 'message' => 'Invalid cart action' ) );
		}

		$this->record_cart_confirmation( $action, 'cancelled' );
		wp_send_json_success();
	}

	/**
	 * Record the real outcome of a chat-initiated cart change as a conversation
	 * event (the chat tool only proposes the change; the mutation happens in the
	 * AJAX endpoints here). No-op without a valid chat session id in the request.
	 */
	private function record_cart_confirmation( string $action, string $outcome, string $name = '' ): void {
		if ( ! class_exists( 'WPAIC_Events' ) || ! class_exists( 'WPAIC_Logs' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for the shopper's own session cart
		$session_id = isset( $_REQUEST['wpaic_session_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['wpaic_session_id'] ) ) : '';
		if ( '' === $session_id || ! wp_is_uuid( $session_id ) ) {
			return;
		}

		$logs            = new WPAIC_Logs();
		$conversation_id = $logs->get_conversation_id( $session_id );
		if ( null === $conversation_id ) {
			return;
		}

		$event_data = array(
			'action'  => $action,
			'outcome' => $outcome,
		);
		if ( '' !== $name ) {
			$event_data['name'] = $name;
		}

		WPAIC_Events::record( $conversation_id, WPAIC_Events::CART_CONFIRMATION, $event_data );

		// Tag the WooCommerce session so a later completed order can be
		// attributed to this conversation (WPAIC_Attribution reads it on
		// woocommerce_payment_complete). Only successful bot adds count.
		if ( 'add' === $action && 'completed' === $outcome && function_exists( 'WC' ) ) {
			$wc = WC();
			if ( is_object( $wc ) && isset( $wc->session ) && is_object( $wc->session ) ) {
				$wc->session->set( 'wpaic_conversation_id', $conversation_id );
			}
		}
	}

	/**
	 * Parse the `items` request param (JSON array of {product_id, quantity}) into a map
	 * of product_id => units to remove. Empty means clear the whole cart.
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
			$product_id = isset( $entry['product_id'] ) ? absint( $entry['product_id'] ) : 0;
			$quantity   = isset( $entry['quantity'] ) ? absint( $entry['quantity'] ) : 0;
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
