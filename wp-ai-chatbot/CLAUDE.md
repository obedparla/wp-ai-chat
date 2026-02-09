# WP AI Chatbot

WordPress plugin - AI chatbot widget with WooCommerce product knowledge and tool calling.

## Project Goal

Create an AI chatbot for WordPress/WooCommerce sites that:
- Answers customer questions about products (search, filter, details)
- Uses OpenAI API with tool calling for structured product queries
- Streams responses in real-time (SSE)
- Provides admin configuration (API key, model, greeting, system prompt)
- Works as floating widget on frontend

## Core Components

1. **Admin Page** - Settings, API key, bot config, chat logs
2. **Frontend Widget** - React chat UI customers interact with
3. **REST API** - Connects frontend to backend, handles SSE streaming
4. **OpenAI Integration** - Chat completions with tool calling
5. **WooCommerce Tools** - Product search, details, categories

## Stack

- **Backend**: PHP 8.2+, WordPress 6.0+, openai-php/client
- **Frontend**: React 18 + Vite + TypeScript + Tailwind CSS v4
- **UI Components**: shadcn/ui (carousel via embla-carousel-react)
- **AI**: OpenAI API (gpt-4o-mini default) with SSE streaming
- **Products**: WooCommerce integration

## Structure

```
wp-ai-chatbot/
├── wp-ai-chatbot.php          # Main plugin file
├── composer.json              # Dependencies (openai-php/client)
├── includes/
│   ├── class-wpaic-loader.php # Bootstraps plugin
│   ├── class-wpaic-admin.php  # Admin settings page
│   ├── class-wpaic-frontend.php # Enqueues React app
│   ├── class-wpaic-api.php    # REST API endpoints
│   ├── class-wpaic-chat.php   # OpenAI integration + tool handling
│   └── class-wpaic-tools.php  # Product search tools
└── frontend/                  # React app (Vite)
    ├── src/
    │   ├── App.tsx
    │   ├── components/
    │   └── hooks/
    └── dist/                  # Built assets (loaded by WP)
```

## Key Patterns

- **Prefix**: `wpaic_` for functions, `WPAIC_` for classes
- **Options**: `wpaic_settings` stores all config
- **REST**: `/wp-json/wpaic/v1/chat/stream` for SSE chat, `/wpaic/v1/products` for products
- **Nonce**: `wp_rest` nonce passed to frontend via `wpaicConfig`

## Tool Calling

Bot has 3 tools:
1. `search_products` - keyword/category/price search
2. `get_product_details` - full product by ID
3. `get_categories` - list all categories

Tools execute server-side, results fed back to OpenAI for final response.

## Dev Commands

```bash
composer install              # Install PHP dependencies
cd frontend && npm install    # Install frontend deps
cd frontend && npm run dev    # Vite dev server (HMR)
cd frontend && npm run build  # Build for production (REQUIRED for styles)
```

## Building for Production

**IMPORTANT**: Frontend must be built after any component/style changes:
```bash
cd frontend && npm run build
```

This generates:
- `dist/assets/main-*.js` - React chat widget bundle
- `dist/assets/main-*.css` - Tailwind CSS for frontend widget
- `dist/assets/admin-*.css` - Tailwind CSS for admin pages (scans PHP files)
- `dist/.vite/manifest.json` - Asset map (WordPress reads this to load files)

Both frontend widget and admin page load Tailwind from built files via manifest. If styles are missing, clean rebuild:
```bash
cd frontend && rm -rf dist node_modules/.vite && npm run build
```

## Admin Settings

Settings > AI Chatbot
- OpenAI API Key
- Model selection
- Greeting message
- Enable/disable toggle

## Dev Standards

- All PRs must pass: types, lint, tests
- Add tests for crucial features as implemented
- Update this file as stack evolves
