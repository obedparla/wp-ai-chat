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
	private ?WPAIC_Product_Tools $product_tools = null;
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
			$this->tools         = new WPAIC_Tools();
			$this->product_tools = new WPAIC_Product_Tools();
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
	 * Reasoning effort for a model. Returns null for models that don't support it,
	 * so the param is omitted rather than sent to a model that would reject it.
	 * Medium effort balances chat latency and cost against tool use quality.
	 */
	private function reasoning_effort_for_model( string $model ): ?string {
		return match ( $model ) {
			'gpt-5-mini' => 'medium',
			default      => null,
		};
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

		$model = $this->settings['model'] ?? 'gpt-5-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-5-mini';
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

			$effort = $this->reasoning_effort_for_model( $model );
			if ( null !== $effort ) {
				$params['reasoning_effort'] = $effort;
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

		$model = $this->settings['model'] ?? 'gpt-5-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-5-mini';
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

			$effort = $this->reasoning_effort_for_model( $model );
			if ( null !== $effort ) {
				$params['reasoning_effort'] = $effort;
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
		$input        = $this->build_responses_input( $messages );
		$instructions = $this->get_system_prompt();
		$tools        = $this->to_responses_tools( $this->get_tool_definitions() );

		$this->provider_completion_loop( $input, $tools, $instructions, $on_chunk );
	}

	/**
	 * Convert conversation history into Responses API `input` items. The system
	 * prompt is sent separately as `instructions`, so it is not included here.
	 *
	 * @param array<int, array<string, mixed>> $messages
	 * @return array<int, array<string, mixed>>
	 */
	private function build_responses_input( array $messages ): array {
		$input = array();
		foreach ( $messages as $msg ) {
			$role = isset( $msg['role'] ) && is_string( $msg['role'] ) ? $msg['role'] : 'user';
			if ( 'assistant' !== $role ) {
				$role = 'user';
			}
			$input[] = array(
				'role'    => $role,
				'content' => (string) ( $msg['content'] ?? '' ),
			);
		}
		return $input;
	}

	/**
	 * Flatten Chat-Completions tool definitions into the Responses API tool shape
	 * ({type, name, description, parameters} instead of nesting under `function`).
	 * strict=false preserves the existing loose-schema behavior.
	 *
	 * @param array<int, array<string, mixed>> $tools
	 * @return array<int, array<string, mixed>>
	 */
	private function to_responses_tools( array $tools ): array {
		$converted = array();
		foreach ( $tools as $tool ) {
			if ( isset( $tool['function'] ) && is_array( $tool['function'] ) ) {
				$function    = $tool['function'];
				$converted[] = array(
					'type'        => 'function',
					'name'        => $function['name'] ?? '',
					'description' => $function['description'] ?? '',
					'parameters'  => $function['parameters'] ?? new \stdClass(),
					'strict'      => false,
				);
			} else {
				$converted[] = $tool;
			}
		}
		return $converted;
	}

	private const MAX_PROVIDER_ITERATIONS = 10;

	/**
	 * Provider completion loop: sends to provider, parses SSE, handles tool calls, recurses.
	 *
	 * @param array<int, array<string, mixed>> $input Responses API input items.
	 * @param array<int, array<string, mixed>> $tools Tool definitions (Responses shape).
	 * @param string $instructions System prompt, sent as the Responses `instructions` field.
	 * @param callable(array<string, mixed>): void $on_chunk Frontend chunk callback.
	 * @param int $iteration Current iteration count (guards against infinite recursion).
	 */
	private function provider_completion_loop( array $input, array $tools, string $instructions, callable $on_chunk, int $iteration = 0 ): void {
		if ( $iteration >= self::MAX_PROVIDER_ITERATIONS ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[WPAIC] Provider completion loop exceeded max iterations (' . self::MAX_PROVIDER_ITERATIONS . ')' );
			$on_chunk( array( 'error' => 'The request required too many processing steps. Please try a simpler question.' ) );
			return;
		}
		$provider_url = $this->license_manager->get_provider_url();

		// Model and reasoning effort are decided by the provider, not the
		// chatbot. We deliberately omit them from the request body.
		$body = array(
			'input'        => $input,
			'instructions' => $instructions,
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
			foreach ( $result['tool_calls'] as $tc ) {
				// Echo the model's function call back into the conversation.
				$input[] = array(
					'type'      => 'function_call',
					'call_id'   => $tc['call_id'],
					'name'      => $tc['name'],
					'arguments' => $tc['arguments'],
				);

				$args        = json_decode( $tc['arguments'], true );
				$parsed_args = is_array( $args ) ? $args : array();

				$on_chunk(
					array(
						'tool_input_available' => array(
							'toolCallId' => $tc['call_id'],
							'toolName'   => $tc['name'],
							'input'      => $parsed_args,
						),
					)
				);

				$tool_result = $this->execute_tool( $tc['name'], $parsed_args );

				$on_chunk(
					array(
						'tool_output_available' => array(
							'toolCallId' => $tc['call_id'],
							'output'     => $tool_result,
						),
					)
				);

				$input[] = array(
					'type'    => 'function_call_output',
					'call_id' => $tc['call_id'],
					'output'  => (string) wp_json_encode( $tool_result ),
				);
			}

			$this->provider_completion_loop( $input, $tools, $instructions, $on_chunk, $iteration + 1 );
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

		/** @var array<string, array{call_id: string, name: string, arguments: string}> $tool_calls Keyed by Responses output item id. */
		$tool_calls  = array();
		$buffer      = '';
		$total_bytes = 0;

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

				// Provider-level error (the provider emits { "error": { "message": ... } } on failure).
				if ( isset( $data['error'] ) ) {
					fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
					$error_message = is_array( $data['error'] ) ? ( $data['error']['message'] ?? 'Provider error' ) : (string) $data['error'];
					return array( 'error' => $error_message );
				}

				// Responses API events are forwarded as { "event": ..., "data": {...} }.
				$event   = isset( $data['event'] ) ? (string) $data['event'] : '';
				$payload = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();

				switch ( $event ) {
					case 'response.output_text.delta':
						$text = $payload['delta'] ?? null;
						if ( $this->should_emit_stream_content( $text ) ) {
							$on_chunk( array( 'content' => $text ) );
						}
						break;

					case 'response.output_item.added':
						$item = isset( $payload['item'] ) && is_array( $payload['item'] ) ? $payload['item'] : array();
						if ( 'function_call' === ( $item['type'] ?? '' ) ) {
							$item_id                = (string) ( $item['id'] ?? '' );
							$call_id                = (string) ( $item['call_id'] ?? '' );
							$name                   = (string) ( $item['name'] ?? '' );
							$tool_calls[ $item_id ] = array(
								'call_id'   => $call_id,
								'name'      => $name,
								'arguments' => (string) ( $item['arguments'] ?? '' ),
							);
							$on_chunk(
								array(
									'tool_input_start' => array(
										'toolCallId' => $call_id,
										'toolName'   => $name,
									),
								)
							);
						}
						break;

					case 'response.function_call_arguments.delta':
						$item_id = (string) ( $payload['item_id'] ?? '' );
						if ( isset( $tool_calls[ $item_id ] ) ) {
							$delta                                = (string) ( $payload['delta'] ?? '' );
							$tool_calls[ $item_id ]['arguments'] .= $delta;
							$on_chunk(
								array(
									'tool_input_delta' => array(
										'toolCallId'     => $tool_calls[ $item_id ]['call_id'],
										'inputTextDelta' => $delta,
									),
								)
							);
						}
						break;

					case 'response.function_call_arguments.done':
						$item_id = (string) ( $payload['item_id'] ?? '' );
						if ( isset( $tool_calls[ $item_id ] ) && isset( $payload['arguments'] ) && is_string( $payload['arguments'] ) ) {
							// The done event carries the authoritative complete arguments string.
							$tool_calls[ $item_id ]['arguments'] = $payload['arguments'];
						}
						break;

					case 'error':
						fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
						$msg = $payload['message'] ?? 'Provider error';
						return array( 'error' => is_string( $msg ) ? $msg : 'Provider error' );
				}
			}
		}

		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( "[WPAIC] Stream finished. Total bytes read: {$total_bytes}, tool_calls: " . count( $tool_calls ) . ', remaining buffer: ' . substr( $buffer, 0, 200 ) );

		if ( ! empty( $tool_calls ) ) {
			return array( 'tool_calls' => array_values( $tool_calls ) );
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
		return ( new WPAIC_System_Prompt( $this->settings, $this->page_context ) )->build();
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
					'name'        => 'get_popular_products',
					'description' => "Get the store's best-selling / most popular products as ready-to-display product cards. Use this for requests like 'best sellers', 'most popular', 'top products', 'what's trending', 'what sells best', or 'your most popular <category>'. Optionally filter by a category slug.",
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'category' => array(
								'type'        => 'string',
								'description' => 'Category slug to filter, e.g. a slug from get_categories',
							),
							'limit'    => array(
								'type'        => 'integer',
								'description' => 'Max results, default 10',
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
					'name'        => 'add_to_cart',
					'description' => 'Add a product to the shopper\'s cart. Call this when the shopper asks to add an item, buy it, or says "add it to my cart". For a variable product (with options like size or color) you MUST pass the chosen variation_id — resolve it from context (for example a variation returned by get_product_details that matches what the shopper said). If you cannot determine the variation with confidence, do NOT guess and do NOT call this tool: ask the shopper which option they want first. The UI confirms the add and updates the cart, so reply with at most one short sentence confirming what was added.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'product_id'   => array(
								'type'        => 'integer',
								'description' => 'The product ID to add',
							),
							'variation_id' => array(
								'type'        => 'integer',
								'description' => 'For a variable product, the chosen variation ID. Omit for simple products.',
							),
							'quantity'     => array(
								'type'        => 'integer',
								'description' => 'Quantity to add (default 1)',
							),
						),
						'required'   => array( 'product_id' ),
					),
				),
			);
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'clear_cart',
					'description' => 'Remove items from the shopper\'s cart, or empty it entirely. Pass `items` to remove only those products; omit `items` to clear the whole cart. For each item, `quantity` is how many units to remove — omit quantity (or set it to the full amount) to remove all units of that product, or set a smaller quantity to remove just some (for example remove 2 of 5 waters). You MUST know each product_id: if you do not have it, call get_cart_contents first to resolve the product_id and current quantity from the item name. If you cannot tell which item the shopper means, ask them before calling. The UI shows a confirmation popup and updates the cart itself, so do NOT ask the shopper to confirm in text and do NOT claim the cart was changed — reply with at most one short sentence (for example: "Sure — just confirm below.").',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
							'items' => array(
								'type'        => 'array',
								'description' => 'Cart items to remove (resolve product_id from get_cart_contents). Omit or leave empty to clear the entire cart.',
								'items'       => array(
									'type'       => 'object',
									'properties' => array(
										'product_id' => array(
											'type'        => 'integer',
											'description' => 'The product ID to remove.',
										),
										'quantity'   => array(
											'type'        => 'integer',
											'description' => 'How many units to remove. Omit to remove all units of this product.',
										),
									),
									'required'   => array( 'product_id' ),
								),
							),
						),
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

		$model = $this->settings['model'] ?? 'gpt-5-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-5-mini';
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

		$model = $this->settings['model'] ?? 'gpt-5-mini';
		if ( ! is_string( $model ) ) {
			$model = 'gpt-5-mini';
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

		if ( null === $this->tools || null === $this->product_tools ) {
			return array( 'error' => 'Product tools unavailable' );
		}

		return match ( $name ) {
			'search_products' => $this->product_tools->search_products( $arguments ),
			'get_popular_products' => $this->product_tools->get_popular_products( $arguments ),
			'get_product_details' => $this->product_tools->get_product_details( (int) ( $arguments['product_id'] ?? 0 ) ),
			'get_categories' => $this->product_tools->get_categories(),
			'get_cart_contents' => $this->tools->get_cart_contents(),
			'get_checkout_action' => $this->tools->get_checkout_action(),
			'add_to_cart' => $this->tools->add_to_cart( $arguments ),
			'clear_cart' => $this->tools->clear_cart( $arguments ),
			'compare_products' => $this->product_tools->compare_products( isset( $arguments['product_ids'] ) && is_array( $arguments['product_ids'] ) ? $arguments['product_ids'] : array() ),
			'get_order_status' => $this->tools->get_order_status( $arguments ),
			'get_shipping_info' => $this->tools->get_shipping_info(),
			default => array( 'error' => 'Unknown tool' ),
		};
	}
}
