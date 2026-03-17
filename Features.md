# WP AI Chatbot — Feature Ideas

Features organized by impact. `Done: false` means unimplemented.

**Architecture note**: All AI traffic goes through our provider plugin. Chatbot installs never have their own API key (Ui and logic for it still exists, don't remove it, we are going to use it later). Features like rate limiting, cost tracking, and model selection are provider-side concerns. The chatbot admin only configures UX, branding, and store-specific behavior.

---

## Tier 1: Transformative (High Impact, High Value)

### 1. Site Content Indexing (Pages, Posts, Policies) — Done: false

The biggest gap today. The bot understands WooCommerce products but knows **nothing** about the rest of the website — pages, blog posts, shipping policies, return policies, about us, contact info, FAQs pages, etc.

- Auto-index all WordPress pages and posts into TNTSearch (extends existing search index infrastructure)
- Hook into `save_post` / `delete_post` to keep index current
- Include custom post types
- WooCommerce policy pages (shipping, returns, refund, privacy) get priority indexing
- New tool: `search_site_content(query)` — bot can look up any page/post content
- Admin toggle: choose which post types to index
- This alone would make the bot 10x more useful — most user questions are about policies, hours, contact info, not products

### 2. Current Page Context — Done: false

The bot has zero awareness of what the user is doing on the site right now.

- Pass current page URL, title, and post type to the chat backend with every message
- If user is on a product page: auto-include that product's details in context
- If user is on cart/checkout: include cart contents in context
- If user is on a category page: know which category they're browsing
- Enables responses like "I see you're looking at the Blue Widget — would you like to know about sizing?"
- Minimal frontend change (read `window.location` + `wpaicConfig.pageContext`), huge UX leap

### 3. Cart Awareness — Done: false

The bot can add to cart but has no idea what's already in it.

- New tool: `get_cart_contents()` — returns items, quantities, totals
- New tool: `update_cart_item(item_key, quantity)` — change quantities or remove items
- Bot can answer "what's in my cart?", "remove the blue shirt", "how much is my total?"
- Enables cross-sell: "You have running shoes in your cart — want to add socks?"
- Read cart via WC session/cookie on the server side

### 4. Quick-Reply Chips / Suggested Actions — Done: false

After every bot response, show 2-3 clickable suggestion buttons.

- AI generates suggestions as structured data (not free text)
- Render as clickable chips below the message
- Examples after product search: "Show me cheaper options", "Add to cart", "Compare these"
- Examples after order lookup: "Track my shipment", "Request return", "Talk to human"
- Dramatically reduces typing friction, especially on mobile
- Could also be admin-configurable for common flows

### 5. Conversation Starters — Done: false

Empty chat state shows predefined prompt buttons instead of just a greeting.

- Admin configures 3-5 starter prompts in settings
- Display as cards/buttons in the empty chat: "Find a product", "Track my order", "Shipping info", "Talk to support"
- Auto-generate starters based on enabled features (WooCommerce tools, handoff, etc.)
- Reduces the "blank page" problem — users don't know what to ask
- Mobile-friendly tap targets

### 6. Coupon & Promotion Support — Done: false

Direct revenue impact. Users constantly ask about discounts.

- New tool: `check_coupon(code)` — validate coupon, return discount details
- New tool: `apply_coupon(code)` — apply to current cart
- New tool: `get_active_promotions()` — list current sales/coupons (admin-controlled visibility)
- Bot can answer "do you have any discounts?", "does code SAVE10 work?"
- Admin toggle: whether bot can proactively mention promotions
- Respects WooCommerce coupon rules (min spend, product restrictions, expiry)

### 7. Message Feedback (Thumbs Up/Down) — Done: false

Essential for measuring and improving bot quality.

- Small thumbs up/down icons on every bot message
- Store feedback in DB linked to conversation + message
- Admin dashboard: see % positive, filter negative-feedback conversations
- Negative feedback messages flagged for review — shows what the bot gets wrong
- Optional: on thumbs-down, show text input "what went wrong?"
- Data goldmine for FAQ training and system prompt tuning

---

## Tier 2: High Value (Strong ROI)

### 8. Token Usage & Cost Tracking (Provider-side) — Done: false

All AI traffic goes through the provider — cost tracking lives there, not on the chatbot.

- Provider tracks tokens per request (prompt + completion from OpenAI response headers)
- Provider admin dashboard: daily/weekly/monthly token usage and estimated cost per connected site
- Per-site usage breakdown (provider serves multiple chatbot installs)
- Cost alerts: notify provider admin when approaching budget threshold
- Per-site rate/cost limits configurable from provider admin
- Average cost per conversation metric
- Chatbot admin sees nothing about tokens/cost — that's our business concern, not theirs

### 9. Rate Limiting (Provider-side) — Done: false

Provider must protect against abuse. One bad chatbot install shouldn't drain the budget.

- Per-site rate limit (X requests per Y minutes, keyed by site_key)
- Per-IP rate limit forwarded from chatbot (chatbot sends client IP in request headers)
- Global rate limit (max concurrent streams across all sites)
- Provider returns 429 with friendly message, chatbot displays it to user
- Provider admin configures thresholds per site or globally
- Tier system: free sites get lower limits, paid sites get higher
- Chatbot admin has no rate limit settings — all enforced at provider

### 10. Logged-In User Context — Done: false

Huge personalization unlock for sites with user accounts.

- Detect logged-in WordPress user, pass name + email to context
- WooCommerce: include recent order history (last 5 orders)
- "Hi Sarah, welcome back! Your last order #1234 shipped yesterday"
- Skip name/email collection for handoff (already known)
- User's WooCommerce address for shipping estimates
- Purchase history enables smarter recommendations

### 11. Product Reviews Tool — Done: false

Reviews drive purchase decisions. Bot should surface them.

- New tool: `get_product_reviews(product_id)` — returns ratings + review text
- Bot can summarize: "This product has 4.5 stars from 23 reviews. Customers love the quality but some mention sizing runs small"
- Show star rating in product cards
- Answer "what do people think about X?"
- Filter by rating: "show me only 5-star reviews"

### 12. Shipping Calculator — Done: false

One of the most common pre-purchase questions.

- New tool: `estimate_shipping(product_ids, destination_country/zip)`
- Leverages WooCommerce shipping zones and methods
- "How much is shipping to Germany?" → real calculated answer
- Show shipping options with prices
- Include estimated delivery times if available

### 14. GDPR Compliance Kit — Done: false

Legal requirement for EU users — many WooCommerce stores sell to EU.

- Data retention policy: auto-delete conversations older than X days
- "Delete my data" tool — user can request deletion from chat
- Admin data export (per user/email)
- Cookie consent integration (respect existing consent plugins)
- Privacy policy link in chat footer
- IP anonymization option
- Provider-side: data retention policies for request logs, no long-term storage of conversation content

---

## Tier 3: Strong Differentiators

### 15. Conversation Analytics Dashboard — Done: false

Turn chat data into business intelligence.

- Total conversations, messages, avg duration
- Peak hours heatmap
- Most common questions/topics (keyword extraction)
- Unanswered/failed questions log (where bot fell short)
- Conversion funnel: chat started → product viewed → added to cart → purchased
- Handoff rate and response time
- All from existing Chat Logs data — just needs aggregation + UI

### 16. Live Chat Takeover — Done: false

Admin jumps into a live conversation, replacing the bot temporarily.

- Admin sees active conversations in real-time
- "Take over" button sends subsequent messages as human, not AI
- Visual indicator to user: "You're now chatting with [Admin Name]"
- "Return to bot" hands back to AI with full context
- Desktop notifications for new conversations
- This is the bridge between AI chatbot and traditional live chat

### 17. Related / "You Might Also Like" Recommendations — Done: false

Proactive selling without being pushy.

- After showing a product, suggest related/upsell products
- Use WooCommerce's built-in related products, upsells, cross-sells
- "Customers who bought this also bought..."
- Context-aware: don't recommend what's already in cart
- New tool: `get_related_products(product_id)` — uses WC relationships

### 18. Fallback & Offline Mode — Done: false

Graceful degradation when OpenAI API is down or slow.

- Detect API failures, switch to FAQ-only mode automatically
- Serve answers from FAQ database without AI
- TNTSearch for content matching as fallback
- "Our AI assistant is temporarily unavailable. Here are some resources that might help:"
- Show relevant FAQ answers + links to pages
- Admin notification when fallback activates
- Huge reliability improvement — AI APIs do go down

### 19. Smart Context Window Management — Done: false

Long conversations blow through token limits and cost.

- Track token count per conversation
- When approaching limit: summarize older messages, keep recent ones verbatim
- Sliding window: always keep last N messages full, compress earlier ones
- Tool call results (large product data) summarized after use
- Reduces cost per long conversation significantly
- Invisible to user — conversation quality maintained

### 20. Webhook / Automation Triggers — Done: false

Connect chat events to external tools.

- Fire webhooks on: new conversation, handoff request, product added to cart via chat, negative feedback
- Zapier/Make compatible webhook format
- Enable flows like: handoff → Slack notification, purchase via chat → CRM update
- Admin configures webhook URLs per event type
- Simple but makes the plugin integrate with any stack

---

## Tier 4: Nice to Have (Polish & Delight)

### 21. Bot Persona Templates — Done: false

Most admins don't know how to write good system prompts.

- Pre-built persona templates: "Friendly Sales Assistant", "Technical Support Agent", "Concierge", "Minimalist Helper"
- Each template: optimized system prompt + suggested greeting + suggested starters
- Admin picks template, customizes from there
- Template preview shows example conversation
- Lower barrier to getting a good-sounding bot

### 22. Conversation Stickiness (Cross-Session Memory) — Done: false

Remember returning visitors beyond the current browser session.

- For logged-in users: load last conversation on return
- For anonymous: optional localStorage persistence (with consent)
- "Welcome back! Last time we talked about running shoes — still interested?"
- Admin controls retention period
- Respect clear-chat as permanent delete

### 23. Multi-Language Auto-Detection Enhancement — Done: false

Current implementation tells OpenAI which language to use. Could be smarter.

- Detect user's language from first message (not just browser locale)
- Switch greeting and UI labels to match (not just bot responses)
- Translate quick-reply chips and conversation starters
- RTL support for Arabic/Hebrew
- Translate product card labels ("Add to Cart" → "Agregar al carrito")

### 24. Image Recognition for Support — Done: false

User uploads a photo, bot understands it.

- "I received a damaged product" + photo → bot sees the damage, initiates return
- "Which product is this?" + photo → bot identifies from catalog
- GPT-4o vision capability (already supported by the model)
- File upload UI in chat input
- Image displayed in conversation thread
- Useful for support scenarios where text alone is insufficient

### 25. Google Analytics Integration — Done: false

Track chat as a conversion funnel in existing analytics.

- Fire GA4 events: `chat_opened`, `message_sent`, `product_viewed_in_chat`, `add_to_cart_from_chat`, `handoff_requested`
- Custom dimensions: conversation_id, message_count
- Admin enters GA measurement ID, events auto-fire
- Zero-code analytics setup — just paste the ID
- Works with existing GA plugins (compatible event layer)

### 26. Blocked Topics / Content Guardrails — Done: false

Admin controls what the bot won't discuss.

- Blocklist: keywords or topics the bot must deflect ("competitor names", "legal advice", "medical")
- Inject guardrails into system prompt dynamically
- "I can't help with that topic, but I can connect you with our team"
- Prevents brand risk from AI hallucination in sensitive areas
- Simple admin UI: textarea with one topic per line

### 27. Chat Widget Placement Options — Done: false

Not every site wants a floating bottom-right bubble.

- Inline embed: shortcode `[wpaic_chat]` renders chat inside page content
- Sidebar widget: WordPress widget for theme sidebars
- Full-page chat: dedicated /chat page template
- Embedded in product pages: chat scoped to that product
- Multiple placement modes coexist

### 28. A/B Testing for Greetings & Prompts — Done: false

Optimize chat engagement with data.

- Define 2-3 greeting variants, system randomly assigns per session
- Track: open rate, message count, conversion per variant
- Simple admin UI: variant A/B/C text fields + traffic split
- After enough data, admin picks winner
- Same framework works for system prompt variants

### 29. Scheduled / Delayed Follow-Up Messages — Done: false

Re-engage users who started but didn't convert.

- If user viewed products but didn't buy, show follow-up popup next visit
- "Still thinking about the Blue Widget? It's 20% off this week!"
- Admin configures follow-up rules: trigger (viewed product, abandoned cart), delay, message
- Requires cross-session tracking (logged-in users or localStorage)
- Respects frequency caps — not spammy

### 30. Export & Backup — Done: false

Data portability and safety.

- Export all conversations as CSV/JSON
- Export FAQ and training data
- Export settings as JSON (import on another site)
- Scheduled automatic backups to email or cloud storage
- Migration tool: move chatbot config between sites
- Essential for agencies managing multiple client sites

---

## Priority Recommendation

If building in order of maximum impact per effort:

**Chatbot plugin (user-facing):**
1. **Site Content Indexing** — #1 gap, leverages existing TNTSearch infra
2. **Current Page Context** — tiny frontend change, massive UX improvement
3. **Conversation Starters** — small feature, big engagement lift
4. **Quick-Reply Chips** — transforms mobile UX
5. **Message Feedback** — essential quality signal, simple to build
6. **Cart Awareness** — closes the sales loop
7. **Coupon Support** — direct revenue driver
8. **GDPR Compliance** — legal requirement for EU stores

**Provider plugin (our server):**
1. **Rate Limiting** — production necessity before any real traffic
2. **Token Usage & Cost Tracking** — cost visibility per site, budget control
3. **Per-site tier/plan enforcement** — free vs paid limits
