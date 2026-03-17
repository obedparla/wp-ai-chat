<?php
/**
 * Tests for WPAIC_Content_Index class.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../includes/class-wpaic-content-index.php';

class WPAIC_ContentIndexTest extends TestCase {
	private WPAIC_Content_Index $index;

	protected function setUp(): void {
		parent::setUp();
		WPAICTestHelper::reset();
		$this->index = new WPAIC_Content_Index();
	}

	protected function tearDown(): void {
		WPAICTestHelper::reset();
		parent::tearDown();
	}

	public function test_get_selected_post_types_returns_defaults_when_no_setting(): void {
		$result = $this->index->get_selected_post_types();

		$this->assertEquals( array( 'page', 'post' ), $result );
	}

	public function test_get_selected_post_types_returns_setting_value_when_configured(): void {
		WPAICTestHelper::set_option(
			'wpaic_settings',
			array( 'content_index_post_types' => array( 'page', 'post', 'product' ) )
		);

		$result = $this->index->get_selected_post_types();

		$this->assertEquals( array( 'page', 'post', 'product' ), $result );
	}

	public function test_get_page_content_returns_null_for_nonexistent_post(): void {
		$result = $this->index->get_page_content( 999 );

		$this->assertNull( $result );
	}

	public function test_get_page_content_returns_null_for_non_published_post(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'Draft Page',
				'post_content' => 'Some content',
				'post_type'    => 'page',
				'post_status'  => 'draft',
			)
		);

		$result = $this->index->get_page_content( 1 );

		$this->assertNull( $result );
	}

	public function test_get_page_content_returns_null_for_password_protected_post(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'            => 1,
				'post_title'    => 'Secret Page',
				'post_content'  => 'Hidden content',
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_password' => 'secret123',
			)
		);

		$result = $this->index->get_page_content( 1 );

		$this->assertNull( $result );
	}

	public function test_get_page_content_returns_null_for_post_not_in_selected_types(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'Custom Type',
				'post_content' => 'Some content',
				'post_type'    => 'custom_type',
				'post_status'  => 'publish',
			)
		);

		$result = $this->index->get_page_content( 1 );

		$this->assertNull( $result );
	}

	public function test_get_page_content_returns_content_for_valid_published_post(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'About Us',
				'post_content' => 'We are a great company.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$result = $this->index->get_page_content( 1 );

		$this->assertNotNull( $result );
		$this->assertEquals( 1, $result['post_id'] );
		$this->assertEquals( 'About Us', $result['title'] );
		$this->assertStringContainsString( 'We are a great company.', $result['content'] );
		$this->assertArrayHasKey( 'url', $result );
	}

	public function test_get_page_content_strips_html_and_shortcodes(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'Formatted Page',
				'post_content' => '<p>Hello <strong>world</strong></p>[shortcode]Extra[/shortcode] text',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$result = $this->index->get_page_content( 1 );

		$this->assertNotNull( $result );
		$this->assertStringNotContainsString( '<p>', $result['content'] );
		$this->assertStringNotContainsString( '<strong>', $result['content'] );
		$this->assertStringNotContainsString( '[shortcode]', $result['content'] );
		$this->assertStringContainsString( 'Hello', $result['content'] );
		$this->assertStringContainsString( 'text', $result['content'] );
	}

	public function test_get_index_status_returns_defaults_when_no_meta(): void {
		$result = $this->index->get_index_status();

		$this->assertFalse( $result['exists'] );
		$this->assertEquals( 0, $result['post_count'] );
		$this->assertNull( $result['last_updated'] );
		$this->assertEquals( array(), $result['indexed_post_types'] );
	}

	public function test_search_fallback_returns_results_when_no_index_file(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'About Our Company',
				'post_content' => 'We sell quality products worldwide.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$results = $this->index->search( 'company' );

		$this->assertNotEmpty( $results );
		$this->assertEquals( 1, $results[0]['post_id'] );
		$this->assertEquals( 'About Our Company', $results[0]['title'] );
		$this->assertArrayHasKey( 'url', $results[0] );
		$this->assertArrayHasKey( 'snippet', $results[0] );
	}

	public function test_search_fallback_returns_empty_when_no_matches(): void {
		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'About Us',
				'post_content' => 'We are great.',
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$results = $this->index->search( 'nonexistent_xyz_term' );

		$this->assertEmpty( $results );
	}

	public function test_search_fallback_generates_snippets(): void {
		$long_content = str_repeat( 'Lorem ipsum dolor sit amet. ', 50 )
			. 'The special keyword appears here in context. '
			. str_repeat( 'Consectetur adipiscing elit. ', 50 );

		WPAICTestHelper::add_mock_post(
			array(
				'ID'           => 1,
				'post_title'   => 'Long Page',
				'post_content' => $long_content,
				'post_type'    => 'page',
				'post_status'  => 'publish',
			)
		);

		$results = $this->index->search( 'keyword' );

		$this->assertNotEmpty( $results );
		$this->assertNotEmpty( $results[0]['snippet'] );
		$this->assertStringContainsString( 'keyword', $results[0]['snippet'] );
	}
}
