# WP AI Chatbot — Improvement Plan

Synthesized from 9 analyst/tester reports (backend, frontend, admin, provider/cost, admin browser tour, gift-shopper, shopping-spree, edge-case, mobile/polish sessions). Live-browser-confirmed findings outrank code-reading speculation. Priorities: **P0** = bugs/breakage or huge-value-small-effort, **P1** = high-impact improvements, **P2** = polish.

---

## Findings Summary

### State of the product

The foundation is genuinely strong: the provider-mode Responses-API tool loop, the validate-server/execute-client cart-mutation pattern, field-weighted search relevance, the pixel-faithful admin live preview, and an Intercom-class widget with carousels, variable-product cards, and a working comparison table. Live sessions completed full shopping arcs (search → compare → add → checkout) with accurate cart math and 8–15s streamed responses, zero chatbot-originated JS errors.

But live testing exposed **two cart-corrupting critical bugs**, **systemic search false negatives that flatly deny products the store sells**, and an **uncapped economic exposure** (no rate limiting, no usage metering, no prompt caching) on a managed-token business model.

### Critical live-confirmed evidence

**1. Cart-action replay (two independent sessions reproduced it):**
- Spree session: verified $49.05 cart silently became **$98.10** at checkout after clicking the chat CHECKOUT CTA, then **$156.16** on the second pass — including a Hand Blender the shopper had explicitly removed via the confirm popup. Three `woocommerce_ajax_add_to_cart` POSTs captured re-firing on each checkout navigation.
  - Screenshots: `.qa-screenshots/shopper-spree-13-checkout-page.png`, `shopper-spree-14-cart-doubled-after-checkout-nav.png`, `shopper-spree-15-checkout-second-replay-156-dollars.png`
- Edge session: cart grew **8 → 11 → 12 → 15 items ($267.17)** across three widget opens with zero user input — every open of a restored conversation re-executed historical adds.
  - Screenshots: `.qa-screenshots/shopper-edge-00-restored-old-convo-cart-jumped-8-to-11.png`, `shopper-edge-01-cart-replay-bug-hand-blender-incremented.png`
- Root cause (code-confirmed): `AddToCartTrigger.tsx` dedupes via an in-memory `Map` while messages persist in sessionStorage; `useClearCart.ts` already solved this with persisted statuses.

**2. Variable-product add-to-cart is dead (mobile session):**
- Hoodie card, Color=blue + Logo=No selected, PICK→ADD enabled, **three clicks produced zero network requests, no state change, no console error**; hoodie absent from `/cart`. Card also showed **$42.00 while the selected variation costs $45.00**.
  - Screenshots: `.qa-screenshots/shopper-mobile-15-hoodie-both-attrs-selected.png`, `shopper-mobile-16-hoodie-variation-adding.png`

**3. Search asserts false catalog absence (three sessions):**
- > "I couldn't find Chanel or Gucci perfume in the store right now." — while 'Chanel Coco Noir Eau De' ($108.53) and 'Gucci Bloom Eau de' ($68.48) exist; "what fragrances do you have then?" found all 5. (`shopper-edge-10`, `shopper-edge-11`)
- > "I found one dress option, but no t-shirts came up in the catalog." — V-Neck T-Shirt (id 64) exists. (`shopper-mobile-11-tshirts-dresses-response.png`)
- > "I couldn't find any running shoes right now." — mens-shoes sneakers exist, found on the very next rephrase. (`shopper-edge-03`, `shopper-edge-04`)

**4. Bot contradicts the store's own data:**
- Shipping: > "Shipping isn't configured for Spain on this site. I only see free shipping set up for the United States" — while `/shipping-policy/` says "over 100 countries... $5.99/$14.99/$29.99." (`shopper-edge-06-policy-and-shipping-answers.png`)
- Comparison: > "the Blue & Black Check Shirt has a higher rating" — its own table showed 3.0 vs the Men Check Shirt's 4.0. Same reply rendered a redundant "3 PICKS" carousel with one product duplicated, above the table. (`shopper-gift-07-comparison-reply-duplicate-carousel.png`, `shopper-gift-08-comparison-table.png`)

