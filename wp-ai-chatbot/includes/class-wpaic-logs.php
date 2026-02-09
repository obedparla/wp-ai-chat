<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_Logs {
	/**
	 * @param string $session_id
	 * @return int|false Conversation ID or false on failure
	 */
	public function create_conversation( string $session_id ): int|false {
		global $wpdb;

		$user_id = get_current_user_id();
		$user_ip = $this->get_client_ip();

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpaic_conversations',
			array(
				'session_id' => $session_id,
				'user_id'    => $user_id > 0 ? $user_id : null,
				'user_ip'    => $user_ip,
			),
			array( '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param string $session_id
	 * @return int|null Conversation ID or null if not found
	 */
	public function get_conversation_id( string $session_id ): ?int {
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_conversations';
		$id    = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM $table WHERE session_id = %s ORDER BY created_at DESC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$session_id
			)
		);

		return null !== $id ? (int) $id : null;
	}

	/**
	 * @param string $session_id
	 * @return int Conversation ID (creates new if not exists)
	 */
	public function get_or_create_conversation( string $session_id ): int {
		$id = $this->get_conversation_id( $session_id );
		if ( null !== $id ) {
			return $id;
		}

		$new_id = $this->create_conversation( $session_id );
		return false !== $new_id ? $new_id : 0;
	}

	/**
	 * @param int $conversation_id
	 * @param string $role 'user' or 'assistant'
	 * @param string $content
	 * @return int|false Message ID or false on failure
	 */
	public function log_message( int $conversation_id, string $role, string $content ): int|false {
		global $wpdb;

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpaic_messages',
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
			),
			array( '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		$wpdb->update(
			$wpdb->prefix . 'wpaic_conversations',
			array( 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $limit
	 * @param int $offset
	 * @return array<int, object{id: int, session_id: string, user_id: int|null, user_ip: string|null, created_at: string, updated_at: string, message_count: int}>
	 */
	public function get_conversations( int $limit = 20, int $offset = 0 ): array {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpaic_conversations';
		$messages_table      = $wpdb->prefix . 'wpaic_messages';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, COUNT(m.id) as message_count
				FROM $conversations_table c
				LEFT JOIN $messages_table m ON c.id = m.conversation_id
				GROUP BY c.id
				ORDER BY c.updated_at DESC
				LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * @param int $conversation_id
	 * @return array<int, object{id: int, role: string, content: string, created_at: string}>
	 */
	public function get_conversation_messages( int $conversation_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'wpaic_messages';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, role, content, created_at FROM $table WHERE conversation_id = %d ORDER BY created_at ASC",
				$conversation_id
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * @return int Total number of conversations
	 */
	public function get_conversation_count(): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_conversations';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
		return (int) $count;
	}

	/**
	 * @param int $conversation_id
	 * @return bool
	 */
	public function delete_conversation( int $conversation_id ): bool {
		global $wpdb;

		$wpdb->delete(
			$wpdb->prefix . 'wpaic_messages',
			array( 'conversation_id' => $conversation_id ),
			array( '%d' )
		);

		$result = $wpdb->delete(
			$wpdb->prefix . 'wpaic_conversations',
			array( 'id' => $conversation_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	private function get_client_ip(): ?string {
		$ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$ip = explode( ',', $ip )[0];
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return null;
	}
}
