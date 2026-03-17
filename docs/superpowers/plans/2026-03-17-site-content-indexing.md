# Site Content Indexing Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Index WordPress pages, posts, and custom post types into TNTSearch so the chatbot can answer questions about site content (policies, FAQs, contact info, etc.).

**Architecture:** New `WPAIC_Content_Index` class mirrors the existing `WPAIC_Search_Index` pattern — separate `content.index` file, fuzzy search via TNTSearch, snippet extraction. Two new AI tools (`search_site_content`, `get_page_content`) let the bot search and read indexed content. Admin UI extends the existing Search Index tab.

**Tech Stack:** PHP 8.2, TNTSearch (already a Composer dependency), WordPress APIs, PHPUnit 11

**Spec:** `docs/superpowers/specs/2026-03-17-site-content-indexing-design.md`

---

## File Structure

| File | Action | Responsibility |
|------|--------|----------------|
| `includes/class-wpaic-content-index.php` | Create | Content indexing, search, snippet generation, fallback search |
| `includes/class-wpaic-tools.php` | Modify | Add `search_site_content()` and `get_page_content()` methods |
| `includes/class-wpaic-chat.php` | Modify | Add tool definitions, tool execution routing, system prompt addition |
| `includes/class-wpaic-loader.php` | Modify | Require new class, add `init_content_index()` with WordPress hooks |
| `includes/class-wpaic-admin.php` | Modify | Extend Search Index tab UI, add AJAX handler, settings save hook |
| `wp-ai-chatbot.php` | Modify | Add `content_index_post_types` default to activation, call `build_index()` |
| `tests/WPAIC_ContentIndexTest.php` | Create | Unit tests for content index class |
| `tests/stubs/wp-stubs.php` | Modify | Add `strip_shortcodes()` stub, `get_post_types()` stub, `post_password` support |

---

### Task 1: Add test stubs for content indexing

**Files:**
- Modify: `tests/stubs/wp-stubs.php`

- [ ] **Step 1: Add `strip_shortcodes()` stub**

In `wp-stubs.php`, after the `wp_strip_all_tags()` function, add:

```php
if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( string $content ): string {
		return preg_replace( '/\[.*?\]/', '', $content );
	}
}
```

- [ ] **Step 2: Add `get_post_types()` stub**

After `strip_shortcodes`, add:

```php
if ( ! function_exists( 'get_post_types' ) ) {
	function get_post_types( array $args = array(), string $output = 'names' ): array {
		$types = WPAICTestHelper::get_option( 'mock_post_types', array( 'post', 'page', 'product', 'attachment' ) );
		if ( ! empty( $args['public'] ) ) {
			// In tests, assume all mock types are public
			return $types;
		}
		return $types;
	}
}
```

- [ ] **Step 3: Add `post_password` support to `WP_Post`**

Add `public string $post_password = '';` to the `WP_Post` class properties (after `post_status`).

- [ ] **Step 4: Fix `WP_Query` stub to support array `post_type`**

In `get_mock_query_posts()`, the existing `post_type` filter does `$p->post_type === $query_args['post_type']` (string equality). Fix it to handle arrays:

```php
if ( isset( $query_args['post_type'] ) ) {
	$allowed_types = (array) $query_args['post_type'];
	$filtered = array_filter( $filtered, fn( $p ) => in_array( $p->post_type, $allowed_types, true ) );
}
```

- [ ] **Step 5: Add `has_password` and `post_status` filtering to `WPAICTestHelper::get_mock_query_posts()`**

In the `get_mock_query_posts()` method, add these filters after the existing post_type filter:

```php
if ( isset( $args['post_status'] ) ) {
	$filtered = array_filter( $filtered, fn( $p ) => $p->post_status === $args['post_status'] );
}

if ( isset( $args['has_password'] ) && false === $args['has_password'] ) {
	$filtered = array_filter( $filtered, fn( $p ) => '' === $p->post_password );
}
```

- [ ] **Step 6: Run tests to verify stubs don't break anything**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit`
Expected: all existing tests pass.

- [ ] **Step 7: Commit**

