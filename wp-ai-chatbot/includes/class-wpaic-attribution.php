<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Links completed WooCommerce orders back to the conversation that drove them.
 *
 * Last-touch, session-based: when the bot successfully adds an item to the cart
 * (WPAIC_Cart), the conversation id is stored on the WooCommerce session. When
 * that session's order reaches payment, the order is tagged and an
 * order_completed event is recorded against the conversation — the one new
 * backend capability the Analytics page needs.
 *
 * Known v1 limits (acceptable): gross totals (no COGS/refund reversal); orders
 * that never fire woocommerce_payment_complete (some manual/COD flows) are
 * missed; last-touch within a single WC session only.
 */
class WPAIC_Attribution {
	private const SESSION_KEY     = 'wpaic_conversation_id';
	private const META_ATTRIBUTED = '_wpaic_attributed';
	private const META_CONVERSATION = '_wpaic_conversation_id';

	public function init(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce standard hook
		add_action( 'woocommerce_payment_complete', array( $this, 'on_payment_complete' ) );
	}

	/**
	 * On payment, attribute the order to the conversation carried on its WC
	 * session. Idempotent: the _wpaic_attributed meta guards against the hook
	 * firing more than once for the same order.
	 */
	public function on_payment_complete( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) || ! class_exists( 'WPAIC_Events' ) ) {
			return;
		}

		$conversation_id = $this->get_session_conversation_id();
		if ( $conversation_id <= 0 ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		if ( '' !== (string) $order->get_meta( self::META_ATTRIBUTED ) ) {
			return;
		}

		$order->update_meta_data( self::META_CONVERSATION, $conversation_id );
		$order->update_meta_data( self::META_ATTRIBUTED, '1' );
		$order->save();

		WPAIC_Events::record(
			$conversation_id,
			WPAIC_Events::ORDER_COMPLETED,
			array(
				'order_id' => $order_id,
				'total'    => (float) $order->get_total(),
				'currency' => $order->get_currency(),
			)
		);
	}

	/**
	 * Conversation id stashed on the current WooCommerce session by the cart-add
	 * path, or 0 when the bot never touched this session's cart.
	 */
	private function get_session_conversation_id(): int {
		if ( ! function_exists( 'WC' ) ) {
			return 0;
		}
		$wc = WC();
		if ( ! is_object( $wc ) || ! isset( $wc->session ) || ! is_object( $wc->session ) ) {
			return 0;
		}
		return (int) $wc->session->get( self::SESSION_KEY, 0 );
	}
}
