<?php
/**
 * StoreChat (WpStoreChat) landing page — "White Modern" design.
 *
 * Visible brand is "WpStoreChat" / "StoreChat". The theme slug, text-domain and
 * function prefixes intentionally stay "cartscout-*" — the theme is symlinked and
 * activated on the provider site as `cartscout-landing`; renaming the slug breaks it.
 * TODO: full slug / text-domain / function-prefix rename (cartscout-* → wpstorechat-*).
 *
 * Asset swap points (search for "TODO:"):
 *   - Feature shots  → each .feat-visual carries data-shot="<key>"; drop an <img> in place of the mock
 *   - Demo video     → #demo-video placeholder (90-sec product video)
 *   - Social proof   → #social-proof slot (real testimonials — NEVER fabricate)
 *   - Store shots    → #store-shots slot (real installed-widget screenshots)
 *   - og:image       → 1200x630 social card
 *   - Checkout links → every .js-buy anchor (Freemius)
 *   - Privacy/Terms  → footer
 */

// Single source of truth — drives both the visible accordion and the FAQPage JSON-LD.
// Copy: File B ("white modern") "Fair questions" set + trust-critical items.
$faqs = array(
	array( 'Do I need an OpenAI API key?', 'No. WpStoreChat is fully managed — we host the AI model on our own servers. You install the plugin, activate your license, and go live. There\'s no OpenAI account to create and no per-token bills to track.' ),
	array( 'Is this really a one-time purchase?', 'Yes. Pay $249 once and own WpStoreChat for life on one site — including lifetime updates and the latest AI models. Prefer to spread it out? There\'s a $99/year option too. Either way, we\'ll never lock features behind a surprise subscription.' ),
	array( 'Will it make things up about my products?', 'No. WpStoreChat answers only from your actual catalog, your pages and your policies. It never invents specs, materials or shipping times — and if it doesn\'t know, it says so and offers a human handoff.' ),
	array( 'Will it slow down my store?', 'No. The widget is a few kilobytes and loads after your page does. The AI runs on our servers, not yours — your hosting does no extra work.' ),
	array( 'What happens if WpStoreChat shuts down?', 'Your store keeps working — the plugin degrades gracefully and never breaks your site. Lifetime licenses include a guarantee: if we ever wind down, we ship a final self-hosted release so you keep what you paid for.' ),
	array( 'Is it GDPR-friendly?', 'Yes. Conversations are stored in your own WordPress database, not sold or used to train outside models. There\'s a built-in consent notice, data export, and one-click deletion.' ),
	array( 'Which AI models does it use?', 'WpStoreChat runs on the latest frontier AI models through our managed backend. We upgrade the model centrally, so your store is always on the newest, smartest version automatically — no plugin update and no extra cost.' ),
	array( 'Is there a free trial?', 'No free trial — but every purchase comes with a 30-day money-back guarantee. Install WpStoreChat on your real store, and if it\'s not right for you, email us within 30 days for a full refund — no forms, no hassle.' ),
	array( 'Is it hard to set up or customize?', 'No code needed. Install, activate, and WpStoreChat reads your store on its own. From the admin you can set the name, logo, role, theme color, tone of voice, conversation starters, languages, and even a custom system prompt — and preview it all live.' ),
);
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-hero="light">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WpStoreChat — Your store just learned how to sell.</title>
<meta name="description" content="WpStoreChat is the AI sales assistant for WooCommerce. It knows everything about your store — from products to shipping and coupons — answers shopper questions, recommends products and closes the sale right inside the chat — 24/7, in 12 languages. No OpenAI key, no monthly fees. One-time payment from $249.">
<link rel="canonical" href="<?php echo esc_url( home_url( '/' ) ); ?>">
<meta name="theme-color" content="#FBF8F1">

<meta property="og:type" content="website">
<meta property="og:site_name" content="WpStoreChat">
<meta property="og:title" content="WpStoreChat — Your store just learned how to sell.">
<meta property="og:description" content="The AI sales assistant for your WooCommerce store. Knows your whole store — products, shipping and coupons — answers every shopper, and turns browsers into buyers — no OpenAI key, no monthly fees.">
<meta property="og:url" content="<?php echo esc_url( home_url( '/' ) ); ?>">
<?php /* TODO: add og:image — 1200x630 → <meta property="og:image" content="...assets/img/og.png"> */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="WpStoreChat — AI Sales Assistant for WooCommerce">
<meta name="twitter:description" content="Your store just learned how to sell. One-time payment, no OpenAI key, no monthly fees.">

<script type="application/ld+json">
{
	"@context": "https://schema.org",
	"@type": "SoftwareApplication",
	"name": "WpStoreChat",
	"alternateName": "WpStoreChat — AI Sales Assistant for WooCommerce",
	"applicationCategory": "BusinessApplication",
	"applicationSubCategory": "AI Shopping Assistant for WooCommerce",
	"operatingSystem": "WordPress with WooCommerce",
	"url": "<?php echo esc_url( home_url( '/' ) ); ?>",
	"description": "WpStoreChat is an AI sales assistant for WooCommerce that finds products, answers shopper questions from real store data, adds items to the cart, tracks orders, and hands off to humans — with fully managed AI and no API key required.",
	"offers": [
		{ "@type": "Offer", "name": "Annual — one site, yearly", "price": "99", "priceCurrency": "USD" },
		{ "@type": "Offer", "name": "Lifetime — one site, pay once", "price": "249", "priceCurrency": "USD" },
		{ "@type": "Offer", "name": "Unlimited — unlimited stores, yearly", "price": "499", "priceCurrency": "USD" }
	],
	"featureList": "Conversational product discovery, in-chat add to cart and checkout, smart recommendations, grounded answers from real store data, order tracking, FAQ/CSV/site-content training, human handoff, conversation insights, 12 languages, proactive engagement, full branding controls",
	"softwareHelp": { "@type": "CreativeWork", "url": "<?php echo esc_url( home_url( '/#faq' ) ); ?>" }
}
</script>
<script type="application/ld+json">
<?php
$faq_entities = array();
foreach ( $faqs as $faq ) {
	$faq_entities[] = array(
		'@type'          => 'Question',
		'name'           => $faq[0],
		'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $faq[1] ),
	);
}
echo wp_json_encode(
	array(
		'@context'   => 'https://schema.org',
		'@type'      => 'FAQPage',
		'mainEntity' => $faq_entities,
	),
	JSON_UNESCAPED_UNICODE
);
?>
</script>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-paper font-body text-tx-dark antialiased' ); ?>>
<?php wp_body_open(); ?>

