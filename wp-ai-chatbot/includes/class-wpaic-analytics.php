<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Aggregates the Analytics dashboard blob from existing conversation/message/
 * event data plus order_completed attribution events. Everything is read-only
 * aggregation over indexed tables; the result is transient-cached per range.
 *
 * Ranges are half-open [since, until) in site-local time, consistent with the
 * existing WPAIC_Events / WPAIC_Logs range helpers.
 *
 * Accepted v1 limits:
 * - Store revenue (get_store_revenue) hydrates paid orders via wc_get_orders;
 *   on very large stores the uncached "all" range is O(orders). Bounded by the
 *   ~5 min transient. A SQL-layer aggregate (HPOS-aware) is the future fix.
 * - Period deltas compare a partial current day against full prior days, so the
 *   hero delta chips read slightly pessimistic early in the day and settle as it
 *   fills — a standard dashboard tradeoff; absolute totals are unaffected.
 */
class WPAIC_Analytics {
	/** @var array<int, string> */
	private const PRESETS = array( '7', '30', '90', 'all' );

	private const DEFAULT_RANGE = '30';
	private const CACHE_TTL      = 300;
	private const MAX_BUCKETS    = 90;

	private WPAIC_Logs $logs;

	public function __construct( ?WPAIC_Logs $logs = null ) {
		$this->logs = $logs ?? new WPAIC_Logs();
	}

	public static function normalize_range( string $range ): string {
		return in_array( $range, self::PRESETS, true ) ? $range : self::DEFAULT_RANGE;
	}

