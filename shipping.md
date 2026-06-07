# Shipping WP AI Chatbot

Grounded launch plan based on a full codebase pass (2026-06-04). Order: **fix blockers → harden → package → verify → market.** Don't market a buggy or abusable build.

`/skill` = run that skill. Items marked **(install first)** are external plugins to install yourself via `!`.

---

## Status snapshot — what's already solid

Don't re-do these. They're done and tested.

- **Tests green**: chatbot 287 PHP / provider 66 PHP / frontend 202 JS, all passing. Vite build works.
- **Feature set is broad & polished** — see `FEATURES.md` and the manual UX pass in `feedback_claude.md` (all flagged items resolved).
- **Auth design is sound**: HMAC-SHA256 signed validation between chatbot ↔ provider, 300s replay window, OpenAI key never leaves the provider.
- **Tool dispatch is safe by construction**: hardcoded `match` allowlist, read-only tools, no write/mutation tools exposed to the model, args type-cast per tool.
- **XSS mitigated**: react-markdown with element whitelist, no `rehype-raw`, links forced `rel="noopener noreferrer"`.
- **Admin surface locked**: all `wp_ajax_wpaic_*` handlers check `current_user_can('manage_options')` + nonce; SQL via `$wpdb->prepare`; settings sanitized.
- **No secrets committed**: only the Freemius *public* key ships (by design); OpenAI key lives solely in provider settings.

---

## Phase 0 — Critical blockers (must fix before ANY paid/public traffic)

These are confirmed in code, not hypotheticals. Each is a launch-stopper.

- [ ] **Rate limiting — the #1 blocker.** There is *zero* throttling anywhere. The chat nonce is handed to every anonymous visitor (`class-wpaic-frontend.php:94`), each message loops up to 10 provider round-trips (`MAX_PROVIDER_ITERATIONS`, `class-wpaic-chat.php:237`), and you hold the shared OpenAI key. Any trial/licensed site — or a scraper — can drive unbounded OpenAI spend.
  - Provider-side (primary): per-install request cap keyed on the existing `usage_bucket` (`fs_install_<id>`), plus a global concurrency cap. Return `429` + `Retry-After`. The plumbing exists (`class-wpaip-install-registry.php`); nothing increments it yet.
  - Chatbot-side (secondary): per-visitor/IP daily message cap (already on the wishlist — `ideas.md:1`). Surface the 429 to the user gracefully — the chatbot has no 429 handling today.
  - **Note:** exact limits depend on the monetization model (still open — see Phase 6). Build the enforcement mechanism now; the *numbers* can be tuned per plan later.
- [ ] **Production provider URL.** `WPAIC_PRODUCTION_PROVIDER_URL` is hardcoded to `http://wp-ai-chatbot-provider.local/...` (your Local dev host, plain HTTP) — `wp-ai-chatbot.php:28`. Ships broken. Replace with the real HTTPS URL. The placeholder guard only catches the literal string `PLACEHOLDER_PROVIDER_URL`, so it won't catch this.
- [ ] **Provider Freemius API token.** `WPAIP_FREEMIUS_API_TOKEN` defaults to `''` (`wp-ai-provider.php:37`). Without it the validator returns 503 for *every* request. Set it on the production provider (wp-config constant or admin field).
- [ ] **Lock down `/products` endpoint.** `GET /wpaic/v1/products` is `permission_callback => '__return_true'` — fully unauthenticated, no license check, attacker-controlled `limit` (`class-wpaic-api.php:41`). Gate behind the nonce + license check, and hard-cap `limit`.
- [ ] **Fix non-published product leak.** `get_product_details` and `compare_products` check `post_type` but not `post_status` (`class-wpaic-tools.php:96-104, 152-156`) — a crafted message can pull draft/private products (prices, SKUs, stock) to anonymous users. Add a `publish` + catalog-visibility check, matching `search_products` (which does it right at `:44`).
- [ ] **Stop leaking upstream error text.** Provider emits raw `$exception->getMessage()` into the public SSE stream (`class-wpaip-streamer.php:44`), surfaced to the frontend. OpenAI errors can carry org/key/model/quota detail. Send a generic message to the client; log the real one server-side only.

