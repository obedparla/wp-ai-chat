<?php
/**
 * Tests for WPAIC_Frontend config generation.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
require_once __DIR__ . '/../includes/class-wpaic-page-context.php';
require_once __DIR__ . '/../includes/class-wpaic-frontend.php';

class WPAIC_FrontendTest extends TestCase {
	private string $content_index_file;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		$upload_dir               = wp_upload_dir();
		$this->content_index_file = $upload_dir['basedir'] . '/wpaic/search/content.index';
		$this->remove_content_index_file();
	}

	protected function tearDown(): void {
		$this->remove_content_index_file();
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_build_frontend_config_uses_manual_conversation_starters(): void {
		$frontend = new WPAIC_Frontend();
		$config   = $frontend->build_frontend_config(
			array(
				'greeting_message'      => 'Hello!',
				'conversation_starters' => array( 'Custom starter', 'Another starter' ),
			)
		);

		$this->assertEquals( array( 'Custom starter', 'Another starter' ), $config['conversationStarters'] );
	}

	public function test_build_frontend_config_generates_woocommerce_starters_by_default(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );

		$frontend = new WPAIC_Frontend();
		$config   = $frontend->build_frontend_config(
			array(
				'greeting_message' => 'Hello!',
			)
		);

		$this->assertContains( 'Help me find a product', $config['conversationStarters'] );
		$this->assertContains( 'Track my order', $config['conversationStarters'] );
		$this->assertContains( 'Show me product categories', $config['conversationStarters'] );
		$this->assertContains( 'What can you help me with?', $config['conversationStarters'] );
		$this->assertNotContains( 'Compare two products', $config['conversationStarters'] );
	}

	public function test_build_frontend_config_includes_content_starter_when_available(): void {
		WPAICTestHelper::set_option( 'test_woocommerce_active', true );
		$this->create_content_index_file();

		$frontend = new WPAIC_Frontend();
		$config   = $frontend->build_frontend_config(
			array(
				'greeting_message' => 'Hello!',
			)
		);

		$this->assertContains( 'What are your shipping and return policies?', $config['conversationStarters'] );
		$this->assertContains( 'Show me product categories', $config['conversationStarters'] );
		$this->assertNotContains( 'I need help from support', $config['conversationStarters'] );
	}

	public function test_build_frontend_config_includes_page_context(): void {
		$page = WPAICTestHelper::add_mock_post(
			array(
				'ID'         => 30,
				'post_title' => 'Refund Policy',
				'post_type'  => 'page',
			)
		);
		WPAICTestHelper::set_queried_object( $page );
		WPAICTestHelper::set_conditional( 'is_singular', true );

		$frontend = new WPAIC_Frontend();
		$config   = $frontend->build_frontend_config(
			array(
				'greeting_message' => 'Hello!',
			)
		);

		$this->assertArrayHasKey( 'pageContext', $config );
		$this->assertSame( 'singular', $config['pageContext']['page_type'] );
		$this->assertSame( 30, $config['pageContext']['post_id'] );
	}

	private function create_content_index_file(): void {
		$directory = dirname( $this->content_index_file );
		if ( ! file_exists( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		file_put_contents( $this->content_index_file, 'test-index' );
	}

	private function remove_content_index_file(): void {
		if ( file_exists( $this->content_index_file ) ) {
			wp_delete_file( $this->content_index_file );
		}
	}
}
