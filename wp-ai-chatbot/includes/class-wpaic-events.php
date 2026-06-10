<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compact per-conversation tool-event log: searches, products shown,
 * add-to-cart, checkout, handoffs. Recorded from the tool execution path and
 * rendered as gray chips in admin transcripts; also the data source for the
 * Insights aggregates.
 */
class WPAIC_Events {
	public const SEARCH_PERFORMED      = 'search_performed';
	public const PRODUCTS_SHOWN        = 'products_shown';
	public const PRODUCT_ADDED_TO_CART = 'product_added_to_cart';
	public const CHECKOUT_STARTED      = 'checkout_started';
	public const HANDOFF_CREATED       = 'handoff_created';
	/**
	 * Real outcome of a chat-initiated cart change (the tool call only proposes
	 * it; the mutation happens later via the cart AJAX endpoints). event_data:
	 * action (add|remove|clear), outcome (completed|failed|cancelled), name?.
	 */
	public const CART_CONFIRMATION     = 'cart_confirmation';

	/**
	 * Record an event for a conversation.
	 *
	 * @param int                  $conversation_id Conversation the event belongs to.
	 * @param string               $event_type      One of the class constants.
	 * @param array<string, mixed> $event_data      Compact event payload (JSON-encoded for storage).
	 * @return int|false Event ID or false on failure.
	 */
	public static function record( int $conversation_id, string $event_type, array $event_data = array() ): int|false {
		global $wpdb;

		if ( $conversation_id <= 0 || '' === $event_type ) {
			return false;
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpaic_events',
			array(
				'conversation_id' => $conversation_id,
				'event_type'      => $event_type,
				'event_data'      => (string) wp_json_encode( $event_data ),
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get all events for a conversation, oldest first. event_data is decoded to an array.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return array<int, object{id: int, conversation_id: int, event_type: string, event_data: array<string, mixed>, created_at: string}>
	 */
	public static function get_for_conversation( int $conversation_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_events';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, conversation_id, event_type, event_data, created_at FROM $table WHERE conversation_id = %d ORDER BY id ASC",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $results ) ) {
			return array();
		}

		foreach ( $results as $event ) {
			$decoded           = json_decode( (string) $event->event_data, true );
			$event->event_data = is_array( $decoded ) ? $decoded : array();
		}

		return $results;
	}

	/**
	 * Count events of a type recorded in the half-open range [$since, $until).
	 *
	 * @param string $event_type One of the class constants.
	 * @param string $since      Inclusive lower bound (MySQL datetime, local time).
	 * @param string $until      Exclusive upper bound (MySQL datetime, local time).
	 */
	public static function count_between( string $event_type, string $since, string $until ): int {
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_events';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE event_type = %s AND created_at >= %s AND created_at < %s",
				$event_type,
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Top zero-result search queries over the last $days days, most frequent
	 * first. Queries are grouped case-insensitively; the first-seen casing is
	 * kept for display.
	 *
	 * @param int $limit Maximum number of queries to return.
	 * @param int $days  Look-back window in days.
	 * @return array<int, array{query: string, count: int}>
	 */
	public static function get_zero_result_searches( int $limit = 10, int $days = 30 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_events';
		$since = gmdate( 'Y-m-d H:i:s', (int) strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_data FROM $table WHERE event_type = %s AND created_at >= %s",
				self::SEARCH_PERFORMED,
				$since
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$counts = array();
		$labels = array();
		foreach ( $rows as $row ) {
			$event_data = json_decode( (string) $row->event_data, true );
			if ( ! is_array( $event_data ) ) {
				continue;
			}
			$query        = isset( $event_data['query'] ) && is_string( $event_data['query'] ) ? trim( $event_data['query'] ) : '';
			$result_count = isset( $event_data['result_count'] ) && is_numeric( $event_data['result_count'] ) ? (int) $event_data['result_count'] : -1;
			if ( '' === $query || 0 !== $result_count ) {
				continue;
			}
			$key = mb_strtolower( $query );
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
	 * Human-readable one-line label for an event, used as a gray chip in transcripts.
	 *
	 * @param string               $event_type Event type.
	 * @param array<string, mixed> $event_data Decoded event payload.
	 */
	public static function describe( string $event_type, array $event_data ): string {
		switch ( $event_type ) {
			case self::SEARCH_PERFORMED:
				$query        = isset( $event_data['query'] ) && is_string( $event_data['query'] ) ? $event_data['query'] : '';
				$result_count = isset( $event_data['result_count'] ) && is_numeric( $event_data['result_count'] ) ? (int) $event_data['result_count'] : 0;
				if ( 0 === $result_count ) {
					return sprintf(
						/* translators: %s: search query */
						__( 'Searched "%s" — no results', 'wp-ai-chatbot' ),
						$query
					);
				}
				return sprintf(
					/* translators: 1: search query, 2: number of results */
					__( 'Searched "%1$s" — %2$d result(s)', 'wp-ai-chatbot' ),
					$query,
					$result_count
				);

			case self::PRODUCTS_SHOWN:
				$names = array();
				if ( isset( $event_data['names'] ) && is_array( $event_data['names'] ) ) {
					$names = array_values( array_filter( $event_data['names'], 'is_string' ) );
				}
				$count   = isset( $event_data['ids'] ) && is_array( $event_data['ids'] ) ? count( $event_data['ids'] ) : count( $names );
				$preview = implode( ', ', array_slice( $names, 0, 3 ) );
				if ( count( $names ) > 3 ) {
					$preview .= '…';
				}
				if ( '' === $preview ) {
					return sprintf(
						/* translators: %d: number of products */
						__( 'Showed %d product(s)', 'wp-ai-chatbot' ),
						$count
					);
				}
				return sprintf(
					/* translators: 1: number of products, 2: product names preview */
					__( 'Showed %1$d product(s): %2$s', 'wp-ai-chatbot' ),
					$count,
					$preview
				);

			case self::PRODUCT_ADDED_TO_CART:
				$name = isset( $event_data['name'] ) && is_string( $event_data['name'] ) ? $event_data['name'] : '';
				return sprintf(
					/* translators: %s: product name */
					__( 'Added %s to cart', 'wp-ai-chatbot' ),
					'' !== $name ? $name : __( 'a product', 'wp-ai-chatbot' )
				);

			case self::CART_CONFIRMATION:
				$action  = isset( $event_data['action'] ) && is_string( $event_data['action'] ) ? $event_data['action'] : '';
				$outcome = isset( $event_data['outcome'] ) && is_string( $event_data['outcome'] ) ? $event_data['outcome'] : '';
				$name    = isset( $event_data['name'] ) && is_string( $event_data['name'] ) ? $event_data['name'] : '';

				if ( 'cancelled' === $outcome ) {
					return __( 'Cart change cancelled — shopper kept the cart', 'wp-ai-chatbot' );
				}

				if ( 'add' === $action ) {
					$label = '' !== $name ? $name : __( 'a product', 'wp-ai-chatbot' );
					if ( 'failed' === $outcome ) {
						return sprintf(
							/* translators: %s: product name */
							__( 'Add to cart failed — %s', 'wp-ai-chatbot' ),
							$label
						);
					}
					return sprintf(
						/* translators: %s: product name */
						__( 'Cart updated — added %s', 'wp-ai-chatbot' ),
						$label
					);
				}

				if ( 'clear' === $action ) {
					return __( 'Cart emptied by shopper', 'wp-ai-chatbot' );
				}

				if ( '' !== $name ) {
					return sprintf(
						/* translators: %s: removed item names */
						__( 'Cart updated — removed %s', 'wp-ai-chatbot' ),
						$name
					);
				}
				return __( 'Cart items removed', 'wp-ai-chatbot' );

			case self::CHECKOUT_STARTED:
				return __( 'Checkout started', 'wp-ai-chatbot' );

			case self::HANDOFF_CREATED:
				return __( 'Handoff request created', 'wp-ai-chatbot' );

			default:
				return $event_type;
		}
	}
}