**5. Ordinal reference failure:**
- "add the second one from that kitchen list" → > "Added the Hand Blender to your cart." — card #10, not #2 (Red Tongs). Tool parts are stripped from history (`transform_messages`), so the model re-searches and guesses. (`shopper-spree-05-second-one-added-hand-blender-not-red-tongs.png`)

**6. Economic exposure (code-confirmed):**
- Provider: no rate limiting (TODO stub at `class-wpaip-api.php:247`), no `max_output_tokens`, no request-size caps, no `prompt_cache_key` despite a ~4k-token system prompt + ~3k tokens of tool definitions resent up to 10× per shopper message. Admin "None" reasoning silently bills medium. Chatbot `/chat/stream` accepts unlimited client-supplied history with only a public nonce.
- Tool payloads send URLs, image URLs, and up to 29 variations per product to the model verbatim — re-sent every loop iteration.

**7. Admin gaps:**
- Handoff "Conversation Transcript" modal contains **no conversation** — only the phone field and a canned summary. (`admin-tour-support-request-detail.png`)
- FAQ save runs `TRUNCATE TABLE` **before** validating the new content — a malformed paste destroys all FAQs.
- Chat Logs: no content preview, "Total Text" character counts, no search/filter; batched user messages and card-only replies never logged. Zero aggregate insight ("did this pay for itself?").

### Confirmed wow moments to protect
- Comparison table with images, sale prices, ratings, working per-product Add to Cart ("feels like a real e-commerce feature, not chatbot output").
- Chat add-to-cart syncing the theme's mini-cart badge live; conversation + carousels surviving full page reloads.
- Honest grounded answers: refund policy 100% verbatim-correct; "no color options listed, but here's a similar Blue & Black Check Shirt"; fluent mid-conversation Spanish with real products.
- Admin Appearance live preview rendering the real shipped widget keystroke-by-keystroke.

---

## Prioritized Plan

### P0 — Bugs/breakage + huge-value-small-effort

- [ ] **P0-1 [frontend, small] Fix cart-action replay: persist executed add_to_cart intents**
  `frontend/src/components/AddToCartTrigger.tsx` dedupes auto-fired intents with a module-level in-memory `Map` keyed by `toolCallId`, but messages (incl. add_to_cart tool parts) persist in sessionStorage (`hooks/useChat.ts` save/restore) — so every page load/navigation re-fires every historical add over WC AJAX. Mirror the `hooks/useClearCart.ts` pattern: persist handled toolCallIds + status in sessionStorage (cleared on `startNewConversation`); on restore, render the stored badge and never re-execute. Belt-and-braces: only execute intents that arrive on a live SSE stream, never ones rehydrated from storage. Add a test asserting a restored conversation fires zero cart requests. This corrupts real carts at checkout — ship first.

- [ ] **P0-2 [frontend, medium] Fix variable-product add-to-cart (silent no-op) + live variation price**
  Live-confirmed: selecting attributes on the Hoodie card enables ADD, but clicking fires zero network requests and the item never reaches the cart; card shows the product-level price ($42.00) instead of the matched variation's ($45.00). Audit the variation branch in `VariableProductCard.tsx` / `lib/cart.ts` add-to-cart path (the recent `requestAddToCart` dedupe refactor in commit 276270d is a suspect — the AJAX call appears never constructed when `variation_id` is present). Wire resolved-variation price/image/stock into the card (FEATURES.md already promises this). Also: disable ADD with an "unavailable" hint when the chosen combo matches no variation. Add an e2e/unit test asserting a network request fires for a variation add.

