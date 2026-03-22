# WP AI – Monorepo

Two WordPress plugins sharing a parent directory. Not a monorepo tool — just co-located projects.

## Sub-projects

### wp-ai-chatbot (user-facing plugin)
Installed on customer WordPress/WooCommerce sites. Renders a floating chat widget, handles conversation logic, executes WooCommerce tool calls (product search, cart, etc.), and streams responses to the frontend via SSE. See `wp-ai-chatbot/AGENTS.md` for details.

### wp-ai-provider (middleman server plugin)
Installed on **our** server only. Acts as a transparent OpenAI proxy — receives chat requests from chatbot instances, forwards to OpenAI, streams the raw response back. Holds the OpenAI API key so end users don't need one. See `wp-ai-provider/AGENTS.md` for details (when created).

## Data Flow

```
Frontend widget (browser)
  ↕ SSE stream
Chatbot backend (customer WP server)
  ↕ HTTP + SSE
Provider (our WP server) ← holds OpenAI key
  ↕ HTTP + SSE
OpenAI API
```

Full path: frontend chatbot → chatbot server-side → provider (server only) → OpenAI → provider (server only) → chatbot server-side → frontend chatbot.

The chatbot backend drives all conversation logic: it sends messages + tool definitions to the provider, parses streamed responses, executes tool calls locally against WooCommerce, appends results, and loops back through the provider until OpenAI returns a final text response. The provider is stateless — it never interprets tool calls.

## Dev Setup

Each sub-project has its own dependencies. See their respective AGENTS.md / README for commands.

## Local Dev Sites (Local by Flywheel)

When debugging, always check logs on both sites:

- **Provider**: `/Users/obedmarquez/Local Sites/wp-ai-chatbot-provider/app/public`
  - Debug log: `wp-content/debug.log`
- **Chatbot**: `/Users/obedmarquez/Local Sites/wp-ai-chatbot/app/public`
  - Debug log: `wp-content/debug.log`

## Known Gotchas

- **Empty tool parameter objects through provider**: OpenAI requires object tool schemas to include `properties`, even for no-arg tools. For empty parameter schemas, use `'type' => 'object', 'properties' => new \stdClass()` in the chatbot plugin. PHP `json_decode($json, true)` on the provider turns `{}` into `[]`, so the provider must normalize empty arrays back to empty objects before forwarding to OpenAI. Do not omit `properties` for no-arg object schemas, or OpenAI will reject the tool schema.