	/**
	 * The full dashboard payload for one range, transient-cached (~5 min).
	 *
	 * @param string $range     One of 7|30|90|all.
	 * @param bool   $use_cache Set false to bypass the transient (tests).
	 * @return array<string, mixed>
	 */
	public function get_dashboard_data( string $range = self::DEFAULT_RANGE, bool $use_cache = true ): array {
		$range = self::normalize_range( $range );

		if ( $use_cache ) {
			$cached = get_transient( 'wpaic_analytics_' . $range );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$data = $this->compute( $range );

		if ( $use_cache ) {
			set_transient( 'wpaic_analytics_' . $range, $data, self::CACHE_TTL );
		}

		return $data;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function compute( string $range ): array {
		$wc_active = function_exists( 'wpaic_is_woocommerce_active' ) ? wpaic_is_woocommerce_active() : false;
		$window    = $this->resolve_range( $range );

		$since = $window['since'];
		$until = $window['until'];

		// --- Conversations / messages (chat-side, always available) ---
		$conversations = $this->logs->count_conversations_between( $since, $until );
		$messages      = $this->count_messages_for_conversations_between( $since, $until );
		$avg_messages  = $conversations > 0 ? round( $messages / $conversations, 1 ) : 0.0;

		// --- Commerce (order_completed events) ---
		$orders_rows = $this->fetch_events_in_range( WPAIC_Events::ORDER_COMPLETED, $since, $until );
		$revenue     = 0.0;
		$orders      = count( $orders_rows );
		foreach ( $orders_rows as $row ) {
			$revenue += isset( $row->event_data['total'] ) && is_numeric( $row->event_data['total'] ) ? (float) $row->event_data['total'] : 0.0;
		}

		$store        = $wc_active ? $this->get_store_revenue( $window['since_ts'], $window['until_ts'] ) : array(
			'revenue' => 0.0,
			'orders'  => 0,
		);
		$store_revenue = $store['revenue'];
		$pct_of_store  = $store_revenue > 0 ? min( 100.0, round( ( $revenue / $store_revenue ) * 100, 1 ) ) : 0.0;
		$bot_aov       = $orders > 0 ? round( $revenue / $orders, 2 ) : 0.0;
		$store_aov     = $store['orders'] > 0 ? round( $store['revenue'] / $store['orders'], 2 ) : 0.0;
		$conv_rate     = $conversations > 0 ? round( ( $orders / $conversations ) * 100, 1 ) : 0.0;

		// --- Engagement ---
		$items_added       = $this->count_items_added( $since, $until );
		$handoffs          = WPAIC_Events::count_between( WPAIC_Events::HANDOFF_CREATED, $since, $until );
		$handoff_convs     = $this->count_distinct_conversations_with_event( WPAIC_Events::HANDOFF_CREATED, $since, $until );
		$self_service_rate = $conversations > 0 ? round( ( ( $conversations - $handoff_convs ) / $conversations ) * 100, 1 ) : 0.0;

		// --- Searches (single fetch, derive top + missed) ---
		$searches      = $this->fetch_events_in_range( WPAIC_Events::SEARCH_PERFORMED, $since, $until );
		$top_searches  = $this->top_searches( $searches, false );
		$missed_search = $this->top_searches( $searches, true );

		// --- Top products ---
		$top_products = $this->top_products( $since, $until );

		// --- Funnel (distinct conversations per stage) ---
		$funnel = array(
			array(
				'key'   => 'conversations',
				'label' => __( 'Conversations', 'wp-ai-chatbot' ),
				'value' => $conversations,
			),
			array(
				'key'   => 'products_shown',
				'label' => __( 'Products shown', 'wp-ai-chatbot' ),
				'value' => $this->count_distinct_conversations_with_event( WPAIC_Events::PRODUCTS_SHOWN, $since, $until ),
			),
			array(
				'key'   => 'add_to_cart',
				'label' => __( 'Add to cart', 'wp-ai-chatbot' ),
				'value' => $this->count_distinct_conversations_with_event( WPAIC_Events::PRODUCT_ADDED_TO_CART, $since, $until ),
			),
			array(
				'key'   => 'checkout_started',
				'label' => __( 'Checkout started', 'wp-ai-chatbot' ),
				'value' => $this->count_distinct_conversations_with_event( WPAIC_Events::CHECKOUT_STARTED, $since, $until ),
			),
			array(
				'key'   => 'order_completed',
				'label' => __( 'Order completed', 'wp-ai-chatbot' ),
				'value' => $this->count_distinct_conversations_with_event( WPAIC_Events::ORDER_COMPLETED, $since, $until ),
			),
		);

		// --- Time series + busiest-times heatmap ---
		$series = $this->build_series( $window, $orders_rows );
		$heat   = $this->build_heatmap( $since, $until );

		// --- Period deltas (hero KPIs only, when comparable) ---
		$deltas = $this->compute_deltas( $window, $wc_active, $revenue, $orders, $conversations, $conv_rate, $bot_aov );

		return array(
			'woocommerceActive' => $wc_active,
			'currency'          => $this->currency(),
			'range'             => array(
				'preset'     => $range,
				'label'      => $window['label'],
				'caption'    => $window['caption'],
				'comparable' => $window['comparable'],
				'prevLabel'  => $window['prev_label'],
				'options'    => $this->range_options( $range ),
			),
			'hasData'           => ( $conversations > 0 || $orders > 0 ),
			'totals'            => array(
				'revenue'       => round( $revenue, 2 ),
				'orders'        => $orders,
				'conversations' => $conversations,
			),
			'storeRevenue'      => round( $store_revenue, 2 ),
			'pctOfStore'        => $pct_of_store,
			'botAov'            => $bot_aov,
			'storeAov'          => $store_aov,
			'itemsAdded'        => $items_added,
			'avgMessages'       => $avg_messages,
			'convRate'          => $conv_rate,
			'handoffs'          => $handoffs,
			'selfService'       => $self_service_rate,
			'funnel'            => $funnel,
			'series'            => $series,
			'topProducts'       => $top_products,
			'topSearches'       => $top_searches,
			'missedSearches'    => $missed_search,
			'heat'              => $heat,
			'deltas'            => $deltas,
		);
	}

	/**
	 * Resolve a preset into half-open [since, until) bounds (both local-time
	 * MySQL strings and unix timestamps), the equal-length preceding window for
	 * deltas, and human labels.
	 *
	 * @return array{since: string, until: string, since_ts: int, until_ts: int, prev_since: string, prev_until: string, comparable: bool, days: int|null, label: string, caption: string, prev_label: string}
	 */
	private function resolve_range( string $range ): array {
		$now_ts       = (int) strtotime( current_time( 'mysql' ) );
		$today_start  = $now_ts - ( $now_ts % DAY_IN_SECONDS );
		$until_ts     = $now_ts + 1;

		if ( 'all' === $range ) {
			$first_ts   = $this->earliest_activity_ts();
			$since_ts   = null !== $first_ts ? $first_ts - ( $first_ts % DAY_IN_SECONDS ) : $today_start;
			$comparable = false;
			$days       = null;
			$prev_since = $prev_until = gmdate( 'Y-m-d H:i:s', $since_ts );
			$label      = __( 'All time', 'wp-ai-chatbot' );
			$prev_label = '';
		} else {
			$days       = (int) $range;
			$since_ts   = $today_start - ( $days - 1 ) * DAY_IN_SECONDS;
			$comparable = true;
			$prev_since = gmdate( 'Y-m-d H:i:s', $since_ts - $days * DAY_IN_SECONDS );
			$prev_until = gmdate( 'Y-m-d H:i:s', $since_ts );
			/* translators: %d: number of days in the range */
			$label = sprintf( _n( 'Last %d day', 'Last %d days', $days, 'wp-ai-chatbot' ), $days );
			/* translators: %d: number of days in the preceding comparison window */
			$prev_label = sprintf( __( 'vs previous %d days', 'wp-ai-chatbot' ), $days );
		}

		$caption = 'all' === $range
			? __( 'All time', 'wp-ai-chatbot' )
			: date_i18n( 'M j', $since_ts ) . ' – ' . date_i18n( 'M j, Y', $now_ts );

		return array(
			'since'      => gmdate( 'Y-m-d H:i:s', $since_ts ),
			'until'      => gmdate( 'Y-m-d H:i:s', $until_ts ),
			'since_ts'   => $since_ts,
			'until_ts'   => $until_ts,
			'prev_since' => $prev_since,
			'prev_until' => $prev_until,
			'comparable' => $comparable,
			'days'       => $days,
			'label'      => $label,
			'caption'    => $caption,
			'prev_label' => $prev_label,
		);
	}

	/**
	 * Segmented-control options. Each preset is a reload link (no client fetch),
	 * mirroring the Chat Logs date filters.
	 *
	 * @return array<int, array{value: string, label: string, url: string}>
	 */
	private function range_options( string $active ): array {
		$labels = array(
			'7'   => __( '7d', 'wp-ai-chatbot' ),
			'30'  => __( '30d', 'wp-ai-chatbot' ),
			'90'  => __( '90d', 'wp-ai-chatbot' ),
			'all' => __( 'All', 'wp-ai-chatbot' ),
		);
		$base    = admin_url( 'admin.php?page=wp-ai-chatbot-analytics' );
		$options = array();
		foreach ( self::PRESETS as $preset ) {
			$options[] = array(
				'value' => $preset,
				'label' => $labels[ $preset ],
				'url'   => add_query_arg( 'wpaic_range', $preset, $base ),
			);
		}
		return $options;
	}

	/**
	 * @return array{symbol: string, code: string}
	 */
	private function currency(): array {
		$symbol = '$';
		$code   = 'USD';
		if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
			$symbol = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
		}
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			$code = (string) get_woocommerce_currency();
		}
		return array(
			'symbol' => $symbol,
			'code'   => $code,
		);
	}

