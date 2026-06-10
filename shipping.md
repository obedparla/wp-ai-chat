# Shipping WP AI Chatbot

Grounded launch plan. Originally from a full codebase pass (2026-06-04); **updated 2026-06-11** after the QA-driven overhaul (PR #11, merged — 4 implementation rounds, 4 browser-verification rounds, thermo-nuclear review + fixes) and the bugfix/UX round (`cc4edb4`). Order: **fix blockers → harden → package → verify → market.**

`/skill` = run that skill. Items marked **(install first)** are external plugins to install yourself via `!`.

---

## Status snapshot — what's already solid

Don't re-do these. Done, tested, and live-browser-verified.

- **Tests green**: chatbot 583 PHP / provider 140 PHP / frontend 327 JS (up from 287/66/202). Vite build works; widget ships as a ~6KB lazy loader + on-demand app chunk.
- **Feature set broad & polished** — see `FEATURES.md` (rewritten to match reality) and `IMPROVEMENT_PLAN.md` (37/37 items shipped: cart-replay + variable-add bugs, search recall, grounding, insights dashboard, onboarding, teaser bubble, a11y, mobile fixes…).
- **Rate limiting & abuse controls exist now** (the old #1 blocker): chatbot per-IP/session fixed-window throttle + request caps + role whitelist + UUID session validation; provider per-install daily message/token budgets returning 429 (surfaced gracefully in-chat); `max_output_tokens` + request-size caps; `/send-transcript` throttled and license-gated.
- **Cost controls**: `prompt_cache_key` (~84-88% cached input measured), reasoning low / none→minimal, `store=false`, model-facing token diet. Real cost ≈ 2¢/conversation on 5.4 mini, ~0.5¢ on nano.
- **Usage metering**: exact per-install daily tokens/messages in an atomic `wpaip_usage_daily` table, admin column with cache-hit %.
- **Auth design sound**: HMAC-SHA256 chatbot↔provider, 300s replay window; grace period now verifies real HMAC against a persisted per-install secret; OpenAI key never leaves the provider; both secret fields masked in admin.
- **Tool dispatch safe by construction**: hardcoded `match` allowlist, args type-cast; cart mutations validated server-side, executed client-side behind confirm popups; coupons tool opt-in (default OFF) and gated at registration + dispatch.
- **XSS mitigated**: marked + DOMPurify (replaced react-markdown) with tag/attr allowlists, links forced `noopener`; admin transcript renderer escape-first; review found zero SQLi/XSS in the new surface.
- **Admin surface locked**: `manage_options` + nonces on all AJAX, `$wpdb->prepare` everywhere, destructive FAQ/CSV saves fixed (parse-first / staged swap).
- **Errors don't leak**: provider + chatbot send generic shopper messages, detail to server logs only.
- **No secrets committed**; `uninstall.php` ships (tables, options, cron, uploads/wpaic index files).

---

## Phase 0 — Critical blockers (must fix before ANY paid/public traffic)

Confirmed in code, not hypotheticals. Each is a launch-stopper.

- [x] **Rate limiting** — shipped in PR #11 (chatbot throttle + provider budgets + 429 handling, see snapshot). Limit *numbers* still need tuning to the monetization decision (Phase 6); mechanism is built and filterable per install (`wpaip_daily_*_budget` filters).
- [ ] **Stale-nonce breakage on cached stores (NEW — found post-PR).** Widget auths REST calls with a `wp_rest` nonce baked into the page (`class-wpaic-frontend.php:97` → `useChat.ts` `X-WP-Nonce`). Full-page caching (WP Rocket/LiteSpeed/Cloudflare APO — near-universal on WooCommerce) serves nonces older than their 12-24h lifetime → **every chat request 403s forever** on exactly the optimized stores most likely to buy. Dev site has no page cache, so QA never saw it.
  - [ ] On 401/403 (`rest_cookie_invalid_nonce`), fetch a fresh nonce from a small never-cached endpoint and retry once — or deliberately drop the nonce on the public chat endpoints and lean on the rate limiter (decide one).
  - [ ] Smoke test behind a real cache plugin (filter `nonce_life` down to minutes to test fast).
- [ ] **Provider production readiness (NEW).** Every customer chatbot depends on one WP server; each streaming chat holds a PHP-FPM worker for 10-40s on BOTH ends. Concurrent shoppers across ~50 sites can exhaust provider workers and take chat down for everyone. Never load-tested.
  - [ ] Size PHP-FPM workers deliberately (pm.max_children vs expected concurrent streams; 1 stream ≈ 1 worker for its duration).
  - [ ] Load test ~20-50 concurrent SSE streams against the provider.
  - [ ] Provider-side concurrent-stream cap returning a friendly 503 (chatbot already renders provider rejections).
  - [ ] Uptime + error monitoring with alerting (the provider is a SPOF for the whole product).
  - [ ] Backups for the provider DB (install registry + usage table are business records).
- [ ] **Production provider URL.** `WPAIC_PRODUCTION_PROVIDER_URL` still hardcoded to `http://wp-ai-chatbot-provider.local/...` (plain HTTP, dev host) — `wp-ai-chatbot.php:28`. Ships broken. Replace with the real HTTPS URL (placeholder guard won't catch this string).
- [ ] **Provider Freemius API token** on the production provider (wp-config constant or admin field) — without it every request 503s.
- [ ] **`/products` endpoint** — `permission_callback => '__return_true'` remains (`class-wpaic-api.php:65`); `limit` is now clamped (max 10) so severity is reduced, but still gate it behind the same nonce + license check as chat.
- [ ] **Non-published product leak — verify the by-ID paths.** Search/popular queries now filter `post_status => publish` (`class-wpaic-product-tools.php:73,222`); confirm `get_product_details`/`compare_products` (direct wc_get_product by ID) also reject draft/private/catalog-hidden products, add the check if not.
- [x] **Upstream error text leak** — fixed (P1-21 + round 3): generic shopper messages, detail server-side.

---

## Phase 1 — Hardening (before launch, lower severity than Phase 0)

- [~] **SSE timeouts / abort** — provider side DONE (Guzzle connect/read timeouts, single pre-emission retry, `connection_aborted()` cutoff). Chatbot side still open: no wall-clock ceiling or `connection_aborted()` in `stream_from_provider` — add a request-duration cap so a stalled provider can't pin customer-site workers.
- [x] **`send-transcript` mail relay** — throttled (IP + session buckets, session_id now sent by the dialog), license-gated, session-validated.
- [ ] **Replay cache (optional).** Captured signed requests replayable within the 300s TTL; a used-signature cache closes it. Less urgent now that budgets cap damage.
- [ ] **Document the "uploaded data is public" model** — `query_custom_data` + FAQs are exposed via chat by design; say so in the Knowledge tab UI; consider per-source "expose to chat" toggle.
- [x] **Security pass** — thermo-nuclear review on the full PR #11 diff (security-focused: endpoints, SQLi, XSS, auth, uninstall) + all findings fixed and re-reviewed ("approve / mergeable"). A fresh `/security-review` on the release build is still cheap insurance before launch.
- [~] `/wp-phpstan` — runs clean on all PR-touched files; 18 pre-existing errors remain in untouched files (bootstrap, license-manager, content-index, frontend manifest). Fix or baseline them.
- [ ] `/wp-performance` — autoloaded options check still worth a pass. Known: `wpaip_install_registry` is still a single option rewritten per upsert (usage moved to a table; registry didn't) — flag for a custom table as installs grow.

---

## Phase 2 — Model & economics

- [x] **Model lineup** — provider-decided (chatbot's requested model ignored): GPT-5 Mini / 5.4 Mini / 5.4 Nano dropdown + reasoning effort selector; dev provider on 5.4 nano low for evaluation.
- [x] **Token usage capture** — exact usage from `response.completed` recorded per install/day (input/cached/output/total).
- [ ] **Nano-vs-mini decision** — run `NANO_VS_MINI_TEST_HANDOFF.md` (10 hard gates derived from mini's verified behavior, 7 soft checks, 3-way verdict). Nano ≈ 3.7× cheaper (~$350/yr busy store vs ~$1,300 mini); ship nano as default only if gates pass.
- [ ] **Default budget numbers** — current defaults cap an install at ~1M tokens/day (≈85 shopper messages, ≈$35/yr worst-case on nano). Deliberate choice needed jointly with Phase 6 pricing: raise for launch, or keep and sell higher budgets as the paid tier.

---

## Phase 3 — Release packaging (distributable plugin)

- [ ] **`wp-ai-chatbot/readme.txt`** (still missing) — stable tag, descriptions, tags, "Tested up to", changelog, screenshots, FAQ.
- [x] **`uninstall.php`** — ships (7 tables, options, cron hooks, uploads/wpaic index dir). Provider is internal-only — N/A.
- [ ] **`LICENSE` file** (still missing, both) — GPLv2 to match headers.
- [ ] **`.distignore` / build script** (still missing) — fresh `npm ci && npm run build`, `composer install --no-dev`, exclude `frontend/node_modules`, `tests/`, dev configs, `.phpunit.cache`, `.qa-screenshots`. `dist/` is gitignored so it must be built into every zip.
- [ ] **Drop dead dependency** — `composer remove openai-php/client` in wp-ai-chatbot (unused since the legacy direct-OpenAI path was deleted in PR #11; needs a machine with composer). Check whether guzzle is still needed transitively.
- [ ] **Install the built zip on a clean WP site** — activation smoke test: widget renders, chat streams, admin pages load, tables created.
- [ ] **Listing assets** — banner, icon, screenshots (`.qa-screenshots/` has ~90 real captures to choose from).
- [ ] **i18n decision** — strings wrapped but no `load_plugin_textdomain()` and no `.pot` (verified still absent). Fine to launch English-only; note it.
- [ ] `/wp-plugin-development` — version bump + header verify before zip.

---

## Phase 4 — Pre-launch verification

- [~] **Freemius end-to-end on a real domain** — partial. Updater config bug fixed (`cc4edb4`: `has_premium_version`/`is_org_compliant` had gated `FS_Plugin_Updater` off — premium updates would never have been offered). Still to do, on a staging site with a real public domain (local dev *bypasses* license validation, so the money path has never fully executed):
  - [ ] install → trial start → provider validates → chat works
  - [ ] sandbox purchase → license activation
  - [ ] trial expiry / revocation → chat hides gracefully, provider rejects mid-conversation with the right message
  - [ ] ship a v-next update through Freemius and confirm upgrade hooks fire on a real update (events table, usage table, `wpaic_index_version` rebuild — written, never run via real update)
  - [ ] confirm `WPAIC_FREEMIUS_SANDBOX_SECRET_KEY` unset in prod and `WP_ENVIRONMENT_TYPE` ≠ `local` on the prod provider
- [ ] **Multi-environment matrix** — release zip clean-installed on: latest WP+Woo, WP 6.0, PHP 8.2 + 8.4, a block + non-block theme (Storefront, Astra), **plus a caching plugin and a JS optimizer** (ties to the Phase 0 nonce blocker; Autoptimize/defer vs the lazy loader). QA so far ran on ONE theme without caching.
- [ ] **Real-device iOS pass** — dvh/safe-area/scroll-lock fixes verified in emulated viewports only.
- [~] **Real-store smoke test** — extensive shopper QA done on the dev store across 4 browser-verification rounds (full arcs, carts to the cent, variable products, multilingual, edge cases). Re-run one shopper script against the **release build** on the matrix above.
- [x] **Test gaps from the original review** — TNTSearch index now tested against a real built index (`WPAIC_SearchIndexTest`); rate-limiting + 429 + budget paths covered; transcript dialog covered. Freemius API client (provider) still thin — acceptable, exercised by the staging walk above.
- [ ] **Rotate the OpenAI key** before it becomes the production key (it appeared in a local gitignored QA screenshot).
- [ ] Re-run full PHPUnit + Vitest suites on the release build — confirm green.

---

## Phase 5 — Marketing & launch

(Untouched — as originally planned.)

- [ ] (install first) Anthropic Marketing plugin: https://claude.com/plugins/marketing
- [ ] (install first, optional) Corey Haines CRO pack: `! /plugin marketplace add coreyhaines31/marketingskills` then `! /plugin install marketing-skills`
- [ ] `/ce-strategy` — `STRATEGY.md`: positioning, target user (WooCommerce store owners), key metrics.
- [ ] `/deep-research` + `/competitive-brief` — competitor scan, pricing benchmarks, gaps. **Feeds Phase 6.**
- [ ] `/frontend-design` — landing page.
- [ ] `/draft-content` — listing copy, launch posts (Product Hunt, r/woocommerce, WP groups), SEO post.
- [ ] `/seo-audit` — landing page + listing.
- [ ] `/email-sequence` — trial → paid nurture.
- [ ] `/campaign-plan` — channels, calendar, success metrics.

Also available now: name shortlist in `name_ideas.md` (trademark constraint: no Woo/WooCommerce/WordPress in the brand).

---

## Phase 6 — Open decision: monetization

Now data-informed. The metering/budget plumbing this depends on is **built** (per-install daily budgets, filterable per bucket, usage + cache-hit visibility in admin).

**Measured economics (June 2026 pricing, real traffic):** ~2¢/conversation on 5.4 mini low, ~0.5¢ on nano. Busy store (200 convos/day) ≈ $1,300/yr mini, ~$350/yr nano. Default budgets as shipped cap any install at ≈ $35/yr worst-case on nano — busy stores hit 429s instead of draining margin.

- **Leading candidate — capped one-time + subscription tier:** $99.99 one-time includes the default daily budget (median store never notices; worst case ~$35/yr cost); busy stores hitting daily 429s upsell to a "Pro usage" subscription (~$29-39/mo) that raises their bucket via the existing filters. Freemius supports recurring.
- One-time + BYOK — zero ongoing cost; needs re-wiring a chatbot-side key path (the legacy one was deliberately deleted in PR #11), so this is now a *build*, not a toggle.
- Monthly managed — aligned but a harder sell vs one-time competitors.
- Unlimited one-time managed — structurally unviable at any model price; ruled out.

Decide before Freemius plan prices (Phase 4) and final budget defaults (Phase 2).

---

## Post-launch

- [ ] `/ce-product-pulse` — usage / quality / error pulse after first traffic.
- [x] **Provider cost & token tracking** — shipped (per-install daily table, admin column, cache-hit %). Remaining nice-to-have: spend alerts/dashboard beyond the table.
- [ ] `wpaip_install_registry` → custom table (scaling; usage already moved, registry didn't).
- [ ] Admin file decomposition (`class-wpaic-admin.php` still ~2,700 lines — flagged by review, deliberately deferred).
- [ ] High-value backlog: message feedback (👍/👎), quick-reply chips, logged-in user context, `get_popular_products` keyword param (last genuine FEATURES.md backlog item). Coupons: DONE (opt-in promotions tool).
- [ ] **GDPR/EU AI Act**: retention setting, IP anonymization, uninstall cleanup shipped. Remaining: merchant-facing disclosure template ("chat is AI + data flows to our service and OpenAI") — EU AI Act Art. 50 disclosure mandatory Aug 2 2026.
