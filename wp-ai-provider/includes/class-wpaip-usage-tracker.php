<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records per-install (usage bucket) daily message/token usage and enforces
 * configurable daily budgets.
 *
 * Storage is a dedicated table keyed (usage_day, usage_bucket). Every
 * increment is a single atomic INSERT ... ON DUPLICATE KEY UPDATE, so
 * concurrent completions never lose counts. Rows older than RETENTION_DAYS
 * are pruned on write. Token counts come from the exact usage object OpenAI
 * reports on the `response.completed` event.
 */
class WPAIP_Usage_Tracker {
	private const RETENTION_DAYS = 30;

	public static function get_table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'wpaip_usage_daily';
	}

	/**
	 * Create (or update) the usage table. Runs on activation and DB-version
	 * upgrades.
	 */
	public static function create_table(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table_name} (
				usage_day date NOT NULL,
				usage_bucket varchar(191) NOT NULL,
				messages bigint(20) unsigned NOT NULL DEFAULT 0,
				input_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
				cached_input_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
				output_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
				total_tokens bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (usage_day, usage_bucket)
			) {$charset_collate};"
		);
	}

	/**
	 * @return array{messages: int, input_tokens: int, cached_input_tokens: int, output_tokens: int, total_tokens: int}
	 */
	public function get_daily_usage( string $usage_bucket, ?string $date = null ): array {
		global $wpdb;

		$date = $date ?? gmdate( 'Y-m-d' );
		$row  = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT messages, input_tokens, cached_input_tokens, output_tokens, total_tokens FROM ' . self::get_table_name() . ' WHERE usage_day = %s AND usage_bucket = %s',
				$date,
				$usage_bucket
			),
			ARRAY_A
		);
		$row  = is_array( $row ) ? $row : array();

		return array(
			'messages'            => (int) ( $row['messages'] ?? 0 ),
			'input_tokens'        => (int) ( $row['input_tokens'] ?? 0 ),
			'cached_input_tokens' => (int) ( $row['cached_input_tokens'] ?? 0 ),
			'output_tokens'       => (int) ( $row['output_tokens'] ?? 0 ),
			'total_tokens'        => (int) ( $row['total_tokens'] ?? 0 ),
		);
	}

	public function record_message( string $usage_bucket ): void {
		$this->increment( $usage_bucket, array( 'messages' => 1 ) );
	}

	/**
	 * Record the exact token usage OpenAI reports for one completed response.
	 *
	 * @param array<string, mixed> $usage Usage object from the Responses API
	 *                                    `response.completed` event.
	 */
	public function record_tokens( string $usage_bucket, array $usage ): void {
		$input_tokens_details = $usage['input_tokens_details'] ?? array();
		$cached_input_tokens  = is_array( $input_tokens_details ) ? (int) ( $input_tokens_details['cached_tokens'] ?? 0 ) : 0;

		$this->increment(
			$usage_bucket,
			array(
				'input_tokens'        => (int) ( $usage['input_tokens'] ?? 0 ),
				'cached_input_tokens' => $cached_input_tokens,
				'output_tokens'       => (int) ( $usage['output_tokens'] ?? 0 ),
				'total_tokens'        => (int) ( $usage['total_tokens'] ?? 0 ),
			)
		);
	}

	/**
	 * Whether the bucket has exhausted today's message or token budget.
	 * A budget of 0 means that limit is disabled.
	 */
	public function is_over_budget( string $usage_bucket ): bool {
		$usage = $this->get_daily_usage( $usage_bucket );

		$message_budget = $this->get_daily_message_budget( $usage_bucket );
		if ( $message_budget > 0 && $usage['messages'] >= $message_budget ) {
			return true;
		}

		$token_budget = $this->get_daily_token_budget( $usage_bucket );
		if ( $token_budget > 0 && $usage['total_tokens'] >= $token_budget ) {
			return true;
		}

		return false;
	}

	public function get_daily_message_budget( string $usage_bucket ): int {
		$budget = $this->get_budget_setting( 'daily_message_budget', WPAIP_Admin::DEFAULT_DAILY_MESSAGE_BUDGET );

		return max( 0, (int) apply_filters( 'wpaip_daily_message_budget', $budget, $usage_bucket ) );
	}

	public function get_daily_token_budget( string $usage_bucket ): int {
		$budget = $this->get_budget_setting( 'daily_token_budget', WPAIP_Admin::DEFAULT_DAILY_TOKEN_BUDGET );

		return max( 0, (int) apply_filters( 'wpaip_daily_token_budget', $budget, $usage_bucket ) );
	}

	/**
	 * Add counters to today's (usage_day, usage_bucket) row in one atomic
	 * upsert so concurrent requests never lose increments.
	 *
	 * @param array<string, int> $increments Counter => amount to add.
	 */
	private function increment( string $usage_bucket, array $increments ): void {
		global $wpdb;

		$messages            = (int) ( $increments['messages'] ?? 0 );
		$input_tokens        = (int) ( $increments['input_tokens'] ?? 0 );
		$cached_input_tokens = (int) ( $increments['cached_input_tokens'] ?? 0 );
		$output_tokens       = (int) ( $increments['output_tokens'] ?? 0 );
		$total_tokens        = (int) ( $increments['total_tokens'] ?? 0 );

		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::get_table_name() . ' (usage_day, usage_bucket, messages, input_tokens, cached_input_tokens, output_tokens, total_tokens)'
				. ' VALUES (%s, %s, %d, %d, %d, %d, %d)'
				. ' ON DUPLICATE KEY UPDATE'
				. ' messages = messages + %d,'
				. ' input_tokens = input_tokens + %d,'
				. ' cached_input_tokens = cached_input_tokens + %d,'
				. ' output_tokens = output_tokens + %d,'
				. ' total_tokens = total_tokens + %d',
				gmdate( 'Y-m-d' ),
				$usage_bucket,
				$messages,
				$input_tokens,
				$cached_input_tokens,
				$output_tokens,
				$total_tokens,
				$messages,
				$input_tokens,
				$cached_input_tokens,
				$output_tokens,
				$total_tokens
			)
		);

		$this->prune();
	}

	private function prune(): void {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::get_table_name() . ' WHERE usage_day < %s',
				$cutoff
			)
		);
	}

	private function get_budget_setting( string $setting_key, int $default ): int {
		$settings = get_option( 'wpaip_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings[ $setting_key ] ) ) {
			return $default;
		}

		return max( 0, (int) $settings[ $setting_key ] );
	}
}
