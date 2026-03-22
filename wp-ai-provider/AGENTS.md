# WP AI Provider

## Key Patterns

- Prefix: `wpaip_`
- Single option key: `wpaip_settings` 
- REST namespace: `wpaip/v1`
- Auth: `X-WPAIP-Site-Key` header, compared with `hash_equals()`
- Tool schema forwarding: PHP `json_decode(..., true)` turns `{}` into `[]`, so empty object schemas from the chatbot must be normalized back to `new \stdClass()` before sending to OpenAI.
