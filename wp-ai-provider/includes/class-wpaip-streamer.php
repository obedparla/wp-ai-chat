<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Streamer {

	private ?\OpenAI\Client $client = null;

	/** @var (callable(array<string, mixed>): void)|null Receives the usage object from response.completed. */
	private $on_response_completed = null;

	public function __construct() {
		$settings = get_option( 'wpaip_settings', array() );
		$api_key  = is_array( $settings ) ? ( $settings['openai_api_key'] ?? '' ) : '';

		if ( is_string( $api_key ) && '' !== $api_key ) {
			$this->client = \OpenAI::client( $api_key );
		}
	}

	public function has_client(): bool {
		return null !== $this->client;
	}

	/**
	 * Register a callback invoked with the exact token usage OpenAI reports
	 * on the final `response.completed` streaming event.
	 *
	 * @param callable(array<string, mixed>): void $callback
	 */
	public function set_on_response_completed( callable $callback ): void {
		$this->on_response_completed = $callback;
	}

	/**
	 * Stream a Responses API response from OpenAI to the output buffer as SSE.
	 *
	 * Each emitted SSE event is the SDK's typed streaming event serialized as
	 * { "event": "response.output_text.delta", "data": { ... } }.
	 *
	 * @param array<string, mixed> $params OpenAI Responses params (input, model, tools, instructions, reasoning, etc.)
	 */
	public function stream( array $params ): void {
		if ( null === $this->client ) {
			$this->emit_error( 'OpenAI API key not configured' );
			$this->emit_done();
			return;
		}

		try {
			$stream = $this->client->responses()->createStreamed( $params );

			foreach ( $stream as $response ) {
				$event_payload = $response->toArray();
				$this->emit_data( $event_payload );
				$this->maybe_notify_response_completed( $event_payload );
			}

			$this->emit_done();
		} catch ( \Exception $exception ) {
			$this->emit_error( $exception->getMessage() );
			$this->emit_done();
		}
	}

	/**
	 * Fire the registered callback when the payload is a `response.completed`
	 * event carrying a usage object ({ "event": ..., "data": { "response": { "usage": ... } } }).
	 *
	 * @param array<string, mixed> $event_payload
	 */
	protected function maybe_notify_response_completed( array $event_payload ): void {
		if ( null === $this->on_response_completed ) {
			return;
		}

		if ( 'response.completed' !== ( $event_payload['event'] ?? '' ) ) {
			return;
		}

		$usage = $event_payload['data']['response']['usage'] ?? null;
		if ( ! is_array( $usage ) ) {
			return;
		}

		( $this->on_response_completed )( $usage );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	protected function emit_data( array $data ): void {
		echo 'data: ' . json_encode( $data ) . "\n\n";
		$this->flush();
	}

	protected function emit_done(): void {
		echo "data: [DONE]\n\n";
		$this->flush();
	}

	protected function emit_error( string $message ): void {
		echo 'data: ' . json_encode( array( 'error' => array( 'message' => $message ) ) ) . "\n\n";
		$this->flush();
	}

	protected function flush(): void {
		if ( ob_get_level() ) {
			ob_flush();
		}
		flush();
	}
}