```bash
git add tests/stubs/wp-stubs.php
git commit -m "test: add stubs for content indexing (strip_shortcodes, get_post_types, post_password, array post_type)"
```

---

### Task 2: Create `WPAIC_Content_Index` — core build and search

**Files:**
- Create: `includes/class-wpaic-content-index.php`
- Create: `tests/WPAIC_ContentIndexTest.php`

- [ ] **Step 1: Write test for `get_selected_post_types()` defaults**

Create `tests/WPAIC_ContentIndexTest.php`:

```php
<?php

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
		// Clean up index file if created
		$upload_dir = wp_upload_dir();
		$index_file = $upload_dir['basedir'] . '/wpaic/search/content.index';
		if ( file_exists( $index_file ) ) {
			unlink( $index_file );
		}
		parent::tearDown();
	}

	public function test_get_selected_post_types_returns_defaults(): void {
		$types = $this->index->get_selected_post_types();
		$this->assertEquals( array( 'page', 'post' ), $types );
	}

	public function test_get_selected_post_types_reads_from_settings(): void {
		WPAICTestHelper::set_option( 'wpaic_settings', array(
			'content_index_post_types' => array( 'page', 'post', 'faq' ),
		) );
		$index = new WPAIC_Content_Index();
		$types = $index->get_selected_post_types();
		$this->assertEquals( array( 'page', 'post', 'faq' ), $types );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ContentIndexTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create `class-wpaic-content-index.php` with `get_selected_post_types()`**

Create `includes/class-wpaic-content-index.php`:

```php
<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TeamTNT\TNTSearch\TNTSearch;

class WPAIC_Content_Index {

	private ?TNTSearch $tnt = null;
	private string $index_path;
	private string $index_name = 'content.index';

	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->index_path = $upload_dir['basedir'] . '/wpaic/search/';
	}

	private function get_tnt(): TNTSearch {
		if ( null === $this->tnt ) {
			$this->tnt = new TNTSearch();
			$this->tnt->loadConfig(
				array(
					'driver'    => 'filesystem',
					'storage'   => $this->index_path,
					'fuzziness' => true,
				)
			);
		}
		return $this->tnt;
	}

	private function ensure_directory(): bool {
		if ( ! file_exists( $this->index_path ) ) {
			return wp_mkdir_p( $this->index_path );
		}
		return true;
	}

	/**
	 * @return array<int, string>
	 */
	public function get_selected_post_types(): array {
		$settings = get_option( 'wpaic_settings', array() );
		if ( is_array( $settings ) && ! empty( $settings['content_index_post_types'] ) && is_array( $settings['content_index_post_types'] ) ) {
			return $settings['content_index_post_types'];
		}
		return array( 'page', 'post' );
	}
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ContentIndexTest.php`
Expected: PASS

- [ ] **Step 5: Write test for `build_index()`**

Add to `WPAIC_ContentIndexTest.php`:

```php
public function test_build_index_indexes_published_pages_and_posts(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'Return Policy',
		'post_content' => 'You can return items within 30 days.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 2,
		'post_title'   => 'About Us',
		'post_content' => 'We are a great company.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	$result = $this->index->build_index();
	$this->assertTrue( $result );

	$status = $this->index->get_index_status();
	$this->assertTrue( $status['exists'] );
	$this->assertEquals( 2, $status['post_count'] );
}