<a class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-[100] focus:rounded-lg focus:bg-acc focus:px-4 focus:py-2 focus:font-bold focus:text-white" href="#main">Skip to content</a>

<!-- ════════════════════════════════ NAV ════════════════════════════════ -->
<header class="site-nav sticky top-0 z-50 border-b border-line bg-paper/90 backdrop-blur-md">
	<nav class="mx-auto flex h-[72px] max-w-[1180px] items-center justify-between px-6 sm:px-8" aria-label="Main">
		<a href="#" class="flex items-center gap-2.5 font-display text-[21px] font-bold text-tx-dark">
			<span class="grid h-[34px] w-[34px] place-items-center rounded-[9px] bg-acc2">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4V5a1 1 0 0 1 1-1Z" fill="#fff"/><circle cx="9.5" cy="10" r="1.1" fill="#0E5A40"/><circle cx="13" cy="10" r="1.1" fill="#0E5A40"/><circle cx="16.5" cy="10" r="1.1" fill="#0E5A40"/></svg>
			</span>
			WpStoreChat
			<span class="hidden rounded bg-acc2-tint px-1.5 py-[3px] text-[10px] font-bold uppercase tracking-wider text-acc2 sm:inline-block">for WooCommerce</span>
		</a>
		<div class="hidden items-center gap-[30px] md:flex">
			<a class="text-[15px] font-medium text-tx-mid transition-colors hover:text-tx-dark" href="#features">Features</a>
			<a class="text-[15px] font-medium text-tx-mid transition-colors hover:text-tx-dark" href="#how">How it works</a>
			<a class="text-[15px] font-medium text-tx-mid transition-colors hover:text-tx-dark" href="#compare">Compare</a>
			<a class="text-[15px] font-medium text-tx-mid transition-colors hover:text-tx-dark" href="#pricing">Pricing</a>
			<a class="text-[15px] font-medium text-tx-mid transition-colors hover:text-tx-dark" href="#faq">FAQ</a>
			<a class="btn-press rounded-full bg-acc px-5 py-2.5 font-display text-sm font-bold text-white shadow-acc" href="#pricing">See pricing</a>
		</div>
		<button data-menu-button aria-expanded="false" aria-controls="mobile-menu" aria-label="Menu" class="flex h-10 w-10 items-center justify-center rounded-full border border-tx-dark/20 text-tx-dark md:hidden">
			<svg width="18" height="12" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path d="M1 1h16M1 6h16M1 11h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
		</button>
	</nav>
	<div data-menu-panel id="mobile-menu" class="hidden border-t border-line bg-paper px-6 py-4 md:hidden">
		<div class="flex flex-col gap-4 text-base font-semibold text-tx-dark">
			<a href="#features">Features</a>
			<a href="#how">How it works</a>
			<a href="#compare">Compare</a>
			<a href="#pricing">Pricing</a>
			<a href="#faq">FAQ</a>
			<a class="js-buy rounded-full bg-acc px-5 py-3 text-center text-white" href="#pricing"><?php /* TODO: Freemius checkout link */ ?>Get WpStoreChat — $249</a>
		</div>
	</div>
</header>

<main id="main">

<!-- ════════════════════════════════ HERO ════════════════════════════════ -->
<header class="relative overflow-hidden bg-paper pb-24 pt-[72px]">
	<div aria-hidden="true" class="pointer-events-none absolute -right-40 -top-40 h-[480px] w-[480px] rounded-full" style="background:radial-gradient(circle, rgba(239,89,39,0.1), transparent 70%)"></div>
	<div class="relative z-10 mx-auto grid max-w-[1180px] items-center gap-16 px-6 sm:px-8 lg:grid-cols-[1.05fr_0.95fr]">
		<div>
			<span class="kicker mb-6">AI Sales Assistant for WooCommerce</span>
			<h1 class="text-[clamp(42px,5.2vw,72px)] font-bold leading-[1.04]">
				Your store just<br>learned how to <span class="hl">sell.</span>
			</h1>
			<p class="mb-9 mt-7 max-w-[490px] text-[19px] leading-relaxed text-tx-mid">
				WpStoreChat is a chat assistant that knows everything about your WooCommerce store, from products to shipping and coupons, and turns browsers into buyers — answering questions, recommending products, and closing the sale right inside the chat.
			</p>
			<div class="mb-[22px] flex flex-wrap gap-3.5">
				<a class="btn-press js-buy inline-flex items-center gap-2.5 rounded-full bg-acc px-[30px] py-4 font-display text-[17px] font-bold text-white shadow-acc" href="#pricing"><?php /* TODO: Freemius checkout link */ ?>Get WpStoreChat — one-time payment</a>
				<a class="btn-press inline-flex items-center gap-2.5 rounded-full border-[1.5px] border-tx-dark/25 px-[30px] py-4 font-display text-[17px] font-bold text-tx-dark" href="#demo">▶ Watch 90-sec demo</a>
			</div>
			<p class="flex flex-wrap gap-x-[18px] gap-y-2 text-[13.5px] text-tx-mid">
				<span class="inline-flex items-center gap-1.5"><b class="text-acc2">✓</b> 30-day money-back guarantee</span>
				<span class="inline-flex items-center gap-1.5"><b class="text-acc2">✓</b> One-time payment, no subscription</span>
				<span class="inline-flex items-center gap-1.5"><b class="text-acc2">✓</b> No OpenAI key needed</span>
			</p>
		</div>

		<!-- Live animated chat demo (built by main.js initChatDemo) + floating chips. -->
		<div class="relative w-full max-w-[420px] justify-self-center lg:justify-self-end" aria-hidden="true">
			<!-- Chip 1 — top-left -->
			<div class="hero-chip top-6 -left-3 hidden max-w-[200px] lg:flex">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#EE5A2A" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M18.4 5.6l-2.8 2.8M8.4 15.6l-2.8 2.8"/></svg>
				<div class="leading-tight">
					<div class="text-[13px] font-bold text-tx-dark">Latest AI models</div>
					<div class="text-[11.5px] text-tx-mid">upgraded automatically</div>
				</div>
			</div>
			<!-- Chip 2 — bottom-right -->
			<div class="hero-chip bottom-20 -right-3 hidden max-w-[210px] lg:flex">
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#0E5A40" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" class="shrink-0"><path d="M12 3 3 8l9 5 9-5-9-5Z"/><path d="M3 13l9 5 9-5M3 18l9 5 9-5"/></svg>
				<div class="leading-tight">
					<div class="text-[13px] font-bold text-tx-dark">Knows your whole store</div>
					<div class="text-[11.5px] text-tx-mid">products · shipping · coupons</div>
				</div>
			</div>

			<div class="overflow-hidden rounded-[22px] bg-card shadow-chat">
			<div class="flex items-center gap-3 bg-acc2 px-5 py-4 text-white">
				<span class="grid h-[38px] w-[38px] shrink-0 place-items-center rounded-full bg-acc font-display text-base font-extrabold text-white">S</span>
				<div>
					<div class="font-display text-base font-bold">WpStoreChat</div>
					<div class="flex items-center gap-1.5 text-xs text-white/80"><span class="h-[7px] w-[7px] animate-pulse-dot rounded-full bg-online"></span> Shopping assistant — online</div>
				</div>
			</div>
			<div id="hero-chat" class="cd-body flex h-[380px] flex-col gap-2.5 overflow-hidden bg-[#FCFBF6] p-[18px]"></div>
			<div class="flex items-center gap-2.5 border-t border-line bg-white px-[18px] py-[13px] text-sm text-tx-dark/40">
				Ask about anything in the store… <span class="ml-auto grid h-8 w-8 place-items-center rounded-full bg-acc2 text-sm text-white">↑</span>
			</div>
			</div>
		</div>
	</div>
