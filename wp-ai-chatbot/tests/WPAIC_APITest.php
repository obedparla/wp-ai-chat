<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-api.php';
require_once __DIR__ . '/../includes/class-wpaic-logs.php';

class WPAIC_APITest extends TestCase {
	private WPAIC_API $api;

	protected function setUp(): void {
		WPAICTestHelper::reset();
		$this->api = new WPAIC_API();
	}

	public function test_verify_nonce_returns_error_when_nonce_missing(): void {
		$request = new WP_REST_Request();

		$result = $this->api->verify_nonce( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_verify_nonce_returns_error_when_nonce_invalid(): void {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'invalid_nonce' );

		$result = $this->api->verify_nonce( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'rest_forbidden', $result->get_error_code() );
	}

	public function test_verify_nonce_returns_true_when_nonce_valid(): void {
		$request = new WP_REST_Request();
		$request->set_header( 'X-WP-Nonce', 'test_nonce_wp_rest' );

		$result = $this->api->verify_nonce( $request );

		$this->assertTrue( $result );
	}

	public function test_verify_nonce_is_case_insensitive_for_header(): void {
		$request = new WP_REST_Request();
		$request->set_header( 'x-wp-nonce', 'test_nonce_wp_rest' );

		$result = $this->api->verify_nonce( $request );

		$this->assertTrue( $result );
	}
}
