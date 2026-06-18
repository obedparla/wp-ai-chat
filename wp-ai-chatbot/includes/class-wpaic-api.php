<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIC_API {
	private const MAX_MESSAGES              = 40;
	private const MAX_TOTAL_CONTENT_LENGTH  = 16000;
	private const RATE_LIMIT_MAX_REQUESTS   = 20;
	private const RATE_LIMIT_WINDOW_SECONDS = 300;

	// Caps for the client-derived product context (see summarize_product_tool_part).
	private const PRODUCT_CONTEXT_MAX_NAME_LENGTH  = 80;
	private const PRODUCT_CONTEXT_MAX_PRICE_LENGTH = 20;
	private const PRODUCT_CONTEXT_MAX_ENTRIES      = 10;
	private const PRODUCT_CONTEXT_MAX_LENGTH       = 2000;

	private WPAIC_Logs $logs;
	private WPAIC_License_Manager $license_manager;

	/**
	 * Per-stream transcript-label bookkeeping: maps streamed toolCallIds to tool
	 * names so collect_card_label() can resolve which tool produced an output.
	 * Reset at the start of each chat stream.
	 *
	 * @var array<string, string>
	 */
	private array $tool_names_by_call_id = array();

	/**
	 * Placeholder labels (see describe_card_payload) collected during the
	 * current stream, used to log a transcript row for card-only replies.
	 *
	 * @var array<int, string>
	 */
	private array $card_only_labels = array();

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
			'/nonce',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_nonce' ),
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
	 * Vend a fresh `wp_rest` nonce. The widget bakes a nonce into the page, but
	 * full-page caching plugins freeze it; once it ages past the nonce tick
	 * (12-24h) it 403s for cached anonymous visitors. The frontend fetches this
	 * (uncached) endpoint to recover. Must never be cached by page/CDN layers.
	 *
	 * @return WP_REST_Response
	 */
	public function get_nonce(): WP_REST_Response {
		$response = new WP_REST_Response( array( 'nonce' => wp_create_nonce( 'wp_rest' ) ) );

		foreach ( wp_get_nocache_headers() as $name => $value ) {
			if ( '' !== $value ) {
				$response->header( $name, $value );
			}
		}
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );

		return $response;
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
				// Drop any client-supplied product_context verbatim; it is rebuilt below
				// from the message's tool parts. Those parts are still client-supplied,
				// which is why summarize_product_tool_part sanitizes and caps the values.
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
					$msg['product_context'] = mb_substr( implode( "\n", $product_summaries ), 0, self::PRODUCT_CONTEXT_MAX_LENGTH );
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
	 * The part comes from the client, and the summary is later forwarded to the
	 * model as a system-role item, so the string values are sanitized (control
	 * characters/newlines stripped, lengths capped) and entries are capped per
	 * part to limit what a tampered payload can smuggle into that item.
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
			if ( count( $entries ) >= self::PRODUCT_CONTEXT_MAX_ENTRIES ) {
				break;
			}
			if ( ! is_array( $product ) || ! isset( $product['id'], $product['name'] ) ) {
				continue;
			}
			$name       = $this->sanitize_product_context_value( (string) $product['name'], self::PRODUCT_CONTEXT_MAX_NAME_LENGTH );
			$price      = $product['price'] ?? '';
			$price      = is_scalar( $price ) ? $this->sanitize_product_context_value( (string) $price, self::PRODUCT_CONTEXT_MAX_PRICE_LENGTH ) : '';
			$price_part = '' !== $price ? ', price ' . $price : '';
			$entries[]  = $position . '. ' . $name . ' (id ' . (int) $product['id'] . $price_part . ')';
			++$position;
		}

		if ( empty( $entries ) ) {
			return null;
		}

		$label = 'compare_products' === $tool_name ? 'Products compared (display order): ' : 'Products shown (display order): ';
		return $label . implode( ' ', $entries );
	}

	/**
	 * Sanitize a client-supplied string destined for the product context:
	 * control characters and newlines collapse to a single space (so crafted
	 * values cannot fake extra lines or entries in the system-role item) and
	 * the result is length-capped.
	 */
	private function sanitize_product_context_value( string $value, int $max_length ): string {
		$sanitized = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $value );
		if ( null === $sanitized ) {
			// Invalid UTF-8 made preg fail; drop the value rather than forward it raw.
			return '';
		}

		return trim( mb_substr( $sanitized, 0, $max_length ) );
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

		$response_content            = '';
		$message_id                  = wp_generate_uuid4();
		$text_started                = false;
		$this->tool_names_by_call_id = array();
		$this->card_only_labels      = array();

		try {
			$chat = new WPAIC_Chat( $page_context );
			$chat->set_conversation_id( $conversation_id );
			$chat->send_stream(
				$messages,
				/** @param array<string, mixed> $data */
				function ( array $data ) use ( &$response_content, &$text_started, $message_id ): void {
					if ( isset( $data['content'] ) && is_string( $data['content'] ) ) {
						if ( ! $text_started ) {
							$text_started = true;
							$this->emit_sse_event( 'text-start', array( 'id' => $message_id ) );
						}
						$response_content .= $data['content'];
						$this->emit_sse_event(
							'text-delta',
							array(
								'id'    => $message_id,
								'delta' => $data['content'],
							)
						);
					}
					if ( isset( $data['tool_input_start'] ) && is_array( $data['tool_input_start'] ) ) {
						$this->emit_sse_event(
							'tool-input-start',
							array(
								'toolCallId' => $data['tool_input_start']['toolCallId'] ?? '',
								'toolName'   => $data['tool_input_start']['toolName'] ?? '',
								'dynamic'    => true,
							)
						);
					}
					if ( isset( $data['tool_input_delta'] ) && is_array( $data['tool_input_delta'] ) ) {
						$this->emit_sse_event(
							'tool-input-delta',
							array(
								'toolCallId'     => $data['tool_input_delta']['toolCallId'] ?? '',
								'inputTextDelta' => $data['tool_input_delta']['inputTextDelta'] ?? '',
							)
						);
					}
					if ( isset( $data['tool_input_available'] ) && is_array( $data['tool_input_available'] ) ) {
						$this->remember_tool_name(
							$data['tool_input_available']['toolCallId'] ?? '',
							$data['tool_input_available']['toolName'] ?? ''
						);
						$this->emit_sse_event(
							'tool-input-available',
							array(
								'toolCallId' => $data['tool_input_available']['toolCallId'] ?? '',
								'toolName'   => $data['tool_input_available']['toolName'] ?? '',
								'input'      => $data['tool_input_available']['input'] ?? new \stdClass(),
								'dynamic'    => true,
							)
						);
					}
					if ( isset( $data['tool_output_available'] ) && is_array( $data['tool_output_available'] ) ) {
						$this->collect_card_label(
							$data['tool_output_available']['toolCallId'] ?? '',
							$data['tool_output_available']['output'] ?? null
						);
						$this->emit_sse_event(
							'tool-output-available',
							array(
								'toolCallId' => $data['tool_output_available']['toolCallId'] ?? '',
								'output'     => $data['tool_output_available']['output'] ?? new \stdClass(),
								'dynamic'    => true,
							)
						);
					}
					if ( isset( $data['done'] ) && true === $data['done'] ) {
						if ( $text_started ) {
							$this->emit_sse_event( 'text-end', array( 'id' => $message_id ) );
						}
						$this->emit_sse_done();
					}
					if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
						$this->emit_sse_event( 'error', array( 'error' => $data['error'] ) );
					}
					flush();
				}
			);
		} catch ( \Throwable $throwable ) {
			// A third-party fatal mid-stream must surface as an SSE error event,
			// not a dead spinner. Detail goes to the server log only.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPAIC] Chat stream failed: ' . $throwable->getMessage() );
			$this->emit_sse_event( 'error', array( 'error' => 'Something went wrong. Please try again.' ) );
			$this->emit_sse_done();
			flush();
		}

		if ( '' !== $response_content ) {
			$this->logs->log_message( $conversation_id, 'assistant', $response_content );
		} elseif ( ! empty( $this->card_only_labels ) ) {
			// Card-only reply (no text): keep a row so the transcript shows the bot responded.
			$this->logs->log_message( $conversation_id, 'assistant', implode( "\n", $this->card_only_labels ) );
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
	 * Remember which tool a streamed toolCallId belongs to, so the matching
	 * tool output can be labeled for the transcript (see collect_card_label).
	 */
	private function remember_tool_name( mixed $tool_call_id, mixed $tool_name ): void {
		if ( is_string( $tool_call_id ) && is_string( $tool_name ) ) {
			$this->tool_names_by_call_id[ $tool_call_id ] = $tool_name;
		}
	}

	/**
	 * Collect the transcript placeholder label for a streamed tool output, if
	 * the tool renders as UI (cards, buttons). Labels are deduplicated.
	 *
	 * @param mixed $tool_call_id toolCallId of the tool output.
	 * @param mixed $output Tool result as emitted to the frontend.
	 */
	private function collect_card_label( mixed $tool_call_id, mixed $output ): void {
		$tool_name  = is_string( $tool_call_id ) ? ( $this->tool_names_by_call_id[ $tool_call_id ] ?? '' ) : '';
		$card_label = $this->describe_card_payload( $tool_name, $output );

		if ( null !== $card_label && ! in_array( $card_label, $this->card_only_labels, true ) ) {
			$this->card_only_labels[] = $card_label;
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
	 * Fixed-window transient throttle, keyed per IP and per session. Each key
	 * stores array('count' => n, 'window_started' => unix time); the window
	 * boundary never moves while requests come in (the transient TTL is anchored
	 * to window start, not to the last request), so a shopper pacing below the
	 * limit is never locked out.
	 *
	 * The get/increment/set sequence is racy under concurrent requests, so a
	 * burst can slightly exceed the cap. Acceptable tradeoff: this is abuse
	 * control, not strict quota enforcement.
	 *
	 * @return string|null Shopper-facing error message when throttled, null otherwise.
	 */
	private function check_rate_limit( string $session_id ): ?string {
		$max_requests   = (int) apply_filters( 'wpaic_rate_limit_max_requests', self::RATE_LIMIT_MAX_REQUESTS );
		$window_seconds = (int) apply_filters( 'wpaic_rate_limit_window_seconds', self::RATE_LIMIT_WINDOW_SECONDS );

		if ( $max_requests <= 0 || $window_seconds <= 0 ) {
			return null;
		}

		// Deliberately REMOTE_ADDR-only: forwarded-for headers are client-spoofable,
		// so trusting them would let abusers escape the throttle. On CDN/proxy-fronted
		// sites all visitors share one IP bucket; the wpaic_rate_limit_* filters are
		// the escape hatch to raise limits there.
		$ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$transient_keys = array(
			'wpaic_throttle_ip_' . md5( $ip_address ),
			'wpaic_throttle_session_' . md5( $session_id ),
		);

		$now = time();

		// Read all counters before incrementing any, so a request rejected by one
		// key does not keep inflating the other (e.g. a throttled IP must not
		// burn through the session budget too).
		$windows = array();
		foreach ( $transient_keys as $transient_key ) {
			$window = get_transient( $transient_key );

			$is_valid_window = is_array( $window )
				&& isset( $window['count'], $window['window_started'] )
				&& ( $now - (int) $window['window_started'] ) < $window_seconds;

			if ( ! $is_valid_window ) {
				$window = array(
					'count'          => 0,
					'window_started' => $now,
				);
			}

			if ( (int) $window['count'] >= $max_requests ) {
				return 'You have sent too many messages in a short time. Please wait a few minutes and try again.';
			}

			$windows[ $transient_key ] = $window;
		}

		foreach ( $windows as $transient_key => $window ) {
			$window['count'] = (int) $window['count'] + 1;
			$ttl_remaining   = $window_seconds - ( $now - (int) $window['window_started'] );
			set_transient( $transient_key, $window, max( 1, $ttl_remaining ) );
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
	 * Emit one SSE data event in the AI SDK UI message stream shape. The
	 * frontend depends on the exact event encoding (a `data: ` prefix, `type`
	 * first, then the fields in the given order), so all stream events go
	 * through this single emitter.
	 *
	 * @param array<string, mixed> $fields Event fields, excluding `type`.
	 */
	private function emit_sse_event( string $type, array $fields = array() ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoding handles escaping.
		echo 'data: ' . wp_json_encode( array( 'type' => $type ) + $fields ) . "\n\n";
	}

	/** Emit the SSE stream terminator the AI SDK frontend waits for. */
	private function emit_sse_done(): void {
		echo "data: [DONE]\n\n";
	}

	/**
	 * Start the SSE stream, emit a single error event (the widget already
	 * renders these), and end the request.
	 */
	private function reject_stream_request( string $error_message ): never {
		$this->start_event_stream();

		$this->emit_sse_event( 'error', array( 'error' => $error_message ) );
		$this->emit_sse_done();
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
		// Same guards as the chat stream: without them this route is an open mail
		// relay — any visitor holding the public nonce could wp_mail arbitrary
		// text to arbitrary addresses without limit.
		$availability_error = $this->ensure_chat_is_available();
		if ( is_wp_error( $availability_error ) ) {
			return $availability_error;
		}

		$session_id = $this->resolve_session_id( $request->get_param( 'session_id' ) );
		if ( null === $session_id ) {
			return new WP_Error( 'invalid_session', 'Your chat session is invalid. Please refresh the page and try again.', array( 'status' => 400 ) );
		}

		$throttle_message = $this->check_rate_limit( $session_id );
		if ( null !== $throttle_message ) {
			return new WP_Error( 'rate_limited', $throttle_message, array( 'status' => 429 ) );
		}

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