</header>

<!-- ════════════════════════════════ STATS BAR ════════════════════════════════ -->
<!-- Bold 4-stat strip (File B copy), replacing the thin trust line. -->
<section class="bg-ink text-tx-light">
	<div class="mx-auto grid max-w-[1180px] grid-cols-2 px-6 sm:px-8 lg:grid-cols-4" data-reveal-group>
		<div class="px-7 py-11">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">70%</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/70">of carts are abandoned. WpStoreChat answers before shoppers leave.</div>
		</div>
		<div class="border-l border-white/10 px-7 py-11">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">24/7</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/70">It never misses a shopper, even when you're asleep or on holiday.</div>
		</div>
		<div class="border-t border-white/10 px-7 py-11 lg:border-l lg:border-t-0">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">12</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/70">languages, auto-detected. Sell to shoppers in their own words.</div>
		</div>
		<div class="border-l border-t border-white/10 px-7 py-11 lg:border-t-0">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">$0</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/70">monthly fee. One flat price — the AI is included.</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ PROBLEM ════════════════════════════════ -->
<!-- Copy: File A "sale walking out the door". -->
<section class="bg-paper py-24">
	<div class="reveal mx-auto max-w-[860px] px-6 text-center sm:px-8">
		<span class="kicker mb-5 justify-center">The silent storefront problem</span>
		<h2 class="text-[clamp(30px,3.6vw,48px)] font-bold leading-[1.1]">
			Every unanswered question is a <span class="hl">sale walking out the door.</span>
		</h2>
		<p class="mx-auto mt-6 max-w-[640px] text-lg leading-relaxed text-tx-mid">
			Shoppers have questions — about sizing, materials, shipping, which product is right for them. When no one answers in the moment, they leave. Your store is open 24/7, but until now, nobody was there to sell.
		</p>
	</div>
</section>

