# WP AI Provider

Transparent OpenAI proxy for WP AI Chatbot instances. Installed on **our** server only.

## Structure

```
wp-ai-provider.php          – bootstrap, activation/deactivation hooks
includes/
  class-wpaip-loader.php    – initializes API + Admin
  class-wpaip-admin.php     – settings page (site key, API key, model)
  class-wpaip-api.php       – REST endpoint POST /wpaip/v1/chat
  class-wpaip-streamer.php  – OpenAI client wrapper, SSE output
tests/
  WPAIP_PluginTest.php
  WPAIP_AdminTest.php
  WPAIP_APITest.php
  WPAIP_StreamerTest.php
  stubs/wp-stubs.php
```

## Key Patterns

- Prefix: `wpaip_`
- Single option key: `wpaip_settings` (array with `openai_api_key`, `model`, `site_key`)
- REST namespace: `wpaip/v1`
- Auth: `X-WPAIP-Site-Key` header, compared with `hash_equals()`

## Dev Commands

```bash
composer install
composer test        # phpunit
```

## Stack

- PHP 8.2+, WordPress 6.0+
- openai-php/client, guzzlehttp/guzzle
- PHPUnit 11 (dev)
