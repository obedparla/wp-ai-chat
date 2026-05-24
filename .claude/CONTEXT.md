# WP AI

An AI chatbot for WordPress/WooCommerce stores. Two co-located plugins: a customer-facing **Chatbot** and a server-side **Provider** that proxies OpenAI traffic and holds the API key.

## Language

### Plugins & deployments

**Chatbot**:
The user-facing plugin (`wp-ai-chatbot`) installed on a customer's WordPress/WooCommerce site. Renders the **Widget**, drives conversation logic, executes **Tools** locally.
_Avoid_: bot plugin, client plugin

**Provider**:
The middleman plugin (`wp-ai-provider`) installed on **our** server only. Holds the OpenAI key, transparently proxies and streams responses. Stateless about conversation content.
_Avoid_: proxy, gateway, backend

**Site**:
A single WordPress installation running the **Chatbot** plugin. Authenticated to the **Provider** by a **Site key**.
_Avoid_: install, tenant, customer

**Site key**:
Per-**Site** shared secret sent as `X-WPAIP-Site-Key` for **Provider** auth, compared with `hash_equals()`.
_Avoid_: api key, token

### Chat domain

**Widget**:
The floating chat UI rendered on the customer's storefront. Includes header, message list, **Conversation starters**, pill input.
_Avoid_: chat box, bubble

**Conversation**:
One end-user's chat session — a sequence of **Messages** between a visitor and the bot, persisted in **Chat logs**.
_Avoid_: thread, session, dialog

**Message**:
A single entry in a **Conversation**: visitor text, bot text, or a **Tool** result. Bot messages may include rendered cards (e.g. product carousel).
_Avoid_: turn, reply

**Greeting**:
The bot's first message shown when the **Widget** opens. Admin-configurable.
_Avoid_: welcome, intro

**Conversation starter**:
A pre-canned first-message pill rendered before the user types. Admin-configurable list with defaults.
_Avoid_: suggestion, prompt

**Proactive popup**:
A timed teaser message shown outside the **Widget** to invite engagement before any visitor input. Configurable delay, message, page targeting.
_Avoid_: nudge, toast

### AI mechanics

**System prompt**:
Admin-configurable instructions prepended to every **Conversation**. Sits under General settings.
_Avoid_: persona, instructions

**Tool**:
An OpenAI-style function the **Chatbot** exposes to the model (e.g. `search_products`, `get_cart`, `get_page_content`). Executed locally by the **Chatbot**; the **Provider** never interprets them.
_Avoid_: function, action, capability

**Tool call**:
One model-issued invocation of a **Tool** during a **Conversation**. Multiple **Tool calls** loop through the **Provider** until the model returns final text.

**Handoff**:
The flow that hands a **Conversation** off to human support. Bot collects name + email, creates a **Support request**, emails the admin.
_Avoid_: escalation, transfer

**Support request**:
A row created by **Handoff** capturing the visitor's contact info, transcript snapshot, and status (`new` / `contacted` / `resolved`).
_Avoid_: ticket, lead

### Training inputs

**Training data**:
Admin-supplied knowledge the bot can answer from. Two kinds today: **CSV sources** and **FAQ**.
_Avoid_: knowledge base, corpus

**CSV source**:
An uploaded CSV with a name/label/description. Exposed to the model as a queryable **Tool**.
_Avoid_: dataset, file

**FAQ**:
Q/A pairs entered in a textarea (`Q: ...` / `A: ...`). Injected into the **System prompt** rather than queried via a **Tool**.
_Avoid_: knowledge, qna

### Licensing

**Freemius**:
Third-party platform that handles trials, licensing, billing, and premium updates for the **Chatbot**.

**License**:
A **Site**'s trial or paid entitlement, managed by **Freemius** and validated through the **Provider**. An inactive **License** hides the **Widget**.
_Avoid_: subscription, plan (those are Freemius-internal terms)

## Relationships

- A **Site** runs one **Chatbot**; All **Sites** share one **Provider**.
- A **Chatbot** authenticates every request to the **Provider** with its **Site key**.
- A **Conversation** belongs to one **Site** and contains many **Messages**.
- A **Message** may trigger zero or more **Tool calls** before the bot's final text.
- A **Handoff** produces exactly one **Support request** from one **Conversation**.
- A **License** belongs to one **Site** and gates whether the **Widget** renders.

## Data flow

```
Widget (browser)
  ↕ SSE
Chatbot (customer WP)        ← runs Tools, owns Conversation state
  ↕ HTTP + SSE  (Site key)
Provider (our WP)            ← holds OpenAI key, stateless
  ↕ HTTP + SSE
OpenAI
```

The **Chatbot** is the conversation driver. It sends **Messages** + **Tool** schemas to the **Provider**, parses streamed deltas, executes **Tool calls** locally, appends results, and loops until OpenAI returns final text. The **Provider** never inspects **Tool calls**.

## Flagged ambiguities

- "Backend" is ambiguous — it can mean the **Chatbot**'s server-side PHP or the **Provider**. Clarify before implementing.
- "Training" overlaps with ML training in general usage — here it means **Training data** ingestion, not model fine-tuning.
