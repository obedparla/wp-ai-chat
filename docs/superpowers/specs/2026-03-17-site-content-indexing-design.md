# Site Content Indexing — Design Spec

## Problem

The chatbot understands WooCommerce products but knows nothing about the rest of the website — pages, blog posts, shipping policies, return policies, about us, contact info, FAQs, etc. Most user questions are about these topics, not products.

## Solution

Index WordPress pages, posts, and custom post types into a separate TNTSearch index. Add two new AI tools: one to search content by query (returns snippets), and one to fetch full page content when the bot needs deeper context.

## Architecture

### Index

- New file: `content.index` in the same directory as `products.index` (`wp-content/uploads/wpaic/search/`)
- Indexed fields per post: title + full body text (HTML stripped, shortcodes stripped via `wp_strip_all_tags(do_shortcode(...))`)
- No meta fields, no ACF, no Yoex — just core post content
- All published posts of admin-selected post types, no count or recency caps
- Separate from product index — no changes to `products.index`

### New class: `WPAIC_Content_Index`

Lives in `wp-ai-chatbot/includes/class-wpaic-content-index.php`. Follows the same patterns as `WPAIC_Search_Index`.

**Methods:**

- `build_index(): bool` — full rebuild. Queries all published posts of selected types, strips HTML/shortcodes, inserts into `content.index`
- `search(string $query, int $limit = 5): array` — fuzzy search, returns array of `[post_id, title, url, snippet]`. Snippet is ~500 chars around the matched text
- `get_page_content(int $post_id): ?array` — returns full post content (title, url, full text stripped of HTML) for a single post
- `index_post(int $post_id): bool` — add/update a single post in the index
- `remove_post(int $post_id): bool` — remove a single post from the index
- `get_index_status(): array` — returns `[exists, post_count, last_updated, indexed_post_types]`
- `get_selected_post_types(): array` — reads from `wpaic_settings['content_index_post_types']`, defaults to `['page', 'post']`

**Index name:** `content.index`

**TNTSearch config:** same as products — filesystem driver, fuzziness enabled, language set to 'no'.

### Snippet generation

When `search()` returns results, for each matching post:

1. Get the post's full stripped content
2. Find the position of the query terms in the content
3. Extract ~500 chars centered on the first match
4. If no positional match found (fuzzy matched on title), return first 500 chars

This keeps tool responses token-efficient while giving the AI enough context to answer.

### Hooks for real-time sync

Register in `WPAIC_Loader` (same pattern as product hooks):

- `save_post` → if post type is in selected types and status is `publish`, call `index_post()`
- `before_delete_post` → call `remove_post()`
- `wp_trash_post` → call `remove_post()`
- `transition_post_status` → if transitioning away from `publish`, call `remove_post()`; if transitioning to `publish`, call `index_post()`

Guard: only fire if the post type is in the admin's selected post types list.

### New AI tools

Added to `get_tool_definitions()` in `class-wpaic-chat.php`. These tools are NOT gated behind WooCommerce — they work on any WordPress site.

**Tool 1: `search_site_content`**

```
name: search_site_content
description: Search the website's pages, posts, and other content. Use when the user asks about policies, contact info, FAQs, company info, or any non-product question.
parameters:
  query (string, required): Search query
```

Returns top 5 results, each with: `post_id`, `title`, `url`, `snippet` (~500 chars).

**Tool 2: `get_page_content`**

```
name: get_page_content
description: Get the full text content of a specific page or post. Use when search_site_content returned a relevant result but the snippet doesn't contain enough detail to answer the user's question.
parameters:
  post_id (integer, required): The post ID from search_site_content results
```

Returns: `post_id`, `title`, `url`, `content` (full text, HTML stripped).

### Tool implementation

Added to `class-wpaic-tools.php`:

- `search_site_content(array $args): array` — instantiates `WPAIC_Content_Index`, calls `search()`, returns results
- `get_page_content(array $args): ?array` — instantiates `WPAIC_Content_Index`, calls `get_page_content()`, returns result