	/**
	 * Messages belonging to conversations created in the range (drives avg
	 * messages per conversation).
	 */
	private function count_messages_for_conversations_between( string $since, string $until ): int {
		global $wpdb;
		$messages_table      = $wpdb->prefix . 'wpaic_messages';
		$conversations_table = $wpdb->prefix . 'wpaic_conversations';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $messages_table m
				INNER JOIN $conversations_table c ON c.id = m.conversation_id
				WHERE c.created_at >= %s AND c.created_at < %s",
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Distinct conversations with at least one event of a type in the range
	 * (funnel stages, handoff exclusion).
	 */
	private function count_distinct_conversations_with_event( string $event_type, string $since, string $until ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_events';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT conversation_id) FROM $table WHERE event_type = %s AND created_at >= %s AND created_at < %s",
				$event_type,
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Fetch events of a type in the range with event_data decoded to an array,
	 * oldest first.
	 *
	 * @return array<int, object{event_data: array<string, mixed>, created_at: string}>
	 */
	private function fetch_events_in_range( string $event_type, string $since, string $until ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_events';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_data, created_at FROM $table WHERE event_type = %s AND created_at >= %s AND created_at < %s ORDER BY created_at ASC",
				$event_type,
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as $row ) {
			$decoded         = json_decode( (string) $row->event_data, true );
			$row->event_data = is_array( $decoded ) ? $decoded : array();
		}

		return $rows;
	}

	/**
	 * Items added to cart = cart_confirmation events with action=add,
	 * outcome=completed (the real, confirmed outcome — not the proposed add).
	 */
	private function count_items_added( string $since, string $until ): int {
		$rows  = $this->fetch_events_in_range( WPAIC_Events::CART_CONFIRMATION, $since, $until );
		$count = 0;
		foreach ( $rows as $row ) {
			$action  = isset( $row->event_data['action'] ) ? $row->event_data['action'] : '';
			$outcome = isset( $row->event_data['outcome'] ) ? $row->event_data['outcome'] : '';
			if ( 'add' === $action && 'completed' === $outcome ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Group search_performed events by query (case-insensitive), most frequent
	 * first. When $missed_only, keep only queries that returned no results.
	 *
	 * @param array<int, object{event_data: array<string, mixed>}> $rows
	 * @return array<int, array{query: string, count: int}>
	 */
	private function top_searches( array $rows, bool $missed_only, int $limit = 7 ): array {
		$counts = array();
		$labels = array();
		foreach ( $rows as $row ) {
			$query = isset( $row->event_data['query'] ) && is_string( $row->event_data['query'] ) ? trim( $row->event_data['query'] ) : '';
			if ( '' === $query ) {
				continue;
			}
			if ( $missed_only ) {
				$result_count = isset( $row->event_data['result_count'] ) && is_numeric( $row->event_data['result_count'] ) ? (int) $row->event_data['result_count'] : -1;
				if ( 0 !== $result_count ) {
					continue;
				}
			}
			$key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $query ) : strtolower( $query );
			if ( ! isset( $counts[ $key ] ) ) {
				$counts[ $key ] = 0;
				$labels[ $key ] = $query;
			}
			++$counts[ $key ];
		}

		arsort( $counts );

		$top = array();
		foreach ( array_slice( $counts, 0, $limit, true ) as $key => $count ) {
			$top[] = array(
				'query' => $labels[ $key ],
				'count' => $count,
			);
		}
		return $top;
	}

	/**
	 * Group product_added_to_cart events by product name, most added first.
	 *
	 * @return array<int, array{name: string, count: int, revenue: float}>
	 */
	private function top_products( string $since, string $until, int $limit = 7 ): array {
		$rows    = $this->fetch_events_in_range( WPAIC_Events::PRODUCT_ADDED_TO_CART, $since, $until );
		$counts  = array();
		$revenue = array();
		foreach ( $rows as $row ) {
			$name = isset( $row->event_data['name'] ) && is_string( $row->event_data['name'] ) ? trim( $row->event_data['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}
			$price = isset( $row->event_data['price'] ) && is_numeric( $row->event_data['price'] ) ? (float) $row->event_data['price'] : 0.0;
			if ( ! isset( $counts[ $name ] ) ) {
				$counts[ $name ]  = 0;
				$revenue[ $name ] = 0.0;
			}
			++$counts[ $name ];
			$revenue[ $name ] += $price;
		}

		arsort( $counts );

		$top = array();
		foreach ( array_slice( $counts, 0, $limit, true ) as $name => $count ) {
			$top[] = array(
				// Cast: a product literally named e.g. "2024" becomes an int array
				// key in PHP, which would break the string `name` contract.
				'name'    => (string) $name,
				'count'   => $count,
				'revenue' => round( $revenue[ $name ], 2 ),
			);
		}
		return $top;
	}

	/**
	 * Revenue / orders / conversations bucketed over time. Daily for bounded
	 * presets; for longer spans the bucket widens so the series never exceeds
	 * MAX_BUCKETS points.
	 *
	 * @param array{since_ts: int, until_ts: int} $window
	 * @param array<int, object{event_data: array<string, mixed>, created_at: string}> $orders_rows
	 * @return array<int, array{label: string, date: string, revenue: float, orders: int, conversations: int}>
	 */
	private function build_series( array $window, array $orders_rows ): array {
		$since_ts = $window['since_ts'];
		$until_ts = $window['until_ts'];
		$span     = max( 1, $until_ts - $since_ts );

		$bucket_seconds = DAY_IN_SECONDS;
		$bucket_count   = (int) ceil( $span / $bucket_seconds );
		if ( $bucket_count > self::MAX_BUCKETS ) {
			$bucket_days    = (int) ceil( $bucket_count / self::MAX_BUCKETS );
			$bucket_seconds = $bucket_days * DAY_IN_SECONDS;
			$bucket_count   = (int) ceil( $span / $bucket_seconds );
		}
		$bucket_count = max( 1, $bucket_count );

		$buckets = array();
		for ( $i = 0; $i < $bucket_count; $i++ ) {
			$start     = $since_ts + $i * $bucket_seconds;
			$buckets[] = array(
				'start'         => $start,
				'end'           => $start + $bucket_seconds,
				'label'         => date_i18n( $bucket_seconds > DAY_IN_SECONDS ? 'M j' : 'D', $start ),
				'date'          => date_i18n( 'M j', $start ),
				'revenue'       => 0.0,
				'orders'        => 0,
				'conversations' => 0,
			);
		}

		$index_for = static function ( int $ts ) use ( $since_ts, $bucket_seconds, $bucket_count ): int {
			$idx = (int) floor( ( $ts - $since_ts ) / $bucket_seconds );
			return max( 0, min( $bucket_count - 1, $idx ) );
		};

		foreach ( $orders_rows as $row ) {
			$ts  = (int) strtotime( (string) $row->created_at );
			$idx = $index_for( $ts );
			$buckets[ $idx ]['revenue'] += isset( $row->event_data['total'] ) && is_numeric( $row->event_data['total'] ) ? (float) $row->event_data['total'] : 0.0;
			++$buckets[ $idx ]['orders'];
		}

		foreach ( $this->conversations_per_day( $window['since_ts'], $window['until_ts'] ) as $day => $count ) {
			$ts  = (int) strtotime( $day . ' 00:00:00' );
			$idx = $index_for( $ts );
			$buckets[ $idx ]['conversations'] += $count;
		}

		return array_map(
			static function ( array $b ): array {
				return array(
					'label'         => $b['label'],
					'date'          => $b['date'],
					'revenue'       => round( $b['revenue'], 2 ),
					'orders'        => $b['orders'],
					'conversations' => $b['conversations'],
				);
			},
			$buckets
		);
	}

	/**
	 * Conversation counts grouped by calendar day in the range.
	 *
	 * @return array<string, int> Map of Y-m-d => count.
	 */
	private function conversations_per_day( int $since_ts, int $until_ts ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_conversations';
		$since = gmdate( 'Y-m-d H:i:s', $since_ts );
		$until = gmdate( 'Y-m-d H:i:s', $until_ts );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) AS d, COUNT(*) AS c FROM $table WHERE created_at >= %s AND created_at < %s GROUP BY DATE(created_at)",
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (string) $row->d ] = (int) $row->c;
			}
		}
		return $out;
	}

	/**
	 * Conversation counts by day-of-week (Monday-first) and hour for the
	 * busiest-times heatmap.
	 *
	 * @return array{dow: array<int, string>, data: array<int, array<int, int>>, max: int}
	 */
	private function build_heatmap( string $since, string $until ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_conversations';

		$grid = array();
		for ( $d = 0; $d < 7; $d++ ) {
			$grid[ $d ] = array_fill( 0, 24, 0 );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ((DAYOFWEEK(created_at) + 5) % 7) AS dow, HOUR(created_at) AS h, COUNT(*) AS c
				FROM $table WHERE created_at >= %s AND created_at < %s
				GROUP BY dow, h",
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$max = 0;
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$d = (int) $row->dow;
				$h = (int) $row->h;
				$c = (int) $row->c;
				if ( $d < 0 || $d > 6 || $h < 0 || $h > 23 ) {
					continue;
				}
				$grid[ $d ][ $h ] = $c;
				$max              = max( $max, $c );
			}
		}

		return array(
			'dow'  => $this->dow_labels(),
			'data' => $grid,
			'max'  => $max,
		);
	}

