<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OpenAI\Client;

class WPAIC_Chat {
	/** @var array<string, mixed> */
	private array $settings;
	/** @var array<string, mixed> */
	private array $page_context = array();
	private ?WPAIC_Tools $tools = null;
	private ?Client $client     = null;
	private WPAIC_License_Manager $license_manager;

	/**
	 * @param array<string, mixed> $page_context
	 */
	public function __construct( array $page_context = array(), ?WPAIC_License_Manager $license_manager = null ) {
		$settings       = get_option( 'wpaic_settings', array() );
		$this->settings = is_array( $settings ) ? $settings : array();
		$this->page_context = ( new WPAIC_Page_Context() )->sanitize( $page_context );
		$this->license_manager = $license_manager ?? new WPAIC_License_Manager();

		if ( wpaic_is_woocommerce_active() ) {
			$this->tools = new WPAIC_Tools();
		}

		if ( ! $this->is_provider_mode() ) {
			$api_key = $this->settings['openai_api_key'] ?? '';
			if ( is_string( $api_key ) && '' !== $api_key ) {
				$this->client = \OpenAI::client( $api_key );
			}
		}
	}

	/**
	 * Check if provider mode is active.
	 */
	public function is_provider_mode(): bool {
		return $this->license_manager->is_provider_url_configured() && $this->license_manager->has_provider_auth();
	}

	/**
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<string, mixed>|WP_Error
	 */
	public function send( array $messages ): array|WP_Error {
		if ( ! $this->is_provider_mode() && null === $this->client ) {
			return new WP_Error( 'no_api_key', 'OpenAI API key not configured', array( 'status' => 500 ) );
		}

		if ( $this->is_provider_mode() ) {
			return $this->send_via_provider( $messages );
		}

		$model = $this->settings['model'] ?? 'gpt-4o-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-4o-mini';
		}

		try {
			$params = array(
				'model'    => $model,
				'messages' => $this->format_messages( $messages ),
			);

			$tools = $this->get_tool_definitions();
			if ( ! empty( $tools ) ) {
				$params['tools'] = $tools;
			}

			$response = $this->client->chat()->create( $params );

			$choice = $response->choices[0];

			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- OpenAI SDK property.
			if ( 'tool_calls' === $choice->finishReason ) {
				return $this->handle_tool_calls( $messages, $choice->message );
			}

			return array( 'content' => $choice->message->content ?? '' );

		} catch ( \Exception $e ) {
			return new WP_Error( 'openai_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * Non-streaming send via provider. Collects full response from SSE stream.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<string, mixed>|WP_Error
	 */
	private function send_via_provider( array $messages ): array|WP_Error {
		$collected_content = '';
		$error             = null;

		$this->send_stream_via_provider(
			$messages,
			function ( array $data ) use ( &$collected_content, &$error ): void {
				if ( isset( $data['content'] ) && is_string( $data['content'] ) ) {
					$collected_content .= $data['content'];
				}
				if ( isset( $data['error'] ) && is_string( $data['error'] ) ) {
					$error = $data['error'];
				}
			}
		);

		if ( null !== $error ) {
			return new WP_Error( 'provider_error', $error, array( 'status' => 500 ) );
		}

		return array( 'content' => $collected_content );
	}

	/**
	 * @param array<int, array<string, mixed>> $messages
	 * @param callable(array<string, mixed>): void $on_chunk
	 */
	public function send_stream( array $messages, callable $on_chunk ): void {
		if ( $this->is_provider_mode() ) {
			$this->send_stream_via_provider( $messages, $on_chunk );
			return;
		}

		if ( null === $this->client ) {
			$on_chunk( array( 'error' => 'Chat is currently unavailable. Please try again later.' ) );
			return;
		}

		$model = $this->settings['model'] ?? 'gpt-4o-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-4o-mini';
		}

		try {
			$params = array(
				'model'    => $model,
				'messages' => $this->format_messages( $messages ),
			);

			$tools = $this->get_tool_definitions();
			if ( ! empty( $tools ) ) {
				$params['tools'] = $tools;
			}

			$stream = $this->client->chat()->createStreamed( $params );

			/** @var array<int, array{id: string|null, type: string, function: array{name: string, arguments: string}, started: bool}> $tool_calls */
			$tool_calls = array();

			foreach ( $stream as $response ) {
				$delta = $response->choices[0]->delta;

				if ( $this->should_emit_stream_content( $delta->content ?? null ) ) {
					$on_chunk( array( 'content' => $delta->content ) );
				}

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- OpenAI SDK property.
				if ( $delta->toolCalls ) {
					// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- OpenAI SDK property.
					foreach ( $delta->toolCalls as $tc ) {
						if ( ! isset( $tool_calls[ $tc->index ] ) ) {
							$tool_calls[ $tc->index ] = array(
								'id'       => $tc->id,
								'type'     => 'function',
								'function' => array(
									'name'      => '',
									'arguments' => '',
								),
								'started'  => false,
							);
						}
						if ( $tc->function->name ) {
							$tool_calls[ $tc->index ]['function']['name'] = $tc->function->name;
							if ( ! $tool_calls[ $tc->index ]['started'] ) {
								$tool_calls[ $tc->index ]['started'] = true;
								$on_chunk(
									array(
										'tool_input_start' => array(
											'toolCallId' => $tool_calls[ $tc->index ]['id'],
											'toolName'   => $tc->function->name,
										),
									)
								);
							}
						}
						if ( $tc->function->arguments ) {
							$tool_calls[ $tc->index ]['function']['arguments'] .= $tc->function->arguments;
							$on_chunk(
								array(
									'tool_input_delta' => array(
										'toolCallId'     => $tool_calls[ $tc->index ]['id'],
										'inputTextDelta' => $tc->function->arguments,
									),
								)
							);
						}
					}
				}

				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- OpenAI SDK property.
				if ( 'tool_calls' === $response->choices[0]->finishReason ) {
					$this->handle_tool_calls_stream( $messages, $tool_calls, $on_chunk );
					return;
				}
			}

			$on_chunk( array( 'done' => true ) );

		} catch ( \Exception $e ) {
			$on_chunk( array( 'error' => $e->getMessage() ) );
		}
	}

	/**
	 * Stream chat completion via provider endpoint, handling tool calls locally.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 * @param callable(array<string, mixed>): void $on_chunk
	 */
	private function send_stream_via_provider( array $messages, callable $on_chunk ): void {
		$formatted_messages = $this->format_messages( $messages );
		$tools              = $this->get_tool_definitions();
		$model              = $this->settings['model'] ?? 'gpt-4o-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-4o-mini';
		}

		$this->provider_completion_loop( $formatted_messages, $tools, $model, $on_chunk );
	}

