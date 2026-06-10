<?php
/**
 * Tests for WPAIC_Logs class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-logs.php';

class WPAIC_LogsTest extends TestCase {
	private WPAIC_Logs $logs;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		if ( ! $wpdb instanceof MockWpdb ) {
			$wpdb = new MockWpdb();
		}
		$wpdb->reset();
		WPAICTestHelper::reset();
		$this->logs = new WPAIC_Logs();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( $wpdb instanceof MockWpdb ) {
			$wpdb->reset();
		}
		WPAICTestHelper::reset();
		unset( $_SERVER['REMOTE_ADDR'] );
		parent::tearDown();
	}

	public function test_create_conversation_returns_id(): void {
		$id = $this->logs->create_conversation( 'test-session-123' );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );
	}

	public function test_get_conversation_id_returns_null_when_not_found(): void {
		$id = $this->logs->get_conversation_id( 'nonexistent-session' );

		$this->assertNull( $id );
	}

	public function test_get_conversation_id_returns_id_when_found(): void {
		$created_id = $this->logs->create_conversation( 'test-session-456' );
		$found_id   = $this->logs->get_conversation_id( 'test-session-456' );

		$this->assertEquals( $created_id, $found_id );
	}

	public function test_get_or_create_conversation_creates_new(): void {
		$id = $this->logs->get_or_create_conversation( 'new-session' );

		$this->assertGreaterThan( 0, $id );
	}

	public function test_get_or_create_conversation_returns_existing(): void {
		$first_id  = $this->logs->create_conversation( 'existing-session' );
		$second_id = $this->logs->get_or_create_conversation( 'existing-session' );

		$this->assertEquals( $first_id, $second_id );
	}

	public function test_log_message_returns_id(): void {
		$conv_id = $this->logs->create_conversation( 'msg-session' );
		$msg_id  = $this->logs->log_message( $conv_id, 'user', 'Hello!' );

		$this->assertIsInt( $msg_id );
		$this->assertGreaterThan( 0, $msg_id );
	}

	public function test_log_message_stores_content(): void {
		$conv_id = $this->logs->create_conversation( 'content-session' );
		$this->logs->log_message( $conv_id, 'user', 'Test message' );
		$this->logs->log_message( $conv_id, 'assistant', 'Test response' );

		$messages = $this->logs->get_conversation_messages( $conv_id );

		$this->assertCount( 2, $messages );
		$this->assertEquals( 'user', $messages[0]->role );
		$this->assertEquals( 'Test message', $messages[0]->content );
		$this->assertEquals( 'assistant', $messages[1]->role );
		$this->assertEquals( 'Test response', $messages[1]->content );
	}

	public function test_get_conversations_returns_list(): void {
		$this->logs->create_conversation( 'session-1' );
		$this->logs->create_conversation( 'session-2' );

		$conversations = $this->logs->get_conversations();

		$this->assertCount( 2, $conversations );
	}

	public function test_get_conversations_includes_message_count(): void {
		$conv_id = $this->logs->create_conversation( 'count-session' );
		$this->logs->log_message( $conv_id, 'user', 'Message 1' );
		$this->logs->log_message( $conv_id, 'assistant', 'Response 1' );
		$this->logs->log_message( $conv_id, 'user', 'Message 2' );

		$conversations = $this->logs->get_conversations();

		$this->assertEquals( 3, $conversations[0]->message_count );
	}

	public function test_get_conversations_includes_first_user_message(): void {
		$conv_id = $this->logs->create_conversation( 'preview-session' );
		$this->logs->log_message( $conv_id, 'assistant', 'Hello! How can I help?' );
		$this->logs->log_message( $conv_id, 'user', 'Do you have red shoes?' );
		$this->logs->log_message( $conv_id, 'user', 'In size 42?' );

		$conversations = $this->logs->get_conversations();

		$this->assertEquals( 'Do you have red shoes?', $conversations[0]->first_user_message );
	}

	public function test_get_conversations_first_user_message_null_without_user_messages(): void {
		$conv_id = $this->logs->create_conversation( 'empty-session' );
		$this->logs->log_message( $conv_id, 'assistant', 'Greeting only' );

		$conversations = $this->logs->get_conversations();

		$this->assertNull( $conversations[0]->first_user_message );
	}

	public function test_get_conversations_search_filter_matches_message_content(): void {
		$matching_id = $this->logs->create_conversation( 'search-match' );
		$this->logs->log_message( $matching_id, 'user', 'I need a refund please' );
		$other_id = $this->logs->create_conversation( 'search-other' );
		$this->logs->log_message( $other_id, 'user', 'Where is my order?' );

		$conversations = $this->logs->get_conversations( 20, 0, array( 'search' => 'refund' ) );

		$this->assertCount( 1, $conversations );
		$this->assertEquals( $matching_id, $conversations[0]->id );
	}

	public function test_get_conversations_date_filters_bound_created_at(): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'old-session',
				'created_at' => '2026-01-05 10:00:00',
			)
		);
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'recent-session',
				'created_at' => '2026-06-01 10:00:00',
			)
		);

		$from_february = $this->logs->get_conversations( 20, 0, array( 'date_from' => '2026-02-01' ) );
		$this->assertCount( 1, $from_february );
		$this->assertEquals( 'recent-session', $from_february[0]->session_id );

		$until_february = $this->logs->get_conversations( 20, 0, array( 'date_to' => '2026-02-01' ) );
		$this->assertCount( 1, $until_february );
		$this->assertEquals( 'old-session', $until_february[0]->session_id );
	}

	public function test_get_conversation_count_applies_filters(): void {
		$matching_id = $this->logs->create_conversation( 'count-match' );
		$this->logs->log_message( $matching_id, 'user', 'gift wrapping available?' );
		$this->logs->create_conversation( 'count-other' );

		$this->assertEquals( 2, $this->logs->get_conversation_count() );
		$this->assertEquals( 1, $this->logs->get_conversation_count( array( 'search' => 'gift wrapping' ) ) );
	}

	public function test_count_conversations_between_uses_half_open_range(): void {
		global $wpdb;
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'inside-range',
				'created_at' => '2026-06-05 10:00:00',
			)
		);
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'at-upper-bound',
				'created_at' => '2026-06-08 00:00:00',
			)
		);
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'before-range',
				'created_at' => '2026-06-01 10:00:00',
			)
		);

		$count = $this->logs->count_conversations_between( '2026-06-05 00:00:00', '2026-06-08 00:00:00' );

		$this->assertEquals( 1, $count );
	}

	public function test_get_conversation_count_returns_total(): void {
		$this->logs->create_conversation( 'count-1' );
		$this->logs->create_conversation( 'count-2' );
		$this->logs->create_conversation( 'count-3' );

		$count = $this->logs->get_conversation_count();

		$this->assertEquals( 3, $count );
	}

	public function test_delete_conversation_removes_conversation_and_messages(): void {
		$conv_id = $this->logs->create_conversation( 'delete-session' );
		$this->logs->log_message( $conv_id, 'user', 'To be deleted' );

		$result = $this->logs->delete_conversation( $conv_id );

		$this->assertTrue( $result );
		$this->assertEquals( 0, $this->logs->get_conversation_count() );
	}

	public function test_get_conversation_messages_returns_empty_for_nonexistent(): void {
		$messages = $this->logs->get_conversation_messages( 99999 );

		$this->assertEmpty( $messages );
	}

	public function test_create_conversation_anonymizes_ip_by_default(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.45';

		$this->logs->create_conversation( 'anon-default-session' );

		$conversations = $this->logs->get_conversations();
		$this->assertEquals( '203.0.113.0', $conversations[0]->user_ip );
	}

	public function test_create_conversation_stores_full_ip_when_anonymization_disabled(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.45';
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'anonymize_ip' => false ) );

		$this->logs->create_conversation( 'full-ip-session' );

		$conversations = $this->logs->get_conversations();
		$this->assertEquals( '203.0.113.45', $conversations[0]->user_ip );
	}

	public function test_create_conversation_anonymizes_ip_when_setting_enabled(): void {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'anonymize_ip' => true ) );

		$this->logs->create_conversation( 'anon-on-session' );

		$conversations = $this->logs->get_conversations();
		$this->assertEquals( '198.51.100.0', $conversations[0]->user_ip );
	}

	public function test_delete_conversations_older_than_purges_conversations_messages_and_events(): void {
		global $wpdb;

		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'stale-session',
				'created_at' => '2025-01-01 10:00:00',
			)
		);
		$stale_id = $wpdb->insert_id;
		$wpdb->insert(
			'wp_wpaic_messages',
			array(
				'conversation_id' => $stale_id,
				'role'            => 'user',
				'content'         => 'Old message',
				'created_at'      => '2025-01-01 10:00:00',
			)
		);
		$wpdb->insert(
			'wp_wpaic_events',
			array(
				'conversation_id' => $stale_id,
				'event_type'      => 'product_click',
				'created_at'      => '2025-01-01 10:00:00',
			)
		);

		$fresh_id = $this->logs->create_conversation( 'fresh-session' );
		$this->logs->log_message( $fresh_id, 'user', 'Recent message' );

		$deleted = $this->logs->delete_conversations_older_than( 30 );

		$this->assertEquals( 1, $deleted );
		$this->assertEquals( 1, $this->logs->get_conversation_count() );
		$this->assertEquals( 'fresh-session', $this->logs->get_conversations()[0]->session_id );
		$this->assertEmpty( $this->logs->get_conversation_messages( $stale_id ) );
		$this->assertEmpty( $wpdb->get_results( "SELECT * FROM wp_wpaic_events WHERE conversation_id = $stale_id" ) );
		$this->assertCount( 1, $this->logs->get_conversation_messages( $fresh_id ) );
	}

	public function test_delete_conversations_older_than_uses_last_activity(): void {
		global $wpdb;

		// Created long ago but active recently: must be kept.
		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'long-lived-session',
				'created_at' => '2025-01-01 10:00:00',
			)
		);
		$conversation_id = $wpdb->insert_id;
		$this->logs->log_message( $conversation_id, 'user', 'Still chatting' );

		$deleted = $this->logs->delete_conversations_older_than( 30 );

		$this->assertEquals( 0, $deleted );
		$this->assertEquals( 1, $this->logs->get_conversation_count() );
	}

	public function test_delete_conversations_older_than_zero_days_is_noop(): void {
		global $wpdb;

		$wpdb->insert(
			'wp_wpaic_conversations',
			array(
				'session_id' => 'ancient-session',
				'created_at' => '2020-01-01 10:00:00',
			)
		);

		$this->assertEquals( 0, $this->logs->delete_conversations_older_than( 0 ) );
		$this->assertEquals( 0, $this->logs->delete_conversations_older_than( -5 ) );
		$this->assertEquals( 1, $this->logs->get_conversation_count() );
	}
}
