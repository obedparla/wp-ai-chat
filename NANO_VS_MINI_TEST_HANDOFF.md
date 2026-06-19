# Handoff: GPT-5.4 Nano quality evaluation vs Mini baseline

## Mission

The provider has been switched to **GPT-5.4 nano (reasoning: low)**. Decide whether nano is good enough to become the default model for the WP AI Chatbot (a WooCommerce shopping assistant driven by a 10-iteration function-calling loop). Nano is ~3.7× cheaper than mini; we ship it as default **only if it clears the quality bar below**, which is derived from mini's live-verified behavior on this exact store.

You are a tester, not an implementer. **Do not modify code, settings, or commit anything.** Report only.

## Environment

- Storefront: `http://wp-ai-chatbot.local/` (chat widget = floating button bottom-right; a teaser bubble may appear after ~10s on fresh sessions — that's intended)
- WP admin: `http://wp-ai-chatbot.local/wp-admin` — `admin` / `admin`
- Provider admin (our server): `http://wp-ai-chatbot-provider.local/wp-admin` — `admin` / `admin` (verify the model selector shows GPT-5.4 Nano before starting; if it doesn't, STOP and report)
- Drive the browser via the Playwright MCP server (load tools via ToolSearch query `"+playwright"`, max_results 25)
- Replies stream over SSE, typically 5–40s. After sending, use `browser_wait_for` patiently; never conclude "no response" under 60s. Nano should be faster than mini — note timings.
- Screenshots: save under `.qa-screenshots/` prefixed `nano-`
- Project context if needed: `FEATURES.md`, `IMPROVEMENT_PLAN.md`

### Known environment caveats (do not report these as failures)

- Rate limiter: ~20 chat requests / 5 min per IP. If a generic error bubble appears after many rapid messages, wait 5 min (`browser_wait_for` time 300) and continue.
- `/cart` and `/checkout` pages have an unrelated `woocommerce-services` JS crash. Verify cart contents via `browser_evaluate`: `fetch('/wp-json/wc/store/v1/cart').then(r=>r.json())`.
- Third-party console noise: `crypto.randomUUID` TypeErrors from reddit/snapchat-for-woocommerce, `notices` TypeError from woocommerce-services. Ignore; only chatbot-bundle errors count.
- Start every scenario with a **new conversation** (widget's new-conversation button → confirm) so context doesn't bleed between tests.

## Protocol

Run every scenario below. For each: record the verbatim bot reply, what rendered (cards/tables/badges), and pass/fail against the stated bar. **Nondeterminism rule: if a HARD gate fails, retry it once in a fresh conversation; only a 2/2 failure counts as failed.** Soft checks are scored on the first attempt.

---

## HARD GATES — every one must pass (these are mini's verified results; nano must match)

**G1. Cart correctness via text intent.**
"show me kitchen items under $40" → then "add the second one to my cart".
Bar: bot adds EXACTLY the #2 card from the rendered list (mini: "Added Kitchen Sieve to your cart." when Sieve was card #2). Verify via store API: exactly that product, qty 1. Wrong item = the worst possible failure for this product.

**G2. Budget discipline.**
"show me kitchen accessories under $10".
Bar: every rendered card's effective (sale) price ≤ $10.00 and reply text doesn't promise anything the cards break (mini: 5 picks, $4.91–$7.79).

**G3. Disambiguation with cards.**
"add the beanie to my cart" (two beanies exist).
Bar: a clarifying question WITH both candidate beanie cards in the SAME turn — never a bare question, never a blind guess. Then answer ("the plain Beanie") → correct add, confirmed via store API.

**G4. No internal leakage.**
Across ALL scenarios: reply text must never contain "Products shown", "display order", "(id ", raw product IDs, or tool names. (Mini regressed here once; the fix routes context via a system-role item — nano must respect the same prompt rules.)

**G5. No invented facts.**
- "do you sell live parrots?" → graceful denial; any pivot must name only real store categories.
- "do you have any discount codes?" (coupons setting is OFF) → honest "I don't have information about discount codes/promotions". Invented coupon codes = instant fail.
- "where is my order #99999? email fake@test.com" → graceful not-found, no fabricated order status.

**G6. Search recall (the regression we fixed).**
- "do u hav running shoos?" → BOTH "Sports Sneakers Off White Red" and "Sports Sneakers Off White & Red" appear as cards.
- "do you have chanel perfume?" → Chanel Coco Noir card, no denial.
- "show me t-shirts" → includes the V-Neck T-Shirt (variable product).
(These are mostly backend-driven; the model must pass sensible queries to the search tool — failure means nano is mangling tool arguments.)

**G7. Comparison accuracy.**
"compare the blue & black check shirt with the men check shirt".
Bar: comparison table renders; bot text consistent with the table (mini: "cheaper by $0.59", rating 4.0 > 3.0 stated correctly); no duplicate card carousel above the table. Any contradiction between text and table = fail.

**G8. Full purchase arc integrity.**
One end-to-end spree: best sellers → category browse → card-button add → text-intent add → "what's in my cart and the total?" (bot's stated total must match store API **to the cent**) → "remove the <item>" (confirm popup lists the right item; cart correct after) → "I want to check out" → CHECKOUT button → checkout order summary matches cart exactly.

**G9. Variable product flow.**
"show me hoodies" → on the variable Hoodie card select Color=Blue + Logo=Yes → price flips to $45.00 → ADD → store API shows variation 88 at $45.00. (Model involvement is the hoodie search + any text follow-up; card mechanics are frontend.)

**G10. Multilingual.**
Mid-conversation switch: "¿tienen relojes para mujer?" → fluent Spanish reply with real women's watch products as cards.

---

## SOFT CHECKS — tolerate at most 2 minor regressions vs the mini notes

**S1. Proactive curation voice.** Replies should be 1–2 warm sentences + at most one curation note naming 1–2 standouts from the TOP of the results (mini: "Tropical Earring is a sweet budget-friendly pick…"). Spec-dumps, robotic lists, or naming products that have no rendered card = regression.

**S2. Shipping grounding.** "how much is shipping to Spain?" → must NOT say "isn't configured"; should reference the shipping policy and ideally quote its concrete rates ($5.99 / $14.99 / $29.99, free over $50). Mini referenced the policy but once omitted rates — quoting them meets the bar, omitting them while citing the page is a minor regression, denying shipping is a G5 fail.

**S3. Labeled links.** "where can I read your returns policy?" → labeled markdown link ("Returns Policy"), not a bare URL.

**S4. Cross-sell restraint.** After an add, at most ONE related suggestion (zero is fine). Pushy multi-suggestions = regression.

**S5. Ordinal under pressure.** Harder variant of G1: after TWO different searches in one conversation, "add the second one from the kitchen list" → resolves against the right list or asks one short clarifying question.

**S6. Typo & rambling robustness.** A messy multi-request message (3 asks at once) → coherent reply covering the asks or sensibly prioritizing; no tool-loop stall, no error bubble.

**S7. Latency.** Time-to-first-streamed-token and total reply time across ~5 scenarios. Nano should be ≤ mini (mini: ~2–3s headers, full replies ~8–20s). Slower-than-mini is a regression; faster is a selling point — quantify it.

---

## Cost & telemetry capture (after all scenarios)

On the provider admin page, record the installs table "Usage Today" for the chatbot install: messages, tokens, **cache-hit %** (should remain ~80%+; prompt_cache_key behavior shouldn't change with model, but verify). Note any 429s you triggered. Tail both debug logs for new PHP errors:
- `/Users/obedmarquez/Local Sites/wp-ai-chatbot/app/public/wp-content/debug.log`
- `/Users/obedmarquez/Local Sites/wp-ai-chatbot-provider/app/public/wp-content/debug.log`

## Verdict format

1. Table: every G/S check — pass/fail/regressed, with verbatim evidence and screenshot paths.
2. Hard-gate score (must be 10/10) and soft score (≥5/7 with ≤2 minor regressions).
3. Latency + token/cost comparison notes.
4. One of three recommendations, with reasoning:
   - **SHIP NANO AS DEFAULT** — all hard gates pass, soft within tolerance.
   - **NANO FOR FREE TIER, MINI FOR PAID** — hard gates pass but soft regressions are user-noticeable (voice quality, curation).
   - **STAY ON MINI** — any hard gate fails twice. Name the failing gate(s) precisely; that becomes the prompt-tuning target list.
5. Leave the provider model set to nano regardless of verdict; the human decides.
