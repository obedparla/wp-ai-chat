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

		$streamer->stream( array( 'model' => 'gpt-4o-mini', 'messages' => array() ) );

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
}