public function test_build_index_excludes_password_protected_posts(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'            => 1,
		'post_title'    => 'Public Page',
		'post_content'  => 'Public content.',
		'post_type'     => 'page',
		'post_status'   => 'publish',
		'post_password' => '',
	) );
	WPAICTestHelper::add_mock_post( array(
		'ID'            => 2,
		'post_title'    => 'Secret Page',
		'post_content'  => 'Secret content.',
		'post_type'     => 'page',
		'post_status'   => 'publish',
		'post_password' => 'secret123',
	) );

	$this->index->build_index();
	$status = $this->index->get_index_status();
	$this->assertEquals( 1, $status['post_count'] );
}
```

- [ ] **Step 6: Implement `build_index()`, `get_index_status()`, and `update_index_meta()`**

Add to `WPAIC_Content_Index`:

```php
public function build_index(): bool {
	if ( ! $this->ensure_directory() ) {
		return false;
	}

	$index_file = $this->index_path . $this->index_name;
	if ( file_exists( $index_file ) ) {
		wp_delete_file( $index_file );
	}

	$tnt     = $this->get_tnt();
	$indexer = $tnt->createIndex( $this->index_name );
	$indexer->setLanguage( 'no' );

	$posts = $this->get_all_content();
	$count = 0;

	foreach ( $posts as $post_data ) {
		$document = $post_data['title'] . ' ' . $post_data['content'];
		$indexer->insert( array(
			'id'   => $post_data['id'],
			'text' => $document,
		) );
		++$count;
	}

	$this->update_index_meta( $count );
	return true;
}

/**
 * @return array<int, array{id: int, title: string, content: string}>
 */
private function get_all_content(): array {
	$post_types = $this->get_selected_post_types();
	$args       = array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'has_password'   => false,
		'posts_per_page' => -1,
		'fields'         => 'ids',
	);

	$query = new WP_Query( $args );
	$posts = array();

	foreach ( $query->posts as $post_id ) {
		$data = $this->get_post_data( (int) $post_id );
		if ( $data ) {
			$posts[] = $data;
		}
	}

	return $posts;
}

/**
 * @return array{id: int, title: string, content: string}|null
 */
private function get_post_data( int $post_id ): ?array {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return null;
	}

	$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

	return array(
		'id'      => $post_id,
		'title'   => $post->post_title,
		'content' => $content,
	);
}

private function update_index_meta( int $count ): void {
	update_option(
		'wpaic_content_index_meta',
		array(
			'post_count'   => $count,
			'last_updated' => current_time( 'mysql' ),
			'post_types'   => $this->get_selected_post_types(),
		)
	);
}

/**
 * @return array{exists: bool, post_count: int, last_updated: ?string, indexed_post_types: array<string>}
 */
public function get_index_status(): array {
	$index_file = $this->index_path . $this->index_name;
	$meta       = get_option( 'wpaic_content_index_meta', array() );
	$meta       = is_array( $meta ) ? $meta : array();

	return array(
		'exists'             => file_exists( $index_file ),
		'post_count'         => isset( $meta['post_count'] ) ? (int) $meta['post_count'] : 0,
		'last_updated'       => isset( $meta['last_updated'] ) ? $meta['last_updated'] : null,
		'indexed_post_types' => isset( $meta['post_types'] ) && is_array( $meta['post_types'] ) ? $meta['post_types'] : array(),
	);
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ContentIndexTest.php`
Expected: PASS

- [ ] **Step 8: Write test for `search()` with snippet generation**

Add to test file:

```php
public function test_search_returns_results_with_snippets(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'Return Policy',
		'post_content' => 'You can return items within 30 days of purchase for a full refund.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 2,
		'post_title'   => 'About Us',
		'post_content' => 'We are a company that sells widgets.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	$this->index->build_index();
	$results = $this->index->search( 'return' );

	$this->assertNotEmpty( $results );
	$this->assertArrayHasKey( 'post_id', $results[0] );
	$this->assertArrayHasKey( 'title', $results[0] );
	$this->assertArrayHasKey( 'url', $results[0] );
	$this->assertArrayHasKey( 'snippet', $results[0] );
	$this->assertEquals( 'Return Policy', $results[0]['title'] );
}

public function test_search_returns_empty_array_when_no_matches(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'About Us',
		'post_content' => 'We are a company.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	$this->index->build_index();
	$results = $this->index->search( 'xyznonexistent' );

	$this->assertIsArray( $results );
}

public function test_search_falls_back_to_wp_query_when_no_index(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'Return Policy',
		'post_content' => 'You can return items within 30 days.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	// Don't build index — should fall back
	$results = $this->index->search( 'return' );

	$this->assertNotEmpty( $results );
	$this->assertEquals( 1, $results[0]['post_id'] );
}
```

- [ ] **Step 9: Implement `search()` with snippet generation and fallback**

Add to `WPAIC_Content_Index`:

```php
/**
 * @return array<int, array{post_id: int, title: string, url: string, snippet: string}>
 */
public function search( string $query, int $limit = 5 ): array {
	$index_file = $this->index_path . $this->index_name;

	if ( ! file_exists( $index_file ) ) {
		return $this->fallback_search( $query, $limit );
	}

	$tnt = $this->get_tnt();
	$tnt->selectIndex( $this->index_name );
	$tnt->fuzziness = true;

	$results  = $tnt->search( $query, $limit * 2 );
	$post_ids = isset( $results['ids'] ) && is_array( $results['ids'] ) ? array_map( 'intval', $results['ids'] ) : array();

	if ( empty( $post_ids ) ) {
		return $this->fallback_search( $query, $limit );
	}

	return $this->format_results( array_slice( $post_ids, 0, $limit ), $query );
}

/**
 * @return array<int, array{post_id: int, title: string, url: string, snippet: string}>
 */
private function fallback_search( string $query, int $limit ): array {
	$post_types = $this->get_selected_post_types();
	$args       = array(
		'post_type'      => $post_types,
		'post_status'    => 'publish',
		'has_password'   => false,
		's'              => $query,
		'posts_per_page' => $limit,
		'fields'         => 'ids',
	);

	$wp_query = new WP_Query( $args );
	$post_ids = array_map( 'intval', $wp_query->posts );

	return $this->format_results( $post_ids, $query );
}

/**
 * @param array<int> $post_ids
 * @return array<int, array{post_id: int, title: string, url: string, snippet: string}>
 */
private function format_results( array $post_ids, string $query ): array {
	$results = array();
	foreach ( $post_ids as $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			continue;
		}
		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );
		$results[] = array(
			'post_id' => $post_id,
			'title'   => $post->post_title,
			'url'     => get_permalink( $post_id ),
			'snippet' => $this->generate_snippet( $content, $query ),
		);
	}
	return $results;
}