- [ ] **P0-3 [backend, medium] Stop asserting false store facts: zero-result search retry + shipping cross-check**
  (a) Search: on zero results, auto-retry before replying — brand token alone ("chanel"), small synonym map (perfume↔fragrance, shoes↔sneakers, t-shirt↔tee), hyphen/plural normalization ("t-shirts" must match "V-Neck T-Shirt"), parent-category fallback. Verify the TNTSearch product index (`class-wpaic-search-index.php`) includes WooCommerce-sample variable products (dummyjson imports search fine; sample ones don't). Prompt: replace "I couldn't find X in the store" with "I didn't find a match for X — closest we have is…" — never assert absence from one keyword miss.
  (b) Shipping: when `get_shipping_info` has no zone match for a destination, also run `search_site_content('shipping')` and answer from/flag the policy page; rewrite "isn't configured on this site" dev-speak to shopper language. Live-confirmed the bot denied products and shipping the store actually offers — the fastest way to lose a sale.

- [ ] **P0-4 [cross, medium] Abuse controls: provider usage metering/caps + chatbot rate limiting + request caps**
  Provider (`wp-ai-provider`): hook `WPAIP_Streamer::stream`'s `response.completed` event (carries exact input/output/cached token usage) to record tokens per `usage_bucket` per day; enforce a configurable daily message/token budget in `resolve_model_for_request`/`authenticate_request` returning 429 with a friendly message (the chatbot already surfaces provider rejections in-chat); show per-install usage in the existing admin installs table. Add `'max_output_tokens' => ~2048` to params in `handle_chat` (`class-wpaip-api.php:178-195`) and cap instructions length (~32KB), input item count (~100), per-item content length in `validate_chat_request`. Chatbot (`class-wpaic-api.php` `handle_chat_stream`): transient-based per-IP/per-session throttle (e.g. 20 req/5min), cap messages array (~40) and total content (~16k chars), reject client-fabricated roles, validate `session_id` as UUID. Today any visitor with the public nonce can burn unlimited provider tokens forever on a one-time-purchase product.

- [ ] **P0-5 [provider, small] Cost quick wins: prompt_cache_key, reasoning low, none→minimal, store=false**
  In `class-wpaip-api.php::handle_chat`: set `$params['prompt_cache_key'] = usage_bucket` (cached input on GPT-5 family is ~90% cheaper; the ~7k-char static prompt + tool definitions resend up to 10×/message — likely the single biggest input-cost lever, one line). Change `DEFAULT_REASONING_EFFORT` medium→low (`class-wpaip-admin.php:9`, activation default `wp-ai-provider.php:71`) — reasoning tokens bill as output (~8× input price) and dominate time-to-first-token; the highly prescriptive prompt should hold quality. Fix the "None" option: it currently omits the param so GPT-5 defaults to **medium** — map none→`'minimal'` (`class-wpaip-api.php:191-195`) and update the tests that codify the omission. Add `store=false` (Responses API defaults to storing every shopper conversation on OpenAI for 30 days). Verify quality via the Playwright flow (search → add → checkout) before/after.

- [ ] **P0-6 [backend, small] Token diet: split model-facing vs frontend-facing tool payloads**
  The same `$tool_result` feeds both the frontend (`tool_output_available`) and the model (`function_call_output`) at `class-wpaic-chat.php:373-388`. Add a `to_model_payload()` pass that strips `url`/`add_to_cart_url`/image URLs and collapses variations to `variation_id + attribute summary + price + stock` (today up to 29 full variation objects per product, re-sent every loop iteration — 50-80% of search-conversation tokens). Also: `wp_strip_all_tags` on `get_product_details` description (`class-wpaic-product-tools.php:378-379`), truncate `get_page_content` to ~4-6k chars (`class-wpaic-content-index.php:144-161`), cap FAQ injection to ~30 pairs with a size hint in the admin (`class-wpaic-system-prompt.php:97-115`), and trim conversation history to last ~20 messages / char budget in `build_responses_input` (`class-wpaic-chat.php:265-278`). Subsumes the FEATURES.md "strip cart/checkout URLs" backlog item.

- [ ] **P0-7 [frontend, small] Cut the 3-second send debounce to ~400ms**
  `MESSAGE_DEBOUNCE_MS = 3000` (`hooks/useChat.ts:55`) adds 3s to time-to-first-token on EVERY turn, including conversation-starter clicks, while showing a fake "Typing…" indicator. One-line change; keep the batching queue for messages typed mid-stream (edge session confirmed batching works and is worth keeping). Cheapest perceived-latency win in the entire product.

- [ ] **P0-8 [admin, small] Fix destructive saves: FAQ truncate-before-validate, CSV delete-before-import**
  `class-wpaic-admin.php:2272` runs `TRUNCATE TABLE wpaic_faqs` then parses; a malformed paste (no Q:/A: markers) shows an error AND deletes all existing FAQs. Parse first, replace only on success; report which pairs failed to parse (blank-line answers currently drop silently). CSV replace (`class-wpaic-admin.php:2151-2206`) deletes the old source before the new import succeeds and inserts row-by-row in one AJAX request — stage into temp rows / batch inserts and swap only on success.

- [ ] **P0-9 [frontend, medium] Scope Tailwind preflight — stop breaking host themes**
  `frontend/src/styles.css` imports full tailwindcss (preflight) plus `@layer base` `*`/body rules, enqueued document-wide (`class-wpaic-frontend.php:53-60`): resets list bullets, heading/paragraph margins, image display, forces white body background on any theme relying on UA defaults, and the global `prefers-reduced-motion * { animation: 0.01ms !important }` block (styles.css:161-169) kills ALL host-theme animations for reduced-motion visitors. Either render into a Shadow DOM root and adopt the stylesheet there (also fixes bleed-IN, removing the `.wpaic-no-underline` hack), or import theme/utilities only, drop the base-layer global rules, and scope reduced-motion under `#wpaic-chatbot-root`. A paid plugin that visibly breaks the storefront on install gets refunded before the AI is judged.

### P1 — High-impact improvements

- [ ] **P1-10 [backend, medium] Carry compact tool context across turns; ground ordinal references in card order**
  `transform_messages` (`class-wpaic-api.php:88-102`) keeps only text parts, so the model cannot resolve "the second one" / "the blue one" against previously shown cards — live-confirmed wrong-item add (card #10 instead of #2). Convert prior tool-output parts into a compact text part, e.g. `Products shown (in display order): 1. Kitchen Sieve (id 12, $8) 2. Red Tongs (id 417, $5.98) …`, and add a prompt rule: resolve ordinals/positions against this list; ask a one-line clarifying question when ambiguous. The most visible quality failure a returning shopper hits.

- [ ] **P1-11 [backend, small] Comparison trust: server-computed differences in compare_products + real attributes**
  Live-confirmed the bot inverted its own table's ratings at the purchase-decision moment. Compute a short `differences` summary server-side in the `compare_products` result (e.g. "A cheaper by $0.59; A rated 4.0 vs B 3.0; B has 3yr warranty") so the model paraphrases pre-computed facts, plus a prompt rule to restate ratings/prices verbatim from tool output. Also include `WC_Product::get_attributes()` (pa_* + custom), weight/dimensions with human labels in the payload — today the flagship table compares only price/stock/rating/categories (`class-wpaic-product-tools.php:280`), same data as the cards.

- [ ] **P1-12 [frontend, small] Card rendering discipline: dedupe, suppress redundant carousels, cap picks, single card after add**
  Live-confirmed: compare reply rendered a "3 PICKS" carousel (one product duplicated) above the comparison table; an add confirmation re-rendered the full 10-card carousel; a gift ask dumped 18 cards under text saying "a few". In the message renderer: dedupe cards by product ID; when a message contains a compare_products payload, drop card payloads for the same IDs; cap rendered picks at ~6; when a message's primary action is add_to_cart, render only the added product's card. Curation is the product's pitch.

- [ ] **P1-13 [backend, medium] Merchandising tools: coupons/what's-on-sale + cross-sells after add-to-cart**
  "Do you have any discounts?" currently dead-ends (grounding rules force "I don't know") — a top shopper question. Add `get_active_promotions` (published, non-expired `shop_coupon` posts with amount/restrictions) and an `on_sale` filter on `search_products` (`wc_get_product_ids_on_sale()`). Include up to 3 `cross_sell_ids`/`upsell_ids` products (id/name/price) in the add_to_cart success payload (`class-wpaic-tools.php:368-379`) + one prompt sentence allowing a single genuinely related suggestion after an add. Clearest "it sells for me" demo a shop owner sees in minute one; raises AOV.

- [ ] **P1-14 [admin, small] Fix transcript data loss + attach conversations to handoff requests**
  Live-confirmed: the handoff "Conversation Transcript" modal shows no conversation, only the phone field. Link the originating conversation ID at `create_handoff_request` time and render the Chat Logs transcript component in the modal. Also log EVERY trailing user message in `handle_chat_stream` (`class-wpaic-api.php:158-161` logs only the last; debounce batching sends several), log assistant turns whose content was card-only (`:271-273` skips empty text), and log a compact tool-event row ("showed 4 products: A, B, C, D", "added X to cart — confirmed") rendered as gray chips in transcripts. Prerequisite for P1-15.

- [ ] **P1-15 [admin, medium] Insights: summary cards, missed-search report, chat-to-cart attribution, scannable logs**
  The tool loop already sees every search (query, result count) and every add_to_cart/checkout action — record them (events table from P1-14) and surface: "This week: 42 chats · 18 products added to cart · 3 checkouts started · 3 handoffs" summary cards, top-10 zero-result searches (knowledge-gap goldmine, with one-click "add FAQ"), conversations/day trend. Replace the meaningless "Total Text" character column in Chat Logs with a first-user-message preview, add date filter + text search. This is the feature that makes a one-time-purchase owner feel it paid for itself; today there is zero aggregate value proof.

- [ ] **P1-16 [backend, small] Humanize category and attribute labels everywhere**
  Live-confirmed in three sessions: chat copy says "kitchen-accessories" / "sports-accessories", card captions show "MENS-SHIRTS", comparison rows show "mens-shirts", variable-card dropdowns show "blue"/"red" term slugs. Return WC term `->name` (keep slug as a separate field for tool params) in `get_categories`, `search_products`, `compare_products`, and variation attribute payloads (`class-wpaic-product-tools.php`). Small serialization fix, large perceived-quality jump.

- [ ] **P1-17 [frontend, small] Mobile viewport fixes: 100dvh, body scroll lock, admin-bar offset, zero-price guard**
  `ChatWidget.tsx:153`: `max-[480px]:h-[100vh]` → `100dvh` (iOS dynamic toolbar buries the input — checkout-killer); lock body scroll while fullscreen widget is open; offset below `#wpadminbar` when `body.admin-bar` present (live-confirmed: header buttons unclickable for logged-in owners testing their own bot); respect `env(safe-area-inset-top)`. Also guard $0.00-priced products: hide price / swap ADD for "View product" (live-confirmed broken-looking cards on WC sample hoodies).

- [ ] **P1-18 [frontend, medium] Proactive teaser bubble instead of auto-opening the full widget**
  Live-confirmed the full widget (fullscreen on mobile) opened within ~1-2s of a fresh session load. Replace the auto-open (`App.tsx:77-84`) with a dismissible message-preview bubble next to the launcher showing the configured proactive text; expand to full chat only on click. Verify the configured delay is actually honored. Extend the admin live preview to show it (Engagement tab). The most complained-about pattern in chat widgets, fixed.

- [ ] **P1-19 [frontend, medium] Skeleton product cards during product tool calls + unread badge on launcher**
  When `activeTools` includes search_products/get_popular_products/compare_products, render 2-3 shimmering card skeletons in-thread (shimmer keyframe exists in styles.css:65-68) instead of only the spinner bar — tool latency reads as the answer assembling itself. Plus: streaming already continues while the widget is closed; pulse the launcher with a `1` badge/preview when a response completes while `!isOpen`. The two cheapest shopper-wow additions.

- [ ] **P1-20 [backend, small] Conversation-engine robustness: try/catch tool execution, error objects for null, quiet logs**
  Wrap `execute_tool()` in the provider loop (`class-wpaic-chat.php:373`) and `handle_chat_stream` in try/catch — today a third-party-plugin fatal inside a WooCommerce call kills the SSE stream with no error event, leaving the widget spinning forever. Return `array('error' => 'Product not found')` instead of null from `get_product_details`/`get_page_content` (null serializes as bare `"null"` to the model). Remove the unconditional per-request `error_log` calls (`class-wpaic-chat.php:466,586`) that fill customer debug.log files.

- [ ] **P1-21 [provider, small] Provider hardening: HTTP timeout + retry, disconnect detection, grace-period signature check, friendly errors**
  `WPAIP_Streamer` uses `\OpenAI::client()` with Guzzle default timeout=0 — a stalled upstream holds FPM workers indefinitely; use `OpenAI::factory()->withHttpClient(new GuzzleHttp\Client(['connect_timeout' => 10, 'timeout' => 120]))`, retry `createStreamed` once on connect/429/5xx before any event is emitted, and check `connection_aborted()` in the event loop (stop paying for generations nobody watches). Security: `maybe_allow_grace_period` (`class-wpaip-license-validator.php:283-310`) skips HMAC verification entirely during Freemius outages — at minimum compare the request's public_key header against the stored `site_public_key`, ideally persist an HMAC-capable secret at `store_valid_record` time. Map raw exception messages (`emit_error`, `class-wpaip-streamer.php:47-49`) to a friendly generic message; log detail server-side.

- [ ] **P1-22 [admin, small] First-run onboarding checklist**
  Activation drops owners into a settings wall; the "Live on site" pill even reads Live when the license gate hides the widget. Add an activation redirect to a dismissible 4-step checklist card on the General tab: 1) start trial/activate (live status from `can_render_chat()`), 2) name + brand (link to Appearance preview), 3) add knowledge (FAQ/CSV/index status), 4) "Open your store and try it" link. Persist completion in an option; build with existing card components. Also fix the header pill to reflect `can_render_chat()` with a reason, not just `settings['enabled']` (`class-wpaic-admin.php:592,613-616`).