### Tool execution

Added to `execute_tool()` in `class-wpaic-chat.php`:

```php
'search_site_content' => $this->execute_content_tool('search_site_content', $arguments),
'get_page_content' => $this->execute_content_tool('get_page_content', $arguments),
```

These are NOT inside the `wpaic_is_woocommerce_active()` gate — content indexing works without WooCommerce.

### Bot response behavior

The AI answers from the content naturally, citing the source with an inline link: `Source: [Page Title](url)`. No new frontend UI component needed — standard markdown rendering handles this.

The system prompt gets a small addition when content indexing is active:

> "You have access to the website's pages and posts. When users ask about policies, contact info, company details, or other non-product topics, use the search_site_content tool. Answer naturally from the content and cite the source page."

## Admin UI

Extend the existing **Search Index** tab (`render_search_tab()` in `class-wpaic-admin.php`).

### New section: "Site Content Index"

Placed below the existing "Product Search Index" section. Contains:

1. **Post type checkboxes** — list all public post types (`get_post_types(['public' => true])`) excluding `product` (already handled by product index) and `attachment`. Each with a checkbox. Default: `page` and `post` checked.
2. **Index status** — same pattern as products: "X pages/posts indexed" + "Last updated: date" or "Not built yet"
3. **Rebuild button** — "Rebuild Content Index" button, same AJAX pattern as product rebuild

### Settings storage

New key in `wpaic_settings`: `content_index_post_types` (array of post type slugs). Saved via the existing settings save flow.

### New AJAX handler

`ajax_rebuild_content_index()` — same pattern as `ajax_rebuild_index()`. Registered as `wp_ajax_wpaic_rebuild_content_index`.

### Index meta storage

New option key: `wpaic_content_index_meta` with `post_count`, `last_updated`, `post_types` (array of which types were indexed).

## What's NOT in scope

- Meta fields / ACF / Yoast SEO data
- Unified search across products + content
- Policy page boosting or pre-loading into system prompt
- Content cards or new frontend components
- Shortcode execution for complex page builders (strip shortcodes, don't render them — page builder content may not make sense as plain text, but core WordPress content will)

## Files to create

| File | Purpose |
|------|---------|
| `includes/class-wpaic-content-index.php` | Content indexing + search + snippet generation |

## Files to modify

| File | Change |
|------|--------|
| `includes/class-wpaic-chat.php` | Add tool definitions for `search_site_content` and `get_page_content`; add to `execute_tool()`; add system prompt addition |
| `includes/class-wpaic-tools.php` | Add `search_site_content()` and `get_page_content()` methods |
| `includes/class-wpaic-admin.php` | Extend Search Index tab with content index UI; add AJAX handler |
| `includes/class-wpaic-loader.php` | Register post save/delete/trash hooks for content index sync; require new class file |
| `wp-ai-chatbot.php` | Require `class-wpaic-content-index.php` (if not autoloaded) |

## Edge cases

- **Empty index**: if no post types selected or index not built, `search_site_content` tool should not appear in tool definitions (same pattern as custom data tool — only add if there's something to search)
- **Post type deregistered**: if a previously-indexed post type plugin is deactivated, the index still has stale entries. Next rebuild cleans them up. No runtime error — TNTSearch returns IDs, and `get_post()` returns null for missing posts, which we skip.
- **Very long pages**: `get_page_content` returns the full stripped text. For extremely long pages (10k+ words), this could burn tokens. Accept this for now — the two-tool pattern means this only happens when the AI specifically needs more context, which is rare.
- **Password-protected posts**: exclude from indexing (`post_status = 'publish'` already handles this, but also check `post_password` is empty)
- **Shortcode-heavy content**: `wp_strip_all_tags(do_shortcode($content))` will render simple shortcodes but page builder shortcodes will produce garbled text. `wp_strip_all_tags(strip_shortcodes($content))` is safer — strips shortcodes without rendering them, keeping only raw text. Use `strip_shortcodes()`.