	private const MAX_PROVIDER_ITERATIONS = 10;

	/**
	 * Provider completion loop: sends to provider, parses SSE, handles tool calls, recurses.
	 *
	 * @param array<int, array<string, mixed>> $formatted_messages Already-formatted messages with system prompt.
	 * @param array<int, array<string, mixed>> $tools Tool definitions.
	 * @param string $model Model name.
	 * @param callable(array<string, mixed>): void $on_chunk Frontend chunk callback.
	 * @param int $iteration Current iteration count (guards against infinite recursion).
	 */
	private function provider_completion_loop( array $formatted_messages, array $tools, string $model, callable $on_chunk, int $iteration = 0 ): void {
		if ( $iteration >= self::MAX_PROVIDER_ITERATIONS ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPAIC] Provider completion loop exceeded max iterations (' . self::MAX_PROVIDER_ITERATIONS . ')' );
			$on_chunk( array( 'error' => 'The request required too many processing steps. Please try a simpler question.' ) );
			return;
		}
		$provider_url = $this->license_manager->get_provider_url();

		$body = array(
			'messages' => $formatted_messages,
			'model'    => $model,
		);
		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$provider_headers = $this->license_manager->get_provider_request_headers( $body );
		if ( empty( $provider_headers ) ) {
			$on_chunk( array( 'error' => 'Provider authentication is not available for this site.' ) );
			return;
		}

		$result = $this->stream_from_provider( $provider_url, $provider_headers, $body, $on_chunk );

		if ( isset( $result['error'] ) ) {
			$on_chunk( array( 'error' => $result['error'] ) );
			return;
		}

		if ( ! empty( $result['tool_calls'] ) ) {
			$tool_calls_for_messages = array_map(
				function ( $tc ) {
					return array(
						'id'       => $tc['id'],
						'type'     => $tc['type'],
						'function' => $tc['function'],
					);
				},
				array_values( $result['tool_calls'] )
			);

			$formatted_messages[] = array(
				'role'       => 'assistant',
				'content'    => null,
				'tool_calls' => $tool_calls_for_messages,
			);

			foreach ( $result['tool_calls'] as $tc ) {
				$args        = json_decode( $tc['function']['arguments'], true );
				$parsed_args = is_array( $args ) ? $args : array();

				$on_chunk(
					array(
						'tool_input_available' => array(
							'toolCallId' => $tc['id'] ?? '',
							'toolName'   => $tc['function']['name'],
							'input'      => $parsed_args,
						),
					)
				);

				$tool_result = $this->execute_tool( $tc['function']['name'], $parsed_args );

				$on_chunk(
					array(
						'tool_output_available' => array(
							'toolCallId' => $tc['id'] ?? '',
							'output'     => $tool_result,
						),
					)
				);

				$formatted_messages[] = array(
					'role'         => 'tool',
					'tool_call_id' => $tc['id'] ?? '',
					'content'      => (string) wp_json_encode( $tool_result ),
				);
			}

			$this->provider_completion_loop( $formatted_messages, $tools, $model, $on_chunk, $iteration + 1 );
			return;
		}

