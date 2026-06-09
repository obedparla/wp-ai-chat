# WP AI – Features

AI shopping assistant for WordPress/WooCommerce. A floating chat widget that helps shoppers
find products, answer questions, and check out — powered by a fully managed AI backend (no
OpenAI key required).

---

## Core Features (Marketing)

The headline capabilities we lead with.

- **AI shopping assistant for WooCommerce** — conversational product discovery, recommendations, and guided shopping that turns questions into purchases.
- **Fully managed AI, no API key** — we host the model on our provider server. Customers install the plugin, start a trial, and go. No OpenAI account, no per-token billing to manage.
- **Beautiful real-time chat widget** — polished floating widget with streaming responses, product cards, and carousels. Mobile-ready.
- **Smart product recommendations** — search, compare, and add to cart directly inside the chat. Rich product cards with images, prices, sale badges, and variations.
- **Cart & checkout assistance** — the bot knows what's in the cart and surfaces a one-tap checkout button when the shopper is ready to buy.
- **Order tracking** — shoppers look up order status and tracking from chat, verified by email.
- **Grounded & accurate** — answers only from your real store data. Never invents specs, materials, or shipping times.
- **Train your bot** — feed it FAQs, CSV data, and your site's pages/posts so it answers in your store's voice with your facts.
- **Human handoff** — escalates to your team when needed, capturing the customer's details and conversation.
- **Fully branded & customizable** — custom name, logo, role, theme color, tone of voice, and system prompt.
- **Multilingual** — auto-detects the shopper's language or locks to one of 12 supported languages.
- **Proactive engagement** — timed popups invite shoppers to chat, with page targeting.
- **Conversation insights** — full chat logs and transcripts in the admin.

---

## Supported Features (Detailed)

Everything currently implemented.

### Chat Widget (frontend)
- Floating chat button (bottom-right), animated pulse until first open, hides on mobile while open
- Real-time streaming responses over SSE
- Header with circular avatar (logo or initials), green online dot, name + role subtitle
- Flat gray assistant bubbles / themed user bubbles, 85% max width
- Markdown rendering (tables, lists, links, bold/italic, code) with links opening in new tabs
- Cluster-based time separators (TODAY / YESTERDAY / day name · HH:MM) on >5min gaps
- Pill input with auto-growing textarea and internal circular up-arrow send button
- Enter to send, Shift+Enter for newline; input auto-focus on open and after send
- Jump-to-latest pill appears when scrolled up in long conversations
- Conversation starter pills on empty chat (custom or auto-generated)
- New conversation button (with confirm), close (X), and Escape-to-close
- Email transcript: send the full conversation to an email address from the widget
- Debounced multi-message sending (batches rapid messages before sending)
- Session persistence in sessionStorage across page reloads

### Product Display
- Product cards: image, name, short description, category caption, price, SALE badge with strikethrough
- 2-column grid for 1–2 products; carousel for 3+ ("N PICKS" header, hover-revealed desktop arrows, swipe hint on touch)
- Add-to-cart button with state machine (ADD → loading → ADDED, error fallback, SOLD OUT disabled)
- Variable products: inline attribute dropdowns; live price/image/stock updates; PICK → ADD
- Product-type-aware cards:
  - External products → affiliate link with custom button text ("BUY PRODUCT")
  - Grouped/bundle → product page link ("VIEW OPTIONS")
  - Out-of-stock simple → disabled "Sold out"
  - Subscriptions → render as simple/variable
  - Unknown types → "View product" fallback
- Comparison table: side-by-side of 2–4 products with per-product add-to-cart
- Checkout CTA button (themed CHECKOUT, with "view cart" fallback) rendered inline on checkout intent

### AI Tools (function calls the model can invoke)
WooCommerce:
- `search_products` — fuzzy search by keyword, category, price range (title/category-weighted relevance)
- `get_popular_products` — best sellers by total sales, with top-rated then newest fallback; optional category filter
- `get_product_details` — full detail for one product
- `get_categories` — category list with product counts
- `compare_products` — side-by-side comparison of 2–4 products
- `get_cart_contents` — current cart items + totals
- `get_checkout_action` — real checkout/cart URLs for the CTA button
- `get_order_status` — order lookup by number + email, with tracking link if available
- `get_shipping_info` — real shipping zones, methods, and costs from store config

Content & knowledge:
- `search_site_content` — full-text search across pages/posts
- `get_page_content` — fetch full text of a page/post (e.g. refund policy)
- `query_custom_data` — query merchant-uploaded CSV data sources (only when sources exist)

Support:
- `create_handoff_request` — escalate to a human (only when handoff enabled)

### Conversation Intelligence
- Multi-turn tool-calling loop (executes tools, feeds results back, up to 10 iterations)
- Proactive, interactive replies: warm 1–2 sentence intro plus at most one curation note (naming 1–2 standout picks) or follow-up question — never spec-dumps, since cards show the details
- Immediate product search for concrete queries; broad top-categories guidance only for genuinely vague asks ("what do you sell?"), with category names grounded strictly on real `get_categories` output (no invented categories)
- Best-seller intent ("most popular", "top products", "what sells best") → popular products shown as cards (not a category list)
- Budget-aware: passes price filters to search when the shopper states a budget
- Cross-language search: translates the shopper's product keywords into the store's catalog language for tool calls while replying in the shopper's language (brand names/model numbers/SKUs kept verbatim)
- Add-to-cart intent → re-shows the product and directs the shopper to tap the on-card ADD button
- Empty-cart checkout intent → tells the shopper their cart is empty and offers help instead of rendering a checkout button
- Pairs gift/category suggestions with actual product picks
- Grounding rules: states only facts present in tool output; no invented specs/materials/shipping times
- Off-topic nudge-back: when natural, ends non-shopping answers with a relevant shopping follow-up (not forced)
- Current-page context injection: bot knows the page type (product/cart/category/etc.) and IDs
- Cart awareness: knows items and totals
- Configurable tone of voice: Neutral, Friendly, Professional, Enthusiastic
- Language: auto-detect or fixed (EN, ES, FR, DE, IT, PT, NL, RU, ZH, JA, KO, AR)

