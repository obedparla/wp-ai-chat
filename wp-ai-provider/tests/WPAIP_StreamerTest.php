<?php

use PHPUnit\Framework\TestCase;

class WPAIP_StreamerTest extends TestCase {

	private const FRIENDLY_ERROR_MESSAGE = 'Something went wrong while generating a response. Please try again in a moment.';

	protected function setUp(): void {
		$GLOBALS['wp_options'] = array();
	}

	public function test_has_client_false_when_no_api_key(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key' => '',
		) );

		$streamer = new WPAIP_Streamer();

		$this->assertFalse( $streamer->has_client() );
	}

	public function test_has_client_true_when_api_key_present(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key' => 'sk-test-key',
		) );

		$streamer = new WPAIP_Streamer();

		$this->assertTrue( $streamer->has_client() );
	}

	public function test_stream_emits_friendly_error_and_done_when_no_client(): void {
		$streamer = new TestableStreamer();

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertCount( 2, $streamer->output );
		$this->assertStringContainsString( '"error"', $streamer->output[0] );
		$this->assertStringContainsString( self::FRIENDLY_ERROR_MESSAGE, $streamer->output[0] );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[1] );
		$this->assertStringContainsString( 'not configured', implode( "\n", $streamer->logged ) );
	}

	public function test_stream_emits_events_and_done(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hi' ) ) );
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => '!' ) ) );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertCount( 3, $streamer->output );
		$this->assertStringContainsString( '"delta":"Hi"', $streamer->output[0] );
		$this->assertStringContainsString( '"delta":"!"', $streamer->output[1] );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[2] );
	}

	public function test_stream_retries_once_on_connection_error_before_any_event(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				throw self::make_transporter_exception();
			},
			static function (): iterable {
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hi' ) ) );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertSame( 2, $streamer->attempt_count );
		$this->assertSame( 1, $streamer->waits );
		$this->assertCount( 2, $streamer->output );
		$this->assertStringContainsString( '"delta":"Hi"', $streamer->output[0] );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[1] );
	}

	public function test_stream_retries_once_on_rate_limit(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				throw new \OpenAI\Exceptions\RateLimitException( new \GuzzleHttp\Psr7\Response( 429 ) );
			},
			static function (): iterable {
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hi' ) ) );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertSame( 2, $streamer->attempt_count );
		$this->assertSame( "data: [DONE]\n\n", end( $streamer->output ) );
	}

	public function test_stream_retries_once_on_server_error(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				throw new \OpenAI\Exceptions\ServerException( new \GuzzleHttp\Psr7\Response( 503 ) );
			},
			static function (): iterable {
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hi' ) ) );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertSame( 2, $streamer->attempt_count );
		$this->assertSame( "data: [DONE]\n\n", end( $streamer->output ) );
	}

	public function test_stream_does_not_retry_twice(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				throw self::make_transporter_exception();
			},
			static function (): iterable {
				throw self::make_transporter_exception();
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertSame( 2, $streamer->attempt_count );
		$this->assertCount( 2, $streamer->output );
		$this->assertStringContainsString( self::FRIENDLY_ERROR_MESSAGE, $streamer->output[0] );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[1] );
	}

	public function test_stream_does_not_retry_after_event_emitted(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hi' ) ) );
				throw self::make_transporter_exception();
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertSame( 1, $streamer->attempt_count );
		$this->assertSame( 0, $streamer->waits );
		$this->assertCount( 3, $streamer->output );
		$this->assertStringContainsString( '"delta":"Hi"', $streamer->output[0] );
		$this->assertStringContainsString( self::FRIENDLY_ERROR_MESSAGE, $streamer->output[1] );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[2] );
	}

	public function test_stream_does_not_retry_non_retryable_exceptions(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				throw new \RuntimeException( 'Invalid request: unsupported tool schema' );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertSame( 1, $streamer->attempt_count );
		$this->assertCount( 2, $streamer->output );
		$this->assertStringContainsString( self::FRIENDLY_ERROR_MESSAGE, $streamer->output[0] );
	}

	public function test_stream_hides_raw_exception_detail_and_logs_it(): void {
		$streamer                   = new TestableStreamer();
		$streamer->client_available = true;
		$streamer->attempts         = array(
			static function (): iterable {
				throw new \RuntimeException( 'sk-secret-key leaked detail' );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertStringNotContainsString( 'sk-secret-key', implode( '', $streamer->output ) );
		$this->assertStringContainsString( 'sk-secret-key leaked detail', implode( "\n", $streamer->logged ) );
	}

	public function test_stream_stops_when_connection_aborted(): void {
		$streamer                     = new TestableStreamer();
		$streamer->client_available   = true;
		$streamer->abort_after_events = 1;
		$streamer->attempts           = array(
			static function (): iterable {
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'Hi' ) ) );
				yield new FakeStreamEvent( array( 'event' => 'response.output_text.delta', 'data' => array( 'delta' => 'never read' ) ) );
			},
		);

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertCount( 1, $streamer->output );
		$this->assertStringContainsString( '"delta":"Hi"', $streamer->output[0] );
		$this->assertStringNotContainsString( '[DONE]', implode( '', $streamer->output ) );
	}

	public function test_emit_data_formats_as_sse(): void {
		$streamer = new TestableStreamer();

		$streamer->testEmitData( array( 'id' => 'test-123', 'choices' => array() ) );

		$this->assertCount( 1, $streamer->output );
		$expected = 'data: ' . json_encode( array( 'id' => 'test-123', 'choices' => array() ) ) . "\n\n";
		$this->assertSame( $expected, $streamer->output[0] );
	}

	public function test_emit_done_sends_done_marker(): void {
		$streamer = new TestableStreamer();

		$streamer->testEmitDone();

		$this->assertCount( 1, $streamer->output );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[0] );
	}

	public function test_emit_error_formats_error_as_sse(): void {
		$streamer = new TestableStreamer();

		$streamer->testEmitError( 'something went wrong' );

		$this->assertCount( 1, $streamer->output );
		$decoded = json_decode( substr( $streamer->output[0], 6, -2 ), true ); // strip "data: " and "\n\n"
		$this->assertSame( 'something went wrong', $decoded['error']['message'] );
	}

	public function test_response_completed_callback_receives_usage(): void {
		$streamer       = new TestableStreamer();
		$captured_usage = null;

		$streamer->set_on_response_completed( static function ( array $usage ) use ( &$captured_usage ): void {
			$captured_usage = $usage;
		} );

		$streamer->testMaybeNotifyResponseCompleted( array(
			'event' => 'response.completed',
			'data'  => array(
				'type'     => 'response.completed',
				'response' => array(
					'usage' => array(
						'input_tokens'         => 1000,
						'input_tokens_details' => array( 'cached_tokens' => 800 ),
						'output_tokens'        => 200,
						'total_tokens'         => 1200,
					),
				),
			),
		) );

		$this->assertIsArray( $captured_usage );
		$this->assertSame( 1000, $captured_usage['input_tokens'] );
		$this->assertSame( 800, $captured_usage['input_tokens_details']['cached_tokens'] );
		$this->assertSame( 200, $captured_usage['output_tokens'] );
		$this->assertSame( 1200, $captured_usage['total_tokens'] );
	}

	public function test_response_completed_callback_not_fired_for_other_events(): void {
		$streamer = new TestableStreamer();
		$fired    = false;

		$streamer->set_on_response_completed( static function ( array $usage ) use ( &$fired ): void {
			$fired = true;
		} );

		$streamer->testMaybeNotifyResponseCompleted( array(
			'event' => 'response.output_text.delta',
			'data'  => array( 'delta' => 'Hi' ),
		) );

		$this->assertFalse( $fired );
	}

	public function test_response_completed_callback_not_fired_without_usage(): void {
		$streamer = new TestableStreamer();
		$fired    = false;

		$streamer->set_on_response_completed( static function ( array $usage ) use ( &$fired ): void {
			$fired = true;
		} );

		$streamer->testMaybeNotifyResponseCompleted( array(
			'event' => 'response.completed',
			'data'  => array(
				'type'     => 'response.completed',
				'response' => array( 'usage' => null ),
			),
		) );

		$this->assertFalse( $fired );
	}

	public function test_completed_event_without_callback_is_a_noop(): void {
		$streamer = new TestableStreamer();

		$streamer->testMaybeNotifyResponseCompleted( array(
			'event' => 'response.completed',
			'data'  => array(
				'type'     => 'response.completed',
				'response' => array( 'usage' => array( 'total_tokens' => 1 ) ),
			),
		) );

		$this->assertSame( array(), $streamer->output );
	}

	private static function make_transporter_exception(): \OpenAI\Exceptions\TransporterException {
		return new \OpenAI\Exceptions\TransporterException(
			new \GuzzleHttp\Exception\ConnectException(
				'Connection timed out',
				new \GuzzleHttp\Psr7\Request( 'POST', 'https://api.openai.com/v1/responses' )
			)
		);
	}
}

