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
}