		$on_chunk( array( 'done' => true ) );
	}

	/**
	 * Open HTTP stream to provider, parse SSE lines, emit text chunks and collect tool calls.
	 *
	 * @param string $url Provider endpoint URL.
	 * @param array<string, string>|string $request_auth Provider auth headers.
	 * @param array<string, mixed> $body Request body.
	 * @param callable(array<string, mixed>): void $on_chunk Frontend callback.
	 * @return array{error?: string, tool_calls?: array<int, array<string, mixed>>} Result.
	 */
	private function stream_from_provider( string $url, array|string $request_auth, array $body, callable $on_chunk ): array {
		$headers = "Content-Type: application/json\r\n";

		if ( is_array( $request_auth ) ) {
			foreach ( $request_auth as $name => $value ) {
				$headers .= "{$name}: {$value}\r\n";
			}
		} else {
			$headers .= "X-WPAIP-Site-Key: {$request_auth}\r\n";
		}

		$context = stream_context_create(
			array(
				'http' => array(
					'method'        => 'POST',
					'header'        => $headers,
					'content'       => wp_json_encode( $body ),
					'timeout'       => 120,
					'ignore_errors' => true,
				),
				'ssl'  => array(
					'verify_peer' => true,
				),
			)
		);

		$stream = @fopen( $url, 'r', false, $context ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $stream ) {
			$last_error = error_get_last();
			$detail     = $last_error['message'] ?? 'unknown error';
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[WPAIC] Provider connection failed: {$detail} | URL: {$url}" );
			return array( 'error' => "Failed to connect to provider: {$detail}" );
		}

		$response_meta = stream_get_meta_data( $stream );
		$http_status   = 0;
		if ( ! empty( $response_meta['wrapper_data'] ) && is_array( $response_meta['wrapper_data'] ) ) {
			foreach ( $response_meta['wrapper_data'] as $header ) {
				if ( preg_match( '/^HTTP\/\S+\s+(\d{3})/', $header, $matches ) ) {
					$http_status = (int) $matches[1];
				}
			}
		}

		if ( $http_status >= 400 ) {
			$error_body = stream_get_contents( $stream );
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "[WPAIC] Provider HTTP {$http_status} | body: {$error_body}" );
			return array(
				'error' => $this->get_provider_http_error_message(
					$http_status,
					is_string( $error_body ) ? $error_body : ''
				),
			);
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "[WPAIC] Provider stream opened, HTTP {$http_status}. Reading..." );

		/** @var array<int, array{id: string|null, type: string, function: array{name: string, arguments: string}, started: bool}> $tool_calls */
		$tool_calls    = array();
		$finish_reason = '';
		$buffer        = '';
		$total_bytes   = 0;

		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( false === $chunk ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[WPAIC] fread returned false, breaking' );
				break;
			}
			if ( '' === $chunk ) {
				continue;
			}
			$total_bytes += strlen( $chunk );
			$buffer      .= $chunk;

			while ( false !== ( $newline_pos = strpos( $buffer, "\n" ) ) ) {
				$line   = substr( $buffer, 0, $newline_pos );
				$buffer = substr( $buffer, $newline_pos + 1 );
				$line   = trim( $line );

				if ( '' === $line ) {
					continue;
				}

				if ( ! str_starts_with( $line, 'data: ' ) ) {
					continue;
				}

				$data_str = substr( $line, 6 );

				if ( '[DONE]' === $data_str ) {
					break 2;
				}

				$data = json_decode( $data_str, true );
				if ( ! is_array( $data ) ) {
					continue;
				}

				if ( isset( $data['error'] ) ) {
					fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					$error_message = is_array( $data['error'] ) ? ( $data['error']['message'] ?? 'Provider error' ) : (string) $data['error'];
					return array( 'error' => $error_message );
				}

				$choices = $data['choices'] ?? array();
				if ( empty( $choices ) ) {
					continue;
				}

				$choice = $choices[0];
				$delta  = $choice['delta'] ?? array();

				if ( $this->should_emit_stream_content( $delta['content'] ?? null ) ) {
					$on_chunk( array( 'content' => $delta['content'] ) );
				}

				if ( ! empty( $delta['tool_calls'] ) ) {
					foreach ( $delta['tool_calls'] as $tc_delta ) {
						$index = $tc_delta['index'] ?? 0;
						if ( ! isset( $tool_calls[ $index ] ) ) {
							$tool_calls[ $index ] = array(
								'id'       => $tc_delta['id'] ?? null,
								'type'     => 'function',
								'function' => array(
									'name'      => '',
									'arguments' => '',
								),
								'started'  => false,
							);
						}
						if ( ! empty( $tc_delta['function']['name'] ) ) {
							$tool_calls[ $index ]['function']['name'] = $tc_delta['function']['name'];
							if ( ! $tool_calls[ $index ]['started'] ) {
								$tool_calls[ $index ]['started'] = true;
								$on_chunk(
									array(
										'tool_input_start' => array(
											'toolCallId' => $tool_calls[ $index ]['id'],
											'toolName'   => $tc_delta['function']['name'],
										),
									)
								);
							}
						}
						if ( ! empty( $tc_delta['function']['arguments'] ) ) {
							$tool_calls[ $index ]['function']['arguments'] .= $tc_delta['function']['arguments'];
							$on_chunk(
								array(
									'tool_input_delta' => array(
										'toolCallId'     => $tool_calls[ $index ]['id'],
										'inputTextDelta' => $tc_delta['function']['arguments'],
									),
								)
							);
						}
					}
				}

				if ( ! empty( $choice['finish_reason'] ) ) {
					$finish_reason = $choice['finish_reason'];
				}
			}
		}

		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "[WPAIC] Stream finished. Total bytes read: {$total_bytes}, finish_reason: {$finish_reason}, remaining buffer: " . substr( $buffer, 0, 200 ) );

		if ( 'tool_calls' === $finish_reason && ! empty( $tool_calls ) ) {
			return array( 'tool_calls' => $tool_calls );
		}

		return array();
	}

	private function get_provider_http_error_message( int $http_status, string $error_body ): string {
		$decoded = json_decode( $error_body, true );
		if ( is_array( $decoded ) ) {
			$message = $decoded['message'] ?? null;
			if ( is_string( $message ) && '' !== trim( $message ) ) {
				return $message;
			}

			$error = $decoded['error'] ?? null;
			if ( is_array( $error ) && isset( $error['message'] ) && is_string( $error['message'] ) && '' !== trim( $error['message'] ) ) {
				return $error['message'];
			}
		}

		return "Provider returned HTTP {$http_status}";
	}

	/**
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<int, array<string, mixed>>
	 */
	private function format_messages( array $messages ): array {
		$formatted = array(
			array(
				'role'    => 'system',
				'content' => $this->get_system_prompt(),
			),
		);

		foreach ( $messages as $msg ) {
			if ( isset( $msg['tool_calls'] ) ) {
				$formatted[] = array(
					'role'       => 'assistant',
					'content'    => $msg['content'] ?? null,
					'tool_calls' => $msg['tool_calls'],
				);
			} elseif ( isset( $msg['role'] ) && 'tool' === $msg['role'] ) {
				$formatted[] = array(
					'role'         => 'tool',
					'tool_call_id' => $msg['tool_call_id'] ?? '',
					'content'      => $msg['content'] ?? '',
				);
			} else {
				$formatted[] = array(
					'role'    => $msg['role'] ?? 'user',
					'content' => $msg['content'] ?? '',
				);
			}
		}

		return $formatted;
	}

	private function should_emit_stream_content( mixed $content ): bool {
		return is_string( $content ) && '' !== $content;
	}

	private function get_system_prompt(): string {
		$custom_prompt                  = $this->settings['system_prompt'] ?? '';
		$faq_section                    = $this->get_faq_instruction();
		$page_context                   = $this->get_page_context_instruction() . $this->get_current_page_context_summary();
		$woocommerce_active             = wpaic_is_woocommerce_active();
		$include_tool_response_guidance = $woocommerce_active;

		if ( is_string( $custom_prompt ) && '' !== trim( $custom_prompt ) ) {
			$base_prompt = $custom_prompt;
		} else {
			$site_name = get_bloginfo( 'name' );

			if ( $woocommerce_active ) {
				$base_prompt = "You are a helpful assistant for {$site_name}. Help customers find products and answer questions. Be friendly and concise. Use tools to search products when asked.";
			} else {
				$base_prompt = "You are a helpful assistant for {$site_name}. Answer questions and help visitors. Be friendly and concise.";
			}
		}

		$prompt = $base_prompt . $this->get_tone_of_voice_instruction() . $faq_section;

		if ( $include_tool_response_guidance ) {
			$prompt .= $this->get_tool_response_instruction();
			$prompt .= $this->get_guided_shopping_instruction();
			$prompt .= $this->get_off_topic_redirection_instruction();
		} else {
			$prompt .= $this->get_non_woocommerce_instruction();
		}

		return $prompt . $page_context . $this->get_handoff_instruction() . $this->get_language_instruction() . $this->get_content_index_instruction();
	}

	private function get_tone_of_voice_instruction(): string {
		$tone_of_voice = $this->settings['tone_of_voice'] ?? 'neutral';
		if ( ! is_string( $tone_of_voice ) ) {
			return '';
		}

		switch ( $tone_of_voice ) {
			case 'friendly':
				return ' Adjust only tone and wording. Use a friendly, warm, conversational, approachable tone.';
			case 'professional':
				return ' Adjust only tone and wording. Use a professional tone that is polished, structured, courteous, clear, and efficient.';
			case 'enthusiastic':
				return ' Adjust only tone and wording. Use an enthusiastic, upbeat, positive tone, but do not become pushy or more proactive than the user\'s request requires.';
			default:
				return '';
		}
	}

	/**
	 * Get FAQ knowledge for system prompt.
	 *
	 * @return string FAQ instruction or empty string.
	 */
	private function get_faq_instruction(): string {
		global $wpdb;
		$faqs_table = $wpdb->prefix . 'wpaic_faqs';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$faqs = $wpdb->get_results( "SELECT question, answer FROM $faqs_table ORDER BY id ASC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $faqs ) ) {
			return '';
		}

		$faq_text = "\n\nYou have the following FAQ knowledge to help answer customer questions. Use this information when relevant, but phrase answers naturally in your own words:\n";

		foreach ( $faqs as $faq ) {
			$faq_text .= "\nQ: " . $faq->question . "\nA: " . $faq->answer . "\n";
		}

		return $faq_text;
	}

	private function get_tool_response_instruction(): string {
		return ' When presenting product search or comparison results, provide ONLY a single short sentence intro (max 10 words) that relates to the query. Example: "Here are some red shoes:" - NEVER list product names, prices, or details in your text response. The product cards will show all details. Your text should be a brief intro only, not a summary of results. For current cart questions, use get_cart_contents and answer directly from its totals and items in plain text. If no results found, explain briefly. CHECKOUT INTENT: When the user signals checkout intent ("checkout", "pay now", "complete purchase", "ready to buy", "go to cart"), call get_checkout_action and reply with at most one short sentence (max 10 words) confirming the action. Do NOT type out the checkout or cart URL — the UI renders a button. STRICT PRODUCT GROUNDING: When answering questions about a specific product (specs, materials, dimensions, features, compatibility, included items, warranty, brand details, etc.), state ONLY facts present in the tool output (name, price, description, attributes, categories, tags, stock, and other returned meta). The merchant-written description is allowed. If a requested attribute is not in the tool output, say explicitly that you do not have that information and offer to help another way. NEVER fill gaps using general or brand knowledge (e.g. "Rolex typically uses...", "this model usually has..."), and NEVER guess, infer, or hedge with "typically", "usually", "commonly", or similar. Do not invent case sizes, materials, movements, capacities, measurements, or any spec not in the data. STRICT SHIPPING GROUNDING: For any shipping question (cost, methods, regions, delivery time), first call get_shipping_info for site-wide policy and/or check the product short_description for per-product shipping notes. State only what those sources contain. NEVER invent delivery durations like "3 to 7 business days" or generic estimates; WooCommerce does not store processing times by default, so if no duration is in the data, say so explicitly. If the tool returns has_shipping_configured=false, tell the customer shipping policy is not configured on this site and offer to connect them with a human via support handoff if available.';
	}

	private function get_guided_shopping_instruction(): string {
		return ' For broad shopping-discovery asks (for example: "show me products" or "what do you sell?"), call get_categories first. List only the top 3-5 categories sorted by highest count, then ask one short clarifying question. Offer the full category list only if requested. Do not call search_products until the user gives direction (such as category, use case, budget, or audience), unless their request is already specific. For "what do you sell?", after category guidance you may use search_site_content and get_page_content for brief business context. If context is missing, say so and do not invent claims. Keep this guidance supportive and non-pushy. GIFT AND RECOMMENDATION QUERIES: When the user asks for gift ideas or recommendations for a person (e.g. "gift for my husband", "something for my mom", "present for a kid"), do not stop at category names. After picking 2-3 relevant categories, call search_products once per category (limit 2-3, using the category slug) so each category is paired with actual product picks. Present the products via the cards; keep your text to the same brief intro rule.';
	}

	private function get_off_topic_redirection_instruction(): string {
		return ' OFF-TOPIC REDIRECTION: After politely answering or declining any non-shopping question, ALWAYS end with a short, natural shopping-related follow-up that\'s relevant to the user\'s apparent context. Keep it conversational, not pushy or templated.';
	}

	private function get_non_woocommerce_instruction(): string {
		return ' WooCommerce product tools are unavailable. Do not pretend to browse products or categories. Stay generally helpful for non-product questions.';
	}

	private function get_page_context_instruction(): string {
		return ' If current page context is available, use it only when it materially helps the answer. If the current page is a product and product_id is present, use get_product_details when the user asks about "this product" or the current product. If the current page is a non-product singular page and post_id is present, use get_page_content when the user is asking about the current page. If the current page is a product category and term_slug is present, use search_products with the category filter when you need products from the current category. If the current page is a product tag, use the tag metadata as context and use search_products with the tag name as the search query when you need matching products. If the current page is cart or checkout and the user asks about cart items or totals, use get_cart_contents. If the user is asking about another page or the current page context is not enough, use search_site_content and then get_page_content.';
	}

	private function get_current_page_context_summary(): string {
		$service = new WPAIC_Page_Context();
		return $service->to_prompt_summary( $this->page_context );
	}

	private function get_handoff_instruction(): string {
		if ( ! $this->is_handoff_enabled() ) {
			return ' If a customer asks to speak to a human or escalate to support, apologize and explain that human support escalation is not currently available, but offer to help them with their question.';
		}

		$fields_to_collect = array( 'name', 'email address' );
		$handoff_fields    = $this->settings['handoff_fields'] ?? array();
		if ( is_array( $handoff_fields ) ) {
			$field_labels = array(
				'phone_number'    => 'phone number',
				'company'         => 'company name',
				'order_number'    => 'order number',
				'request_message' => 'a message describing their issue',
			);
			foreach ( $handoff_fields as $field ) {
				if ( isset( $field_labels[ $field ] ) ) {
					$fields_to_collect[] = $field_labels[ $field ];
				}
			}
		}

		$fields_list = implode( ', ', $fields_to_collect );

		return " When a customer asks to speak to a human, talk to support, or escalate their issue, collect the following information: {$fields_list}. Once you have all required info, use the create_handoff_request tool to submit the request.";
	}

	private function get_content_index_instruction(): string {
		$content_index = new WPAIC_Content_Index();
		$status        = $content_index->get_index_status();
		if ( ! $status['exists'] ) {
			return '';
		}
		return ' You have access to the website\'s pages and posts. When users ask about policies, contact info, company details, or other non-product topics, use the search_site_content tool. If a snippet doesn\'t contain enough detail, use get_page_content to read the full page. Answer naturally from the content and cite the source page.';
	}

	private function get_language_instruction(): string {
		$language = $this->settings['language'] ?? 'auto';
		if ( ! is_string( $language ) || 'auto' === $language ) {
			return ' Always respond in the same language the user writes in.';
		}

		$language_names = array(
			'en' => 'English',
			'es' => 'Spanish',
			'fr' => 'French',
			'de' => 'German',
			'it' => 'Italian',
			'pt' => 'Portuguese',
			'nl' => 'Dutch',
			'ru' => 'Russian',
			'zh' => 'Chinese',
			'ja' => 'Japanese',
			'ko' => 'Korean',
			'ar' => 'Arabic',
		);

		$lang_name = $language_names[ $language ] ?? $language;
		return " Always respond in {$lang_name}.";
	}

	/**
	 * Check if handoff feature is enabled.
	 *
	 * @return bool True if handoff is enabled.
	 */
	private function is_handoff_enabled(): bool {
		return ! empty( $this->settings['handoff_enabled'] );
	}

	/**
	 * Get tool definition for custom data querying, or null if no sources exist.
	 *
	 * @return array<string, mixed>|null Tool definition or null.
	 */
	private function get_custom_data_tool_definition(): ?array {
		$sources = WPAIC_Tools::get_data_sources();

		if ( empty( $sources ) ) {
			return null;
		}

		$source_descriptions = array();
		foreach ( $sources as $source ) {
			$cols_str              = implode( ', ', $source['columns'] );
			$source_descriptions[] = "- '{$source['name']}': {$source['description']} (columns: $cols_str)";
		}

		$description = "Query custom data uploaded by the store owner. Available sources:\n" . implode( "\n", $source_descriptions );

		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'query_custom_data',
				'description' => $description,
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'source_name' => array(
							'type'        => 'string',
							'description' => 'Name of the data source to query',
							'enum'        => array_column( $sources, 'name' ),
						),
						'query'       => array(
							'type'        => 'string',
							'description' => 'Search query to filter results (optional, empty returns all)',
						),
					),
					'required'   => array( 'source_name' ),
				),
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_tool_definitions(): array {
		$tools = array();

		if ( wpaic_is_woocommerce_active() ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'search_products',
					'description' => 'Search products by keyword, category, or price range',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'search'    => array(
								'type'        => 'string',
								'description' => 'Search keyword',
							),
							'category'  => array(
								'type'        => 'string',
								'description' => 'Category slug to filter',
							),
							'min_price' => array(
								'type'        => 'number',
								'description' => 'Min price',
							),
							'max_price' => array(
								'type'        => 'number',
								'description' => 'Max price',
							),
							'limit'     => array(
								'type'        => 'integer',
								'description' => 'Max results (default 10)',
							),
						),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_product_details',
					'description' => 'Get detailed product info by ID',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'product_id' => array(
								'type'        => 'integer',
								'description' => 'Product ID',
							),
						),
						'required'   => array( 'product_id' ),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_categories',
					'description' => 'Get all product categories',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_cart_contents',
					'description' => 'Get the current customer cart contents and totals. Use when they ask what is in their cart or what their total is.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'compare_products',
					'description' => 'Compare multiple products side by side. Use when user wants to compare features, prices, or specs of 2-4 products.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'product_ids' => array(
								'type'        => 'array',
								'items'       => array( 'type' => 'integer' ),
								'description' => 'Array of product IDs to compare (2-4 products)',
								'minItems'    => 2,
								'maxItems'    => 4,
							),
						),
						'required'   => array( 'product_ids' ),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_order_status',
					'description' => 'Look up order status by order number and email. Use when customer wants to check their order status or track a shipment.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'order_number' => array(
								'type'        => 'string',
								'description' => 'The order number or ID',
							),
							'email'        => array(
								'type'        => 'string',
								'description' => 'Customer billing email for verification',
							),
						),
						'required'   => array( 'order_number', 'email' ),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_checkout_action',
					'description' => 'Return the WooCommerce checkout and cart URLs so the chat UI can render a styled Checkout CTA button. Call this whenever the user expresses intent to check out, pay, complete their purchase, or view their cart (for example: "I want to checkout now", "take me to checkout", "pay now", "show my cart", "go to cart"). Do not describe the URLs in your text response; the UI renders the button. Keep your text reply to one short sentence (max 10 words) confirming the action.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'get_shipping_info',
					'description' => 'Get site-wide shipping zones, methods, and costs configured in WooCommerce. Use when the customer asks about shipping cost, shipping options, where the store ships, or shipping times. Returns only what is actually configured — never invent delivery times or costs not in the response.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => new \stdClass(),
					),
				),
			);
		}

		if ( $this->is_handoff_enabled() ) {
			$handoff_properties = array(
				'customer_name'        => array(
					'type'        => 'string',
					'description' => 'Customer name',
				),
				'customer_email'       => array(
					'type'        => 'string',
					'description' => 'Customer email address for support to contact them',
				),
				'conversation_summary' => array(
					'type'        => 'string',
					'description' => 'Brief summary of the conversation and what help the customer needs',
				),
			);
			$handoff_required = array( 'customer_name', 'customer_email', 'conversation_summary' );

			$handoff_fields = $this->settings['handoff_fields'] ?? array();
			if ( is_array( $handoff_fields ) ) {
				$optional_field_definitions = array(
					'phone_number'    => array(
						'type'        => 'string',
						'description' => 'Customer phone number',
					),
					'company'         => array(
						'type'        => 'string',
						'description' => 'Customer company name',
					),
					'order_number'    => array(
						'type'        => 'string',
						'description' => 'Related order number',
					),
					'request_message' => array(
						'type'        => 'string',
						'description' => 'Customer message describing their issue',
					),
				);

				foreach ( $handoff_fields as $field ) {
					if ( isset( $optional_field_definitions[ $field ] ) ) {
						$handoff_properties[ $field ] = $optional_field_definitions[ $field ];
						$handoff_required[]           = $field;
					}
				}
			}

			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_handoff_request',
					'description' => 'Create a support request to hand off to a human agent. Use when customer explicitly asks to speak to a human, talk to support, or escalate. Collect all required fields before calling this tool.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => $handoff_properties,
						'required'   => $handoff_required,
					),
				),
			);
		}

		$custom_data_tool = $this->get_custom_data_tool_definition();
		if ( null !== $custom_data_tool ) {
			$tools[] = $custom_data_tool;
		}

		$tools[] = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'search_site_content',
				'description' => 'Search the website\'s pages, posts, and other content. Use when the user asks about policies, contact info, FAQs, company info, or any non-product question.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'Search query',
						),
					),
					'required'   => array( 'query' ),
				),
			),
		);

		$tools[] = array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'get_page_content',
				'description' => 'Get the full text content of a specific page or post. Use when search_site_content returned a relevant result but the snippet doesn\'t contain enough detail to answer the user\'s question.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'The post ID from search_site_content results',
						),
					),
					'required'   => array( 'post_id' ),
				),
			),
		);

		return $tools;
	}

	/**
	 * @param array<int, array<string, mixed>> $messages
	 * @param object $assistant_message
	 * @return array<string, mixed>|WP_Error
	 */
	private function handle_tool_calls( array $messages, object $assistant_message ): array|WP_Error {
		/** @var array<int, array{id: string, type: string, function: array{name: string, arguments: string}}> $tool_calls_arr */
		$tool_calls_arr = array();

		/** @var object{id: string, function: object{name: string, arguments: string}} $tc */
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- OpenAI SDK property.
		foreach ( $assistant_message->toolCalls as $tc ) {
			$tool_calls_arr[] = array(
				'id'       => $tc->id,
				'type'     => 'function',
				'function' => array(
					'name'      => $tc->function->name,
					'arguments' => $tc->function->arguments,
				),
			);
		}

		$messages[] = array(
			'role'       => 'assistant',
			'content'    => $assistant_message->content ?? null,
			'tool_calls' => $tool_calls_arr,
		);

		foreach ( $tool_calls_arr as $tc ) {
			$args       = json_decode( $tc['function']['arguments'], true );
			$result     = $this->execute_tool( $tc['function']['name'], is_array( $args ) ? $args : array() );
			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tc['id'],
				'content'      => (string) wp_json_encode( $result ),
			);
		}

		if ( null === $this->client ) {
			return new WP_Error( 'no_api_key', 'OpenAI API key not configured', array( 'status' => 500 ) );
		}

		$model = $this->settings['model'] ?? 'gpt-4o-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-4o-mini';
		}

		try {
			$response = $this->client->chat()->create(
				array(
					'model'    => $model,
					'messages' => $this->format_messages( $messages ),
				)
			);

			return array( 'content' => $response->choices[0]->message->content ?? '' );
		} catch ( \Exception $e ) {
			return new WP_Error( 'openai_error', $e->getMessage(), array( 'status' => 500 ) );
		}
	}

	/**
	 * @param array<int, array<string, mixed>> $messages
	 * @param array<int, array{id: string|null, type: string, function: array{name: string, arguments: string}, started?: bool}> $tool_calls
	 * @param callable(array<string, mixed>): void $on_chunk
	 */
	private function handle_tool_calls_stream( array $messages, array $tool_calls, callable $on_chunk ): void {
		$tool_calls_for_messages = array_map(
			function ( $tc ) {
				return array(
					'id'       => $tc['id'],
					'type'     => $tc['type'],
					'function' => $tc['function'],
				);
			},
			array_values( $tool_calls )
		);

		$messages[] = array(
			'role'       => 'assistant',
			'content'    => null,
			'tool_calls' => $tool_calls_for_messages,
		);

		foreach ( $tool_calls as $tc ) {
			$args        = json_decode( $tc['function']['arguments'], true );
			$parsed_args = is_array( $args ) ? $args : array();

			$on_chunk(
				array(
					'tool_input_available' => array(
						'toolCallId' => $tc['id'] ?? '',
						'toolName'   => $tc['function']['name'],
						'input'      => $parsed_args,
					),
				)
			);

			$result = $this->execute_tool( $tc['function']['name'], $parsed_args );

			$on_chunk(
				array(
					'tool_output_available' => array(
						'toolCallId' => $tc['id'] ?? '',
						'output'     => $result,
					),
				)
			);

			$messages[] = array(
				'role'         => 'tool',
				'tool_call_id' => $tc['id'] ?? '',
				'content'      => (string) wp_json_encode( $result ),
			);
		}

		if ( null === $this->client ) {
			$on_chunk( array( 'error' => 'Chat is currently unavailable. Please try again later.' ) );
			return;
		}

		$model = $this->settings['model'] ?? 'gpt-4o-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-4o-mini';
		}

		try {
			$stream = $this->client->chat()->createStreamed(
				array(
					'model'    => $model,
					'messages' => $this->format_messages( $messages ),
				)
			);

			foreach ( $stream as $response ) {
				if ( $this->should_emit_stream_content( $response->choices[0]->delta->content ?? null ) ) {
					$on_chunk( array( 'content' => $response->choices[0]->delta->content ) );
				}
			}

			$on_chunk( array( 'done' => true ) );
		} catch ( \Exception $e ) {
			$on_chunk( array( 'error' => 'Something went wrong. Please try again.' ) );
		}
	}

	/**
	 * @param string $name
	 * @param array<string, mixed> $arguments
	 * @return array<string, mixed>|array<int, array<string, mixed>>|null
	 */
	private function execute_tool( string $name, array $arguments ): array|null {
		// Handoff and custom data tools work without WooCommerce
		if ( 'create_handoff_request' === $name ) {
			$tools = new WPAIC_Tools();
			return $tools->create_handoff_request( $arguments );
		}

		if ( 'query_custom_data' === $name ) {
			$tools = new WPAIC_Tools();
			return $tools->query_custom_data( $arguments );
		}

		if ( 'search_site_content' === $name ) {
			$tools = new WPAIC_Tools();
			return $tools->search_site_content( $arguments );
		}

		if ( 'get_page_content' === $name ) {
			$tools = new WPAIC_Tools();
			return $tools->get_page_content( $arguments );
		}

		if ( null === $this->tools ) {
			return array( 'error' => 'Product tools unavailable' );
		}

		return match ( $name ) {
			'search_products' => $this->tools->search_products( $arguments ),
			'get_product_details' => $this->tools->get_product_details( (int) ( $arguments['product_id'] ?? 0 ) ),
			'get_categories' => $this->tools->get_categories(),
			'get_cart_contents' => $this->tools->get_cart_contents(),
			'get_checkout_action' => $this->tools->get_checkout_action(),
			'compare_products' => $this->tools->compare_products( isset( $arguments['product_ids'] ) && is_array( $arguments['product_ids'] ) ? $arguments['product_ids'] : array() ),
			'get_order_status' => $this->tools->get_order_status( $arguments ),
			'get_shipping_info' => $this->tools->get_shipping_info(),
			default => array( 'error' => 'Unknown tool' ),
		};
	}
}