---

## Phase 1 — Hardening (do before launch, lower severity than Phase 0)

- [ ] **SSE timeouts / abort.** No wall-clock cap; worst case ~20 min/request across the 10-iteration loop. Add a hard request-duration ceiling, an iteration/token cap, and a `connection_aborted()` bailout in both `stream_from_provider` (`class-wpaic-chat.php:344`) and the provider streamer loop. Protects PHP-FPM workers from exhaustion.
- [ ] **`send-transcript` = open mail relay.** Any visitor with the public nonce can POST an arbitrary `email` + transcript and the site sends branded mail (`class-wpaic-api.php:340`). Rate-limit per IP, cap transcript size, ideally bind to a logged conversation.
- [ ] **Replay cache (optional, ties to rate limiting).** A captured signed request can be replayed within the 300s TTL. A used-signature cache closes it; less urgent once rate limiting lands.
- [ ] **Document the "uploaded data is public" model.** `query_custom_data` (incl. empty-query "return all", `class-wpaic-tools.php:729`) and FAQs are fully exposed via chat by design. Make this explicit to admins in the Train Bot UI; consider a per-source "expose to chat" toggle.
- [ ] `/security-review` — final security pass on chatbot + provider (SSE, license validation, tool calls) to catch anything missed.
- [ ] `/wp-phpstan` — static analysis, fix findings (config at `wp-ai-chatbot/phpstan.neon`).
- [ ] `/wp-performance` — check autoloaded options, queries, HTTP/SSE paths. Note: `wpaip_install_registry` is a single WP option rewritten on every upsert — will become write-contention as installs grow; flag for a custom table post-launch.

---

## Phase 2 — Model upgrade

- [ ] **Confirm the current OpenAI lineup** on the account, then pick a cheap default + a premium option. Today the dropdown offers `gpt-4o-mini` (default), `gpt-4o`, `gpt-5` on both sides (`class-wpaic-admin.php:35-40`, `class-wpaip-admin.php:17-22`).
- [ ] **`gpt-5` is offered but never validated** against account access — selecting it fails at OpenAI if the account lacks access. Either validate the model string at request time, or restrict the dropdown to models you've confirmed.
- [ ] Update the default to the best price/quality model for the launch tier, and re-run the chat test suite (system-prompt assertions in `WPAIC_ChatTest` may need tuning).
- [ ] Consider requesting `stream_options: {include_usage: true}` from OpenAI so the provider can capture token usage — prerequisite for cost tracking and any usage-based pricing.

---

## Phase 3 — Release packaging (distributable plugin)

- [ ] **Write `wp-ai-chatbot/readme.txt`** (missing) — WP.org/Freemius standard: stable tag, short + long description, tags, "Tested up to", changelog, screenshots, FAQ. Use `/ce-strategy` + `/draft-content`.
- [ ] **`uninstall.php`** (missing, both plugins) — clean up the 6 chatbot tables + options on delete.
- [ ] **`LICENSE` file** (missing, both) — GPLv2 to match the header.
- [ ] **`.distignore`** (missing) — exclude `frontend/node_modules/`, `tests/`, dev configs, `.phpunit.cache`. Without it the zip ships bloated.
- [ ] **Build frontend before packaging** — `cd frontend && npm run build`. `dist/` is gitignored, so it must be built fresh into every release zip.
- [ ] **Listing assets** (missing) — `assets/banner-772x250.png`, `assets/icon-256x256.png`, `assets/screenshot-N.png`. Use `/ce-demo-reel` to capture widget screenshots/GIF.
- [ ] **i18n decision** — strings are wrapped (`__()` heavily used) but there's no `load_plugin_textdomain()` call and no `.pot`. Fine to launch English-only; note it. If shipping translations, add the bootstrap + generate the `.pot`.
- [ ] `/wp-plugin-development` — release packaging: version bump, build zip, verify headers (both currently v1.0.0).

---

