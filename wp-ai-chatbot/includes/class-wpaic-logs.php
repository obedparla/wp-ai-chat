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

		if ( null !== $user_ip && $this->should_anonymize_ip() ) {
			$user_ip = wp_privacy_anonymize_ip( $user_ip );
		}

		$result = $wpdb->insert(
			$wpdb->prefix . 'wpaic_conversations',
			array(
				'session_id' => $session_id,
				'user_id'    => $user_id > 0 ? $user_id : null,
				'user_ip'    => $user_ip,
				// Stamp in WP-local time (not the MySQL CURRENT_TIMESTAMP default,
				// which resolves in the DB server's zone) so conversation rows line
				// up with the event table and the analytics range bounds, both of
				// which use current_time( 'mysql' ).
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s' )
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
	 * @param array{search?: string, date_from?: string, date_to?: string} $filters Optional filters: text search over message
	 *                                                                              content, and Y-m-d date bounds on created_at.
	 * @return array<int, object{id: int, session_id: string, user_id: int|null, user_ip: string|null, created_at: string, updated_at: string, message_count: int, first_user_message: string|null}>
	 */
	public function get_conversations( int $limit = 20, int $offset = 0, array $filters = array() ): array {
		global $wpdb;

		$conversations_table = $wpdb->prefix . 'wpaic_conversations';
		$messages_table      = $wpdb->prefix . 'wpaic_messages';

		list( $where_sql, $where_values ) = $this->build_conversation_filters( $filters );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $where_sql contains placeholders only.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Placeholder count varies with $where_sql.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT c.*, COUNT(m.id) as message_count,
				(SELECT m2.content FROM $messages_table m2 WHERE m2.conversation_id = c.id AND m2.role = 'user' ORDER BY m2.id ASC LIMIT 1) as first_user_message
				FROM $conversations_table c
				LEFT JOIN $messages_table m ON c.id = m.conversation_id
				$where_sql
				GROUP BY c.id
				ORDER BY c.updated_at DESC
				LIMIT %d OFFSET %d",
				...array_merge( $where_values, array( $limit, $offset ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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
	 * @param array{search?: string, date_from?: string, date_to?: string} $filters Same filters as get_conversations().
	 * @return int Total number of conversations matching the filters
	 */
	public function get_conversation_count( array $filters = array() ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_conversations';

		list( $where_sql, $where_values ) = $this->build_conversation_filters( $filters );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		if ( empty( $where_values ) ) {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM $table c" );
		} else {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table c $where_sql", ...$where_values ) );
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

		return (int) $count;
	}

	/**
	 * Count conversations created in the half-open range [$since, $until).
	 *
	 * @param string $since Inclusive lower bound (MySQL datetime, local time).
	 * @param string $until Exclusive upper bound (MySQL datetime, local time).
	 */
	public function count_conversations_between( string $since, string $until ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'wpaic_conversations';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE created_at >= %s AND created_at < %s",
				$since,
				$until
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $count;
	}

	/**
	 * Build the WHERE clause + prepare() values for conversation list filters.
	 * Conversation columns must be referenced through the `c` alias.
	 *
	 * @param array{search?: string, date_from?: string, date_to?: string} $filters
	 * @return array{0: string, 1: array<int, string>}
	 */
	private function build_conversation_filters( array $filters ): array {
		global $wpdb;

		$clauses = array();
		$values  = array();

		if ( ! empty( $filters['date_from'] ) ) {
			$clauses[] = 'c.created_at >= %s';
			$values[]  = $filters['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$clauses[] = 'c.created_at <= %s';
			$values[]  = $filters['date_to'] . ' 23:59:59';
		}

		if ( ! empty( $filters['search'] ) ) {
			$messages_table = $wpdb->prefix . 'wpaic_messages';
			$clauses[]      = "EXISTS (SELECT 1 FROM $messages_table ms WHERE ms.conversation_id = c.id AND ms.content LIKE %s)";
			$values[]       = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		return array(
			empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses ),
			$values,
		);
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

	/**
	 * Delete conversations whose last activity is older than $days, along with
	 * their messages and events. Used by the daily retention cron.
	 *
	 * @param int $days Retention window in days. Values < 1 mean keep forever.
	 * @return int Number of conversations deleted.
	 */
	public function delete_conversations_older_than( int $days ): int {
		global $wpdb;

		if ( $days < 1 ) {
			return 0;
		}

		$conversations_table = $wpdb->prefix . 'wpaic_conversations';
		$cutoff              = gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - $days * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot use placeholders.
		$conversation_ids = array_map(
			'intval',
			(array) $wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM $conversations_table WHERE updated_at < %s", $cutoff )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $conversation_ids ) ) {
			return 0;
		}

		$id_list = implode( ',', $conversation_ids );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- IDs are cast to int above; table names cannot use placeholders.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wpaic_messages WHERE conversation_id IN ($id_list)" );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}wpaic_events WHERE conversation_id IN ($id_list)" );
		$wpdb->query( "DELETE FROM $conversations_table WHERE id IN ($id_list)" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		return count( $conversation_ids );
	}

	/**
	 * Whether visitor IPs should be anonymized before storage. Defaults to on
	 * when the setting has never been saved.
	 */
	private function should_anonymize_ip(): bool {
		$settings = get_option( 'wpaic_settings', array() );

		if ( ! is_array( $settings ) || ! array_key_exists( 'anonymize_ip', $settings ) ) {
			return true;
		}

		return ! empty( $settings['anonymize_ip'] );
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
