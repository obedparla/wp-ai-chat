<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use OpenAI\Client;

class WPAIC_Chat {
	/** @var array<string, mixed> */
	private array $settings;
	private ?WPAIC_Tools $tools = null;
	private ?Client $client     = null;

	public function __construct() {
		$settings       = get_option( 'wpaic_settings', array() );
		$this->settings = is_array( $settings ) ? $settings : array();

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
	 * Check if provider mode is active (provider_url + provider_site_key both set).
	 */
	public function is_provider_mode(): bool {
		$provider_url      = $this->settings['provider_url'] ?? '';
		$provider_site_key = $this->settings['provider_site_key'] ?? '';
		return is_string( $provider_url ) && '' !== $provider_url
			&& is_string( $provider_site_key ) && '' !== $provider_site_key;
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

				if ( $delta->content ) {
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

	/**
	 * Provider completion loop: sends to provider, parses SSE, handles tool calls, recurses.
	 *
	 * @param array<int, array<string, mixed>> $formatted_messages Already-formatted messages with system prompt.
	 * @param array<int, array<string, mixed>> $tools Tool definitions.
	 * @param string $model Model name.
	 * @param callable(array<string, mixed>): void $on_chunk Frontend chunk callback.
	 */
	private function provider_completion_loop( array $formatted_messages, array $tools, string $model, callable $on_chunk ): void {
		$provider_url      = $this->settings['provider_url'];
		$provider_site_key = $this->settings['provider_site_key'];

		$body = array(
			'messages' => $formatted_messages,
			'model'    => $model,
		);
		if ( ! empty( $tools ) ) {
			$body['tools'] = $tools;
		}

		$result = $this->stream_from_provider( $provider_url, $provider_site_key, $body, $on_chunk );

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

			$this->provider_completion_loop( $formatted_messages, $tools, $model, $on_chunk );
			return;
		}

		$on_chunk( array( 'done' => true ) );
	}

	/**
	 * Open HTTP stream to provider, parse SSE lines, emit text chunks and collect tool calls.
	 *
	 * @param string $url Provider endpoint URL.
	 * @param string $site_key Authentication key.
	 * @param array<string, mixed> $body Request body.
	 * @param callable(array<string, mixed>): void $on_chunk Frontend callback.
	 * @return array{error?: string, tool_calls?: array<int, array<string, mixed>>} Result.
	 */
	private function stream_from_provider( string $url, string $site_key, array $body, callable $on_chunk ): array {
		$context = stream_context_create(
			array(
				'http' => array(
					'method'  => 'POST',
					'header'  => "Content-Type: application/json\r\nX-WPAIP-Site-Key: {$site_key}\r\n",
					'content' => wp_json_encode( $body ),
					'timeout' => 120,
				),
				'ssl'  => array(
					'verify_peer' => true,
				),
			)
		);

		$stream = @fopen( $url, 'r', false, $context ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $stream ) {
			return array( 'error' => 'Failed to connect to provider' );
		}

		/** @var array<int, array{id: string|null, type: string, function: array{name: string, arguments: string}, started: bool}> $tool_calls */
		$tool_calls    = array();
		$finish_reason = '';
		$buffer        = '';

		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			if ( false === $chunk ) {
				break;
			}
			$buffer .= $chunk;

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

				if ( ! empty( $delta['content'] ) ) {
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

		if ( 'tool_calls' === $finish_reason && ! empty( $tool_calls ) ) {
			return array( 'tool_calls' => $tool_calls );
		}

		return array();
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

	private function get_system_prompt(): string {
		$custom_prompt = $this->settings['system_prompt'] ?? '';
		$faq_section   = $this->get_faq_instruction();

		if ( is_string( $custom_prompt ) && '' !== trim( $custom_prompt ) ) {
			return $custom_prompt . $faq_section . $this->get_tool_response_instruction() . $this->get_handoff_instruction() . $this->get_language_instruction();
		}

		$site_name = get_bloginfo( 'name' );

		if ( wpaic_is_woocommerce_active() ) {
			return "You are a helpful assistant for {$site_name}. Help customers find products and answer questions. Be friendly and concise. Use tools to search products when asked." . $faq_section . $this->get_tool_response_instruction() . $this->get_handoff_instruction() . $this->get_language_instruction();
		}

		return "You are a helpful assistant for {$site_name}. Answer questions and help visitors. Be friendly and concise." . $faq_section . $this->get_handoff_instruction() . $this->get_language_instruction();
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
		return ' When presenting tool results (products, comparisons, order info), provide ONLY a single short sentence intro (max 10 words) that relates to the query. Example: "Here are some red shoes:" - NEVER list product names, prices, or details in your text response. The product cards will show all details. Your text should be a brief intro only, not a summary of results. If no results found, explain briefly.';
	}

	private function get_handoff_instruction(): string {
		if ( $this->is_handoff_enabled() ) {
			return ' When a customer asks to speak to a human, talk to support, or escalate their issue, first ask for their name, then ask for their email address. Once you have both, use the create_handoff_request tool to submit the request.';
		}

		return ' If a customer asks to speak to a human or escalate to support, apologize and explain that human support escalation is not currently available, but offer to help them with their question.';
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
		}

		if ( $this->is_handoff_enabled() ) {
			$tools[] = array(
				'type'     => 'function',
				'function' => array(
					'name'        => 'create_handoff_request',
					'description' => 'Create a support request to hand off to a human agent. Use when customer explicitly asks to speak to a human, talk to support, or escalate. First ask for their name and email before calling this tool.',
					'parameters'  => array(
						'type'       => 'object',
						'properties' => array(
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
						),
						'required'   => array( 'customer_name', 'customer_email', 'conversation_summary' ),
					),
				),
			);
		}

		$custom_data_tool = $this->get_custom_data_tool_definition();
		if ( null !== $custom_data_tool ) {
			$tools[] = $custom_data_tool;
		}

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
				if ( $response->choices[0]->delta->content ) {
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
	 * @return array<string, mixed>|null
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

		if ( null === $this->tools ) {
			return array( 'error' => 'Product tools unavailable' );
		}

		return match ( $name ) {
			'search_products' => $this->tools->search_products( $arguments ),
			'get_product_details' => $this->tools->get_product_details( (int) ( $arguments['product_id'] ?? 0 ) ),
			'get_categories' => $this->tools->get_categories(),
			'compare_products' => $this->tools->compare_products( isset( $arguments['product_ids'] ) && is_array( $arguments['product_ids'] ) ? $arguments['product_ids'] : array() ),
			'get_order_status' => $this->tools->get_order_status( $arguments ),
			default => array( 'error' => 'Unknown tool' ),
		};
	}
}