<!-- ════════════════════════════════ FEATURES ════════════════════════════════ -->
<!-- Ported from electric: 5 numbered mock-chat story blocks (File B copy). -->
<section id="features" class="bg-paper2 py-24">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center">Everything it does</span>
			<h2 class="my-[18px] section-title">Watch it <span class="hl">sell.</span></h2>
			<p class="text-lg leading-relaxed text-tx-mid">A full sales team, in one chat widget — grounded in your real store data.</p>
		</div>

		<!-- 01 ─ discovery → cart → checkout -->
		<article class="reveal grid items-center gap-10 py-12 lg:grid-cols-2 lg:gap-[72px]">
			<div>
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">01</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(26px,2.8vw,38px)] font-bold leading-[1.12]">From "just browsing" to checkout. One chat.</h3>
				<p class="mb-[22px] max-w-[460px] text-[16.5px] leading-relaxed text-tx-mid">Shoppers describe what they want in plain language. WpStoreChat searches your real catalog, adds the right product to the cart — size, color and all — and drops a one-tap Checkout button the moment they're ready.</p>
				<div class="flex flex-wrap gap-2">
					<span class="feat-chip">Conversational discovery</span>
					<span class="feat-chip">Stock &amp; variations</span>
					<span class="feat-chip">One-tap checkout</span>
				</div>
			</div>
			<div class="feat-visual" data-shot="sell">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="feat-numeral" aria-hidden="true">01</span>
				<div class="feat-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-user">Waterproof jacket, women's M, under $150</div>
						<div class="mini-msg mini-bot">Two in stock that fit the bill:</div>
						<div class="flex gap-2">
							<div class="mini-card"><div class="mini-thumb" style="background:linear-gradient(135deg,#0B593F,#09422F)"></div><b>Stormline W</b><div>$129</div></div>
							<div class="mini-card"><div class="mini-thumb" style="background:linear-gradient(135deg,#D98A4B,#C26A2E)"></div><b>Drift Shell</b><div>$144</div></div>
						</div>
						<div class="mini-msg mini-user">First one, in navy</div>
						<div class="mini-msg mini-bot">In your cart — Stormline W, navy, size M. ✓</div>
						<div class="mini-btn">Checkout — $129.00</div>
					</div>
				</div>
			</div>
		</article>

		<!-- 02 ─ comparison table -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-12 lg:grid-cols-2 lg:gap-[72px]">
			<div class="lg:order-2">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">02</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(26px,2.8vw,38px)] font-bold leading-[1.12]">It closes the "which one?" moment.</h3>
				<p class="mb-[22px] max-w-[460px] text-[16.5px] leading-relaxed text-tx-mid">Side-by-side comparison tables with real specs and live prices — and an add-to-cart button right in the table. Shoppers decide and buy in the same breath.</p>
				<div class="flex flex-wrap gap-2">
					<span class="feat-chip">Compare 2–4 products</span>
					<span class="feat-chip">Real-time prices</span>
					<span class="feat-chip">Buy from the table</span>
				</div>
			</div>
			<div class="feat-visual lg:order-1" data-shot="compare">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="feat-numeral" aria-hidden="true">02</span>
				<div class="feat-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-user">Ridgerunner vs Skyline?</div>
						<table class="mini-table w-full border-collapse">
							<tbody>
								<tr><th></th><th>Ridgerunner 2</th><th>Skyline Trail</th></tr>
								<tr><td>Price</td><td><b>$89</b></td><td><b>$112</b></td></tr>
								<tr><td>Weight</td><td>240 g</td><td>198 g</td></tr>
								<tr><td>Waterproof</td><td class="font-bold text-green">Yes</td><td>No</td></tr>
								<tr><td></td><td><div class="mini-atc">Add to cart</div></td><td><div class="mini-atc">Add to cart</div></td></tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</article>

		<!-- 03 ─ proactive + coupons + cross-sell -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-12 lg:grid-cols-2 lg:gap-[72px]">
			<div>
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">03</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(26px,2.8vw,38px)] font-bold leading-[1.12]">It makes the first move.</h3>
				<p class="mb-[22px] max-w-[460px] text-[16.5px] leading-relaxed text-tx-mid">A perfectly timed hello invites shoppers in. Today's deals and coupon codes surface on request, and every add-to-cart gets one smart cross-sell suggestion to lift your average order value.</p>
				<div class="flex flex-wrap gap-2">
					<span class="feat-chip">Page-targeted greeting</span>
					<span class="feat-chip">Coupon surfacing</span>
					<span class="feat-chip">Cross-sells</span>
				</div>
			</div>
			<div class="feat-visual" data-shot="proactive">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="feat-numeral" aria-hidden="true">03</span>
				<div class="feat-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-bot">That jacket pairs well with the Trailhead Beanie — $19, and it ships in the same box. Want it?</div>
						<div class="mini-msg mini-user">Any discounts running?</div>
						<div class="mini-msg mini-bot"><span class="mini-tag">SPRING15</span> &nbsp;15% off orders over $100 — applied at checkout.</div>
					</div>
				</div>
				<div class="absolute bottom-[22px] right-[22px] z-20 max-w-[200px] rounded-xl bg-acc px-3.5 py-3 font-display text-[12.5px] font-bold leading-snug text-white shadow-pop after:absolute after:-bottom-[7px] after:right-6 after:border-[7px] after:border-b-0 after:border-transparent after:border-t-acc after:content-['']">👋 Looking for a gift? I can narrow it down in 20 seconds.</div>
			</div>
		</article>

		<!-- 04 ─ grounded & accurate (spec-sensitive trust block) -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-12 lg:grid-cols-2 lg:gap-[72px]">
			<div class="lg:order-2">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">04</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(26px,2.8vw,38px)] font-bold leading-[1.12]">Grounded &amp; accurate — it never makes things up.</h3>
				<p class="mb-[22px] max-w-[460px] text-[16.5px] leading-relaxed text-tx-mid">WpStoreChat answers only from your real store data — products, pages, shipping and policies. It never invents specs, materials, or shipping times — so it never costs you trust. When it doesn't know, it says so and offers a human.</p>
				<div class="flex flex-wrap gap-2">
					<span class="feat-chip">Never invents specs or materials</span>
					<span class="feat-chip">Real prices &amp; stock only</span>
					<span class="feat-chip">Order tracking by email</span>
				</div>
			</div>
			<div class="feat-visual lg:order-1" data-shot="grounded">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="feat-numeral" aria-hidden="true">04</span>
				<div class="feat-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-user">Is the oak table solid wood or veneer?</div>
						<div class="mini-msg mini-bot">Solid European oak, per the product page — 4cm top, 38&nbsp;kg. I only quote what's in your catalog.</div>
						<div class="mini-msg mini-user">Ships to Spain by Friday?</div>
						<div class="mini-msg mini-bot">I don't have a guaranteed date for Spain — I won't guess. Want me to connect you with the team?</div>
					</div>
				</div>
			</div>
		</article>

		<!-- 05 ─ support autopilot + handoff + insights -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-12 lg:grid-cols-2 lg:gap-[72px]">
			<div>
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">05</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(26px,2.8vw,38px)] font-bold leading-[1.12]">Support answers itself — and tells you what shoppers want.</h3>
				<p class="mb-[22px] max-w-[460px] text-[16.5px] leading-relaxed text-tx-mid">Order status by email lookup, policy answers quoted from your own pages, and a human handoff that lands with the full conversation attached. Full transcripts in wp-admin show exactly what's stopping people from buying.</p>
				<div class="flex flex-wrap gap-2">
					<span class="feat-chip">Human handoff</span>
					<span class="feat-chip">Policy answers</span>
					<span class="feat-chip">Conversation insights</span>
				</div>
			</div>
			<div class="feat-visual" data-shot="support">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="feat-numeral" aria-hidden="true">05</span>
				<div class="feat-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-user">Where's my order? anna@mail.com</div>
						<div class="mini-msg mini-bot">Order <b>#4182</b> shipped Tuesday — arriving <b>tomorrow</b> via DHL. 📦</div>
						<div class="mini-msg mini-user">Can I still change the address?</div>
						<div class="mini-msg mini-bot">That one needs a human — I've sent your chat to the store team. They reply within a few hours.</div>
					</div>
				</div>
			</div>
		</article>

		<?php /* Social proof: implemented as the #testimonials section just below this </section>. NOTE: those quotes are PLACEHOLDER (example) content — replace with REAL ones, or hide the section, before launch. */ ?>
		<!-- <div id="social-proof"></div> -->
		<?php /* TODO: real-store screenshots slot — drop in REAL installed-widget screenshots here once supplied. Do NOT fabricate. */ ?>
		<!-- <div id="store-shots"></div> -->
	</div>
</section>

<!-- ════════════════════════════════ TESTIMONIALS ════════════════════════════════ -->
<?php
/* ⚠ PLACEHOLDER TESTIMONIALS — example content, NOT real customers.
   Per the no-synthetic-data rule, replace these with REAL store-owner quotes
   (or hide this section) before launch. Edit the array; the markup updates itself. */
