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
- **Deals & promotions** — surfaces active coupon codes and on-sale products on request, and suggests one genuinely related cross-sell after an add to cart.
- **Cart & checkout assistance** — the bot knows what's in the cart and surfaces a one-tap checkout button when the shopper is ready to buy.
- **Order tracking** — shoppers look up order status and tracking from chat, verified by email.
- **Grounded & accurate** — answers only from your real store data. Never invents specs, materials, or shipping times.
- **Train your bot** — feed it FAQs, CSV data, and your site's pages/posts so it answers in your store's voice with your facts.
- **Human handoff** — escalates to your team when needed, capturing the customer's details and conversation.
- **Fully branded & customizable** — custom name, logo, role, theme color, tone of voice, and system prompt.
- **Multilingual** — auto-detects the shopper's language or locks to one of 12 supported languages.
- **Proactive engagement** — a timed, dismissible teaser bubble invites shoppers to chat, with page targeting.
- **Conversation insights** — weekly summary cards (chats, carts, checkouts, handoffs), a zero-result-search knowledge-gap report, and full chat logs and transcripts in the admin.
- **Privacy-conscious** — conversation retention controls, IP anonymization, and a clean uninstall.

---

## Supported Features (Detailed)

Everything currently implemented.

### Chat Widget (frontend)
- Floating chat button (bottom-right), animated pulse until first open, hides on mobile while open; unread badge + pulse when a reply finishes while the widget is closed
- Lazy loading: a ~6KB stub renders the launcher + proactive teaser and dynamic-imports the full React app on first interaction (or idle) — storefront pageviews don't pay the full bundle cost
- Proactive teaser bubble: dismissible message-preview next to the launcher after the configured delay (never auto-opens the full widget); expands to full chat on click
- Scoped styles: no Tailwind preflight bleed into the host theme; reduced-motion rules scoped to the widget root
- Real-time streaming responses over SSE
- Skeleton product cards shimmer in-thread while product tools (search / popular / compare) run
- Header with circular avatar (logo or initials), green online dot, name + role subtitle
- Flat gray assistant bubbles / themed user bubbles, 85% max width
- Markdown rendering (tables, lists, links, bold/italic, code) with links opening in new tabs
- Cluster-based time separators (TODAY / YESTERDAY / day name · HH:MM) on >5min gaps
- Pill input with auto-growing textarea and internal circular up-arrow send button
- Enter to send, Shift+Enter for newline; input auto-focus on open and after send
- Jump-to-latest pill appears when scrolled up in long conversations
- Conversation starter pills on empty chat (custom or auto-generated)
- New conversation button (with confirm), close (X), and Escape-to-close
- Clear/remove confirm via the same popup pattern as new-chat (add fires immediately, no confirm); clear/remove shows an inline result badge afterward
- Human-readable tool progress labels for every tool ("Adding to cart…", "Updating your cart…", etc.) — never the raw tool name
- Email transcript: send the full conversation to an email address from the widget
- Debounced multi-message sending (~400ms; batches rapid messages, including ones typed mid-stream)
- Session persistence in sessionStorage across page reloads; executed cart actions are persisted too, so restored conversations never re-fire historical adds/removes
- Mobile: full-height via 100dvh (iOS dynamic toolbar safe), body scroll lock while open, offset below the WP admin bar, safe-area insets respected
- Accessibility: message list announced as an aria-live log, focus-trapped dialogs with Cancel focused by default, focus returned to the launcher on close, keyboard-visible carousel arrows

### Product Display
- Product cards: image, name, short description, humanized category caption, price, SALE badge with strikethrough
- 2-column grid for 1–2 products; carousel for 3+ ("N PICKS" header, hover-revealed desktop arrows, swipe hint on touch)
- Card discipline: cards deduped by product ID, picks capped at 6, redundant carousels suppressed when a comparison table shows the same products, only the added product's card after an add-to-cart
- Add-to-cart button with state machine (ADD → loading → ADDED; inline error state with auto-reset — failures never navigate away; SOLD OUT disabled)
- Zero-price guard: $0.00-priced products hide the price and swap ADD for a "View product" link
- Variable products: inline attribute dropdowns; live price/image/stock updates; PICK → ADD; ADD disabled with an "unavailable" hint when the chosen combo matches no variation
- Product-type-aware cards:
  - External products → affiliate link with custom button text ("BUY PRODUCT")
  - Grouped/bundle → product page link ("VIEW OPTIONS")
  - Out-of-stock simple → disabled "Sold out"
  - Subscriptions → render as simple/variable
  - Unknown types → "View product" fallback