### P2 — Polish / hygiene

- [ ] **P2-23 [backend, medium] Remove the legacy direct-OpenAI path + dead Settings API code**
  The Chat Completions path (`class-wpaic-chat.php` send/send_stream/handle_tool_calls*, ~500 lines) omits `tools` on follow-up calls (no tool chaining), has no iteration guard, and contradicts the "no API key" positioning; the provider path is newer and tested. Delete it, the unused non-stream POST `/chat` route (`class-wpaic-api.php:21-29`), the never-rendered Settings API registration block + field renderers (~250 lines, `class-wpaic-admin.php:188-290,364-475`), and the `openai_api_key` setting (currently silently wiped on Licensing-tab saves). Update orphaned tests. Per repo rule: surface conflicts, don't average.

- [ ] **P2-24 [frontend, medium] Lazy-load the widget bundle**
  ~552KB raw / ~167KB gzip JS + 69KB CSS parse on every storefront pageview even if chat is never opened (`class-wpaic-frontend.php:53-68`) — hurts merchants' Core Web Vitals. Ship a <5KB stub rendering the launcher + proactive teaser timer; dynamic-import the React app on first interaction or `requestIdleCallback`. Evaluate replacing react-markdown+remark-gfm (bulk of the 388KB shared chunk) with a lighter renderer; drop unused shadcn chart/sidebar/.dark CSS vars.