$testimonials = array(
	array(
		'quote'     => "It paid for itself in the first weekend. Shoppers ask it the questions they'd never email us, and it just",
		'highlight' => 'closes them.',
		'initials'  => 'MR',
		'name'      => 'Maya Renner',
		'role'      => 'Founder, Wildgrove Coffee Co.',
		'avatar'    => 'bg-acc2',
	),
	array(
		'quote'     => 'No OpenAI key, no monthly bill, no headache. I installed it on a Sunday night and it was',
		'highlight' => 'selling by Monday morning.',
		'initials'  => 'PL',
		'name'      => 'Priya Lal',
		'role'      => 'Owner, Saffron & Sage',
		'avatar'    => 'bg-acc',
	),
	array(
		'quote'     => "The product recommendations are scary good. It bundles items I'd never have paired, and our",
		'highlight' => 'average order value is up.',
		'initials'  => 'TV',
		'name'      => 'Tomás Vega',
		'role'      => 'Founder, Atlas Outdoor Supply',
		'avatar'    => 'bg-acc2',
	),
);
?>
<section id="testimonials" class="bg-paper py-24">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center">Don't just take our word for it</span>
			<h2 class="my-[18px] section-title">What store owners are saying.</h2>
			<p class="text-lg leading-relaxed text-tx-mid">WpStoreChat is already answering shoppers and closing sales for WooCommerce stores around the world.</p>
		</div>
		<div class="grid gap-[22px] lg:grid-cols-3" data-reveal-group>
			<?php foreach ( $testimonials as $t ) : ?>
			<figure class="reveal relative flex flex-col rounded-[20px] border border-line bg-card p-8 shadow-card lg:p-9">
				<svg class="pointer-events-none absolute right-7 top-7 h-9 w-9 text-acc2/15" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9 6.5C6.4 7.6 4.6 10 4.6 13v4.5h5.3v-5.3H7.3c0-1.5.9-2.7 2.3-3.3L9 6.5Zm8.5 0C14.9 7.6 13.1 10 13.1 13v4.5h5.3v-5.3h-2.6c0-1.5.9-2.7 2.3-3.3l-.6-2.4Z"/></svg>
				<div class="mb-5 flex gap-1 text-butter" aria-label="Rated 5 out of 5 stars">
					<?php for ( $s = 0; $s < 5; $s++ ) : ?><svg class="h-[18px] w-[18px]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 1.7l2.45 4.96 5.48.8-3.96 3.86.94 5.46L10 14.18l-4.9 2.6.94-5.46L2.07 7.46l5.48-.8L10 1.7Z"/></svg><?php endfor; ?>
				</div>
				<blockquote class="mb-7 text-[19px] font-bold leading-[1.45] text-tx-dark">
					<?php echo esc_html( $t['quote'] ); ?> <span class="text-acc2"><?php echo esc_html( $t['highlight'] ); ?></span>
				</blockquote>
				<figcaption class="mt-auto flex items-center gap-3.5 border-t border-line pt-6">
					<span class="grid h-12 w-12 shrink-0 place-items-center rounded-full <?php echo esc_attr( $t['avatar'] ); ?> font-display text-sm font-bold text-white"><?php echo esc_html( $t['initials'] ); ?></span>
					<span class="leading-tight">
						<span class="block font-bold text-tx-dark"><?php echo esc_html( $t['name'] ); ?></span>
						<span class="block text-sm text-tx-mid"><?php echo esc_html( $t['role'] ); ?></span>
					</span>
				</figcaption>
			</figure>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ DEMO + HOW IT WORKS ════════════════════════════════ -->
<!-- KEEP white: "A real conversation. A real sale." + "Three steps. No API key. No code." (File A) -->
<section id="demo" class="bg-paper py-24">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center">See it work</span>
			<h2 class="my-[18px] section-title">A real conversation. A <span class="hl">real sale.</span></h2>
			<p class="text-lg leading-relaxed text-tx-mid">See WpStoreChat turn a question into a checkout in 90 seconds.</p>
		</div>

		<!-- Video placeholder -->
		<div id="demo-video" class="reveal relative mx-auto mb-20 grid aspect-video max-w-[860px] place-items-center overflow-hidden rounded-[20px] bg-ink2 shadow-visual">
			<?php /* TODO: replace placeholder with the 90-sec product video (poster + <video> or embed). */ ?>
			<span class="grid h-16 w-16 place-items-center rounded-full bg-acc text-2xl text-white shadow-acc" aria-hidden="true">▶</span>
		</div>

		<div class="reveal grid gap-[22px] lg:grid-cols-4" data-reveal-group>
			<div class="rounded-[18px] border border-line bg-card p-7 shadow-card">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">01</div>
				<h3 class="mb-2.5 mt-3 text-[20px] font-bold">Shopper asks</h3>
				<p class="text-[15px] leading-relaxed text-tx-mid">"I need a gift for a coffee lover under $50." WpStoreChat understands intent — no menus, no search box.</p>
			</div>
			<div class="rounded-[18px] border border-line bg-card p-7 shadow-card">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">02</div>
				<h3 class="mb-2.5 mt-3 text-[20px] font-bold">It recommends</h3>
				<p class="text-[15px] leading-relaxed text-tx-mid">It pulls real products from your catalog with images, prices, and sale badges — and explains why each one fits.</p>
			</div>
			<div class="rounded-[18px] border border-line bg-card p-7 shadow-card">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">03</div>
				<h3 class="mb-2.5 mt-3 text-[20px] font-bold">Adds to cart in chat</h3>
				<p class="text-[15px] leading-relaxed text-tx-mid">One tap adds the product. WpStoreChat handles variations, stock, and pricing live.</p>
			</div>
			<div class="rounded-[18px] border border-line bg-card p-7 shadow-card">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc">04</div>
				<h3 class="mb-2.5 mt-3 text-[20px] font-bold">Closes the sale</h3>
				<p class="text-[15px] leading-relaxed text-tx-mid">When the shopper is ready, a Checkout button appears right in the conversation.</p>
			</div>
		</div>

		<!-- Three steps. No API key. No code. (condensed AI-models trust folded in) -->
		<div class="mt-20">
			<div class="reveal mx-auto mb-12 max-w-[700px] text-center">
				<span class="kicker justify-center">Live in minutes</span>
				<h3 class="my-4 text-[clamp(28px,3.2vw,42px)] font-bold leading-[1.1]">Three steps. No API key. No code.</h3>
				<p class="text-lg leading-relaxed text-tx-mid">Most "AI" plugins make you create an OpenAI account, paste keys, and babysit per-token bills. WpStoreChat is fully managed — we run the AI for you, and auto-upgrade to the newest model centrally at no extra cost.</p>
			</div>
			<div class="grid gap-[22px] lg:grid-cols-3" data-reveal-group>
				<div class="rounded-[18px] border border-line bg-card p-[30px] shadow-card lg:p-[34px]">
					<div class="grid h-11 w-11 place-items-center rounded-xl bg-acc2 font-display text-[19px] font-extrabold text-white">1</div>
					<h4 class="mb-2.5 mt-[22px] text-[22px] font-bold">Install the plugin</h4>
					<p class="text-[15px] leading-relaxed text-tx-mid">Add WpStoreChat to WordPress like any plugin and activate your license. The AI comes with it — no developer required, no keys to paste.</p>
					<span class="mt-4 inline-block text-xs font-bold uppercase tracking-wider text-acc">~2 minutes</span>
				</div>
				<div class="rounded-[18px] border border-line bg-card p-[30px] shadow-card lg:p-[34px]">
					<div class="grid h-11 w-11 place-items-center rounded-xl bg-acc2 font-display text-[19px] font-extrabold text-white">2</div>
					<h4 class="mb-2.5 mt-[22px] text-[22px] font-bold">It reads your store</h4>
					<p class="text-[15px] leading-relaxed text-tx-mid">WpStoreChat automatically indexes your WooCommerce products, prices, categories, stock, shipping, coupons, and pages. Nothing to wire up.</p>
					<span class="mt-4 inline-block text-xs font-bold uppercase tracking-wider text-acc">~3 minutes</span>
				</div>
				<div class="rounded-[18px] border border-line bg-card p-[30px] shadow-card lg:p-[34px]">
					<div class="grid h-11 w-11 place-items-center rounded-xl bg-acc2 font-display text-[19px] font-extrabold text-white">3</div>
					<h4 class="mb-2.5 mt-[22px] text-[22px] font-bold">Go live &amp; sell</h4>
					<p class="text-[15px] leading-relaxed text-tx-mid">Brand the widget with your name, logo, and color — then watch it start answering and selling 24/7.</p>
					<span class="mt-4 inline-block text-xs font-bold uppercase tracking-wider text-acc">From day one</span>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ ROI ════════════════════════════════ -->
