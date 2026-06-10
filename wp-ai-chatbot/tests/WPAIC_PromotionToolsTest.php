<?php
/**
 * Tests for WPAIC_Promotion_Tools class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-promotion-tools.php';

class WPAIC_PromotionToolsTest extends TestCase {
	private WPAIC_Promotion_Tools $promotion_tools;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		$this->promotion_tools = new WPAIC_Promotion_Tools();
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_get_active_promotions_returns_empty_state_when_no_coupons(): void {
		$result = $this->promotion_tools->get_active_promotions();

		$this->assertFalse( $result['has_promotions'] );
		$this->assertStringContainsString( 'no active coupons or promotions', $result['message'] );
		$this->assertArrayNotHasKey( 'promotions', $result );
	}

	public function test_get_active_promotions_returns_coupon_details(): void {
		$this->create_mock_coupon( 50, 'SAVE10', 'percent', '10' );
		WPAICTestHelper::set_post_meta( 50, 'free_shipping', 'yes' );
		WPAICTestHelper::set_post_meta( 50, 'minimum_amount', '25' );
		WPAICTestHelper::set_post_meta( 50, 'date_expires', (string) ( time() + DAY_IN_SECONDS ) );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertTrue( $result['has_promotions'] );
		$this->assertCount( 1, $result['promotions'] );

		$promotion = $result['promotions'][0];
		$this->assertSame( 'SAVE10', $promotion['code'] );
		$this->assertSame( 'percent', $promotion['discount_type'] );
		$this->assertSame( '10', $promotion['amount'] );
		$this->assertTrue( $promotion['free_shipping'] );
		$this->assertSame( '25', $promotion['minimum_spend'] );
		$this->assertSame( gmdate( 'Y-m-d', time() + DAY_IN_SECONDS ), $promotion['expires'] );
	}

	public function test_get_active_promotions_includes_coupon_description(): void {
		$this->create_mock_coupon( 50, 'WELCOME5', 'fixed_cart', '5' );
		WPAICTestHelper::get_mock_post( 50 )->post_excerpt = '<p>5 off your first order</p>';

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertSame( '5 off your first order', $result['promotions'][0]['description'] );
	}

	public function test_get_active_promotions_skips_expired_coupons(): void {
		$this->create_mock_coupon( 50, 'EXPIRED', 'percent', '20' );
		WPAICTestHelper::set_post_meta( 50, 'date_expires', (string) ( time() - DAY_IN_SECONDS ) );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertFalse( $result['has_promotions'] );
	}

	public function test_get_active_promotions_includes_coupon_with_empty_expiry_meta(): void {
		$this->create_mock_coupon( 50, 'NOEXPIRY', 'percent', '15' );
		// WooCommerce stores date_expires as '' for coupons that never expire.
		WPAICTestHelper::set_post_meta( 50, 'date_expires', '' );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertTrue( $result['has_promotions'] );
		$this->assertSame( 'NOEXPIRY', $result['promotions'][0]['code'] );
	}

	/**
	 * Expiry is filtered in the WP_Query itself: a full page of expired coupons
	 * must not mask an active one behind the posts_per_page cap.
	 */
	public function test_get_active_promotions_expired_coupons_cannot_mask_active_ones(): void {
		for ( $coupon_id = 100; $coupon_id < 120; $coupon_id++ ) {
			$this->create_mock_coupon( $coupon_id, 'EXPIRED' . $coupon_id, 'percent', '20' );
			WPAICTestHelper::set_post_meta( $coupon_id, 'date_expires', (string) ( time() - DAY_IN_SECONDS ) );
		}
		$this->create_mock_coupon( 200, 'STILLGOOD', 'percent', '10' );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertTrue( $result['has_promotions'] );
		$this->assertCount( 1, $result['promotions'] );
		$this->assertSame( 'STILLGOOD', $result['promotions'][0]['code'] );
	}

	public function test_get_active_promotions_skips_usage_exhausted_coupons(): void {
		$this->create_mock_coupon( 50, 'USEDUP', 'percent', '15' );
		WPAICTestHelper::set_post_meta( 50, 'usage_limit', '5' );
		WPAICTestHelper::set_post_meta( 50, 'usage_count', '5' );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertFalse( $result['has_promotions'] );
	}

	public function test_get_active_promotions_skips_unpublished_coupons(): void {
		$this->create_mock_coupon( 50, 'DRAFTCODE', 'percent', '30', 'draft' );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertFalse( $result['has_promotions'] );
	}

	public function test_get_active_promotions_lists_restricted_product_names(): void {
		$this->create_mock_product( 1, 'Red Shirt', '19.99' );
		$this->create_mock_coupon( 50, 'SHIRTDEAL', 'percent', '10' );
		WPAICTestHelper::set_post_meta( 50, 'product_ids', '1' );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertSame( array( 'Red Shirt' ), $result['promotions'][0]['limited_to_products'] );
	}

	public function test_get_active_promotions_unavailable_when_woocommerce_inactive(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		$this->create_mock_coupon( 50, 'SAVE10', 'percent', '10' );

		$result = $this->promotion_tools->get_active_promotions();

		$this->assertFalse( $result['has_promotions'] );
	}

	/**
	 * Creates a mock WooCommerce coupon post with metadata.
	 */
	private function create_mock_coupon( int $id, string $code, string $discount_type, string $amount, string $post_status = 'publish' ): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'          => $id,
				'post_title'  => $code,
				'post_type'   => 'shop_coupon',
				'post_status' => $post_status,
			)
		);

		WPAICTestHelper::set_post_meta( $id, 'discount_type', $discount_type );
		WPAICTestHelper::set_post_meta( $id, 'coupon_amount', $amount );
	}

	/**
	 * Creates a mock WooCommerce product post (only what coupon restrictions read).
	 */
	private function create_mock_product( int $id, string $title, string $price ): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'          => $id,
				'post_title'  => $title,
				'post_type'   => 'product',
				'post_status' => 'publish',
			)
		);

		WPAICTestHelper::set_post_meta( $id, '_price', $price );
	}
}
