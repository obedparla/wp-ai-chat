# Plan: Create root-level prd.json

## Context
The PROMPT_prd.md describes features for the wp-ai project that span both sub-projects (chatbot + provider). A new `prd.json` at `wp-ai/` root will track these cross-project PRD items separately from the chatbot-specific `wp-ai-chatbot/prd.json`.

## Action
Create `/Users/obedmarquez/Documents/projects/wp-ai/prd.json` with PRD items covering:

1. **Root CLAUDE.md** (Documentation) — describes two-project structure and data flow
2. **Provider WordPress plugin scaffold** (Infrastructure) — base plugin file, composer.json, activation hooks
3. **Provider REST endpoint** (Functional) — accepts chat messages from chatbot clients
4. **Provider OpenAI streaming** (Functional) — forwards to OpenAI, streams SSE back to caller
5. **Chatbot refactored to use provider** (Functional) — routes requests through provider instead of OpenAI directly
6. **End-to-end streaming** (Functional) — full chain works without buffering
7. **Provider/direct mode toggle** (Functional) — backwards-compatible admin setting to switch modes

Each item: category, description, steps to verify, passes: false.

## Verification
- File exists at `wp-ai/prd.json`
- Valid JSON, parseable
- All items have required fields
- Descriptions clear enough for another AI to implement