/**
 * Fake streamed event mirroring the SDK's toArray() surface.
 */
class FakeStreamEvent {
	/** @var array<string, mixed> */
	private array $payload;

	/**
	 * @param array<string, mixed> $payload
	 */
	public function __construct( array $payload ) {
		$this->payload = $payload;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return $this->payload;
	}
}

/**
 * Testable subclass that captures output/logs instead of echoing, and feeds
 * scripted stream attempts instead of calling OpenAI.
 */
class TestableStreamer extends WPAIP_Streamer {
	/** @var array<int, string> */
	public array $output = array();

	/** @var array<int, string> */
	public array $logged = array();

	/** @var array<int, callable(): iterable<int, FakeStreamEvent>> One callable per createStreamed attempt. */
	public array $attempts = array();

	public int $attempt_count = 0;

	public int $waits = 0;

	public bool $client_available = false;

	/** Report the connection as aborted once this many events were emitted (-1: never). */
	public int $abort_after_events = -1;

	private int $emitted_event_count = 0;

	public function __construct() {
		// Skip parent — no OpenAI client.
	}

	public function has_client(): bool {
		return $this->client_available;
	}

	protected function create_streamed_response( array $params ): iterable {
		$attempt = $this->attempts[ $this->attempt_count ] ?? static fn(): array => array();
		$this->attempt_count++;

		return $attempt();
	}

	protected function is_connection_aborted(): bool {
		return $this->abort_after_events >= 0 && $this->emitted_event_count >= $this->abort_after_events;
	}

	protected function wait_before_retry(): void {
		$this->waits++;
	}

	protected function log_error( string $message ): void {
		$this->logged[] = $message;
	}

	protected function emit_data( array $data ): void {
		$this->output[] = 'data: ' . json_encode( $data ) . "\n\n";
		$this->emitted_event_count++;
	}

	protected function emit_done(): void {
		$this->output[] = "data: [DONE]\n\n";
	}

	protected function emit_error( string $message ): void {
		$this->output[] = 'data: ' . json_encode( array( 'error' => array( 'message' => $message ) ) ) . "\n\n";
	}

	protected function flush(): void {
		// No-op in tests.
	}

	public function testEmitData( array $data ): void {
		$this->emit_data( $data );
	}

	public function testEmitDone(): void {
		$this->emit_done();
	}

	public function testEmitError( string $message ): void {
		$this->emit_error( $message );
	}

	/**
	 * @param array<string, mixed> $event_payload
	 */
	public function testMaybeNotifyResponseCompleted( array $event_payload ): void {
		$this->maybe_notify_response_completed( $event_payload );
	}
}