private function generate_snippet( string $content, string $query, int $length = 500 ): string {
	if ( '' === $content ) {
		return '';
	}

	$lower_content = strtolower( $content );
	$lower_query   = strtolower( $query );

	// Find position of query terms
	$words = explode( ' ', $lower_query );
	$position = false;
	foreach ( $words as $word ) {
		if ( '' === $word ) {
			continue;
		}
		$position = strpos( $lower_content, $word );
		if ( false !== $position ) {
			break;
		}
	}

	if ( false === $position ) {
		return mb_substr( $content, 0, $length );
	}

	$half   = (int) ( $length / 2 );
	$start  = max( 0, $position - $half );
	$snippet = mb_substr( $content, $start, $length );

	if ( $start > 0 ) {
		$snippet = '...' . $snippet;
	}
	if ( $start + $length < mb_strlen( $content ) ) {
		$snippet .= '...';
	}

	return $snippet;
}
```

- [ ] **Step 10: Run tests**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ContentIndexTest.php`
Expected: PASS

- [ ] **Step 11: Write test for `get_page_content()`**

Add to test file:

```php
public function test_get_page_content_returns_full_content(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'Return Policy',
		'post_content' => 'Full return policy text here with all details.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	$result = $this->index->get_page_content( 1 );

	$this->assertNotNull( $result );
	$this->assertEquals( 1, $result['post_id'] );
	$this->assertEquals( 'Return Policy', $result['title'] );
	$this->assertStringContainsString( 'Full return policy text', $result['content'] );
	$this->assertArrayHasKey( 'url', $result );
}

public function test_get_page_content_returns_null_for_nonexistent(): void {
	$result = $this->index->get_page_content( 999 );
	$this->assertNull( $result );
}
```

- [ ] **Step 12: Implement `get_page_content()`**

Add to `WPAIC_Content_Index`:

```php
/**
 * @return array{post_id: int, title: string, url: string, content: string}|null
 */
public function get_page_content( int $post_id ): ?array {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ) {
		return null;
	}

	return array(
		'post_id' => $post_id,
		'title'   => $post->post_title,
		'url'     => get_permalink( $post_id ),
		'content' => wp_strip_all_tags( strip_shortcodes( $post->post_content ) ),
	);
}
```