## Phase 4 — Pre-launch verification

- [ ] **Freemius end-to-end in sandbox then live**: trial start, license activation, premium update flow, expiry → chat hides. Confirm `WPAIC_FREEMIUS_SANDBOX_SECRET_KEY` is *unset* in prod (else SDK runs sandbox mode), and `WP_ENVIRONMENT_TYPE` is not `local` on the prod provider (else the local-dev license bypass activates).
- [ ] **Confirm chatbot & provider Freemius product IDs match** (both `27158`) and point at the intended production product, with plans/trial/pricing configured in the Freemius dashboard (pricing lives there, not in code).
- [ ] **Multi-environment matrix** — install the release zip clean on: latest WP + WooCommerce, WP 6.0 (min declared), PHP 8.2 (min declared) + 8.4, a non-block theme + a block theme, and with common plugins (caching, security). Verify the widget renders, streams, and tool calls work on each.
- [ ] **Real-store smoke test** — load test products across types (simple, variable, external, grouped, out-of-stock, subscription), then run the `feedback_claude.md` shopper script again against the release build.
- [ ] `/verify` + `/ce-test-browser` — widget works end-to-end in a real browser.
- [ ] **Tighten test gaps** found in review: Freemius API client (`class-wpaip-freemius-api.php`, untested — real billing integration), TNTSearch index build (`class-wpaic-search-index.php`, only fallback path tested), `send-transcript` dialog UI. Add coverage for the new rate-limiting + 429 paths.
- [ ] Re-run full PHPUnit + Vitest suites — confirm green after all changes.

---

## Phase 5 — Marketing & launch

- [ ] (install first) Anthropic Marketing plugin: https://claude.com/plugins/marketing
- [ ] (install first, optional) Corey Haines CRO pack: `! /plugin marketplace add coreyhaines31/marketingskills` then `! /plugin install marketing-skills`
- [ ] `/ce-strategy` — `STRATEGY.md`: positioning, target user (WooCommerce store owners), key metrics.
- [ ] `/deep-research` + `/competitive-brief` — competitor scan (other WooCommerce AI chatbots), pricing benchmarks, gaps. **Feeds the monetization decision (Phase 6).**
- [ ] `/frontend-design` — landing page for the plugin.
- [ ] `/draft-content` — listing copy, launch posts (Product Hunt, r/woocommerce, WP FB/Telegram/Discord groups per `todo.md`), SEO blog post.
- [ ] `/seo-audit` — audit landing page + listing.
- [ ] `/email-sequence` — trial → paid nurture sequence (ties into Freemius trials).
- [ ] `/campaign-plan` — launch campaign: channels, calendar, success metrics.

---

## Phase 6 — Open decision: monetization (deferred, not blocking)

Parked at your request. Captured here so the Phase 0 rate-limiting work can be tuned once decided. The pivotal fact: **you hold the shared OpenAI key, so any managed-key model needs hard per-site quotas or cost diverges from revenue.**

- **Monthly, managed key** — recurring revenue covers recurring cost (aligned). Needs per-plan quotas (building anyway in Phase 0).
- **One-time + BYOK** — zero ongoing cost, so one-time is safe. Needs wiring the dormant chatbot-side key UI + a provider bypass/pass-through.
- **Hybrid** — widest market, most build (both of the above).
- **One-time, managed key** — riskiest; only viable with a strict enforced cap and break-even math.

Decide before setting Freemius plan prices (Phase 4) and finalizing rate-limit numbers (Phase 0).

---

## Post-launch

- [ ] `/ce-product-pulse` — usage / quality / error pulse after first traffic.
- [ ] **Provider cost & token tracking** — capture `usage` per request, per-site spend dashboard, budget alerts. None exists today; needed to know if you're profitable.
- [ ] `wpaip_install_registry` → custom table (scaling).
- [ ] High-value backlog from `todo.md`/`ideas.md`: message feedback (👍/👎 — quality signal), quick-reply chips, coupon support, GDPR kit (EU AI Act Art. 50 disclosure mandatory Aug 2 2026), logged-in user context.
