<?php
/**
 * Tests for WPAIC_Events class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-events.php';

class WPAIC_EventsTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		if ( ! $wpdb instanceof MockWpdb ) {
			$wpdb = new MockWpdb();
		}
		$wpdb->reset();
		WPAICTestHelper::reset();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_record_returns_event_id(): void {
		$event_id = WPAIC_Events::record( 5, WPAIC_Events::CHECKOUT_STARTED );

		$this->assertIsInt( $event_id );
		$this->assertGreaterThan( 0, $event_id );
	}

	public function test_record_returns_false_for_invalid_conversation(): void {
		$this->assertFalse( WPAIC_Events::record( 0, WPAIC_Events::CHECKOUT_STARTED ) );
		$this->assertFalse( WPAIC_Events::record( -1, WPAIC_Events::CHECKOUT_STARTED ) );
	}

	public function test_record_returns_false_for_empty_event_type(): void {
		$this->assertFalse( WPAIC_Events::record( 5, '' ) );
	}

	public function test_get_for_conversation_returns_decoded_event_data(): void {
		WPAIC_Events::record(
			7,
			WPAIC_Events::SEARCH_PERFORMED,
			array(
				'query'        => 'red shoes',
				'result_count' => 4,
			)
		);

		$events = WPAIC_Events::get_for_conversation( 7 );

		$this->assertCount( 1, $events );
		$this->assertEquals( WPAIC_Events::SEARCH_PERFORMED, $events[0]->event_type );
		$this->assertIsArray( $events[0]->event_data );
		$this->assertEquals( 'red shoes', $events[0]->event_data['query'] );
		$this->assertEquals( 4, $events[0]->event_data['result_count'] );
	}

	public function test_get_for_conversation_only_returns_matching_conversation(): void {
		WPAIC_Events::record( 1, WPAIC_Events::CHECKOUT_STARTED );
		WPAIC_Events::record( 2, WPAIC_Events::CHECKOUT_STARTED );
		WPAIC_Events::record( 1, WPAIC_Events::HANDOFF_CREATED, array( 'request_id' => 9 ) );

		$events = WPAIC_Events::get_for_conversation( 1 );

		$this->assertCount( 2, $events );
		$this->assertEquals( WPAIC_Events::CHECKOUT_STARTED, $events[0]->event_type );
		$this->assertEquals( WPAIC_Events::HANDOFF_CREATED, $events[1]->event_type );
	}

	public function test_get_for_conversation_returns_empty_when_none(): void {
		$this->assertSame( array(), WPAIC_Events::get_for_conversation( 99 ) );
	}

	public function test_count_between_counts_only_matching_type_in_range(): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => 1,
				'event_type'      => WPAIC_Events::CHECKOUT_STARTED,
				'event_data'      => '{}',
				'created_at'      => '2026-06-05 10:00:00',
			)
		);
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => 2,
				'event_type'      => WPAIC_Events::CHECKOUT_STARTED,
				'event_data'      => '{}',
				'created_at'      => '2026-05-01 10:00:00',
			)
		);
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => 3,
				'event_type'      => WPAIC_Events::HANDOFF_CREATED,
				'event_data'      => '{}',
				'created_at'      => '2026-06-05 11:00:00',
			)
		);

		$count = WPAIC_Events::count_between( WPAIC_Events::CHECKOUT_STARTED, '2026-06-01 00:00:00', '2026-06-08 00:00:00' );

		$this->assertEquals( 1, $count );
	}

	public function test_count_between_upper_bound_is_exclusive(): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => 1,
				'event_type'      => WPAIC_Events::PRODUCT_ADDED_TO_CART,
				'event_data'      => '{}',
				'created_at'      => '2026-06-08 00:00:00',
			)
		);

		$this->assertEquals( 0, WPAIC_Events::count_between( WPAIC_Events::PRODUCT_ADDED_TO_CART, '2026-06-01 00:00:00', '2026-06-08 00:00:00' ) );
		$this->assertEquals( 1, WPAIC_Events::count_between( WPAIC_Events::PRODUCT_ADDED_TO_CART, '2026-06-08 00:00:00', '2026-06-09 00:00:00' ) );
	}

	public function test_get_zero_result_searches_groups_and_orders_by_count(): void {
		WPAIC_Events::record( 1, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'vegan leather bag', 'result_count' => 0 ) );
		WPAIC_Events::record( 2, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'Vegan Leather Bag', 'result_count' => 0 ) );
		WPAIC_Events::record( 3, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'unicorn saddle', 'result_count' => 0 ) );
		WPAIC_Events::record( 4, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'red shoes', 'result_count' => 5 ) );

		$top = WPAIC_Events::get_zero_result_searches();

		$this->assertCount( 2, $top );
		$this->assertEquals( 'vegan leather bag', $top[0]['query'] );
		$this->assertEquals( 2, $top[0]['count'] );
		$this->assertEquals( 'unicorn saddle', $top[1]['query'] );
		$this->assertEquals( 1, $top[1]['count'] );
	}

	public function test_get_zero_result_searches_respects_limit(): void {
		WPAIC_Events::record( 1, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'first', 'result_count' => 0 ) );
		WPAIC_Events::record( 2, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'second', 'result_count' => 0 ) );
		WPAIC_Events::record( 3, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'third', 'result_count' => 0 ) );

		$top = WPAIC_Events::get_zero_result_searches( 2 );

		$this->assertCount( 2, $top );
	}

	public function test_get_zero_result_searches_excludes_old_events(): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => 1,
				'event_type'      => WPAIC_Events::SEARCH_PERFORMED,
				'event_data'      => '{"query":"ancient query","result_count":0}',
				'created_at'      => '2020-01-01 10:00:00',
			)
		);

		$this->assertSame( array(), WPAIC_Events::get_zero_result_searches() );
	}

	public function test_get_zero_result_searches_skips_blank_and_malformed_queries(): void {
		WPAIC_Events::record( 1, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => '   ', 'result_count' => 0 ) );
		WPAIC_Events::record( 2, WPAIC_Events::SEARCH_PERFORMED, array( 'result_count' => 0 ) );

		$this->assertSame( array(), WPAIC_Events::get_zero_result_searches() );
	}

	public function test_describe_search_performed(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::SEARCH_PERFORMED,
			array(
				'query'        => 'mug',
				'result_count' => 3,
			)
		);

		$this->assertStringContainsString( '"mug"', $label );
		$this->assertStringContainsString( '3', $label );
	}

	public function test_describe_search_performed_zero_results(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::SEARCH_PERFORMED,
			array(
				'query'        => 'unobtainium',
				'result_count' => 0,
			)
		);

		$this->assertStringContainsString( 'no results', $label );
	}

	public function test_describe_products_shown_truncates_names(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::PRODUCTS_SHOWN,
			array(
				'ids'   => array( 1, 2, 3, 4 ),
				'names' => array( 'Alpha', 'Bravo', 'Charlie', 'Delta' ),
			)
		);

		$this->assertStringContainsString( '4', $label );
		$this->assertStringContainsString( 'Alpha, Bravo, Charlie', $label );
		$this->assertStringNotContainsString( 'Delta', $label );
		$this->assertStringContainsString( '…', $label );
	}

	public function test_describe_product_added_to_cart(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::PRODUCT_ADDED_TO_CART,
			array(
				'id'    => 12,
				'name'  => 'Kitchen Sieve',
				'price' => '8.00',
			)
		);

		$this->assertStringContainsString( 'Kitchen Sieve', $label );
		$this->assertStringContainsString( 'cart', $label );
	}

	public function test_describe_checkout_and_handoff(): void {
		$this->assertEquals( 'Checkout started', WPAIC_Events::describe( WPAIC_Events::CHECKOUT_STARTED, array() ) );
		$this->assertEquals( 'Handoff request created', WPAIC_Events::describe( WPAIC_Events::HANDOFF_CREATED, array( 'request_id' => 3 ) ) );
	}

	public function test_describe_unknown_event_type_falls_back_to_type(): void {
		$this->assertEquals( 'mystery_event', WPAIC_Events::describe( 'mystery_event', array() ) );
	}

	public function test_describe_cart_confirmation_add_completed(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::CART_CONFIRMATION,
			array(
				'action'  => 'add',
				'outcome' => 'completed',
				'name'    => 'Classic Tee',
			)
		);

		$this->assertEquals( 'Cart updated — added Classic Tee', $label );
	}

	public function test_describe_cart_confirmation_add_failed(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::CART_CONFIRMATION,
			array(
				'action'  => 'add',
				'outcome' => 'failed',
				'name'    => 'Classic Tee',
			)
		);

		$this->assertEquals( 'Add to cart failed — Classic Tee', $label );
	}

	public function test_describe_cart_confirmation_clear_completed(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::CART_CONFIRMATION,
			array(
				'action'  => 'clear',
				'outcome' => 'completed',
			)
		);

		$this->assertEquals( 'Cart emptied by shopper', $label );
	}

	public function test_describe_cart_confirmation_remove_completed_with_names(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::CART_CONFIRMATION,
			array(
				'action'  => 'remove',
				'outcome' => 'completed',
				'name'    => 'Water, Soda',
			)
		);

		$this->assertEquals( 'Cart updated — removed Water, Soda', $label );
	}

	public function test_describe_cart_confirmation_cancelled(): void {
		$label = WPAIC_Events::describe(
			WPAIC_Events::CART_CONFIRMATION,
			array(
				'action'  => 'clear',
				'outcome' => 'cancelled',
			)
		);

		$this->assertEquals( 'Cart change cancelled — shopper kept the cart', $label );
	}
}