	/**
	 * Monday-first weekday abbreviations in the site locale (matches the
	 * (DAYOFWEEK+5)%7 grid index used by build_heatmap), built with date_i18n
	 * like the time-series labels rather than hardcoded English.
	 *
	 * @return array<int, string>
	 */
	private function dow_labels(): array {
		// 1970-01-05 was a Monday; offset gives each weekday in order.
		$monday = 4 * DAY_IN_SECONDS;
		$labels = array();
		for ( $i = 0; $i < 7; $i++ ) {
			$labels[] = date_i18n( 'D', $monday + $i * DAY_IN_SECONDS );
		}
		return $labels;
	}

	/**
	 * Gross store revenue + paid order count in the window, via WooCommerce.
	 *
	 * @return array{revenue: float, orders: int}
	 */
	private function get_store_revenue( int $since_ts, int $until_ts ): array {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array(
				'revenue' => 0.0,
				'orders'  => 0,
			);
		}

		$statuses = function_exists( 'wc_get_is_paid_statuses' ) ? wc_get_is_paid_statuses() : array( 'processing', 'completed' );

		$orders = wc_get_orders(
			array(
				'status'       => $statuses,
				'date_created' => $since_ts . '...' . ( $until_ts - 1 ),
				'limit'        => -1,
				'return'       => 'objects',
			)
		);

