# Fix: Bot not using search_site_content tool

## Context

The chatbot has `search_site_content` and `get_page_content` tools always registered in `get_tool_definitions()`, but the **system prompt instruction** that tells the bot to use them is gated on whether the index file physically exists (`content.index`).

```php
// class-wpaic-chat.php:609-616
private function get_content_index_instruction(): string {
    $content_index = new WPAIC_Content_Index();
    $status        = $content_index->get_index_status();
    if ( ! $status['exists'] ) {
        return '';  // ← bot never learns about the tools
    }
    return ' You have access to the website\'s pages and posts...';
}
```

This creates a mismatch: tools are available but the bot has no prompt guidance to use them. The tools already handle missing indexes gracefully via `fallback_search()` (falls back to `WP_Query`), so gating the prompt on file existence is unnecessary.

Likely broke because:
- The admin saved with no content post types checked (the refactored UI allows `allow_empty_content_selection = true`)
- `build_index()` saw `empty($post_types)` → called `clear_index()` → deleted `content.index`
- Even after re-checking types, the index wasn't rebuilt (no auto-rebuild on settings save in the new AJAX flow)

## Fix

Change `get_content_index_instruction()` to check whether content post types are configured rather than whether the index file exists. If post types are selected, always include the instruction — the tools handle missing indexes via fallback.

### File: `wp-ai-chatbot/includes/class-wpaic-chat.php`

Change `get_content_index_instruction()` (~line 609):

```php
// Before:
if ( ! $status['exists'] ) {
    return '';
}

// After:
$content_index = new WPAIC_Content_Index();
$post_types = $content_index->get_selected_post_types();
if ( empty( $post_types ) ) {
    return '';
}
```

No need to instantiate or check index status at all — just check if the admin has post types selected.

## Verification

1. Run existing tests: `cd wp-ai-chatbot && vendor/bin/phpunit`
2. On the local site, go to Search tab, ensure page/post are checked, rebuild index
3. Open chatbot, ask "What is your return policy?" → bot should use search_site_content and answer from the Returns Policy page
4. Delete `content.index` manually, ask again → bot should still attempt the tool (fallback search)
