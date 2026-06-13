<?php
/**
 * Tests for WPAIC_Attribution: linking a completed order to the conversation
 * carried on its WooCommerce session, idempotently.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-events.php';
require_once __DIR__ . '/../includes/class-wpaic-attribution.php';

class WPAIC_AttributionTest extends TestCase {
	private WPAIC_Attribution $attribution;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb, $mock_wc;
		if ( ! $wpdb instanceof MockWpdb ) {
			$wpdb = new MockWpdb();
		}
		$wpdb->reset();
		WPAICTestHelper::reset();
		$mock_wc = null; // Fresh WooCommerce singleton (empty session) per test.
		$this->attribution = new WPAIC_Attribution();
	}

	protected function tearDown(): void {
		global $wpdb, $mock_wc;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		WPAICTestHelper::reset();
		$mock_wc = null;
		parent::tearDown();
	}

	public function test_records_order_completed_event_and_tags_order(): void {
		WC()->session->set( 'wpaic_conversation_id', 7 );
		WPAICTestHelper::add_mock_order( array( 'id' => 1, 'total' => 150.0, 'currency' => 'USD' ) );

		$this->attribution->on_payment_complete( 1 );

		$events = WPAIC_Events::get_for_conversation( 7 );
		$this->assertCount( 1, $events );
		$this->assertSame( WPAIC_Events::ORDER_COMPLETED, $events[0]->event_type );
		$this->assertSame( 1, $events[0]->event_data['order_id'] );
		// Whole floats serialize to int through JSON; analytics casts on read.
		$this->assertEquals( 150.0, $events[0]->event_data['total'] );
		$this->assertSame( 'USD', $events[0]->event_data['currency'] );

		$order = wc_get_order( 1 );
		$this->assertSame( 7, $order->get_meta( '_wpaic_conversation_id' ) );
		$this->assertSame( '1', $order->get_meta( '_wpaic_attributed' ) );
	}

	public function test_attribution_is_idempotent(): void {
		WC()->session->set( 'wpaic_conversation_id', 7 );
		WPAICTestHelper::add_mock_order( array( 'id' => 1, 'total' => 150.0, 'currency' => 'USD' ) );

		$this->attribution->on_payment_complete( 1 );
		$this->attribution->on_payment_complete( 1 );

		$this->assertCount( 1, WPAIC_Events::get_for_conversation( 7 ) );
	}

	public function test_no_event_without_session_conversation(): void {
		WPAICTestHelper::add_mock_order( array( 'id' => 1, 'total' => 150.0, 'currency' => 'USD' ) );

		$this->attribution->on_payment_complete( 1 );

		$this->assertCount( 0, WPAIC_Events::get_for_conversation( 7 ) );
		$order = wc_get_order( 1 );
		$this->assertSame( '', $order->get_meta( '_wpaic_attributed' ) );
	}

	public function test_no_event_when_order_missing(): void {
		WC()->session->set( 'wpaic_conversation_id', 7 );

		$this->attribution->on_payment_complete( 999 );

		$this->assertCount( 0, WPAIC_Events::get_for_conversation( 7 ) );
	}
}
