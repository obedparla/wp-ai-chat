<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_API {
	private WPAIC_Logs $logs;

	public function __construct() {
		$this->logs = new WPAIC_Logs();
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'wpaic/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
			)
		);

		register_rest_route(
			'wpaic/v1',
			'/chat/stream',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat_stream' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
			)
		);

		register_rest_route(
			'wpaic/v1',
			'/products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify REST API nonce.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error
	 */
	public function verify_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Nonce verification failed',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Transform AI SDK UIMessage format to OpenAI format.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<int, array<string, mixed>>
	 */
	private function transform_messages( array $messages ): array {
		foreach ( $messages as &$msg ) {
			if ( isset( $msg['parts'] ) && is_array( $msg['parts'] ) ) {
				$content = '';
				foreach ( $msg['parts'] as $part ) {
					if ( is_array( $part ) && 'text' === ( $part['type'] ?? '' ) ) {
						$content .= $part['text'] ?? '';
					}
				}
				$msg['content'] = $content;
				unset( $msg['parts'] );
			}
		}
		return $messages;
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_chat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$messages = $request->get_param( 'messages' );

		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return new WP_Error( 'no_messages', 'Messages are required', array( 'status' => 400 ) );
		}

		$messages = $this->transform_messages( $messages );
		$chat     = new WPAIC_Chat();
		$response = $chat->send( $messages );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return rest_ensure_response( $response );
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_Error|void
	 */
	public function handle_chat_stream( WP_REST_Request $request ) {
		$messages   = $request->get_param( 'messages' );
		$session_id = $request->get_param( 'session_id' );

		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return new WP_Error( 'no_messages', 'Messages are required', array( 'status' => 400 ) );
		}

		$messages = $this->transform_messages( $messages );

		if ( empty( $session_id ) || ! is_string( $session_id ) ) {
			$session_id = wp_generate_uuid4();
		}

		$conversation_id = $this->logs->get_or_create_conversation( $session_id );

		$last_message = end( $messages );
		if ( is_array( $last_message ) && 'user' === ( $last_message['role'] ?? '' ) ) {
			$this->logs->log_message( $conversation_id, 'user', $last_message['content'] ?? '' );
		}

		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );
		header( 'x-vercel-ai-ui-message-stream: v1' );

		if ( ob_get_level() ) {
			ob_end_clean();
		}

		$response_content = '';
		$message_id       = wp_generate_uuid4();
		$text_started     = false;
		$chat             = new WPAIC_Chat();
		$chat->send_stream(
			$messages,
			/** @param array<string, mixed> $data */
			function ( array $data ) use ( &$response_content, &$text_started, $message_id ): void {
				if ( isset( $data['content'] ) && is_string( $data['content'] ) ) {
					if ( ! $text_started ) {
						$text_started = true;
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
						echo 'data: ' . wp_json_encode(
							array(
								'type' => 'text-start',
								'id'   => $message_id,
							)
						) . "\n\n";
					}
					$response_content .= $data['content'];
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
					echo 'data: ' . wp_json_encode(
						array(
							'type'  => 'text-delta',
							'id'    => $message_id,
							'delta' => $data['content'],
						)
					) . "\n\n";
				}
				if ( isset( $data['tool_input_start'] ) && is_array( $data['tool_input_start'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
					echo 'data: ' . wp_json_encode(
						array(
							'type'       => 'tool-input-start',
							'toolCallId' => $data['tool_input_start']['toolCallId'] ?? '',
							'toolName'   => $data['tool_input_start']['toolName'] ?? '',
							'dynamic'    => true,
						)
					) . "\n\n";
				}
				if ( isset( $data['tool_input_delta'] ) && is_array( $data['tool_input_delta'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
					echo 'data: ' . wp_json_encode(
						array(
							'type'           => 'tool-input-delta',
							'toolCallId'     => $data['tool_input_delta']['toolCallId'] ?? '',
							'inputTextDelta' => $data['tool_input_delta']['inputTextDelta'] ?? '',
						)
					) . "\n\n";
				}
				if ( isset( $data['tool_input_available'] ) && is_array( $data['tool_input_available'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
					echo 'data: ' . wp_json_encode(
						array(
							'type'       => 'tool-input-available',
							'toolCallId' => $data['tool_input_available']['toolCallId'] ?? '',
							'toolName'   => $data['tool_input_available']['toolName'] ?? '',
							'input'      => $data['tool_input_available']['input'] ?? new \stdClass(),
							'dynamic'    => true,
						)
					) . "\n\n";
				}
				if ( isset( $data['tool_output_available'] ) && is_array( $data['tool_output_available'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
					echo 'data: ' . wp_json_encode(
						array(
							'type'       => 'tool-output-available',
							'toolCallId' => $data['tool_output_available']['toolCallId'] ?? '',
							'output'     => $data['tool_output_available']['output'] ?? new \stdClass(),
							'dynamic'    => true,
						)
					) . "\n\n";
				}
				if ( isset( $data['done'] ) && true === $data['done'] ) {
					if ( $text_started ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
						echo 'data: ' . wp_json_encode(
							array(
								'type' => 'text-end',
								'id'   => $message_id,
							)
						) . "\n\n";
					}
					echo "data: [DONE]\n\n";
				}
				if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
					echo 'data: ' . wp_json_encode(
						array(
							'type'  => 'error',
							'error' => $data['error'],
						)
					) . "\n\n";
				}
				flush();
			}
		);

		if ( '' !== $response_content ) {
			$this->logs->log_message( $conversation_id, 'assistant', $response_content );
		}

		exit;
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_products( WP_REST_Request $request ): WP_REST_Response {
		$tools    = new WPAIC_Tools();
		$search   = $request->get_param( 'search' );
		$category = $request->get_param( 'category' );
		$limit    = $request->get_param( 'limit' ) ?? 10;

		$products = $tools->search_products(
			array(
				'search'   => is_string( $search ) ? $search : null,
				'category' => is_string( $category ) ? $category : null,
				'limit'    => is_numeric( $limit ) ? (int) $limit : 10,
			)
		);

		return rest_ensure_response( $products );
	}
}
