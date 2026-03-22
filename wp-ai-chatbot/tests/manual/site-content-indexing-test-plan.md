# Site Content Indexing — Browser Agent Testing Plan

Base URL: `http://wp-ai-chatbot.local`
Admin: `http://wp-ai-chatbot.local/wp-admin`
Settings page: `wp-admin/admin.php?page=wp-ai-chatbot&tab=search`

The testing agent operates entirely through the browser — no filesystem, terminal, or code access. All assertions are based on visible UI, page content, and chatbot responses.

## Prerequisites

Before running tests, the site must have:
- Plugin active
- Admin login credentials
- At least 5 published pages with distinct content:
  - "About Us" — company background
  - "Contact" — email, phone, address
  - "Shipping Policy" — shipping methods, timeframes
  - "Returns Policy" — return window, conditions
  - "FAQ" — common questions and answers
- At least 2 published blog posts on distinct topics
- WooCommerce active with products (for coexistence tests)

---

## 1. Admin UI — Search Tab

### 1.1 Content index section renders
1. Navigate to `wp-admin/admin.php?page=wp-ai-chatbot&tab=search`
2. Scroll below the "Product Search Index" section
3. Assert text "Site Content Index" is visible on the page
4. Assert checkboxes are visible for post types
5. Assert checkboxes for "page" and "post" exist and are checked
6. Assert no checkbox labeled "product" or "attachment" exists

### 1.2 Rebuild content index
1. On the Search tab, find the "Rebuild Content Index" button
2. Click it
3. Assert text "Rebuilding..." appears near the button
4. Wait up to 10 seconds
5. Assert a success message appears containing "rebuilt successfully" and a number (item count)
6. Wait for page reload (or reload manually after 2 seconds)
7. Assert the content index status area shows a green checkmark/icon
8. Assert the status text contains a number > 0 and a date/time string

### 1.3 Product index unaffected by content rebuild
1. On the Search tab, note the product index count and "Last updated" text
2. Click "Rebuild Content Index"
3. Wait for success
4. After page reload, assert product index count and timestamp are unchanged

### 1.4 Post type selection saves
1. On the Search tab, uncheck "post" (keep "page" checked)
2. Click the Save/Submit button for the settings form
3. Wait for page reload
4. Navigate back to the Search tab
5. Assert "page" checkbox is checked
6. Assert "post" checkbox is unchecked

### 1.5 Post type change triggers rebuild
1. On the Search tab, note the content index "Last updated" timestamp
2. Change the post type selection (check or uncheck a type)
3. Save settings
4. Navigate back to the Search tab
5. Assert the "Last updated" timestamp is more recent than before

### 1.6 Restore defaults for subsequent tests
1. On the Search tab, ensure both "page" and "post" are checked
2. Save settings
3. Click "Rebuild Content Index" and wait for success

---

## 2. Index Correctness via Admin UI

### 2.1 New page increases count
1. On the Search tab, note the current indexed item count
2. Navigate to `wp-admin/post-new.php?post_type=page`
3. Set title: "Unique Zebra Testing Page"
4. Set body: "This page contains unique zebra content for verification purposes"
5. Publish the page
6. Navigate back to Search tab
7. Click "Rebuild Content Index"
8. After success/reload, assert the item count increased by 1

### 2.2 Password-protected page excluded
1. Note the current content index count
2. Navigate to `wp-admin/post-new.php?post_type=page`
3. Set title: "Secret Password Page"
4. Set body: "This should not be indexed"
5. Set visibility to password-protected (set a password)
6. Publish the page
7. Navigate back to Search tab, click "Rebuild Content Index"
8. After success/reload, assert the item count did NOT increase

### 2.3 Cleanup
1. Navigate to Pages list in admin
2. Trash "Unique Zebra Testing Page" and "Secret Password Page"

---

## 3. Real-Time Sync via Chatbot

These tests verify that content changes are reflected in chatbot responses without a manual rebuild.

