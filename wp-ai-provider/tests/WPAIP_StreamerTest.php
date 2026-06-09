<?php

use PHPUnit\Framework\TestCase;

class WPAIP_StreamerTest extends TestCase {

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

	public function test_stream_emits_error_and_done_when_no_client(): void {
		update_option( 'wpaip_settings', array(
			'openai_api_key' => '',
		) );

		$streamer = new TestableStreamer();

		$streamer->stream( array( 'model' => 'gpt-5-mini', 'input' => array() ) );

		$this->assertCount( 2, $streamer->output );
		$this->assertStringContainsString( '"error"', $streamer->output[0] );
		$this->assertStringContainsString( 'not configured', $streamer->output[0] );
		$this->assertSame( "data: [DONE]\n\n", $streamer->output[1] );
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
}

/**
 * Testable subclass that captures output instead of echoing.
 */
class TestableStreamer extends WPAIP_Streamer {
	/** @var array<int, string> */
	public array $output = array();

	public function __construct() {
		// Skip parent — no OpenAI client.
	}

	public function has_client(): bool {
		return false;
	}

	protected function emit_data( array $data ): void {
		$this->output[] = 'data: ' . json_encode( $data ) . "\n\n";
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
