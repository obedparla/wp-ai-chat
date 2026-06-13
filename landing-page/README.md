# CartScout Landing Page (WordPress theme)

Conversion-focused, single-page WordPress theme for the chatbot's marketing site.
Static HTML + Tailwind v4, compiled CSS inlined into `<head>` — no render-blocking
requests, self-hosted variable fonts, whole page ≈ 20KB gzipped.

## Build

```bash
npm install
npm run build    # compiles src/main.css → assets/css/main.css (committed)
npm run watch    # dev mode
```

The compiled CSS is committed, so the theme works without a build step.

## Install (local dev)

Symlinked into the provider site and activated:

```bash
ln -sfn "$(pwd)" "/Users/obedmarquez/Local Sites/wp-ai-chatbot-provider/app/public/wp-content/themes/cartscout-landing"
```

## Pending asset swaps (search `TODO:` in index.php)

- **Feature shots** → each feature visual is a styled mini-mock carrying `data-shot="<key>"`
  (`sell`/`compare`/`proactive`/`insights`/`support`); drop an `<img>` in place of the mock
- **og:image** → 1200×630 social card
- **Checkout links** → every `.js-buy` anchor points at `#`; wire Freemius checkout URLs
- **Privacy / Terms pages** → footer links

## Brand rename

"CartScout" is a placeholder from the name shortlist (`../name_ideas.md`). To rename:
case-sensitive find/replace of `CartScout` and `Scout` (chat header avatar name) in
`index.php`, plus `cartscout` strings in `functions.php`/`style.css`.

## Design system — "Electric"

- Bold, dark-dominant for WooCommerce shop owners: deep navy/violet ink (`#120B2E` / `#1B1142`),
  electric-lime accent (`#D9FF3D`, solid `.hl` highlighter tilted -1°), violet secondary (`#8B5CF6`),
  light "paper" sections (`#F7F6FB`), green for cart/sale cues. Dark sticky nav, dark hero.
- Space Grotesk (display, variable) + Archivo (body, variable), self-hosted in `assets/fonts/`.
- Pricing: 3 plans — Starter $99/yr · Forever $299 once (featured) · Unlimited $499/yr. No free trial;
  30-day money-back guarantee.
- Animations: scroll reveals (IntersectionObserver; `data-reveal-group` staggers children),
  looping hero chat demo (`#hero-chat`, built by `main.js`), tilted lime marquee — all disabled
  under `prefers-reduced-motion`.
- SEO/AEO: SoftwareApplication + FAQPage JSON-LD, semantic headings, FAQ mirrors schema text.

Whole page ≈ 20KB gzipped; compiled CSS inlined into `<head>`, fonts preloaded, no
render-blocking requests. Re-run Lighthouse after copy/asset swaps.
