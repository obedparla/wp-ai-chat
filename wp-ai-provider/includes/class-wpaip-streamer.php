<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Streamer {

	private const CONNECT_TIMEOUT_SECONDS = 10;
	private const REQUEST_TIMEOUT_SECONDS = 120;

	/**
	 * Shopper-facing error message. Raw exception detail must never reach the
	 * chat widget — it is logged server-side instead.
	 */
	private const FRIENDLY_ERROR_MESSAGE = 'Something went wrong while generating a response. Please try again in a moment.';

	private ?\OpenAI\Client $client = null;

	/** @var (callable(array<string, mixed>): void)|null Receives the usage object from response.completed. */
	private $on_response_completed = null;

	public function __construct() {
		$settings = get_option( 'wpaip_settings', array() );
		$api_key  = is_array( $settings ) ? ( $settings['openai_api_key'] ?? '' ) : '';

		if ( is_string( $api_key ) && '' !== $api_key ) {
			$this->client = \OpenAI::factory()
				->withApiKey( $api_key )
				->withHttpClient(
					new \GuzzleHttp\Client(
						array(
							'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
							'timeout'         => self::REQUEST_TIMEOUT_SECONDS,
						)
					)
				)
				->make();
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
	 * Transient upstream failures (connection error, 429, 5xx) are retried
	 * once, but only while no event has been emitted to the client yet.
	 *
	 * @param array<string, mixed> $params OpenAI Responses params (input, model, tools, instructions, reasoning, etc.)
	 */
	public function stream( array $params ): void {
		if ( ! $this->has_client() ) {
			$this->log_error( 'WPAIP_Streamer: OpenAI API key not configured.' );
			$this->emit_error( self::FRIENDLY_ERROR_MESSAGE );
			$this->emit_done();
			return;
		}

		$has_emitted_event = false;
		$has_retried       = false;

		while ( true ) {
			try {
				$stream = $this->create_streamed_response( $params );

				foreach ( $stream as $response ) {
					// Stop reading (and paying for) the OpenAI stream once the
					// chatbot client has disconnected.
					if ( $this->is_connection_aborted() ) {
						return;
					}

					$event_payload = $response->toArray();
					$this->maybe_notify_response_completed( $event_payload );

					$slim_payload = $this->slim_event_payload( $event_payload );
					if ( null === $slim_payload ) {
						continue;
					}

					$this->emit_data( $slim_payload );
					$has_emitted_event = true;
				}

				$this->emit_done();
				return;
			} catch ( \Exception $exception ) {
				if ( ! $has_retried && ! $has_emitted_event && $this->is_retryable( $exception ) ) {
					$has_retried = true;
					$this->log_error( 'WPAIP_Streamer: retrying after ' . get_class( $exception ) . ': ' . $exception->getMessage() );
					$this->wait_before_retry();
					continue;
				}

				$this->log_error( 'WPAIP_Streamer: OpenAI stream failed (' . get_class( $exception ) . '): ' . $exception->getMessage() );
				$this->emit_error( self::FRIENDLY_ERROR_MESSAGE );
				$this->emit_done();
				return;
			}
		}
	}

	/**
	 * Connection failures, rate limits, and 5xx responses are transient and
	 * safe to retry before anything has been streamed to the client.
	 */
	protected function is_retryable( \Exception $exception ): bool {
		return $exception instanceof \OpenAI\Exceptions\TransporterException
			|| $exception instanceof \OpenAI\Exceptions\RateLimitException
			|| $exception instanceof \OpenAI\Exceptions\ServerException;
	}

	/**
	 * @param array<string, mixed> $params
	 * @return iterable<int, object> Streamed OpenAI response events.
	 */
	protected function create_streamed_response( array $params ): iterable {
		return $this->client->responses()->createStreamed( $params );
	}

	protected function is_connection_aborted(): bool {
		return connection_aborted() > 0;
	}

	protected function wait_before_retry(): void {
		sleep( 1 );
	}

	protected function log_error( string $message ): void {
		error_log( $message );
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
	 * Reduce a typed OpenAI streaming event to only what the chatbot consumes,
	 * or null to skip forwarding it entirely.
	 *
	 * Lifecycle events (response.created, response.in_progress, content_part.*,
	 * output_text.done, ...) each embed the full response object — instructions
	 * and tool definitions included, ~25KB per event. Forwarding them wholesale
	 * pushes ~100KB of dead weight per turn through every buffer between OpenAI
	 * and the shopper before/after the visible tokens, which both wastes
	 * bandwidth and delays the first painted token.
	 *
	 * @param array<string, mixed> $event_payload { event: string, data: array } from the SDK.
	 * @return array<string, mixed>|null
	 */
	protected function slim_event_payload( array $event_payload ): ?array {
		$event = $event_payload['event'] ?? '';
		$data  = isset( $event_payload['data'] ) && is_array( $event_payload['data'] ) ? $event_payload['data'] : array();

		switch ( $event ) {
			case 'response.output_text.delta':
				return array(
					'event' => $event,
					'data'  => array( 'delta' => $data['delta'] ?? '' ),
				);

			case 'response.output_item.added':
				$item = isset( $data['item'] ) && is_array( $data['item'] ) ? $data['item'] : array();
				// The chatbot only acts on new function_call items; message and
				// reasoning items would be ignored on the other end.
				if ( 'function_call' !== ( $item['type'] ?? '' ) ) {
					return null;
				}
				return array(
					'event' => $event,
					'data'  => array(
						'item' => array(
							'id'        => $item['id'] ?? '',
							'type'      => 'function_call',
							'call_id'   => $item['call_id'] ?? '',
							'name'      => $item['name'] ?? '',
							'arguments' => $item['arguments'] ?? '',
						),
					),
				);

			case 'response.function_call_arguments.delta':
				return array(
					'event' => $event,
					'data'  => array(
						'item_id' => $data['item_id'] ?? '',
						'delta'   => $data['delta'] ?? '',
					),
				);

			case 'response.function_call_arguments.done':
				return array(
					'event' => $event,
					'data'  => array(
						'item_id'   => $data['item_id'] ?? '',
						'arguments' => $data['arguments'] ?? '',
					),
				);

			case 'response.completed':
				// Usage only; the full payload repeats instructions + tools + output.
				return array(
					'event' => $event,
					'data'  => array(
						'response' => array(
							'usage' => $data['response']['usage'] ?? null,
						),
					),
				);

			case 'error':
				return $event_payload;

			default:
				return null;
		}
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
