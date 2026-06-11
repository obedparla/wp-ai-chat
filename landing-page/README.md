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

- **Showcase video** → `section#showcase` placeholder frame
- **og:image** → 1200×630 social card
- **Checkout links** → every `.js-buy` anchor points at `#pricing`/`#`; wire Freemius checkout URLs
- **Privacy / Terms pages** → footer links
- Optionally swap the animated hero chat demo for a real widget screenshot

## Brand rename

"CartScout" is a placeholder from the name shortlist (`../name_ideas.md`). To rename:
case-sensitive find/replace of `CartScout`, `Cart<span` wordmarks, and `Scout` (chat
header avatar name) in `index.php`, plus `cartscout` strings in `functions.php`/`style.css`.

## Design system

- Warm editorial "modern general store": cream paper `--color-paper`, espresso ink,
  persimmon accent (deep variant for small text/buttons — AA contrast), pine green, butter.
- Fraunces (display serif, variable) + Hanken Grotesk (body, variable), self-hosted in `assets/fonts/`.
- Animations: scroll reveals (IntersectionObserver), self-playing hero chat demo,
  marquee, all disabled under `prefers-reduced-motion`.
- SEO/AEO: SoftwareApplication + FAQPage JSON-LD, semantic headings, FAQ mirrors schema text.

Lighthouse (local): SEO 100 · Accessibility 96 · Best Practices 78 (remaining failures
are HTTP-on-local only).