### 3.1 Newly published page is findable
1. Rebuild the content index (Search tab → Rebuild)
2. Create a new page titled "Platypus Care Guide" with body: "The platypus requires a semi-aquatic habitat with access to freshwater streams. Feed them insect larvae and small crustaceans."
3. Publish it
4. Navigate to any frontend page
5. Open the chatbot widget
6. Type: "How do I care for a platypus?"
7. Assert the bot's response mentions "semi-aquatic" or "freshwater" or "insect larvae" (content from the page)
8. Assert the response contains a link to the "Platypus Care Guide" page

### 3.2 Updated page reflects new content
1. Edit the "Platypus Care Guide" page
2. Change the body to: "The platypus is now best cared for in a terrarium with dry sand and tropical plants."
3. Update/save the page
4. Open the chatbot (new conversation or refresh)
5. Ask: "What habitat does a platypus need?"
6. Assert the response mentions "terrarium" or "dry sand" or "tropical plants" (new content)
7. Assert the response does NOT mention "semi-aquatic" or "freshwater" (old content)

### 3.3 Trashed page is no longer findable
1. Trash the "Platypus Care Guide" page
2. Open the chatbot (new conversation)
3. Ask: "Tell me about platypus care"
4. Assert the bot does NOT reference the "Platypus Care Guide" page
5. Assert the bot responds with something like "I don't have information about that" or similar

### 3.4 Unpublished page removed
1. Create and publish a page titled "Narwhal Facts" with body: "Narwhals have a single long tusk that is actually an elongated tooth."
2. Open chatbot, ask "What do you know about narwhals?"
3. Assert the bot mentions the tusk/tooth fact
4. Go back to admin, edit "Narwhal Facts", change status to "Draft"
5. Open chatbot (new conversation), ask "Tell me about narwhals"
6. Assert the bot can no longer reference that page content

### 3.5 Republished page re-appears
1. Edit "Narwhal Facts" page, change status back to "Publish"
2. Open chatbot (new conversation), ask "What are narwhal tusks?"
3. Assert the bot references the tusk/tooth content again
4. Cleanup: trash "Narwhal Facts"

