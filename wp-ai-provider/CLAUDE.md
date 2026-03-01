# WP AI Provider

## Key Patterns

- Prefix: `wpaip_`
- Single option key: `wpaip_settings` (array with `openai_api_key`, `model`, `site_key`)
- REST namespace: `wpaip/v1`
- Auth: `X-WPAIP-Site-Key` header, compared with `hash_equals()`