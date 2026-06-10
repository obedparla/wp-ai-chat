<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Promotion_Tools {
	/**
	 * How many coupons get_active_promotions reads and returns at most.
	 */
	private const MAX_PROMOTIONS = 20;

	/**
	 * Get the store's currently active promotions: published, non-expired
	 * WooCommerce coupons (`shop_coupon` posts) with their code, discount
	 * amount/type, common restrictions, and expiry. Coupons whose usage limit is
	 * exhausted are skipped so the bot never advertises a dead code.
	 *
	 * @return array<string, mixed>
	 */
	public function get_active_promotions(): array {
		if ( ! wpaic_is_woocommerce_active() ) {
			return array(
				'has_promotions' => false,
				'message'        => 'No promotion information is available.',
			);
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => self::MAX_PROMOTIONS,
				// Exclude expired coupons in the query itself so a page of
				// expired coupons cannot mask active ones behind the
				// posts_per_page cap. WooCommerce stores `date_expires` as a
				// timestamp, or empty/absent when the coupon never expires.
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'     => 'date_expires',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => 'date_expires',
						'value'   => '',
						'compare' => '=',
					),
					array(
						'key'     => 'date_expires',
						'value'   => time(),
						'compare' => '>=',
						'type'    => 'NUMERIC',
					),
				),
			)
		);

		$promotions = array();
		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$expiry_timestamp = get_post_meta( $post->ID, 'date_expires', true );
			if ( is_numeric( $expiry_timestamp ) && (int) $expiry_timestamp > 0 && (int) $expiry_timestamp < time() ) {
				continue;
			}

			$usage_limit = get_post_meta( $post->ID, 'usage_limit', true );
			$usage_count = get_post_meta( $post->ID, 'usage_count', true );
			if ( is_numeric( $usage_limit ) && (int) $usage_limit > 0 && (int) $usage_count >= (int) $usage_limit ) {
				continue;
			}

			$promotions[] = $this->format_promotion( $post );
		}

		if ( empty( $promotions ) ) {
			return array(
				'has_promotions' => false,
				'message'        => 'There are no active coupons or promotions right now.',
			);
		}

		return array(
			'has_promotions' => true,
			'promotions'     => $promotions,
		);
	}

	/**
	 * Format one coupon post into a model-friendly promotion payload.
	 *
	 * @param WP_Post $post Coupon post.
	 * @return array<string, mixed>
	 */
	private function format_promotion( WP_Post $post ): array {
		$discount_type = get_post_meta( $post->ID, 'discount_type', true );

		$promotion = array(
			'code'          => $post->post_title,
			'discount_type' => is_string( $discount_type ) && '' !== $discount_type ? $discount_type : 'fixed_cart',
			'amount'        => (string) get_post_meta( $post->ID, 'coupon_amount', true ),
		);

		if ( '' !== $post->post_excerpt ) {
			$promotion['description'] = wp_strip_all_tags( $post->post_excerpt );
		}

		$expiry_timestamp = get_post_meta( $post->ID, 'date_expires', true );
		if ( is_numeric( $expiry_timestamp ) && (int) $expiry_timestamp > 0 ) {
			$promotion['expires'] = gmdate( 'Y-m-d', (int) $expiry_timestamp );
		}

		if ( 'yes' === get_post_meta( $post->ID, 'free_shipping', true ) ) {
			$promotion['free_shipping'] = true;
		}

		$minimum_amount = get_post_meta( $post->ID, 'minimum_amount', true );
		if ( is_numeric( $minimum_amount ) && (float) $minimum_amount > 0 ) {
			$promotion['minimum_spend'] = (string) $minimum_amount;
		}

		$maximum_amount = get_post_meta( $post->ID, 'maximum_amount', true );
		if ( is_numeric( $maximum_amount ) && (float) $maximum_amount > 0 ) {
			$promotion['maximum_spend'] = (string) $maximum_amount;
		}

		$restricted_product_names = $this->get_promotion_restricted_product_names( $post->ID );
		if ( ! empty( $restricted_product_names ) ) {
			$promotion['limited_to_products'] = $restricted_product_names;
		}

		return $promotion;
	}

	/**
	 * Product names a coupon is restricted to (WC stores `product_ids` as a
	 * comma-separated string or an array of IDs).
	 *
	 * @return array<int, string>
	 */
	private function get_promotion_restricted_product_names( int $coupon_id ): array {
		$product_ids_raw = get_post_meta( $coupon_id, 'product_ids', true );
		$product_ids     = is_array( $product_ids_raw ) ? $product_ids_raw : explode( ',', (string) $product_ids_raw );

		$names = array();
		foreach ( $product_ids as $product_id ) {
			if ( ! is_numeric( $product_id ) || (int) $product_id <= 0 ) {
				continue;
			}
			$product_post = get_post( (int) $product_id );
			if ( $product_post instanceof WP_Post && '' !== $product_post->post_title ) {
				$names[] = $product_post->post_title;
			}
		}

		return $names;
	}
}