- [ ] **P2-25 [frontend, small] Inline error state for card add-to-cart failures (stop navigating away)**
  `ProductCard.tsx:86-88` / `VariableProductCard.tsx:86-88` / `ComparisonTable.tsx:74-77` catch AJAX failure with `window.location.href = add_to_cart_url` — a full-page navigation out of the conversation. Wire the already-built (currently unreachable) `'error'` CartState with auto-reset instead. Also align ComparisonTable button styling (gradient/rounded-lg) with the flat rounded-full CTAs used everywhere else.

- [ ] **P2-26 [frontend, small] Accessibility pass**
  `role="log"` + `aria-live="polite"` on the message list (streamed replies are never announced); focus traps in ConfirmDialog/SendTranscriptDialog; default-focus Cancel (not the destructive confirm); return focus to launcher on close; `focus-visible:opacity-100` on the hidden carousel arrows; make admin color-preset dots real buttons with aria-labels. EU accessibility act applies to shops — a real marketing differentiator, all cheap.

- [ ] **P2-27 [admin, small] Admin truthfulness bundle: unsaved-changes guard, index freshness wording, handoff badge, markdown transcripts**
  Live-confirmed silent loss of Appearance edits on tab navigation — track dirty state + beforeunload prompt + "Save changes •" indicator. Reword "Index is fresh" next to a month-old timestamp ("Up to date — no content changed since May 6"); show products-not-indexed-on-activation honestly (`wp-ai-chatbot.php:205-208` builds only the content index). Add a `awaiting_mod`-style count bubble of status=new handoffs on the admin menu. Render transcripts through the markdown renderer (raw `**asterisks**` show today) and include confirmation outcomes.