- Comparison table: side-by-side of 2–4 products with per-product add-to-cart; renders attribute, weight, and dimension rows when present
- Checkout CTA button (themed CHECKOUT, with "view cart" fallback) rendered inline on checkout intent

### AI Tools (function calls the model can invoke)
WooCommerce:
- `search_products` — fuzzy search by keyword, category, price range, and `on_sale` filter (title/category-weighted relevance, zero-result auto-retry, default 6 results)
- `get_popular_products` — best sellers by total sales, with top-rated then newest fallback; optional category filter
- `get_product_details` — full detail for one product
- `get_categories` — category list with product counts (human-readable names plus slugs for tool params)
- `compare_products` — side-by-side comparison of 2–4 products with real attributes/weight/dimensions and a server-computed `differences` summary (who's cheaper and by how much, rating/stock differences) so the model paraphrases facts instead of deriving them
- `get_active_promotions` — active, non-expired coupon codes with amounts and restrictions
- `get_cart_contents` — current cart items + totals
- `add_to_cart` — add a product (or a specified variation) to the shopper's cart; validates stock/purchasability, asks for the variation when one is needed, and signals the widget to add it over WooCommerce AJAX; success payload includes up to 3 cross-sell/upsell products for one optional follow-up suggestion
- `clear_cart` — remove items from the cart or empty it entirely; per-item quantity support (remove 2 of 5 waters reduces the line, omit quantity to remove all of a product), validated against the live cart and executed by the widget over WooCommerce AJAX behind a confirmation popup
- `get_checkout_action` — real checkout/cart URLs for the CTA button
- `get_order_status` — order lookup by number + email, with tracking link if available
- `get_shipping_info` — real shipping zones, methods, and costs from store config

Content & knowledge:
- `search_site_content` — full-text search across pages/posts
- `get_page_content` — fetch full text of a page/post (e.g. refund policy)
- `query_custom_data` — query merchant-uploaded CSV data sources (only when sources exist)

Support:
- `create_handoff_request` — escalate to a human (only when handoff enabled)

Token diet: the model receives a slimmed payload (no cart/checkout/image URLs, variations collapsed to id + attributes + price + stock, descriptions stripped/truncated) while the frontend gets the full payload for cards; conversation history and FAQ injection are capped.

### Conversation Intelligence
- Multi-turn tool-calling loop (executes tools, feeds results back, up to 10 iterations); tool execution wrapped in try/catch so a third-party fatal returns an error object to the model instead of killing the SSE stream
- Ordinal/positional grounding: a compact system-role context line lists the product cards just shown in display order, so "the second one" / "the blue one" resolves against what the shopper actually saw; the internal context never leaks into replies, and ambiguous references get one clarifying question
- Disambiguation with cards: "which one do you mean?" questions always run the search first and show the candidates as cards in the same turn
- Text/card alignment: never enumerates more products in text than are rendered as cards (default 6 picks)
- Comparison accuracy: restates prices/ratings/stock verbatim from tool output and bases verdicts on the server-computed differences summary
- Discount intent: coupon/promo questions answered only from `get_active_promotions` output (codes and conditions quoted exactly, never invented); "what's on sale" uses the `on_sale` search filter
- Proactive, interactive replies: warm 1–2 sentence intro plus at most one curation note (naming 1–2 standout picks) or follow-up question — never spec-dumps, since cards show the details
- Immediate product search for concrete queries; broad top-categories guidance only for genuinely vague asks ("what do you sell?"), with category names grounded strictly on real `get_categories` output (no invented categories)
- Best-seller intent ("most popular", "top products", "what sells best") → popular products shown as cards (not a category list)
- Budget-aware: passes price filters to search when the shopper states a budget
- Cross-language search: translates the shopper's product keywords into the store's catalog language for tool calls while replying in the shopper's language (brand names/model numbers/SKUs kept verbatim)
- Add-to-cart intent → the bot adds the item to the cart via the `add_to_cart` tool; the widget fires it over WooCommerce AJAX and shows an "Added to cart" confirmation. For variable products it resolves the chosen variation from context or asks which option, never guessing
- Clear-cart / remove-item intent → the bot calls `clear_cart` (resolving product IDs and quantities from `get_cart_contents`); the widget shows a confirmation popup (like the new-chat prompt) listing exactly what will be removed, then mutates the cart over WooCommerce AJAX on confirm. The bot never asks "are you sure?" in text or claims the cart changed before confirmation
- Empty-cart checkout intent → tells the shopper their cart is empty and offers help instead of rendering a checkout button
- Pairs gift/category suggestions with actual product picks
- Grounding rules: states only facts present in tool output; no invented specs/materials/shipping times
- Never asserts catalog absence from a single keyword miss — search auto-retries built-in, plus one model-side retry from a different angle before saying "no match", always offering the closest alternative
- Shipping grounding: when no shipping zone matches the destination, fetches the store's shipping policy page and quotes its concrete rates instead of claiming shipping "isn't configured"
- Pivot suggestions name only real categories from `get_categories` output (no invented "pet items")
- Off-topic nudge-back: when natural, ends non-shopping answers with a relevant shopping follow-up (not forced)
- Current-page context injection: bot knows the page type (product/cart/category/etc.) and IDs
- Cart awareness: knows items and totals
- Configurable tone of voice: Neutral, Friendly, Professional, Enthusiastic
- Language: auto-detect or fixed (EN, ES, FR, DE, IT, PT, NL, RU, ZH, JA, KO, AR)

### Search Indexing
- TNTSearch fuzzy index for products (title, description, SKU, categories, variation attributes) with WP_Query fallback
- Field-weighted relevance: title/category/SKU/attribute matches rank above description-only matches, with a description-only fallback when nothing stronger matches (prevents e.g. a "water-resistant" watch surfacing for "water")
- Zero-result auto-retry: brand-token fallback ("chanel perfume" → "chanel"), synonym map (perfume↔fragrance, shoes↔sneakers, t-shirt↔tee), hyphen/plural normalization ("t-shirts" matches "V-Neck T-Shirt"), parent-category fallback
- Phrase-level synonym recall: phrase variants ("running shoes"/"trainers"/"kicks" → sneakers) merged into results whenever the phrase appears, bounded and deduped
- $0.00-priced products down-ranked so priceless sample data stops leading recommendations
- Humanized labels everywhere: category and attribute names returned as display names (slug-like names like "kitchen-accessories" humanized to "Kitchen Accessories"), slugs kept as separate fields for tool params
- TNTSearch content index for configurable post types (pages, posts) with WP_Query fallback
- Admin re-index controls, freshness/status indicator with honest wording, and indexed-item counts

### Admin Configuration
Cross-tab:
- First-run onboarding checklist (dismissible card on the General tab): 1) start trial/activate, 2) name + brand, 3) add knowledge, 4) open your store and try it — live completion state per step
- Unsaved-changes guard: dirty-state tracking, beforeunload prompt, and a "Save changes •" indicator
- Header "Live on site" pill reflects whether the widget can actually render (license + enabled), with a reason when it can't

