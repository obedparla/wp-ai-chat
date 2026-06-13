<?php
/**
 * Tests for WPAIC_Analytics aggregation. Caching is bypassed (use_cache=false)
 * so each assertion reflects freshly-seeded data.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-events.php';
require_once __DIR__ . '/../includes/class-wpaic-logs.php';
require_once __DIR__ . '/../includes/class-wpaic-analytics.php';

class WPAIC_AnalyticsTest extends TestCase {
	private WPAIC_Analytics $analytics;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		if ( ! $wpdb instanceof MockWpdb ) {
			$wpdb = new MockWpdb();
		}
		$wpdb->reset();
		WPAICTestHelper::reset();
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		$this->analytics = new WPAIC_Analytics();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	/** Recent local-time datetime that lands inside the 7/30/90-day windows. */
	private function recent( int $days_ago = 2 ): string {
		return gmdate( 'Y-m-d H:i:s', (int) strtotime( current_time( 'mysql' ) ) - $days_ago * DAY_IN_SECONDS );
	}

	private function seed_conversation( int $id, string $created_at ): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'session-' . $id,
				'created_at' => $created_at,
			)
		);
	}

	/**
	 * @param array<string, mixed> $event_data
	 */
	private function seed_event( int $conversation_id, string $event_type, array $event_data, string $created_at ): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => $conversation_id,
				'event_type'      => $event_type,
				'event_data'      => wp_json_encode( $event_data ),
				'created_at'      => $created_at,
			)
		);
	}

	/** A coherent dataset: 3 conversations + the full event mix, all in range. */
	private function seed_dataset(): void {
		global $wpdb;
		$at = $this->recent( 2 );

		foreach ( array( 1, 2, 3 ) as $id ) {
			$this->seed_conversation( $id, $at );
		}

		// 3 messages across 2 conversations -> avg 1.0 over 3 conversations.
		foreach ( array( array( 1, 'user', 'hi' ), array( 1, 'assistant', 'hello' ), array( 2, 'user', 'help' ) ) as $m ) {
			$wpdb->insert(
				'wp_wpaic_messages',
				array(
					'conversation_id' => $m[0],
					'role'            => $m[1],
					'content'         => $m[2],
					'created_at'      => $at,
				)
			);
		}

		$this->seed_event( 1, WPAIC_Events::ORDER_COMPLETED, array( 'order_id' => 11, 'total' => 100, 'currency' => 'USD' ), $at );
		$this->seed_event( 2, WPAIC_Events::ORDER_COMPLETED, array( 'order_id' => 12, 'total' => 200, 'currency' => 'USD' ), $at );

		$this->seed_event( 1, WPAIC_Events::PRODUCT_ADDED_TO_CART, array( 'name' => 'Shoe', 'price' => 50 ), $at );
		$this->seed_event( 1, WPAIC_Events::PRODUCT_ADDED_TO_CART, array( 'name' => 'Shoe', 'price' => 50 ), $at );
		$this->seed_event( 2, WPAIC_Events::PRODUCT_ADDED_TO_CART, array( 'name' => 'Hat', 'price' => 20 ), $at );

		$this->seed_event( 1, WPAIC_Events::PRODUCTS_SHOWN, array( 'ids' => array( 1 ), 'names' => array( 'Shoe' ) ), $at );
		$this->seed_event( 2, WPAIC_Events::PRODUCTS_SHOWN, array( 'ids' => array( 2 ), 'names' => array( 'Hat' ) ), $at );
		$this->seed_event( 3, WPAIC_Events::PRODUCTS_SHOWN, array( 'ids' => array( 3 ), 'names' => array( 'Bag' ) ), $at );

		$this->seed_event( 1, WPAIC_Events::CHECKOUT_STARTED, array(), $at );
		$this->seed_event( 3, WPAIC_Events::HANDOFF_CREATED, array( 'request_id' => 5 ), $at );

		$this->seed_event( 1, WPAIC_Events::CART_CONFIRMATION, array( 'action' => 'add', 'outcome' => 'completed', 'name' => 'Shoe' ), $at );
		$this->seed_event( 2, WPAIC_Events::CART_CONFIRMATION, array( 'action' => 'add', 'outcome' => 'completed', 'name' => 'Hat' ), $at );
		$this->seed_event( 1, WPAIC_Events::CART_CONFIRMATION, array( 'action' => 'add', 'outcome' => 'failed', 'name' => 'Shoe' ), $at );
		$this->seed_event( 2, WPAIC_Events::CART_CONFIRMATION, array( 'action' => 'remove', 'outcome' => 'completed', 'name' => 'Hat' ), $at );

		$this->seed_event( 1, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'shoes', 'result_count' => 5 ), $at );
		$this->seed_event( 2, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'Shoes', 'result_count' => 5 ), $at );
		$this->seed_event( 3, WPAIC_Events::SEARCH_PERFORMED, array( 'query' => 'hat', 'result_count' => 0 ), $at );
	}

	public function test_zero_data_returns_zeros_without_error(): void {
		$data = $this->analytics->get_dashboard_data( '30', false );

		$this->assertFalse( $data['hasData'] );
		$this->assertSame( 0.0, $data['totals']['revenue'] );
		$this->assertSame( 0, $data['totals']['orders'] );
		$this->assertSame( 0, $data['totals']['conversations'] );
		$this->assertSame( 0.0, $data['convRate'] );
		$this->assertSame( 0, $data['itemsAdded'] );
		$this->assertSame( array(), $data['topProducts'] );
		$this->assertSame( array(), $data['missedSearches'] );
		$this->assertSame( 0, $data['heat']['max'] );
		$this->assertCount( 5, $data['funnel'] );
		$this->assertSame( 0, $data['funnel'][0]['value'] );
	}

	public function test_revenue_orders_and_conversion_rate(): void {
		$this->seed_dataset();
		$data = $this->analytics->get_dashboard_data( '30', false );

		$this->assertTrue( $data['hasData'] );
		$this->assertSame( 300.0, $data['totals']['revenue'] );
		$this->assertSame( 2, $data['totals']['orders'] );
		$this->assertSame( 3, $data['totals']['conversations'] );
		$this->assertSame( 150.0, $data['botAov'] );
		$this->assertEqualsWithDelta( 66.7, $data['convRate'], 0.05 );
	}

	public function test_funnel_stage_counts_are_distinct_conversations(): void {
		$this->seed_dataset();
		$funnel = $this->analytics->get_dashboard_data( '30', false )['funnel'];
		$by_key = array();
		foreach ( $funnel as $stage ) {
			$by_key[ $stage['key'] ] = $stage['value'];
		}

		$this->assertSame( 3, $by_key['conversations'] );
		$this->assertSame( 3, $by_key['products_shown'] );
		$this->assertSame( 2, $by_key['add_to_cart'] );
		$this->assertSame( 1, $by_key['checkout_started'] );
		$this->assertSame( 2, $by_key['order_completed'] );
	}

	public function test_items_added_counts_only_completed_adds(): void {
		$this->seed_dataset();
		$data = $this->analytics->get_dashboard_data( '30', false );

		// Two add/completed; the add/failed and remove/completed are excluded.
		$this->assertSame( 2, $data['itemsAdded'] );
	}

	public function test_top_and_missed_searches(): void {
		$this->seed_dataset();
		$data = $this->analytics->get_dashboard_data( '30', false );

		$this->assertSame( 'shoes', $data['topSearches'][0]['query'] );
		$this->assertSame( 2, $data['topSearches'][0]['count'] );
		$this->assertCount( 1, $data['missedSearches'] );
		$this->assertSame( 'hat', $data['missedSearches'][0]['query'] );
		$this->assertSame( 1, $data['missedSearches'][0]['count'] );
	}

	public function test_top_products_grouped_by_name(): void {
		$this->seed_dataset();
		$top = $this->analytics->get_dashboard_data( '30', false )['topProducts'];

		$this->assertSame( 'Shoe', $top[0]['name'] );
		$this->assertSame( 2, $top[0]['count'] );
		$this->assertSame( 100.0, $top[0]['revenue'] );
		$this->assertSame( 'Hat', $top[1]['name'] );
		$this->assertSame( 1, $top[1]['count'] );
	}

	public function test_self_service_rate_and_handoffs(): void {
		$this->seed_dataset();
		$data = $this->analytics->get_dashboard_data( '30', false );

		$this->assertSame( 1, $data['handoffs'] );
		// (3 conversations - 1 with a handoff) / 3 = 66.7%.
		$this->assertEqualsWithDelta( 66.7, $data['selfService'], 0.05 );
	}

	public function test_avg_messages(): void {
		$this->seed_dataset();
		$data = $this->analytics->get_dashboard_data( '30', false );

		$this->assertSame( 1.0, $data['avgMessages'] );
	}

	public function test_heatmap_buckets_by_day_of_week_and_hour(): void {
		$ts = (int) strtotime( current_time( 'mysql' ) ) - 2 * DAY_IN_SECONDS;
		$at = gmdate( 'Y-m-d H:i:s', $ts );
		foreach ( array( 1, 2, 3 ) as $id ) {
			$this->seed_conversation( $id, $at );
		}

		$data = $this->analytics->get_dashboard_data( '30', false );
		$dow  = ( (int) gmdate( 'N', $ts ) ) - 1;
		$hour = (int) gmdate( 'G', $ts );

		$this->assertCount( 7, $data['heat']['data'] );
		$this->assertCount( 24, $data['heat']['data'][0] );
		$this->assertSame( 3, $data['heat']['data'][ $dow ][ $hour ] );
		$this->assertSame( 3, $data['heat']['max'] );
	}

	public function test_range_filtering_excludes_old_data_but_all_time_includes_it(): void {
		$recent = $this->recent( 2 );
		$old    = gmdate( 'Y-m-d H:i:s', (int) strtotime( current_time( 'mysql' ) ) - 60 * DAY_IN_SECONDS );

		$this->seed_conversation( 1, $recent );
		$this->seed_conversation( 2, $old );
		$this->seed_event( 1, WPAIC_Events::ORDER_COMPLETED, array( 'order_id' => 1, 'total' => 100 ), $recent );
		$this->seed_event( 2, WPAIC_Events::ORDER_COMPLETED, array( 'order_id' => 2, 'total' => 999 ), $old );

		$thirty = $this->analytics->get_dashboard_data( '30', false );
		$all    = $this->analytics->get_dashboard_data( 'all', false );

		$this->assertSame( 1, $thirty['totals']['orders'] );
		$this->assertSame( 100.0, $thirty['totals']['revenue'] );
		$this->assertSame( 1, $thirty['totals']['conversations'] );

		$this->assertSame( 2, $all['totals']['orders'] );
		$this->assertSame( 1099.0, $all['totals']['revenue'] );
		$this->assertSame( 2, $all['totals']['conversations'] );
	}

	public function test_deltas_null_for_all_time_and_when_no_prior_window(): void {
		$this->seed_dataset();

		$all = $this->analytics->get_dashboard_data( 'all', false );
		$this->assertNull( $all['deltas']['revenue'] );
		$this->assertFalse( $all['range']['comparable'] );

		// Comparable range but empty prior window -> null deltas (no baseline).
		$thirty = $this->analytics->get_dashboard_data( '30', false );
		$this->assertTrue( $thirty['range']['comparable'] );
		$this->assertNull( $thirty['deltas']['revenue'] );
	}

	public function test_range_options_and_normalization(): void {
		$data = $this->analytics->get_dashboard_data( 'bogus', false );

		$this->assertSame( '30', $data['range']['preset'] );
		$this->assertCount( 4, $data['range']['options'] );
		$this->assertSame( '7', $data['range']['options'][0]['value'] );
	}

	public function test_woocommerce_inactive_flag(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', false );
		$this->seed_dataset();
		$data = $this->analytics->get_dashboard_data( '30', false );

		$this->assertFalse( $data['woocommerceActive'] );
		$this->assertSame( 0.0, $data['storeRevenue'] );
		// Chat-side metrics still computed.
		$this->assertSame( 3, $data['totals']['conversations'] );
		$this->assertSame( 1, $data['handoffs'] );
	}
}
