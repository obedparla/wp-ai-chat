<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_API {

	private ?WPAIP_Streamer $streamer = null;

	public function set_streamer( WPAIP_Streamer $streamer ): void {
		$this->streamer = $streamer;
	}

	private function get_streamer(): WPAIP_Streamer {
		if ( null === $this->streamer ) {
			$this->streamer = new WPAIP_Streamer();
		}
		return $this->streamer;
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
	 * Authenticate incoming requests via site key header.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return bool|WP_Error
	 */
	public function authenticate_request( WP_REST_Request $request ): bool|WP_Error {
		$site_key = $request->get_header( 'X-WPAIP-Site-Key' );

		if ( empty( $site_key ) || ! is_string( $site_key ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Missing site key',
				array( 'status' => 403 )
			);
		}

		$settings       = get_option( 'wpaip_settings', array() );
		$stored_site_key = is_array( $settings ) ? ( $settings['site_key'] ?? '' ) : '';

		if ( '' === $stored_site_key || ! hash_equals( $stored_site_key, $site_key ) ) {
			return new WP_Error(
				'rest_forbidden',
				'Invalid site key',
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate the chat request payload.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request
	 * @return WP_Error|null Null when valid, WP_Error otherwise.
	 */
	public function validate_chat_request( WP_REST_Request $request ): ?WP_Error {
		$messages = $request->get_param( 'messages' );
		$tools    = $request->get_param( 'tools' );
		$model    = $request->get_param( 'model' );

		if ( empty( $messages ) || ! is_array( $messages ) ) {
			return new WP_Error(
				'invalid_request',
				'messages field is required and must be an array',
				array( 'status' => 400 )
			);
		}

		foreach ( $messages as $message ) {
			if ( ! is_array( $message ) || ! isset( $message['role'] ) || ! isset( $message['content'] ) ) {
				if ( is_array( $message ) && 'tool' === ( $message['role'] ?? '' ) && isset( $message['tool_call_id'] ) ) {
					continue;
				}
				if ( is_array( $message ) && 'assistant' === ( $message['role'] ?? '' ) && isset( $message['tool_calls'] ) ) {
					continue;
				}
				return new WP_Error(
					'invalid_request',
					'Each message must have role and content fields',
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

		if ( null !== $model && ! is_string( $model ) ) {
			return new WP_Error(
				'invalid_request',
				'model must be a string when provided',
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

		$messages = $request->get_param( 'messages' );
		$tools    = $request->get_param( 'tools' );
		$model    = $request->get_param( 'model' );

		$settings       = get_option( 'wpaip_settings', array() );
		$default_model  = is_array( $settings ) ? ( $settings['model'] ?? 'gpt-4o-mini' ) : 'gpt-4o-mini';

		$params = array(
			'model'    => is_string( $model ) ? $model : $default_model,
			'messages' => $messages,
		);

		if ( is_array( $tools ) && ! empty( $tools ) ) {
			$params['tools'] = $tools;
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
}