### 3.6 Non-selected post type ignored
1. Go to Search tab, uncheck "post" (keep only "page")
2. Save settings
3. Create a new blog post titled "Axolotl Regeneration Research" with body: "Axolotls can regenerate entire limbs including bones, muscles, and nerves."
4. Publish it
5. Open chatbot, ask "Can axolotls regenerate?"
6. Assert the bot does NOT reference the blog post content (it's a `post`, not a `page`)
7. Cleanup: re-check "post" in Search tab, save settings. Trash the blog post.

---

## 4. Chatbot — Content Questions

### 4.1 Return policy question
1. Navigate to any frontend page
2. Open the chatbot widget
3. Type: "What is your return policy?"
4. Wait for response
5. Assert the response contains specific details from the Returns Policy page (not a generic "I don't know")
6. Assert the response contains a link to the Returns Policy page

### 4.2 Contact info question
1. Ask the chatbot: "How can I contact you?"
2. Assert the response contains contact details from the Contact page (email, phone, or address)
3. Assert a link to the Contact page is present

### 4.3 Shipping question
1. Ask: "What are your shipping options?"
2. Assert the response references content from the Shipping Policy page

### 4.4 About us question
1. Ask: "Tell me about your company"
2. Assert the response includes details from the About Us page

### 4.5 Blog post question
1. Ask about a topic covered in one of the blog posts
2. Assert the response references content from that blog post

### 4.6 No results — graceful handling
1. Ask: "What is the airspeed velocity of an unladen swallow?"
2. Assert the bot responds without error
3. Assert the response does NOT contain error messages or raw tool output
4. Assert the bot acknowledges it doesn't have that info or gives a general response

### 4.7 Product question uses product tools, not content tools
1. Ask: "Show me your cheapest products"
2. Assert the response shows product cards or product info (not page content)
3. Assert no page/post links appear in the response (product URLs are fine)

---

## 5. Deep Content Retrieval

### 5.1 Detailed question from long page
1. Create a page titled "Complete Widget Manual" with 1500+ words of detailed content. Include a specific fact deep in the text, e.g., paragraph 10: "The widget's maximum operating temperature is 85 degrees Celsius."
2. Publish it, rebuild index
3. Open chatbot, ask: "What is the maximum operating temperature of the widget?"
4. Assert the response mentions "85 degrees Celsius"
5. Assert a link to "Complete Widget Manual" is included
6. Cleanup: trash the page

### 5.2 Source citation format
1. Ask any content question that the bot can answer from a page
2. Assert the bot's response contains a clickable link
3. Click the link
4. Assert it navigates to the correct page on the site

---

## 6. Edge Cases

### 6.1 Shortcode content stripped
1. Create a page titled "Gallery and Contact Test" with body: `[gallery ids="1,2,3"] We offer premium photography services for weddings and events. [contact-form-7 id="123" title="Contact"]`
2. Publish, rebuild index
3. Ask chatbot: "Do you offer photography services?"
4. Assert the response mentions "photography" or "weddings and events"
5. Assert the response does NOT contain `[gallery` or `[contact-form-7` or any raw shortcode text
6. Cleanup: trash the page

### 6.2 HTML stripped from responses
1. Create a page titled "Styled Content Test" with HTML in the body: `<div class="fancy"><table><tr><td><strong>Our hours:</strong></td><td>9am-5pm</td></tr></table></div>`
2. Publish, rebuild index
3. Ask chatbot: "What are your hours?"
4. Assert the response mentions "9am-5pm"
5. Assert the response does NOT contain HTML tags like `<div>`, `<table>`, `<td>`, `<strong>`
6. Cleanup: trash the page

### 6.3 Non-Latin content (multibyte)
1. Create a page titled "Política de Envío" with body in Spanish: "Los envíos nacionales se realizan en un plazo de 3 a 5 días hábiles. El costo de envío estándar es de $5.99."
2. Publish, rebuild index
3. Ask chatbot: "¿Cuál es el costo de envío?"
4. Assert the response mentions "$5.99" or "3 a 5 días"
5. Assert no garbled/mojibake characters in the response
6. Cleanup: trash the page

### 6.4 Concurrent product and content question
1. Ask chatbot: "Do you have any shoes, and what is your return policy?"
2. Assert the response addresses BOTH topics
3. Assert product information appears (product names/prices if shoes exist)
4. Assert return policy content from the Returns Policy page also appears

---

## 7. Plugin Activation/Deactivation

### 7.1 Deactivate and reactivate builds index
1. Navigate to `wp-admin/plugins.php`
2. Deactivate "WP AI Chatbot"
3. Reactivate "WP AI Chatbot"
4. Navigate to `wp-admin/admin.php?page=wp-ai-chatbot&tab=search`
5. Assert the content index status shows a green icon (index exists)
6. Assert the item count is > 0

---

## 8. WooCommerce Independence

### 8.1 Content search works without WooCommerce
1. Navigate to `wp-admin/plugins.php`
2. Deactivate WooCommerce
3. Navigate to any frontend page
4. Open the chatbot widget
5. Ask: "What is your return policy?"
6. Assert the bot responds with content from the Returns Policy page (not an error)
7. Assert no visible errors in the chat interface
8. Reactivate WooCommerce when done

### 8.2 Admin UI works without WooCommerce
1. With WooCommerce deactivated
2. Navigate to `wp-admin/admin.php?page=wp-ai-chatbot&tab=search`
3. Assert the "Site Content Index" section is visible
4. Assert the "Rebuild Content Index" button works
5. Reactivate WooCommerce

---

## Notes for the browser agent

- **New conversations**: After content changes, always start a fresh chatbot conversation (close and reopen the widget, or reload the page) to avoid cached context.
- **Assertions on chatbot responses**: The bot uses AI to generate answers, so assert on key facts/phrases from the source content rather than exact strings. E.g., if the page says "30-day return window", assert the response contains "30" and "return" rather than an exact sentence.
- **Timing**: After clicking "Rebuild Content Index", wait up to 10 seconds for the AJAX response. After saving settings, wait for the page to reload before asserting.
- **Cleanup**: Always trash test pages after each test section to avoid polluting subsequent tests.
- **Tool visibility**: The browser agent cannot directly observe which AI tools are called. Instead, infer tool usage from the bot's response quality — if it answers with specific page content and a source link, the content tools worked.