<!-- KEEP: "The sales you're losing right now" + 4 stat chips (File A). Dark section. -->
<section class="bg-ink py-24 text-tx-light">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center text-butter before:bg-butter">Why it pays for itself</span>
			<h2 class="my-[18px] section-title">The sales you're losing <span class="hl-light">right now.</span></h2>
			<p class="text-lg leading-relaxed text-tx-light/70">WpStoreChat works the exact moments where conversions are won or lost — the unanswered question, the wrong product, the abandoned cart.</p>
		</div>
		<div class="reveal grid gap-px overflow-hidden rounded-[20px] border border-white/10 bg-white/10 sm:grid-cols-2">
			<div class="bg-ink p-9">
				<div class="font-display text-[clamp(36px,4vw,56px)] font-bold leading-none text-acc">24/7</div>
				<p class="mt-3 text-[15px] leading-relaxed text-tx-light/70">Always-on selling — every timezone, every language, no breaks.</p>
			</div>
			<div class="bg-ink p-9">
				<div class="font-display text-[clamp(36px,4vw,56px)] font-bold leading-none text-acc">&lt;1s</div>
				<p class="mt-3 text-[15px] leading-relaxed text-tx-light/70">Instant answers to product questions that would otherwise go unanswered.</p>
			</div>
			<div class="bg-ink p-9">
				<div class="font-display text-[clamp(36px,4vw,56px)] font-bold leading-none text-acc">$0</div>
				<p class="mt-3 text-[15px] leading-relaxed text-tx-light/70">OpenAI keys, per-token bills, or AI infrastructure to manage.</p>
			</div>
			<div class="bg-ink p-9">
				<div class="font-display text-[clamp(36px,4vw,56px)] font-bold leading-none text-acc">1×</div>
				<p class="mt-3 text-[15px] leading-relaxed text-tx-light/70">A single sale a month covers the cost. Everything after is upside.</p>
			</div>
		</div>
		<p class="reveal mx-auto mt-8 max-w-[760px] text-center text-[15px] leading-relaxed text-tx-light/60">WpStoreChat recovers carts, raises average order value with smart recommendations, and deflects repetitive support — the three levers that move ecommerce revenue.</p>
	</div>
</section>

<!-- ════════════════════════════════ NO-SUBSCRIPTION MANIFESTO ════════════════════════════════ -->
<!-- KEEP (strongest differentiator): "We hate subscriptions. So we killed it." $0/mo watermark. File A. -->
<section class="relative overflow-hidden bg-acc py-24 text-white">
	<span aria-hidden="true" class="pointer-events-none absolute -bottom-10 right-4 select-none font-display text-[clamp(120px,22vw,300px)] font-extrabold leading-none text-white/10">$0/mo</span>
	<div class="reveal relative z-10 mx-auto max-w-[820px] px-6 sm:px-8">
		<span class="kicker mb-5 text-white/80 before:bg-white">Our promise</span>
		<h2 class="text-[clamp(34px,4.4vw,60px)] font-bold leading-[1.05]">We hate subscriptions. So we killed it.</h2>
		<p class="mt-6 max-w-[620px] text-lg leading-relaxed text-white/90">
			WpStoreChat is a one-time purchase. Pay once, own it forever. No monthly creep, no surprise renewals, no holding your store hostage. Buy it like you'd buy a tool — because that's what it is.
		</p>
		<div class="mt-9 flex flex-wrap items-center gap-4">
			<a class="btn-press js-buy inline-flex items-center rounded-full bg-white px-7 py-3.5 font-display text-[16px] font-bold text-acc" href="#pricing"><?php /* TODO: Freemius checkout link */ ?>Pay once — $249</a>
			<a class="font-display text-[16px] font-bold text-white underline-offset-4 hover:underline" href="#pricing">Or $99/year →</a>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ COMPARISON ════════════════════════════════ -->
