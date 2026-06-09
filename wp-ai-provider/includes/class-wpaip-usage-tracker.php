<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records per-install (usage bucket) daily message/token usage and enforces
 * configurable daily budgets.
 *
 * Storage is a single non-autoloaded option holding per-day, per-bucket
 * counters, pruned to RETENTION_DAYS on every write. Token counts come from
 * the exact usage object OpenAI reports on the `response.completed` event.
 */
class WPAIP_Usage_Tracker {
	private const OPTION_NAME    = 'wpaip_usage_daily';
	private const RETENTION_DAYS = 30;

	/**
	 * @return array{messages: int, input_tokens: int, cached_input_tokens: int, output_tokens: int, total_tokens: int}
	 */
	public function get_daily_usage( string $usage_bucket, ?string $date = null ): array {
		$date   = $date ?? gmdate( 'Y-m-d' );
		$option = $this->get_usage_option();
		$bucket = $option[ $date ][ $usage_bucket ] ?? array();
		$bucket = is_array( $bucket ) ? $bucket : array();

		return array(
			'messages'            => (int) ( $bucket['messages'] ?? 0 ),
			'input_tokens'        => (int) ( $bucket['input_tokens'] ?? 0 ),
			'cached_input_tokens' => (int) ( $bucket['cached_input_tokens'] ?? 0 ),
			'output_tokens'       => (int) ( $bucket['output_tokens'] ?? 0 ),
			'total_tokens'        => (int) ( $bucket['total_tokens'] ?? 0 ),
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
	 * @param array<string, int> $increments Counter => amount to add.
	 */
	private function increment( string $usage_bucket, array $increments ): void {
		$date   = gmdate( 'Y-m-d' );
		$option = $this->prune( $this->get_usage_option() );

		$bucket = $option[ $date ][ $usage_bucket ] ?? array();
		$bucket = is_array( $bucket ) ? $bucket : array();
		foreach ( $increments as $counter => $amount ) {
			$bucket[ $counter ] = (int) ( $bucket[ $counter ] ?? 0 ) + $amount;
		}
		$option[ $date ][ $usage_bucket ] = $bucket;

		update_option( self::OPTION_NAME, $option, false );
	}

	/**
	 * @return array<string, array<string, array<string, int>>>
	 */
	private function get_usage_option(): array {
		$option = get_option( self::OPTION_NAME, array() );

		return is_array( $option ) ? $option : array();
	}

	/**
	 * @param array<string, array<string, array<string, int>>> $option
	 * @return array<string, array<string, array<string, int>>>
	 */
	private function prune( array $option ): array {
		$cutoff = gmdate( 'Y-m-d', time() - self::RETENTION_DAYS * DAY_IN_SECONDS );

		foreach ( array_keys( $option ) as $date ) {
			if ( $date < $cutoff ) {
				unset( $option[ $date ] );
			}
		}

		return $option;
	}

	private function get_budget_setting( string $setting_key, int $default ): int {
		$settings = get_option( 'wpaip_settings', array() );
		if ( ! is_array( $settings ) || ! isset( $settings[ $setting_key ] ) ) {
			return $default;
		}

		return max( 0, (int) $settings[ $setting_key ] );
	}
}
