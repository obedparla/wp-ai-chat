# WP AI Chatbot — PRD

## Problem Statement

Several small UX and reliability issues in the chatbot admin settings page need fixing. This PRD accumulates incremental improvements.

---

## 1. Move "Custom System Prompt" from Appearance to General

### Problem Statement

The "Custom System Prompt" textarea is under Settings > Appearance, but it controls chatbot behavior, not visual appearance. Users expect it under General.

### Solution

Move the system prompt textarea from the Appearance tab to the General tab. Update the `$tab_fields` mapping in `sanitize_settings()` so the field saves correctly under the new tab.

### User Stories

1. As an admin, I want the system prompt under General settings, so that behavior settings are grouped logically.

### Implementation Decisions

- Move the textarea HTML block from `render_appearance_tab()` to `render_general_tab()`
- Move `'system_prompt'` from `$tab_fields['appearance']` to `$tab_fields['general']` in `sanitize_settings()`
- Remove the `$system_prompt` variable from `render_appearance_tab()`, add it to `render_general_tab()`
- No changes to sanitization logic or storage — field key stays `system_prompt`

### Testing Decisions

- Verify existing settings tests still pass (settings are already tested in WPAIC_AdminTest)
- Manual verification: save a system prompt on General tab, reload, confirm it persists
- Manual verification: Appearance tab no longer shows the system prompt field

### Out of Scope

- Renaming the field key or changing storage format
- Adding system prompt validation or preview

---

## 2. Fix FAQ "Questions & Answers" Save Reliability

### Problem Statement

The "Questions & Answers" save on the Train Bot tab can silently fail — reporting success even when database inserts fail (e.g., missing table). The `$wpdb->insert()` return value is not checked, and `++$count` increments unconditionally.

### Solution

Add error handling to `ajax_save_faqs()`: check `$wpdb->insert()` return value before incrementing count, and return an error response if all inserts fail. Also ensure training tables exist before operating on them (call `wpaic_create_training_tables()` if needed).

### User Stories

1. As an admin, I want the FAQ save to report accurately whether my Q&A pairs were saved.
2. As an admin, I want FAQ saves to work even if the plugin was updated without reactivation (tables auto-created).

### Implementation Decisions

- Check `$wpdb->insert()` return value — only increment `$count` on success
- Before truncating, verify the FAQs table exists; if not, call `wpaic_create_training_tables()`
- If inserts fail, return `wp_send_json_error` with a descriptive message instead of a misleading success

### Testing Decisions

- Existing FAQ tests cover the happy path (parse, save, clear, replace) — verify they still pass
- No new unit tests needed since the logic change is minor (adding a conditional around `++$count`)

### Out of Scope

- Changing the Q&A format or parsing logic
- Adding auto-save or draft persistence for the textarea