- [ ] **Step 13: Write tests for `index_post()` and `remove_post()`**

Add to test file:

```php
public function test_index_post_adds_to_existing_index(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'About Us',
		'post_content' => 'We are great.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );
	$this->index->build_index();

	WPAICTestHelper::add_mock_post( array(
		'ID'           => 2,
		'post_title'   => 'Contact Us',
		'post_content' => 'Email us at hello@example.com.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );
	$result = $this->index->index_post( 2 );
	$this->assertTrue( $result );

	$results = $this->index->search( 'contact' );
	$this->assertNotEmpty( $results );
	$this->assertEquals( 'Contact Us', $results[0]['title'] );
}

public function test_remove_post_removes_from_index(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'Old Page',
		'post_content' => 'Old content to be removed.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );
	$this->index->build_index();

	$result = $this->index->remove_post( 1 );
	$this->assertTrue( $result );
}

public function test_index_post_triggers_full_build_when_no_index(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 1,
		'post_title'   => 'New Page',
		'post_content' => 'Brand new content.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	$result = $this->index->index_post( 1 );
	$this->assertTrue( $result );

	$status = $this->index->get_index_status();
	$this->assertTrue( $status['exists'] );
}
```

- [ ] **Step 14: Implement `index_post()` and `remove_post()`**

Add to `WPAIC_Content_Index`:

```php
public function index_post( int $post_id ): bool {
	$index_file = $this->index_path . $this->index_name;
	if ( ! file_exists( $index_file ) ) {
		return $this->build_index();
	}

	$data = $this->get_post_data( $post_id );
	if ( ! $data ) {
		return false;
	}

	$tnt = $this->get_tnt();
	$tnt->selectIndex( $this->index_name );
	$indexer = $tnt->getIndex();

	$document = $data['title'] . ' ' . $data['content'];
	$indexer->update(
		$post_id,
		array(
			'id'   => $post_id,
			'text' => $document,
		)
	);

	return true;
}

public function remove_post( int $post_id ): bool {
	$index_file = $this->index_path . $this->index_name;
	if ( ! file_exists( $index_file ) ) {
		return true;
	}

	$tnt = $this->get_tnt();
	$tnt->selectIndex( $this->index_name );
	$indexer = $tnt->getIndex();
	$indexer->delete( $post_id );

	return true;
}
```

- [ ] **Step 15: Run all tests**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ContentIndexTest.php`
Expected: PASS

- [ ] **Step 16: Run full test suite**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit`
Expected: all tests pass, no regressions.

- [ ] **Step 17: Commit**

```bash
git add includes/class-wpaic-content-index.php tests/WPAIC_ContentIndexTest.php
git commit -m "feat: add WPAIC_Content_Index class with build, search, snippet generation"
```

---

### Task 3: Add tool methods to `WPAIC_Tools`

**Files:**
- Modify: `includes/class-wpaic-tools.php`
- Modify: `tests/WPAIC_ToolsTest.php`

- [ ] **Step 1: Write tests for the new tool methods**

Add to `tests/WPAIC_ToolsTest.php`, at the top add require:

```php
require_once __DIR__ . '/../includes/class-wpaic-content-index.php';
```

Then add test methods:

```php
// --- Content search tests ---

public function test_search_site_content_returns_results(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 100,
		'post_title'   => 'Shipping Policy',
		'post_content' => 'We ship within 3-5 business days.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	// Build index first
	$content_index = new WPAIC_Content_Index();
	$content_index->build_index();

	$result = $this->tools->search_site_content( array( 'query' => 'shipping' ) );

	$this->assertArrayHasKey( 'results', $result );
	$this->assertNotEmpty( $result['results'] );
	$this->assertEquals( 'Shipping Policy', $result['results'][0]['title'] );
}

public function test_search_site_content_requires_query(): void {
	$result = $this->tools->search_site_content( array() );
	$this->assertArrayHasKey( 'error', $result );
}

public function test_get_page_content_returns_full_content(): void {
	WPAICTestHelper::add_mock_post( array(
		'ID'           => 100,
		'post_title'   => 'About Us',
		'post_content' => 'We are a great company that does great things.',
		'post_type'    => 'page',
		'post_status'  => 'publish',
	) );

	$result = $this->tools->get_page_content( array( 'post_id' => 100 ) );

	$this->assertArrayHasKey( 'title', $result );
	$this->assertEquals( 'About Us', $result['title'] );
	$this->assertArrayHasKey( 'content', $result );
}

public function test_get_page_content_returns_error_for_missing_post(): void {
	$result = $this->tools->get_page_content( array( 'post_id' => 999 ) );
	$this->assertArrayHasKey( 'error', $result );
}

public function test_get_page_content_requires_post_id(): void {
	$result = $this->tools->get_page_content( array() );
	$this->assertArrayHasKey( 'error', $result );
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ToolsTest.php --filter="search_site_content|get_page_content"`
Expected: FAIL — methods don't exist.

- [ ] **Step 3: Implement the tool methods**

Add to `class-wpaic-tools.php`, after the `query_custom_data()` method:

```php
/**
 * Search site content (pages, posts) by query.
 *
 * @param array{query?: string} $args Search arguments.
 * @return array<string, mixed>
 */
public function search_site_content( array $args ): array {
	$query = isset( $args['query'] ) && is_string( $args['query'] ) ? sanitize_text_field( $args['query'] ) : '';

	if ( '' === $query ) {
		return array( 'error' => 'Search query is required.' );
	}

	$content_index = new WPAIC_Content_Index();
	$results       = $content_index->search( $query );

	return array(
		'results' => $results,
		'count'   => count( $results ),
	);
}

/**
 * Get full content of a page or post.
 *
 * @param array{post_id?: int} $args Arguments.
 * @return array<string, mixed>
 */
public function get_page_content( array $args ): array {
	$post_id = isset( $args['post_id'] ) && is_numeric( $args['post_id'] ) ? (int) $args['post_id'] : 0;

	if ( 0 === $post_id ) {
		return array( 'error' => 'Post ID is required.' );
	}

	$content_index = new WPAIC_Content_Index();
	$result        = $content_index->get_page_content( $post_id );

	if ( null === $result ) {
		return array( 'error' => 'Page not found.' );
	}

	return $result;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit tests/WPAIC_ToolsTest.php`
Expected: all PASS

- [ ] **Step 5: Commit**

```bash
git add includes/class-wpaic-tools.php tests/WPAIC_ToolsTest.php
git commit -m "feat: add search_site_content and get_page_content tool methods"
```

---

### Task 4: Wire tools into `WPAIC_Chat`

**Files:**
- Modify: `includes/class-wpaic-chat.php`

- [ ] **Step 1: Add tool definitions to `get_tool_definitions()`**

In `class-wpaic-chat.php`, in `get_tool_definitions()`, add after the WooCommerce `if` block closes (after line 795) and before the handoff tool:

```php
// Content search tools — work without WooCommerce
$tools[] = array(
	'type'     => 'function',
	'function' => array(
		'name'        => 'search_site_content',
		'description' => "Search the website's pages, posts, and other content. Use when the user asks about policies, contact info, FAQs, company info, or any non-product question.",
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'query' => array(
					'type'        => 'string',
					'description' => 'Search query',
				),
			),
			'required'   => array( 'query' ),
		),
	),
);
$tools[] = array(
	'type'     => 'function',
	'function' => array(
		'name'        => 'get_page_content',
		'description' => 'Get the full text content of a specific page or post. Use when search_site_content returned a relevant result but the snippet does not contain enough detail to answer the question.',
		'parameters'  => array(
			'type'       => 'object',
			'properties' => array(
				'post_id' => array(
					'type'        => 'integer',
					'description' => 'The post ID from search_site_content results',
				),
			),
			'required'   => array( 'post_id' ),
		),
	),
);
```

- [ ] **Step 2: Add tool execution routing to `execute_tool()`**

In `execute_tool()`, add before the `if ( null === $this->tools )` check (before line 1028), after the `query_custom_data` block:

```php
if ( 'search_site_content' === $name ) {
	$tools = new WPAIC_Tools();
	return $tools->search_site_content( $arguments );
}

if ( 'get_page_content' === $name ) {
	$tools = new WPAIC_Tools();
	return $tools->get_page_content( $arguments );
}
```

- [ ] **Step 3: Add content search instruction to system prompt**

In `get_system_prompt()`, add a call to a new method before `get_language_instruction()`. Add this method to the class:

```php
private function get_content_search_instruction(): string {
	return " You have access to the website's pages and posts. When users ask about policies, contact info, company details, or other non-product topics, use the search_site_content tool. If a snippet doesn't contain enough detail, use get_page_content to read the full page. Answer naturally from the content and cite the source page with a link.";
}
```

Then in `get_system_prompt()`, append `$this->get_content_search_instruction()` to each return statement (same pattern as `get_language_instruction()`).

- [ ] **Step 4: Run full test suite**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit`
Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add includes/class-wpaic-chat.php
git commit -m "feat: wire content search tools into chat — definitions, execution, system prompt"
```

---

### Task 5: Register hooks in `WPAIC_Loader`

**Files:**
- Modify: `includes/class-wpaic-loader.php`

- [ ] **Step 1: Add require and init call**

In `load_dependencies()`, add after the `class-wpaic-search-index.php` require:

```php
require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-content-index.php';
```

In `init()`, add after `$this->init_search_index();`:

```php
$this->init_content_index();
```

- [ ] **Step 2: Add `init_content_index()` method**

Add after `init_search_index()`:

Use `transition_post_status` as the single source of truth for publish/unpublish. Don't also hook `save_post` — WordPress fires both on every save, causing double `index_post()` calls.

```php
private function init_content_index(): void {
	$content_index  = new WPAIC_Content_Index();
	$selected_types = $content_index->get_selected_post_types();

	add_action(
		'transition_post_status',
		function ( string $new_status, string $old_status, \WP_Post $post ) use ( $content_index, $selected_types ): void {
			if ( ! in_array( $post->post_type, $selected_types, true ) ) {
				return;
			}
			if ( 'publish' === $new_status && '' === $post->post_password ) {
				$content_index->index_post( $post->ID );
			} elseif ( 'publish' === $old_status && 'publish' !== $new_status ) {
				$content_index->remove_post( $post->ID );
			}
		},
		10,
		3
	);

	add_action(
		'before_delete_post',
		function ( int $post_id ) use ( $content_index, $selected_types ): void {
			if ( in_array( get_post_type( $post_id ), $selected_types, true ) ) {
				$content_index->remove_post( $post_id );
			}
		}
	);

	add_action(
		'wp_trash_post',
		function ( int $post_id ) use ( $content_index, $selected_types ): void {
			if ( in_array( get_post_type( $post_id ), $selected_types, true ) ) {
				$content_index->remove_post( $post_id );
			}
		}
	);
}
```

- [ ] **Step 3: Run full test suite**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit`
Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add includes/class-wpaic-loader.php
git commit -m "feat: register content index hooks in loader — save, delete, trash, transition"
```

---

### Task 6: Extend Admin UI — Search Index tab

**Files:**
- Modify: `includes/class-wpaic-admin.php`

- [ ] **Step 1: Register the new AJAX handler**

In `__construct()` or `init()`, add after the existing `wpaic_rebuild_index` action:

```php
add_action( 'wp_ajax_wpaic_rebuild_content_index', array( $this, 'ajax_rebuild_content_index' ) );
```

- [ ] **Step 2: Add `ajax_rebuild_content_index()` method**

Add after `ajax_rebuild_index()`:

```php
public function ajax_rebuild_content_index(): void {
	check_ajax_referer( 'wpaic_rebuild_content_index', '_wpnonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wp-ai-chatbot' ) ) );
	}

	$content_index = new WPAIC_Content_Index();
	$result        = $content_index->build_index();

	if ( $result ) {
		$status = $content_index->get_index_status();
		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of posts indexed */
					__( 'Content index rebuilt successfully. %d items indexed.', 'wp-ai-chatbot' ),
					$status['post_count']
				),
			)
		);
	} else {
		wp_send_json_error( array( 'message' => __( 'Failed to rebuild content index. Check file permissions.', 'wp-ai-chatbot' ) ) );
	}
}
```

