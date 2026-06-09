<?php

use PHPUnit\Framework\TestCase;

class WPAIP_UsageTrackerTest extends TestCase {

	private WPAIP_Usage_Tracker $tracker;

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
		$GLOBALS['wp_filters'] = array();

		$this->tracker = new WPAIP_Usage_Tracker();
	}

	protected function tearDown(): void {
		$GLOBALS['wp_options'] = array();
		$GLOBALS['wp_filters'] = array();
	}

	public function test_get_daily_usage_returns_zeros_when_nothing_recorded(): void {
		$usage = $this->tracker->get_daily_usage( 'fs_install_1' );

		$this->assertSame( 0, $usage['messages'] );
		$this->assertSame( 0, $usage['input_tokens'] );
		$this->assertSame( 0, $usage['cached_input_tokens'] );
		$this->assertSame( 0, $usage['output_tokens'] );
		$this->assertSame( 0, $usage['total_tokens'] );
	}

	public function test_record_message_increments_today(): void {
		$this->tracker->record_message( 'fs_install_1' );
		$this->tracker->record_message( 'fs_install_1' );

		$usage = $this->tracker->get_daily_usage( 'fs_install_1' );

		$this->assertSame( 2, $usage['messages'] );
	}

	public function test_record_tokens_accumulates_from_responses_usage_object(): void {
		$this->tracker->record_tokens( 'fs_install_1', array(
			'input_tokens'         => 1000,
			'input_tokens_details' => array( 'cached_tokens' => 800 ),
			'output_tokens'        => 200,
			'output_tokens_details' => array( 'reasoning_tokens' => 50 ),
			'total_tokens'         => 1200,
		) );
		$this->tracker->record_tokens( 'fs_install_1', array(
			'input_tokens'         => 500,
			'input_tokens_details' => array( 'cached_tokens' => 0 ),
			'output_tokens'        => 100,
			'total_tokens'         => 600,
		) );

		$usage = $this->tracker->get_daily_usage( 'fs_install_1' );

		$this->assertSame( 1500, $usage['input_tokens'] );
		$this->assertSame( 800, $usage['cached_input_tokens'] );
		$this->assertSame( 300, $usage['output_tokens'] );
		$this->assertSame( 1800, $usage['total_tokens'] );
	}

	public function test_usage_is_tracked_per_bucket(): void {
		$this->tracker->record_message( 'fs_install_1' );
		$this->tracker->record_message( 'fs_install_2' );
		$this->tracker->record_message( 'fs_install_2' );

		$this->assertSame( 1, $this->tracker->get_daily_usage( 'fs_install_1' )['messages'] );
		$this->assertSame( 2, $this->tracker->get_daily_usage( 'fs_install_2' )['messages'] );
	}

	public function test_is_over_budget_false_when_under_both_budgets(): void {
		update_option( 'wpaip_settings', array(
			'daily_message_budget' => 10,
			'daily_token_budget'   => 1000,
		) );

		$this->tracker->record_message( 'fs_install_1' );
		$this->tracker->record_tokens( 'fs_install_1', array( 'total_tokens' => 500 ) );

		$this->assertFalse( $this->tracker->is_over_budget( 'fs_install_1' ) );
	}

	public function test_is_over_budget_true_at_message_budget(): void {
		update_option( 'wpaip_settings', array(
			'daily_message_budget' => 2,
			'daily_token_budget'   => 0,
		) );

		$this->tracker->record_message( 'fs_install_1' );
		$this->assertFalse( $this->tracker->is_over_budget( 'fs_install_1' ) );

		$this->tracker->record_message( 'fs_install_1' );
		$this->assertTrue( $this->tracker->is_over_budget( 'fs_install_1' ) );
	}

	public function test_is_over_budget_true_when_token_budget_exhausted(): void {
		update_option( 'wpaip_settings', array(
			'daily_message_budget' => 0,
			'daily_token_budget'   => 1000,
		) );

		$this->tracker->record_tokens( 'fs_install_1', array( 'total_tokens' => 1000 ) );

		$this->assertTrue( $this->tracker->is_over_budget( 'fs_install_1' ) );
	}

	public function test_zero_budgets_disable_enforcement(): void {
		update_option( 'wpaip_settings', array(
			'daily_message_budget' => 0,
			'daily_token_budget'   => 0,
		) );

		update_option( 'wpaip_usage_daily', array(
			gmdate( 'Y-m-d' ) => array(
				'fs_install_1' => array( 'messages' => 999999, 'total_tokens' => 999999999 ),
			),
		) );

		$this->assertFalse( $this->tracker->is_over_budget( 'fs_install_1' ) );
	}

	public function test_default_budgets_apply_when_settings_missing(): void {
		update_option( 'wpaip_usage_daily', array(
			gmdate( 'Y-m-d' ) => array(
				'fs_install_1' => array( 'messages' => WPAIP_Admin::DEFAULT_DAILY_MESSAGE_BUDGET ),
			),
		) );

		$this->assertTrue( $this->tracker->is_over_budget( 'fs_install_1' ) );
	}

	public function test_budget_filters_override_settings(): void {
		update_option( 'wpaip_settings', array(
			'daily_message_budget' => 0,
			'daily_token_budget'   => 1000000,
		) );

		add_filter( 'wpaip_daily_token_budget', static function ( int $budget, string $usage_bucket ): int {
			return 'fs_install_1' === $usage_bucket ? 100 : $budget;
		} );

		$this->tracker->record_tokens( 'fs_install_1', array( 'total_tokens' => 150 ) );
		$this->tracker->record_tokens( 'fs_install_2', array( 'total_tokens' => 150 ) );

		$this->assertTrue( $this->tracker->is_over_budget( 'fs_install_1' ) );
		$this->assertFalse( $this->tracker->is_over_budget( 'fs_install_2' ) );
	}

	public function test_old_days_are_pruned_on_write(): void {
		update_option( 'wpaip_usage_daily', array(
			'2020-01-01' => array(
				'fs_install_1' => array( 'messages' => 5 ),
			),
		) );

		$this->tracker->record_message( 'fs_install_1' );

		$option = get_option( 'wpaip_usage_daily' );

		$this->assertArrayNotHasKey( '2020-01-01', $option );
		$this->assertArrayHasKey( gmdate( 'Y-m-d' ), $option );
	}

	public function test_yesterday_does_not_count_toward_today(): void {
		$yesterday = gmdate( 'Y-m-d', time() - DAY_IN_SECONDS );
		update_option( 'wpaip_usage_daily', array(
			$yesterday => array(
				'fs_install_1' => array( 'messages' => 100, 'total_tokens' => 5000 ),
			),
		) );

		$usage = $this->tracker->get_daily_usage( 'fs_install_1' );

		$this->assertSame( 0, $usage['messages'] );
		$this->assertSame( 0, $usage['total_tokens'] );
		$this->assertSame( 100, $this->tracker->get_daily_usage( 'fs_install_1', $yesterday )['messages'] );
	}
}