General tab:
- Enable/disable chatbot toggle
- Greeting message
- Response language (auto-detect or 12 fixed languages)
- Tone of voice (Neutral / Friendly / Professional / Enthusiastic)
- Custom system prompt (advanced override)
- Privacy: conversation retention (delete conversations older than N days via daily cron) and anonymize-IP toggle (on by default)

Appearance tab:
- Chatbot name, role/subtitle
- Logo upload (media library) with letter fallback
- Theme color picker (10 presets + custom hex), default Indigo (#2545B8)
- Live chat preview: renders the real ChatWidget UI, updating live from form inputs with a sample conversation

Engagement tab:
- Conversation starters (up to 5; auto-generated if blank)
- Human handoff settings (enable; choose which optional fields to collect — phone, company, order number, message; name + email always collected)
- Proactive teaser (enable, delay 1–300s, message, page targeting: all / shop / product / homepage) — rendered in the live preview

Knowledge tab:
- CSV data sources: upload (name, label, description, file ≤5MB), row counts, trained badge, delete/replace; replace is staged so the old source survives a failed import
- FAQ pairs: Q/A textarea injected into the system prompt, answered naturally; saves parse before replacing, so a malformed paste never wipes existing FAQs
- Site content indexing: choose products + post types, re-index all, status display

Licensing tab:
- Trial / license status badge and active indicator
- Activate license, manage billing, see plans (Freemius flow)
- Provider endpoint display (connected / placeholder) and optional staging override

### Admin Pages
- Insights: weekly summary cards (chats · products added to cart · checkouts started · handoffs) with week-over-week trend, plus a knowledge-gap report of top zero-result searches — backed by an events table recording every search, add, checkout, and handoff
- Chat Logs: paginated conversation list with first-user-message preview and message count, text search + date filters, transcripts rendered as markdown with gray tool-event chips ("showed 4 products: …", "added X to cart"), delete individual conversations; batched user messages and card-only replies are logged too
- Support Requests: paginated handoff list, inline status (New / Contacted / Resolved), full conversation transcript attached to each request alongside collected fields, email shortcut; admin menu shows a count bubble of new requests

### Licensing & Billing
- Freemius-powered trials, licensing, billing, and premium updates
- Chat hidden on the frontend until a trial or license is active
- Signed requests to the provider (HMAC-SHA256, Freemius install ID + public key, 5-min signature TTL)
- Stream endpoint hardening: per-IP/session rate limiting, message-count and content-size caps, role validation, UUID session IDs
- `.local` / `.test` / staging installs work against the provider in local development
- Provider rejections surface the exact reason in the chat stream
- Lifecycle hygiene: `uninstall.php` drops all plugin tables and options

### WP-CLI
- `wp wpaic import-dummyjson` — import demo WooCommerce products from dummyjson.com (`--limit`, `--skip`, `--purge`, `--dry-run`); maps images, categories (slug names humanized for display), brand, SKU, dimensions, prices, stock, ratings

---

## Ideas & Backlog (not yet implemented)

What genuinely remains after the 2026-06 improvement rounds:

- **Keyword filter on `get_popular_products`** — support "most popular running shoes" by combining a search keyword with popularity ordering; today it takes only a category slug (the prompt falls back to `search_products` when no category matches).
- **In-stock-first best sellers** — optionally rank in-stock items ahead of sold-out top sellers.
- **Configurable catalog language** — a configurable or data-detected catalog language instead of the `get_locale()` heuristic for multi-language stores (synonym expansion on zero results has shipped).
- **Deterministic budget parsing** — derive `min_price`/`max_price` in code from phrases like "around $300" rather than relying on the model to pick the band.

---

## Provider (our server only)

The middleman plugin that holds the OpenAI key so customers don't need one. Internal infrastructure,
not customer-installed.

- Transparent OpenAI proxy: receives chat requests, forwards to OpenAI, streams SSE back; stateless (never interprets tool calls)
- Signed-request authentication (HMAC-SHA256 over install ID, public key, timestamp, body hash; `hash_equals` comparison; 5-min TTL)
- Freemius license/trial validation per request, with 5-minute API caching and a 24-hour grace period when Freemius is unavailable (grace-period requests still checked against the stored public key)
- Local-dev bypass for localhost/`.local`/`.test`/staging origins
- Install registry tracking validated sites (status, license, last seen, usage bucket key)
- Usage metering: exact input/output/cached token usage recorded per install per day from `response.completed` events; configurable daily message/token budgets enforced with a friendly 429 the chatbot surfaces in-chat
- Request caps: `max_output_tokens` on every request, instructions-length, input-item-count, and per-item content-size limits in request validation
- Prompt caching: `prompt_cache_key` set per usage bucket so the static system prompt + tool definitions hit OpenAI's cached-input pricing across loop iterations
- `store=false` on all requests — shopper conversations are never retained on OpenAI
- HTTP hardening: Guzzle connect/read timeouts, one retry on connect/429/5xx before any event is emitted, `connection_aborted()` detection stops paying for streams nobody is watching
- Friendly generic error messages to shoppers; raw exception detail logged server-side only
- Tool schema normalization: restores empty object schemas (`{}`) that PHP turns into `[]` before forwarding to OpenAI
- Provider-decided model: the provider picks the model + reasoning effort per request and ignores whatever the chatbot sends, so models can be upgraded or throttled centrally without shipping a chatbot update
- Admin settings: OpenAI API key and Freemius API token rendered as masked fields (saved values never echoed; last 4 chars shown), model (GPT-5 Mini default / GPT-5.4 Mini / GPT-5.4 Nano) and reasoning effort (None / Low default / Medium / High — "None" maps to the Responses API's `minimal`) as independent selectors, Freemius product ID; dashboard of recently validated installs with per-install daily usage incl. cache-hit %
