<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_API {

	private const DEFAULT_MAX_OUTPUT_TOKENS = 2048;
	private const MAX_INPUT_ITEMS           = 100;
	private const MAX_INSTRUCTIONS_LENGTH   = 32768;
	private const MAX_INPUT_ITEM_LENGTH     = 65536;

	private ?WPAIP_Streamer $streamer = null;
	/** @var array<string, mixed> */
	private array $request_context = array();
	private ?WPAIP_License_Validator $license_validator = null;
	private ?WPAIP_Usage_Tracker $usage_tracker = null;

	public function set_streamer( WPAIP_Streamer $streamer ): void {
		$this->streamer = $streamer;
	}

	public function set_license_validator( WPAIP_License_Validator $license_validator ): void {
		$this->license_validator = $license_validator;
	}

	public function set_usage_tracker( WPAIP_Usage_Tracker $usage_tracker ): void {
		$this->usage_tracker = $usage_tracker;
	}

	private function get_streamer(): WPAIP_Streamer {
		if ( null === $this->streamer ) {
			$this->streamer = new WPAIP_Streamer();
		}
		return $this->streamer;
	}

	private function get_license_validator(): WPAIP_License_Validator {
		if ( null === $this->license_validator ) {
			$this->license_validator = new WPAIP_License_Validator();
		}

		return $this->license_validator;
	}

	private function get_usage_tracker(): WPAIP_Usage_Tracker {
		if ( null === $this->usage_tracker ) {
			$this->usage_tracker = new WPAIP_Usage_Tracker();
		}

		return $this->usage_tracker;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route(
			'wpaip/v1',
			'/chat',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_chat' ),
				'permission_callback' => array( $this, 'authenticate_request' ),
			)
		);
	}

	/**
	 * Authenticate incoming requests via Freemius install identity.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error
	 */
	public function authenticate_request( WP_REST_Request $request ): bool|WP_Error {
		$validation_result = $this->get_license_validator()->validate_request( $request );
		if ( is_wp_error( $validation_result ) ) {
			return $validation_result;
		}

		$this->request_context = $validation_result;

		return true;
	}

	/**
	 * Validate the chat request payload.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_Error|null Null when valid, WP_Error otherwise.
	 */
	public function validate_chat_request( WP_REST_Request $request ): ?WP_Error {
		$input        = $request->get_param( 'input' );
		$tools        = $request->get_param( 'tools' );
		$model        = $request->get_param( 'model' );
		$instructions = $request->get_param( 'instructions' );

		if ( empty( $input ) || ! is_array( $input ) ) {
			return new WP_Error(
				'invalid_request',
				'input field is required and must be an array',
				array( 'status' => 400 )
			);
		}

		if ( count( $input ) > self::MAX_INPUT_ITEMS ) {
			return new WP_Error(
				'invalid_request',
				sprintf( 'input must not exceed %d items', self::MAX_INPUT_ITEMS ),
				array( 'status' => 400 )
			);
		}

		foreach ( $input as $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error(
					'invalid_request',
					'Each input item must be an object',
					array( 'status' => 400 )
				);
			}
			// Cap the serialized size of every item — covers message content,
			// function_call arguments, and function_call_output payloads alike.
			if ( strlen( (string) wp_json_encode( $item ) ) > self::MAX_INPUT_ITEM_LENGTH ) {
				return new WP_Error(
					'invalid_request',
					sprintf( 'Each input item must not exceed %d characters', self::MAX_INPUT_ITEM_LENGTH ),
					array( 'status' => 400 )
				);
			}
			// Function call / output items are typed; message items carry role + content.
			if ( isset( $item['type'] ) ) {
				continue;
			}
			if ( ! isset( $item['role'] ) || ! isset( $item['content'] ) ) {
				return new WP_Error(
					'invalid_request',
					'Each input message must have role and content fields',
					array( 'status' => 400 )
				);
			}
		}

		if ( null !== $tools && ! is_array( $tools ) ) {
			return new WP_Error(
				'invalid_request',
				'tools must be an array when provided',
				array( 'status' => 400 )
			);
		}

		if ( null !== $instructions && ! is_string( $instructions ) ) {
			return new WP_Error(
				'invalid_request',
				'instructions must be a string when provided',
				array( 'status' => 400 )
			);
		}

		if ( is_string( $instructions ) && strlen( $instructions ) > self::MAX_INSTRUCTIONS_LENGTH ) {
			return new WP_Error(
				'invalid_request',
				sprintf( 'instructions must not exceed %d characters', self::MAX_INSTRUCTIONS_LENGTH ),
				array( 'status' => 400 )
			);
		}

		if ( null !== $model && ! is_string( $model ) ) {
			return new WP_Error(
				'invalid_request',
				'model must be a string when provided',
				array( 'status' => 400 )
			);
		}

		$reasoning_effort = $request->get_param( 'reasoning_effort' );
		if ( null !== $reasoning_effort && ! is_string( $reasoning_effort ) ) {
			return new WP_Error(
				'invalid_request',
				'reasoning_effort must be a string when provided',
				array( 'status' => 400 )
			);
		}

		return null;
	}

	/**
	 * Handle incoming chat request. Validates, then streams OpenAI response as SSE.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_REST_Response|WP_Error|void Returns WP_Error on validation failure, void on stream.
	 */
	public function handle_chat( WP_REST_Request $request ) {
		$validation_error = $this->validate_chat_request( $request );
		if ( null !== $validation_error ) {
			return $validation_error;
		}

		$streamer = $this->get_streamer();
		if ( ! $streamer->has_client() ) {
			return new WP_Error(
				'server_error',
				'OpenAI API key not configured',
				array( 'status' => 500 )
			);
		}

		$usage_bucket = $this->request_context['usage_bucket'] ?? '';
		$usage_bucket = is_string( $usage_bucket ) ? $usage_bucket : '';

		// Per-install daily budget: reject before spending OpenAI tokens. The
		// chatbot surfaces this message to the shopper in-chat.
		if ( '' !== $usage_bucket ) {
			$usage_tracker = $this->get_usage_tracker();

			if ( $usage_tracker->is_over_budget( $usage_bucket ) ) {
				return new WP_Error(
					'rate_limited',
					'The chat assistant has reached its daily limit. Please try again tomorrow, or contact the store directly for help.',
					array( 'status' => 429 )
				);
			}

			$usage_tracker->record_message( $usage_bucket );
			$streamer->set_on_response_completed(
				static function ( array $usage ) use ( $usage_tracker, $usage_bucket ): void {
					$usage_tracker->record_tokens( $usage_bucket, $usage );
				}
			);
		}

		$input        = $request->get_param( 'input' );
		$tools        = $request->get_param( 'tools' );
		$instructions = $request->get_param( 'instructions' );

		// The provider — not the chatbot — decides model and reasoning effort.
		// Any model/reasoning_effort the request carries is validated (see
		// validate_chat_request) but deliberately ignored here, so older
		// chatbot versions that still send them keep working.
		$resolved = $this->resolve_model_for_request();

		$params = array(
			'model' => $resolved['model'],
			'input' => $input,
			// Responses API stores conversations on OpenAI for 30 days by default.
			'store' => false,
			// Hard per-response output ceiling (reasoning + visible tokens) so a
			// single runaway request cannot burn unbounded output tokens. OpenAI
			// requires a minimum of 16.
			'max_output_tokens' => max( 16, (int) apply_filters( 'wpaip_max_output_tokens', self::DEFAULT_MAX_OUTPUT_TOKENS ) ),
		);

		// Route requests from the same install to the same cache shard so the
		// static prompt + tool definitions hit OpenAI's prompt cache (~90%
		// cheaper cached input on the GPT-5 family).
		if ( '' !== $usage_bucket ) {
			$params['prompt_cache_key'] = $usage_bucket;
		}

		if ( is_string( $instructions ) && '' !== $instructions ) {
			$params['instructions'] = $instructions;
		}

		if ( is_array( $tools ) && ! empty( $tools ) ) {
			$params['tools'] = $this->fix_tool_schemas( $tools );
		}

		// Responses API takes reasoning effort nested under `reasoning`. It has
		// no 'none' level, and omitting the param makes GPT-5 silently default
		// to medium — so map 'none' to 'minimal', the lowest Responses effort.
		$reasoning_effort = 'none' === $resolved['reasoning_effort'] ? 'minimal' : $resolved['reasoning_effort'];
		if ( '' !== $reasoning_effort ) {
			$params['reasoning'] = array( 'effort' => $reasoning_effort );
		}

		// @codeCoverageIgnoreStart
		if ( ! defined( 'WPAIP_TESTING' ) ) {
			header( 'Content-Type: text/event-stream; charset=utf-8' );
			header( 'Cache-Control: no-cache' );
			header( 'Connection: keep-alive' );
			header( 'X-Accel-Buffering: no' );

			if ( ob_get_level() ) {
				ob_end_clean();
			}
		}
		// @codeCoverageIgnoreEnd

		$streamer->stream( $params );

		// Must exit to prevent WordPress from appending to the SSE stream.
		// @codeCoverageIgnoreStart
		if ( ! defined( 'WPAIP_TESTING' ) ) {
			exit;
		}
		// @codeCoverageIgnoreEnd
	}

	/**
	 * Decide the model + reasoning effort for this request, server-side.
	 *
	 * The provider is the sole authority: it picks the admin-selected option
	 * and ignores whatever the chatbot sent. The per-request context in
	 * $this->request_context (install ID, license ID, usage_bucket) is the
	 * seam for future per-install throttling/abuse downgrades.
	 *
	 * @return array{model: string, reasoning_effort: string}
	 */
	private function resolve_model_for_request(): array {
		$settings = get_option( 'wpaip_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		$model            = $settings['model'] ?? '';
		$reasoning_effort = $settings['reasoning_effort'] ?? '';

		$valid_models = WPAIP_Admin::get_available_models();
		if ( ! is_string( $model ) || ! isset( $valid_models[ $model ] ) ) {
			$model = WPAIP_Admin::DEFAULT_MODEL;
		}

		$valid_efforts = WPAIP_Admin::get_available_reasoning_efforts();
		if ( ! is_string( $reasoning_effort ) || ! isset( $valid_efforts[ $reasoning_effort ] ) ) {
			$reasoning_effort = WPAIP_Admin::DEFAULT_REASONING_EFFORT;
		}

		// TODO: per-install throttling/abuse rules keyed off
		// $this->request_context['usage_bucket'] can override model/effort here.

		return array(
			'model'            => $model,
			'reasoning_effort' => $reasoning_effort,
		);
	}

	/**
	 * Fix empty arrays that should be JSON objects in tool schemas.
	 *
	 * PHP's json_decode turns {} into [] (empty array). When re-encoded,
	 * [] becomes a JSON array instead of object, which OpenAI rejects.
	 *
	 * @param array<int, array<string, mixed>> $tools
	 * @return array<int, array<string, mixed>>
	 */
	private function fix_tool_schemas( array $tools ): array {
		foreach ( $tools as &$tool ) {
			if ( ! isset( $tool['parameters'] ) ) {
				continue;
			}
			$tool['parameters'] = $this->fix_empty_objects( $tool['parameters'] );
		}
		return $tools;
	}

	/**
	 * Recursively convert empty arrays to stdClass so json_encode produces {} not [].
	 *
	 * @param mixed $value
	 * @return mixed
	 */
	private function fix_empty_objects( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( empty( $value ) ) {
			return new \stdClass();
		}

		foreach ( $value as $key => &$item ) {
			$item = $this->fix_empty_objects( $item );
		}

		return $value;
	}
}