		$revenue = 0.0;
		$count   = 0;
		if ( is_array( $orders ) ) {
			foreach ( $orders as $order ) {
				if ( ! is_object( $order ) || ! method_exists( $order, 'get_total' ) ) {
					continue;
				}
				$revenue += (float) $order->get_total();
				++$count;
			}
		}

		return array(
			'revenue' => $revenue,
			'orders'  => $count,
		);
	}

	/**
	 * Earliest activity timestamp across conversations and events, for the
	 * "all time" range start. Null when there is no data.
	 */
	private function earliest_activity_ts(): ?int {
		global $wpdb;
		$conversations = $wpdb->prefix . 'wpaic_conversations';
		$events        = $wpdb->prefix . 'wpaic_events';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Table names cannot use placeholders; no user input.
		$first = $wpdb->get_var( "SELECT MIN(created_at) FROM (SELECT MIN(created_at) AS created_at FROM $conversations UNION ALL SELECT MIN(created_at) FROM $events) t" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		if ( null === $first || '' === $first ) {
			return null;
		}
		return (int) strtotime( (string) $first );
	}

	/**
	 * Relative % change of the hero KPIs vs the preceding equal-length window.
	 * Null when not comparable (all time) or the prior window is empty.
	 *
	 * @param array{prev_since: string, prev_until: string, comparable: bool} $window
	 * @return array<string, float|null>
	 */
	private function compute_deltas( array $window, bool $wc_active, float $revenue, int $orders, int $conversations, float $conv_rate, float $bot_aov ): array {
		$null = array(
			'revenue'       => null,
			'orders'        => null,
			'conversations' => null,
			'convRate'      => null,
			'aov'           => null,
		);

		if ( ! $window['comparable'] ) {
			return $null;
		}

		$prev_since = $window['prev_since'];
		$prev_until = $window['prev_until'];

		$prev_orders        = WPAIC_Events::count_between( WPAIC_Events::ORDER_COMPLETED, $prev_since, $prev_until );
		$prev_conversations = $this->logs->count_conversations_between( $prev_since, $prev_until );

		$prev_revenue = 0.0;
		foreach ( $this->fetch_events_in_range( WPAIC_Events::ORDER_COMPLETED, $prev_since, $prev_until ) as $row ) {
			$prev_revenue += isset( $row->event_data['total'] ) && is_numeric( $row->event_data['total'] ) ? (float) $row->event_data['total'] : 0.0;
		}

		$prev_conv_rate = $prev_conversations > 0 ? ( $prev_orders / $prev_conversations ) * 100 : 0.0;
		$prev_aov       = $prev_orders > 0 ? $prev_revenue / $prev_orders : 0.0;

		return array(
			'revenue'       => $this->pct_change( $revenue, $prev_revenue ),
			'orders'        => $this->pct_change( (float) $orders, (float) $prev_orders ),
			'conversations' => $this->pct_change( (float) $conversations, (float) $prev_conversations ),
			'convRate'      => $this->pct_change( $conv_rate, $prev_conv_rate ),
			'aov'           => $this->pct_change( $bot_aov, $prev_aov ),
		);
	}

	/**
	 * Relative percent change, or null when the prior value is zero (no
	 * meaningful baseline).
	 */
	private function pct_change( float $current, float $previous ): ?float {
		if ( $previous <= 0.0 ) {
			return null;
		}
		return round( ( ( $current - $previous ) / $previous ) * 100, 1 );
	}
}