- [ ] **P2-28 [admin, small] Privacy + lifecycle hygiene: retention setting, IP anonymization, uninstall.php**
  Visitor IPs stored indefinitely (`class-wpaic-logs.php:16-26`) with no purge/anonymize option and no WP privacy exporter/eraser integration — a purchase blocker for EU merchants. Add "delete conversations older than N days" cron + anonymize-IP toggle. Add `uninstall.php` dropping the six custom tables + options (none exists today).

---

## Suggested execution order

1. **Week 1 — stop the bleeding:** P0-1, P0-2, P0-7, P0-8 (shipper-visible bugs), P0-5 (one-line cost wins).
2. **Week 2 — economics + trust:** P0-4, P0-6, P0-3, P1-20, P1-21.
3. **Week 3 — quality & wow:** P0-9, P1-10, P1-11, P1-12, P1-16, P1-17.
4. **Week 4 — sell-for-me value:** P1-13, P1-14, P1-15, P1-18, P1-19, P1-22.
5. **Then:** P2 hygiene as capacity allows.

Verification: every cart/checkout item must be re-verified with the Playwright flow on `http://wp-ai-chatbot.local/` (search → add → remove → checkout CTA → reload → reopen widget → confirm cart unchanged), and cost items verified against provider logs showing cached-token hits and reduced reasoning tokens.

---

## Round-2 addenda (from P0 verification, 2026-06-10)

- [ ] **NEW-A [backend, small] Search recall + grounding polish (verification findings)**
  (a) "running shoos" surfaced women's heels but never the actual 'Sports Sneakers Off White Red' products — when primary results lack a title-token match for the query noun, merge in synonym-variant results (shoe↔sneaker↔trainer) before returning. (b) Shipping: bot referenced the policy page but said "costs not shown for Spain" while the page lists $5.99/$14.99/$29.99 — strengthen the no-zone-match hint to instruct the model to actually fetch the shipping policy page (get_page_content) and quote concrete rates. (c) Pivot wording invented "pet items" the store doesn't carry — prompt rule: pivot suggestions must name only real categories from get_categories output. (d) Down-rank $0.00-priced products in search results so priceless sample data stops leading recommendations.

- [ ] **NEW-B [provider, small] Provider admin: mask OpenAI key + surface cache hit rate**
  The OpenAI API key renders as a plaintext text input (full sk-... visible in page source). Render as password-type field showing only last 4 chars when saved (keep editable). Usage Today column shows msgs + total tokens but not the tracked cached_input_tokens — add cache-hit % so cost monitoring needs no DB access.