<!-- Ported from electric: NAMED-competitor table with real first-year dollar figures (File B). -->
<section id="compare" class="bg-paper py-24">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center">The math</span>
			<h2 class="my-[18px] section-title">Stop <span class="hl">renting</span> your chatbot.</h2>
			<p class="text-lg leading-relaxed text-tx-mid">Most AI chatbots bill monthly, per seat, or per conversation — and aren't built for WooCommerce. WpStoreChat is one flat price. Everything included.</p>
		</div>
		<div class="reveal overflow-x-auto">
			<table class="w-full min-w-[680px] border-separate border-spacing-0 overflow-hidden rounded-[18px] bg-card text-left text-[15px] shadow-card">
				<thead>
					<tr class="bg-ink font-mono text-xs uppercase tracking-[0.08em] text-tx-light">
						<th class="px-[22px] py-[18px] font-semibold">Chatbot</th>
						<th class="px-[22px] py-[18px] font-semibold">Pricing model</th>
						<th class="px-[22px] py-[18px] font-semibold">Cost, 1st year*</th>
						<th class="px-[22px] py-[18px] font-semibold">Seat fees</th>
						<th class="px-[22px] py-[18px] font-semibold">WooCommerce native</th>
					</tr>
				</thead>
				<tbody>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="bg-acc2-tint px-[22px] py-[18px] font-display font-bold text-acc2">WpStoreChat</td>
						<td class="bg-acc2-tint px-[22px] py-[18px] font-bold">Flat — yearly or once</td>
						<td class="bg-acc2-tint px-[22px] py-[18px] font-display font-bold">$99</td>
						<td class="bg-acc2-tint px-[22px] py-[18px] font-bold">None</td>
						<td class="bg-acc2-tint px-[22px] py-[18px] font-bold text-green">✓ Cart, checkout, orders</td>
					</tr>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="px-[22px] py-[18px] font-semibold">Intercom + Fin AI</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Per seat + per resolution</td>
						<td class="px-[22px] py-[18px] font-semibold">~$1,400+</td>
						<td class="px-[22px] py-[18px] text-tx-mid">$29+/seat/mo</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic support bot</td>
					</tr>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="px-[22px] py-[18px] font-semibold">Tidio + Lyro AI</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Monthly + AI add-on</td>
						<td class="px-[22px] py-[18px] font-semibold">~$960+</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Per-operator tiers</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic chat</td>
					</tr>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="px-[22px] py-[18px] font-semibold">Chatbase</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Monthly + credits</td>
						<td class="px-[22px] py-[18px] font-semibold">~$480+</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Credit overages</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic, no cart</td>
					</tr>
					<tr>
						<td class="px-[22px] py-[18px] font-semibold">Crisp</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Monthly + AI credits</td>
						<td class="px-[22px] py-[18px] font-semibold">~$660+</td>
						<td class="px-[22px] py-[18px] text-tx-mid">Per-workspace</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic chat</td>
					</tr>
				</tbody>
			</table>
			<p class="mt-[18px] text-center text-[13px] text-tx-mid">*Typical published pricing for a small store with AI features enabled, billed for 12 months. Checked June 2026.</p>
		</div>
		<p class="reveal mt-11 text-center font-display text-[clamp(24px,2.6vw,34px)] font-bold">That's <span class="hl">$380 to $1,300+</span> back in your pocket. Every year.</p>
	</div>
</section>

<!-- ════════════════════════════════ PRICING ════════════════════════════════ -->
<!-- 3 tiers — Annual $99/yr · Lifetime $249 one-time (featured) · Unlimited $499/yr (agency / multi-store). -->
<!--
	PRICING DECISION (2026-06-19): owner re-added the 3rd "Unlimited/agency" tier on top of the
	buyer-panel-preferred 2-tier layout. Lifetime kept at $249 one-time (NOT the old $299 "Forever").
	The SoftwareApplication JSON-LD "offers" above include all three tiers.
-->
<section id="pricing" class="bg-paper2 py-24">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center">Simple, honest pricing</span>
			<h2 class="my-[18px] section-title">Buy it once. <span class="hl">Own it for good.</span></h2>
			<p class="text-lg leading-relaxed text-tx-mid">One plugin, one price, every feature. No tiers that hide the good stuff behind a higher bill.</p>
		</div>
		<div class="mx-auto grid max-w-[1120px] items-stretch gap-[22px] lg:grid-cols-3" data-reveal-group>
			<!-- Annual -->
			<div class="flex flex-col rounded-[20px] border border-line bg-card p-9 shadow-card lg:p-[36px]">
				<div class="font-mono text-sm font-bold uppercase tracking-[0.12em]">Annual</div>
				<div class="mb-6 text-[13.5px] text-tx-mid">Lower upfront · 1 site</div>
				<div class="flex items-end gap-1.5">
					<span class="font-display text-[54px] font-bold leading-none">$99</span>
					<span class="mb-1.5 text-sm text-tx-mid">/ year</span>
				</div>
				<div class="mb-[26px] mt-2 text-sm text-tx-mid">Renew yearly for continued updates &amp; support.</div>
				<ul class="mb-8 flex flex-1 flex-col gap-3 text-[14.5px] leading-snug [&>li]:before:text-acc2">
					<li class="plan-tick">Every feature, no limits</li>
					<li class="plan-tick">Fully managed AI — no OpenAI key</li>
					<li class="plan-tick">Always the latest AI models</li>
					<li class="plan-tick">Updates &amp; support for 1 year</li>
				</ul>
				<a class="btn-press js-buy btn-ghost" href="#"><?php /* TODO: Freemius checkout link */ ?>Choose annual</a>
			</div>

			<!-- Lifetime (best value) -->
			<div class="relative flex flex-col rounded-[20px] bg-ink2 p-9 text-tx-light shadow-plan lg:scale-[1.03] lg:p-[36px]">
				<span class="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-acc px-4 py-1.5 font-mono text-[11px] font-bold uppercase tracking-wider text-white">Best value · pay once</span>
				<div class="font-mono text-sm font-bold uppercase tracking-[0.12em]">Lifetime</div>
				<div class="mb-6 text-[13.5px] text-tx-light/70">Own it. No subscription, ever · 1 site</div>
				<div class="flex items-end gap-1.5">
					<span class="font-display text-[54px] font-bold leading-none">$249</span>
					<span class="mb-1.5 text-sm text-tx-light/70">one-time</span>
				</div>
				<div class="mb-[26px] mt-2 text-sm text-tx-light/70">Pay once. Own it forever.</div>
				<ul class="mb-8 flex flex-1 flex-col gap-3 text-[14.5px] leading-snug [&>li]:before:text-acc">
					<li class="plan-tick">Everything in Annual</li>
					<li class="plan-tick">Lifetime updates — never pay again</li>
					<li class="plan-tick">Latest AI models, forever</li>
					<li class="plan-tick">Best price from a 2nd year on</li>
				</ul>
				<a class="btn-press js-buy rounded-full bg-acc px-6 py-3.5 text-center font-display text-[15px] font-bold text-white shadow-acc" href="#"><?php /* TODO: Freemius checkout link */ ?>Get WpStoreChat for life</a>
			</div>

			<!-- Unlimited (agency / multi-store) -->
			<div class="flex flex-col rounded-[20px] border border-line bg-card p-9 shadow-card lg:p-[36px]">
				<div class="font-mono text-sm font-bold uppercase tracking-[0.12em]">Unlimited</div>
				<div class="mb-6 text-[13.5px] text-tx-mid">For agencies &amp; multi-store brands</div>
				<div class="flex items-end gap-1.5">
					<span class="font-display text-[54px] font-bold leading-none">$499</span>
					<span class="mb-1.5 text-sm text-tx-mid">/ year</span>
				</div>
				<div class="mb-[26px] mt-2 text-sm text-tx-mid">Every store you run, on one plan.</div>
				<ul class="mb-8 flex flex-1 flex-col gap-3 text-[14.5px] leading-snug [&>li]:before:text-acc2">
					<li class="plan-tick">Unlimited stores &amp; client sites</li>
					<li class="plan-tick">Every feature, fully managed AI</li>
					<li class="plan-tick">All updates &amp; priority support</li>
					<li class="plan-tick">30-day money-back guarantee</li>
				</ul>
				<a class="btn-press js-buy btn-ghost" href="#"><?php /* TODO: Freemius checkout link */ ?>Get Unlimited</a>
			</div>
		</div>
		<p class="reveal mx-auto mt-9 max-w-[680px] text-center text-sm leading-relaxed text-tx-mid">
			<b class="text-tx-dark">30-day money-back guarantee.</b> Try WpStoreChat on your real store. If it's not lifting your sales, email us within 30 days for a full refund — no forms, no hassle. (No free trial; just a real guarantee.)
		</p>
	</div>