- [ ] **Step 3: Extend `render_search_tab()` with content index section**

At the end of `render_search_tab()`, before the closing `<?php }`, add the content index section. This section contains:

1. A heading "Site Content Index"
2. Post type checkboxes — iterate `get_post_types(['public' => true])`, exclude `product` and `attachment`, check against saved `content_index_post_types` setting (default `page` and `post`)
3. Index status display — same pattern as product index: green icon if exists with count/date, red icon if missing
4. "Rebuild Content Index" button with AJAX handler (same jQuery pattern as product rebuild, different IDs: `#wpaic-rebuild-content-index`, `#wpaic-rebuild-content-status`, action `wpaic_rebuild_content_index`)
5. Save post type selections via a hidden form that POSTs to the existing settings save flow

The HTML/Tailwind styling should match the existing product index section exactly.

- [ ] **Step 4: Save post type selections on settings save**

In the settings sanitization/save callback, handle the `content_index_post_types` key — sanitize as an array of sanitized slugs. Also compare old vs new value; if different, trigger a rebuild:

```php
$old_settings = get_option( 'wpaic_settings', array() );
// ... after saving new settings ...
$old_types = $old_settings['content_index_post_types'] ?? array( 'page', 'post' );
$new_types = $new_settings['content_index_post_types'] ?? array( 'page', 'post' );
if ( $old_types !== $new_types ) {
	$content_index = new WPAIC_Content_Index();
	$content_index->build_index();
}
```

- [ ] **Step 5: Test manually in Local dev site**

1. Go to WP Admin → WP AI Chatbot → Settings → Search Index tab
2. Verify "Site Content Index" section appears below product index
3. Verify post type checkboxes show (page, post checked by default)
4. Click "Rebuild Content Index" — verify success message with count
5. Verify index status shows correctly after rebuild

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpaic-admin.php
git commit -m "feat: add content index section to Search Index admin tab"
```

---

### Task 7: Plugin activation — auto-build content index

**Files:**
- Modify: `wp-ai-chatbot.php`

- [ ] **Step 1: Add default `content_index_post_types` to activation settings**

In `wpaic_activate()`, add `content_index_post_types` to the default settings array:

```php
'content_index_post_types' => array( 'page', 'post' ),
```

- [ ] **Step 2: Build content index on activation**

In `wpaic_activate()`, after `wpaic_create_tables()`, add:

```php
require_once WPAIC_PLUGIN_DIR . 'includes/class-wpaic-content-index.php';
$content_index = new WPAIC_Content_Index();
$content_index->build_index();
```

Note: need the require here because `WPAIC_Loader` hasn't run yet during activation.

- [ ] **Step 3: Run full test suite**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit`
Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add wp-ai-chatbot.php
git commit -m "feat: auto-build content index on plugin activation"
```

---

### Task 8: Integration test — end-to-end verification

**Files:**
- No new files

- [ ] **Step 1: Run full test suite**

Run: `cd wp-ai-chatbot && vendor/bin/phpunit`
Expected: all tests pass, no regressions.

- [ ] **Step 2: Manual smoke test on Local dev site**

1. Deactivate and reactivate the plugin — verify content index builds
2. Go to Settings → Search Index — verify content section shows with count
3. Create a new page "Test Policy" with content "Items can be returned within 14 days"
4. Open the chat widget and ask "what is your return policy?"
5. Verify the bot uses `search_site_content` tool and responds with policy content + source link
6. Ask "tell me more about that" — verify bot uses `get_page_content` if needed
7. Trash the test page — verify it's removed from search results
8. Change post type selections in admin — verify rebuild triggers

- [ ] **Step 3: Final commit if any fixes needed**

```bash
git add -A
git commit -m "fix: address integration test findings"
```

---

## Unresolved Questions

None — all decisions were made during brainstorming.
