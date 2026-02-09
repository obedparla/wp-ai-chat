<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPAIP_Streamer {

	private ?\OpenAI\Client $client = null;

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
	 * Stream a chat completion from OpenAI to the output buffer as SSE.
	 *
	 * @param array<string, mixed> $params OpenAI chat completion params (messages, model, tools, etc.)
	 */
	public function stream( array $params ): void {
		if ( null === $this->client ) {
			$this->emit_error( 'OpenAI API key not configured' );
			$this->emit_done();
			return;
		}

		try {
			$stream = $this->client->chat()->createStreamed( $params );

			foreach ( $stream as $response ) {
				$this->emit_data( $response->toArray() );
			}

			$this->emit_done();
		} catch ( \Exception $exception ) {
			$this->emit_error( $exception->getMessage() );
			$this->emit_done();
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