### Search Indexing
- TNTSearch fuzzy index for products (title, description, SKU, categories, variation attributes) with WP_Query fallback
- Field-weighted relevance: title/category/SKU/attribute matches rank above description-only matches, with a description-only fallback when nothing stronger matches (prevents e.g. a "water-resistant" watch surfacing for "water")
- TNTSearch content index for configurable post types (pages, posts) with WP_Query fallback
- Admin re-index controls, freshness/status indicator, and indexed-item counts

### Admin Configuration
General tab:
- Enable/disable chatbot toggle
- Greeting message
- Response language (auto-detect or 12 fixed languages)
- Tone of voice (Neutral / Friendly / Professional / Enthusiastic)
- Custom system prompt (advanced override)

Appearance tab:
- Chatbot name, role/subtitle
- Logo upload (media library) with letter fallback
- Theme color picker (10 presets + custom hex), default Indigo (#2545B8)
- Live chat preview: renders the real ChatWidget UI, updating live from form inputs with a sample conversation

Engagement tab:
- Conversation starters (up to 5; auto-generated if blank)
- Human handoff settings (enable; choose which optional fields to collect — phone, company, order number, message; name + email always collected)
- Proactive popup (enable, delay 1–300s, message, page targeting: all / shop / product / homepage)

Knowledge tab:
- CSV data sources: upload (name, label, description, file ≤5MB), row counts, trained badge, delete/replace
- FAQ pairs: Q/A textarea injected into the system prompt, answered naturally
- Site content indexing: choose products + post types, re-index all, status display

Licensing tab:
- Trial / license status badge and active indicator
- Activate license, manage billing, see plans (Freemius flow)
- Provider endpoint display (connected / placeholder) and optional staging override

### Admin Pages
- Chat Logs: paginated conversation list with per-conversation message count and total text (character) count, expandable/viewable transcripts, delete individual conversations
- Support Requests: paginated handoff list, inline status (New / Contacted / Resolved), transcript + collected fields view, email shortcut

### Licensing & Billing
- Freemius-powered trials, licensing, billing, and premium updates
- Chat hidden on the frontend until a trial or license is active
- Signed requests to the provider (HMAC-SHA256, Freemius install ID + public key, 5-min signature TTL)
- `.local` / `.test` / staging installs work against the provider in local development
- Provider rejections surface the exact reason in the chat stream

### WP-CLI
- `wp wpaic import-dummyjson` — import demo WooCommerce products from dummyjson.com (`--limit`, `--skip`, `--purge`, `--dry-run`); maps images, categories, brand, SKU, dimensions, prices, stock, ratings

---

## Ideas & Backlog (not yet implemented)

Good ideas surfaced while improving conversational UX (2026-06-08), deferred to keep changes focused:

- **Server-side add-to-cart tool** — let the bot add items conversationally (deferred: chat REST cart-session vs storefront cart sync, variable-product/variation selection, and frontend badge updates all need care).
- **Keyword filter on `get_popular_products`** — support "most popular running shoes" by combining a search keyword with popularity ordering; today it takes only a category slug.
- **In-stock-first best sellers** — optionally rank in-stock items ahead of sold-out top sellers.
- **Multilingual search robustness** — auto-broaden / synonym-expand when a translated query returns nothing (e.g. "running shoes" → "shoes"/"sneakers"); and a configurable or data-detected catalog language instead of the `get_locale()` heuristic for multi-language stores.
- **Deterministic budget parsing** — derive `min_price`/`max_price` in code from phrases like "around $300" rather than relying on the model to pick the band.
- **Strip cart/checkout/add-to-cart URLs from tool output** — defense-in-depth so a prompt slip can never leak a URL into chat text (the frontend cards already hold them).
- **Unit-test best-seller ordering** — teach the WP_Query test stub to honor `orderby` (incl. named meta-query clauses) so total_sales/rating/date ordering is verifiable; add upper-bound limit-clamp and default-limit cases.

---

## Provider (our server only)

The middleman plugin that holds the OpenAI key so customers don't need one. Internal infrastructure,
not customer-installed.

- Transparent OpenAI proxy: receives chat requests, forwards to OpenAI, streams SSE back; stateless (never interprets tool calls)
- Signed-request authentication (HMAC-SHA256 over install ID, public key, timestamp, body hash; `hash_equals` comparison; 5-min TTL)
- Freemius license/trial validation per request, with 5-minute API caching and a 24-hour grace period when Freemius is unavailable
- Local-dev bypass for localhost/`.local`/`.test`/staging origins
- Install registry tracking validated sites (status, license, last seen, usage bucket key for future rate limiting)
- Tool schema normalization: restores empty object schemas (`{}`) that PHP turns into `[]` before forwarding to OpenAI
- Provider-decided model: the provider picks the model + reasoning effort per request and ignores whatever the chatbot sends, so models can be upgraded or throttled centrally without shipping a chatbot update (usage-bucket seam left for per-install rules)
- Admin settings: OpenAI API key, model (GPT-5 Mini default / GPT-5.4 Mini / GPT-5.4 Nano) and reasoning effort (None / Low / Medium default / High) as independent selectors, Freemius product ID + API token; dashboard of recently validated installs
