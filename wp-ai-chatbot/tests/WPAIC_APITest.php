<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-page-context.php';
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

	public function test_sanitize_page_context_keeps_allowed_fields_and_strips_query_args(): void {
		$reflection = new ReflectionClass( $this->api );
		$method     = $reflection->getMethod( 'sanitize_page_context' );
		$method->setAccessible( true );

		$result = $method->invoke(
			$this->api,
			array(
				'page_type'  => 'product',
				'title'      => 'Blue Widget',
				'url'        => 'http://example.com/product/blue-widget/?utm_source=test',
				'post_id'    => '42',
				'post_type'  => 'product',
				'product_id' => '42',
				'ignored'    => 'value',
			)
		);

		$this->assertSame(
			array(
				'page_type'  => 'product',
				'title'      => 'Blue Widget',
				'url'        => 'http://example.com/product/blue-widget/',
				'post_id'    => 42,
				'post_type'  => 'product',
				'product_id' => 42,
			),
			$result
		);
	}

	public function test_sanitize_page_context_returns_empty_array_for_invalid_payload(): void {
		$reflection = new ReflectionClass( $this->api );
		$method     = $reflection->getMethod( 'sanitize_page_context' );
		$method->setAccessible( true );

		$this->assertSame( array(), $method->invoke( $this->api, 'not-an-array' ) );
		$this->assertSame(
			array(),
			$method->invoke(
				$this->api,
				array(
					'page_type' => 'invalid',
					'title'     => 'Bad page',
					'url'       => 'http://example.com/bad-page/',
				)
			)
		);
	}

	public function test_send_transcript_rejects_missing_email(): void {
		$request = new WP_REST_Request();
		$request->set_params( array( 'transcript' => 'Hello' ) );

		$result = $this->api->handle_send_transcript( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
	}

	public function test_send_transcript_rejects_invalid_email(): void {
		$request = new WP_REST_Request();
		$request->set_params( array( 'email' => 'not-an-email', 'transcript' => 'Hello' ) );

		$result = $this->api->handle_send_transcript( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'invalid_email', $result->get_error_code() );
	}

	public function test_send_transcript_rejects_empty_transcript(): void {
		$request = new WP_REST_Request();
		$request->set_params( array( 'email' => 'user@example.com', 'transcript' => '' ) );

		$result = $this->api->handle_send_transcript( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'empty_transcript', $result->get_error_code() );
	}

	public function test_send_transcript_rejects_missing_transcript(): void {
		$request = new WP_REST_Request();
		$request->set_params( array( 'email' => 'user@example.com' ) );

		$result = $this->api->handle_send_transcript( $request );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'empty_transcript', $result->get_error_code() );
	}

	public function test_send_transcript_sends_email_on_valid_input(): void {
		$request = new WP_REST_Request();
		$request->set_params( array(
			'email'      => 'user@example.com',
			'transcript' => "You: Hi\n\nAssistant: Hello!",
		) );

		$result = $this->api->handle_send_transcript( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$data = $result->get_data();
		$this->assertTrue( $data['success'] );

		$mail = WPAICTestHelper::get_option( 'test_last_mail' );
		$this->assertEquals( 'user@example.com', $mail['to'] );
		$this->assertStringContainsString( 'conversation', $mail['subject'] );
		$this->assertStringContainsString( "You: Hi\n\nAssistant: Hello!", $mail['message'] );
	}

	public function test_send_transcript_uses_chatbot_name_in_email(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array( 'chatbot_name' => 'ShopBot' ) );

		$request = new WP_REST_Request();
		$request->set_params( array(
			'email'      => 'user@example.com',
			'transcript' => 'Some conversation',
		) );

		$result = $this->api->handle_send_transcript( $request );

		$this->assertInstanceOf( WP_REST_Response::class, $result );
		$mail = WPAICTestHelper::get_option( 'test_last_mail' );
		$this->assertStringContainsString( 'ShopBot', $mail['subject'] );
		$this->assertStringContainsString( 'ShopBot', $mail['message'] );
	}
}
