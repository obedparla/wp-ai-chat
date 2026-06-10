<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_API {
	private const MAX_MESSAGES              = 40;
	private const MAX_TOTAL_CONTENT_LENGTH  = 16000;
	private const RATE_LIMIT_MAX_REQUESTS   = 20;
	private const RATE_LIMIT_WINDOW_SECONDS = 300;

	private WPAIC_Logs $logs;
	private WPAIC_License_Manager $license_manager;

	public function __construct( ?WPAIC_License_Manager $license_manager = null ) {
		$this->logs = new WPAIC_Logs();
		$this->license_manager = $license_manager ?? new WPAIC_License_Manager();
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
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

		register_rest_route(
			'wpaic/v1',
			'/send-transcript',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_send_transcript' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
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
	 * Tools whose outputs render as product cards or a comparison table in the
	 * widget. Their products are summarized into the transformed text content so
	 * the model can resolve "the second one" against what was actually shown.
	 */
	private const PRODUCT_BEARING_TOOLS = array(
		'search_products',
		'get_popular_products',
		'get_product_details',
		'compare_products',
	);

	/**
	 * Transform AI SDK UIMessage format to OpenAI format. Text parts are
	 * concatenated into `content`; product-bearing tool parts become a compact
	 * "Products shown (display order)" summary stored under `product_context`.
	 * The summary is forwarded to the model as a system-role input item (see
	 * WPAIC_Chat::build_responses_input), never as assistant text, so ordinal
	 * references survive across turns without the model echoing the list into
	 * shopper-visible replies.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<int, array<string, mixed>>
	 */
	private function transform_messages( array $messages ): array {
		foreach ( $messages as &$msg ) {
			if ( is_array( $msg ) ) {
				// product_context is server-generated only; never trust a client-supplied value.
				unset( $msg['product_context'] );
			}
			if ( isset( $msg['parts'] ) && is_array( $msg['parts'] ) ) {
				$content           = '';
				$product_summaries = array();
				foreach ( $msg['parts'] as $part ) {
					if ( ! is_array( $part ) ) {
						continue;
					}
					if ( 'text' === ( $part['type'] ?? '' ) ) {
						$content .= $part['text'] ?? '';
						continue;
					}
					$summary = $this->summarize_product_tool_part( $part );
					if ( null !== $summary ) {
						$product_summaries[] = $summary;
					}
				}
				if ( ! empty( $product_summaries ) ) {
					$msg['product_context'] = implode( "\n", $product_summaries );
				}
				$msg['content'] = $content;
				unset( $msg['parts'] );
			}
		}
		return $messages;
	}

	/**
	 * Build a compact text summary of a product-bearing dynamic-tool part,
	 * preserving display order. Keeps only name, id, and price so the carried
	 * context stays cheap.
	 *
	 * @param array<string, mixed> $part
	 */
	private function summarize_product_tool_part( array $part ): ?string {
		if ( 'dynamic-tool' !== ( $part['type'] ?? '' ) || 'output-available' !== ( $part['state'] ?? '' ) ) {
			return null;
		}

		$tool_name = $part['toolName'] ?? '';
		if ( ! in_array( $tool_name, self::PRODUCT_BEARING_TOOLS, true ) ) {
			return null;
		}

		$output = $part['output'] ?? null;
		if ( ! is_array( $output ) ) {
			return null;
		}

		if ( 'compare_products' === $tool_name ) {
			$products = isset( $output['products'] ) && is_array( $output['products'] ) ? $output['products'] : array();
		} elseif ( 'get_product_details' === $tool_name ) {
			$products = array( $output );
		} else {
			$products = $output;
		}

		$entries  = array();
		$position = 1;
		foreach ( $products as $product ) {
			if ( ! is_array( $product ) || ! isset( $product['id'], $product['name'] ) ) {
				continue;
			}
			$price      = $product['price'] ?? '';
			$price_part = is_scalar( $price ) && '' !== (string) $price ? ', price ' . $price : '';
			$entries[]  = $position . '. ' . $product['name'] . ' (id ' . (int) $product['id'] . $price_part . ')';
			++$position;
		}

		if ( empty( $entries ) ) {
			return null;
		}

		$label = 'compare_products' === $tool_name ? 'Products compared (display order): ' : 'Products shown (display order): ';
		return $label . implode( ' ', $entries );
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_Error|void
	 */
	public function handle_chat_stream( WP_REST_Request $request ) {
		$availability_error = $this->ensure_chat_is_available();
		if ( is_wp_error( $availability_error ) ) {
			return $availability_error;
		}

		$messages     = $request->get_param( 'messages' );
		$session_id   = $request->get_param( 'session_id' );
		$page_context = $this->sanitize_page_context( $request->get_param( 'page_context' ) );

		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return new WP_Error( 'no_messages', 'Messages are required', array( 'status' => 400 ) );
		}

		$messages = $this->transform_messages( $messages );

		$session_id = $this->resolve_session_id( $session_id );
		if ( null === $session_id ) {
			$this->reject_stream_request( 'Your chat session is invalid. Please refresh the page and start a new conversation.' );
		}

		$throttle_message = $this->check_rate_limit( $session_id );
		if ( null !== $throttle_message ) {
			$this->reject_stream_request( $throttle_message );
		}

		$validation_message = $this->validate_chat_messages( $messages );
		if ( null !== $validation_message ) {
			$this->reject_stream_request( $validation_message );
		}

		$conversation_id = $this->logs->get_or_create_conversation( $session_id );

		$this->log_trailing_user_messages( $conversation_id, $messages );

		$this->start_event_stream();

		$response_content      = '';
		$message_id            = wp_generate_uuid4();
		$text_started          = false;
		$tool_names_by_call_id = array();
		$card_only_labels      = array();

		try {
			$chat = new WPAIC_Chat( $page_context );
			$chat->set_conversation_id( $conversation_id );
			$chat->send_stream(
				$messages,
				/** @param array<string, mixed> $data */
				function ( array $data ) use ( &$response_content, &$text_started, &$tool_names_by_call_id, &$card_only_labels, $message_id ): void {
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
						$tool_call_id = $data['tool_input_available']['toolCallId'] ?? '';
						$tool_name    = $data['tool_input_available']['toolName'] ?? '';
						if ( is_string( $tool_call_id ) && is_string( $tool_name ) ) {
							$tool_names_by_call_id[ $tool_call_id ] = $tool_name;
						}
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
						$tool_call_id = $data['tool_output_available']['toolCallId'] ?? '';
						$card_label   = $this->describe_card_payload(
							is_string( $tool_call_id ) ? ( $tool_names_by_call_id[ $tool_call_id ] ?? '' ) : '',
							$data['tool_output_available']['output'] ?? null
						);
						if ( null !== $card_label && ! in_array( $card_label, $card_only_labels, true ) ) {
							$card_only_labels[] = $card_label;
						}
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
		} catch ( \Throwable $throwable ) {
			// A third-party fatal mid-stream must surface as an SSE error event,
			// not a dead spinner. Detail goes to the server log only.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPAIC] Chat stream failed: ' . $throwable->getMessage() );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
			echo 'data: ' . wp_json_encode(
				array(
					'type'  => 'error',
					'error' => 'Something went wrong. Please try again.',
				)
			) . "\n\n";
			echo "data: [DONE]\n\n";
			flush();
		}

		if ( '' !== $response_content ) {
			$this->logs->log_message( $conversation_id, 'assistant', $response_content );
		} elseif ( ! empty( $card_only_labels ) ) {
			// Card-only reply (no text): keep a row so the transcript shows the bot responded.
			$this->logs->log_message( $conversation_id, 'assistant', implode( "\n", $card_only_labels ) );
		}

		exit;
	}

	/**
	 * Log every trailing user message. Frontend debounce batching can send
	 * several consecutive user messages in one request; logging only the last
	 * loses the rest from the transcript.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 */
	private function log_trailing_user_messages( int $conversation_id, array $messages ): void {
		$trailing_user_contents = array();
		for ( $i = count( $messages ) - 1; $i >= 0; $i-- ) {
			$message = $messages[ $i ] ?? null;
			if ( ! is_array( $message ) || 'user' !== ( $message['role'] ?? '' ) ) {
				break;
			}
			$content = $message['content'] ?? '';
			array_unshift( $trailing_user_contents, is_string( $content ) ? $content : '' );
		}

		foreach ( $trailing_user_contents as $content ) {
			$this->logs->log_message( $conversation_id, 'user', $content );
		}
	}

	/**
	 * Placeholder transcript text for tools whose output renders as UI (cards,
	 * buttons) instead of text, so a card-only assistant turn still logs a row.
	 *
	 * @param mixed $output Tool result as emitted to the frontend.
	 */
	private function describe_card_payload( string $tool_name, mixed $output ): ?string {
		switch ( $tool_name ) {
			case 'search_products':
			case 'get_popular_products':
				return is_array( $output ) && ! empty( $output ) ? '[Sent product cards]' : null;

			case 'get_product_details':
				return is_array( $output ) && empty( $output['error'] ) ? '[Sent product card]' : null;

			case 'compare_products':
				return is_array( $output ) && ! empty( $output['products'] ) ? '[Sent product comparison]' : null;

			case 'get_checkout_action':
				return '[Sent checkout button]';

			case 'add_to_cart':
				return is_array( $output ) && ! empty( $output['success'] ) ? '[Sent add-to-cart confirmation]' : null;

			default:
				return null;
		}
	}

	/**
	 * Validate the client-supplied session id. Returns a generated UUID when
	 * absent, the original value when it is a valid UUID, or null when the
	 * client sent a fabricated/non-UUID value.
	 *
	 * @param mixed $session_id
	 */
	private function resolve_session_id( mixed $session_id ): ?string {
		if ( empty( $session_id ) || ! is_string( $session_id ) ) {
			return wp_generate_uuid4();
		}

		if ( ! wp_is_uuid( $session_id ) ) {
			return null;
		}

		return $session_id;
	}

	/**
	 * Transient-based throttle, keyed per IP and per session.
	 *
	 * @return string|null Shopper-facing error message when throttled, null otherwise.
	 */
	private function check_rate_limit( string $session_id ): ?string {
		$max_requests   = (int) apply_filters( 'wpaic_rate_limit_max_requests', self::RATE_LIMIT_MAX_REQUESTS );
		$window_seconds = (int) apply_filters( 'wpaic_rate_limit_window_seconds', self::RATE_LIMIT_WINDOW_SECONDS );

		if ( $max_requests <= 0 || $window_seconds <= 0 ) {
			return null;
		}

		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$transient_keys = array(
			'wpaic_throttle_ip_' . md5( $ip_address ),
			'wpaic_throttle_session_' . md5( $session_id ),
		);

		$throttled = false;
		foreach ( $transient_keys as $transient_key ) {
			$request_count = get_transient( $transient_key );

			if ( false === $request_count ) {
				set_transient( $transient_key, 1, $window_seconds );
				continue;
			}

			if ( (int) $request_count >= $max_requests ) {
				$throttled = true;
				continue;
			}

			set_transient( $transient_key, (int) $request_count + 1, $window_seconds );
		}

		if ( $throttled ) {
			return 'You have sent too many messages in a short time. Please wait a few minutes and try again.';
		}

		return null;
	}

	/**
	 * Validate client-supplied messages: count cap, total content size cap,
	 * role whitelist (clients may only send user/assistant messages).
	 *
	 * @param array<int, mixed> $messages Messages after transform_messages().
	 * @return string|null Shopper-facing error message when invalid, null otherwise.
	 */
	private function validate_chat_messages( array $messages ): ?string {
		if ( count( $messages ) > self::MAX_MESSAGES ) {
			return 'This conversation has grown too long. Please start a new conversation.';
		}

		$total_content_length = 0;
		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) ) {
				return 'Some messages could not be read. Please refresh the page and try again.';
			}

			$role = $message['role'] ?? '';
			if ( 'user' !== $role && 'assistant' !== $role ) {
				return 'Some messages could not be read. Please refresh the page and try again.';
			}

			$content = $message['content'] ?? '';
			if ( ! is_string( $content ) ) {
				return 'Some messages could not be read. Please refresh the page and try again.';
			}

			// product_context is server-generated from client-supplied parts, so it
			// must count toward the same total size cap as the content itself.
			$product_context = $message['product_context'] ?? '';

			$total_content_length += strlen( $content ) + ( is_string( $product_context ) ? strlen( $product_context ) : 0 );
			if ( $total_content_length > self::MAX_TOTAL_CONTENT_LENGTH ) {
				return 'This conversation has grown too large. Please start a new conversation.';
			}
		}

		return null;
	}

	private function start_event_stream(): void {
		header( 'Content-Type: text/event-stream; charset=utf-8' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );
		header( 'x-vercel-ai-ui-message-stream: v1' );

		if ( ob_get_level() ) {
			ob_end_clean();
		}
	}

	/**
	 * Start the SSE stream, emit a single error event (the widget already
	 * renders these), and end the request.
	 */
	private function reject_stream_request( string $error_message ): never {
		$this->start_event_stream();

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
		echo 'data: ' . wp_json_encode(
			array(
				'type'  => 'error',
				'error' => $error_message,
			)
		) . "\n\n";
		echo "data: [DONE]\n\n";
		flush();

		exit;
	}

	private function ensure_chat_is_available(): ?WP_Error {
		if ( ! $this->license_manager->has_valid_chat_license() ) {
			return new WP_Error(
				'chat_unavailable',
				'Chat is unavailable until a valid trial or license is active.',
				array( 'status' => 403 )
			);
		}

		if ( ! $this->license_manager->is_provider_url_configured() ) {
			return new WP_Error(
				'chat_unavailable',
				'Chat is unavailable because the provider URL is not configured.',
				array( 'status' => 503 )
			);
		}

		if ( ! $this->license_manager->has_provider_auth() ) {
			return new WP_Error(
				'chat_unavailable',
				'Chat is unavailable because this site has not finished license activation yet.',
				array( 'status' => 403 )
			);
		}

		return null;
	}

	/**
	 * @param mixed $page_context
	 * @return array<string, mixed>
	 */
	private function sanitize_page_context( mixed $page_context ): array {
		$service = new WPAIC_Page_Context();
		return $service->sanitize( $page_context );
	}

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response
	 */
	public function get_products( WP_REST_Request $request ): WP_REST_Response {
		$tools    = new WPAIC_Product_Tools();
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

	/**
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_send_transcript( WP_REST_Request $request ) {
		$email      = $request->get_param( 'email' );
		$transcript = $request->get_param( 'transcript' );

		if ( empty( $email ) || ! is_string( $email ) || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', 'A valid email address is required.', array( 'status' => 400 ) );
		}

		if ( empty( $transcript ) || ! is_string( $transcript ) ) {
			return new WP_Error( 'empty_transcript', 'Transcript is required.', array( 'status' => 400 ) );
		}

		$email      = sanitize_email( $email );
		$transcript = sanitize_textarea_field( $transcript );

		$settings     = get_option( 'wpaic_settings', array() );
		$chatbot_name = ! empty( $settings['chatbot_name'] ) ? $settings['chatbot_name'] : 'AI Assistant';
		$site_name    = get_bloginfo( 'name' );

		$subject = sprintf(
			/* translators: 1: chatbot name, 2: site name */
			__( 'Your %1$s conversation — %2$s', 'wp-ai-chatbot' ),
			$chatbot_name,
			$site_name
		);

		$message  = sprintf(
			/* translators: %s: chatbot name */
			__( "Here's your conversation transcript from %s:\n\n", 'wp-ai-chatbot' ),
			$chatbot_name
		);
		$message .= "---\n" . $transcript . "\n---\n\n";
		$message .= sprintf(
			/* translators: %s: site URL */
			__( "Visit us: %s\n", 'wp-ai-chatbot' ),
			home_url()
		);

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );
		$sent    = wp_mail( $email, $subject, $message, $headers );

		if ( ! $sent ) {
			return new WP_Error( 'email_failed', 'Failed to send email. Please try again.', array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}
}