</section>

<!-- ════════════════════════════════ FAQ ════════════════════════════════ -->
<section id="faq" class="bg-paper py-24">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal section-head">
			<span class="kicker justify-center">Questions, answered</span>
			<h2 class="mt-[18px] section-title">Everything you might ask.</h2>
		</div>
		<div class="reveal mx-auto flex max-w-[780px] flex-col gap-3">
			<?php foreach ( $faqs as $i => $faq ) : ?>
			<details class="faq-item overflow-hidden rounded-[14px] border border-line bg-card" <?php echo 0 === $i ? 'open' : ''; ?>>
				<summary class="flex items-center justify-between gap-4 px-6 py-5 font-display text-[16.5px] font-bold">
					<?php echo esc_html( $faq[0] ); ?>
				</summary>
				<p class="px-6 pb-[22px] text-[15px] leading-relaxed text-tx-mid"><?php echo esc_html( $faq[1] ); ?></p>
			</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ FINAL CTA ════════════════════════════════ -->
<!-- KEEP white framing (File A heading) with price + guarantee near the button. -->
<section class="bg-acc2 py-24 text-center text-tx-light">
	<div class="reveal mx-auto max-w-[1180px] px-6 sm:px-8">
		<span class="kicker mb-5 justify-center text-butter before:bg-butter">Stop losing sales to silence</span>
		<h2 class="mb-7 text-[clamp(34px,4.6vw,60px)] font-bold leading-[1.05]">Give your store a salesperson<br>that <span class="hl-light">never sleeps.</span></h2>
		<p class="mx-auto mb-9 max-w-[560px] text-lg leading-relaxed text-tx-light/70">Install WpStoreChat today and let it turn your next browser into a buyer. One-time payment. 30-day money-back guarantee.</p>
		<div class="flex flex-wrap items-center justify-center gap-4">
			<a class="btn-press js-buy inline-flex items-center gap-2.5 rounded-full bg-acc px-[30px] py-4 font-display text-[17px] font-bold text-white shadow-acc" href="#pricing"><?php /* TODO: Freemius checkout link */ ?>Get WpStoreChat — $249 one-time</a>
			<a class="btn-press inline-flex items-center gap-2.5 rounded-full border-[1.5px] border-white/30 px-[30px] py-4 font-display text-[17px] font-bold text-tx-light" href="#pricing">Compare plans</a>
		</div>
		<p class="mt-6 text-sm text-tx-light/60">No subscription · No OpenAI key · Cancel-free forever</p>
	</div>
</section>

</main>

<!-- ════════════════════════════════ FOOTER ════════════════════════════════ -->
<footer class="bg-ink2 py-9 text-tx-light/70">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
			<a href="#" class="flex items-center gap-2.5 font-display text-[17px] font-bold text-tx-light">
				<span class="grid h-[30px] w-[30px] place-items-center rounded-lg bg-acc2">
					<svg width="19" height="19" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H9l-4 4V5a1 1 0 0 1 1-1Z" fill="#fff"/><circle cx="9.5" cy="10" r="1.1" fill="#0E5A40"/><circle cx="13" cy="10" r="1.1" fill="#0E5A40"/><circle cx="16.5" cy="10" r="1.1" fill="#0E5A40"/></svg>
				</span>
				WpStoreChat
			</a>
			<nav class="flex flex-wrap justify-center gap-6 text-sm" aria-label="Footer">
				<a class="transition-colors hover:text-tx-light" href="#features">Features</a>
				<a class="transition-colors hover:text-tx-light" href="#how">How it works</a>
				<a class="transition-colors hover:text-tx-light" href="#compare">Compare</a>
				<a class="transition-colors hover:text-tx-light" href="#pricing">Pricing</a>
				<a class="transition-colors hover:text-tx-light" href="#faq">FAQ</a>
				<?php /* TODO: add Privacy Policy + Terms pages and link them here */ ?>
			</nav>
			<span class="text-sm">© <?php echo esc_html( gmdate( 'Y' ) ); ?> WpStoreChat. Built for WooCommerce.</span>
		</div>
		<p class="mt-7 border-t border-white/10 pt-7 text-center text-xs leading-relaxed text-tx-light/60">
			WpStoreChat is an independent product and is not affiliated with or endorsed by Automattic. “WooCommerce” and “WordPress” are trademarks of their respective owners, used for descriptive purposes only.
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
