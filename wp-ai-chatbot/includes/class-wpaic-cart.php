<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Cart {
	public function init(): void {
		add_action( 'wp_ajax_woocommerce_ajax_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
		add_action( 'wp_ajax_nopriv_woocommerce_ajax_add_to_cart', array( $this, 'ajax_add_to_cart' ) );
	}

	public function ajax_add_to_cart(): void {
		if ( ! wpaic_is_woocommerce_active() ) {
			wp_send_json_error( array( 'message' => 'WooCommerce not active' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for add to cart
		$product_id = isset( $_REQUEST['product_id'] ) ? absint( $_REQUEST['product_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public AJAX for add to cart
		$quantity = isset( $_REQUEST['quantity'] ) ? absint( $_REQUEST['quantity'] ) : 1;

		if ( $product_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'Invalid product ID' ) );
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			wp_send_json_error( array( 'message' => 'Product not found' ) );
		}

		if ( ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => 'Product cannot be purchased' ) );
		}

		if ( ! $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => 'Product is out of stock' ) );
		}

		$cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

		if ( $cart_item_key ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook
			do_action( 'woocommerce_ajax_added_to_cart', $product_id );

			wp_send_json_success(
				array(
					'message'       => 'Product added to cart',
					'cart_item_key' => $cart_item_key,
					'cart_count'    => WC()->cart->get_cart_contents_count(),
					'cart_total'    => WC()->cart->get_cart_total(),
					'fragments'     => $this->get_cart_fragments(),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => 'Failed to add product to cart' ) );
		}
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
