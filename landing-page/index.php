<?php
/**
 * CartScout landing page — "Electric" design.
 *
 * Asset swap points (search for "TODO:"):
 *   - Feature shots  → each .feat-visual carries data-shot="<key>"; drop an <img> in place of the mock
 *   - og:image       → 1200x630 social card
 *   - Checkout links → every .js-buy anchor
 *   - Privacy/Terms  → footer
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> data-hero="dark">
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CartScout — More WooCommerce sales. Zero extra work.</title>
<meta name="description" content="CartScout is the AI salesperson for your WooCommerce store. It chats with shoppers, finds the right product, and adds it to their cart — 24/7, in 12 languages. No API keys, no monthly fees. One flat price: $99/year or $299 once.">
<link rel="canonical" href="<?php echo esc_url( home_url( '/' ) ); ?>">
<meta name="theme-color" content="#120B2E">

<meta property="og:type" content="website">
<meta property="og:site_name" content="CartScout">
<meta property="og:title" content="CartScout — More WooCommerce sales. Zero extra work.">
<meta property="og:description" content="The AI salesperson for your WooCommerce store. Finds products, answers questions, fills carts — around the clock, in 12 languages. No monthly fees.">
<meta property="og:url" content="<?php echo esc_url( home_url( '/' ) ); ?>">
<?php /* TODO: add og:image — 1200x630 → <meta property="og:image" content="...assets/img/og.png"> */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="CartScout — AI Chatbot for WooCommerce">
<meta name="twitter:description" content="More WooCommerce sales. Zero extra work. One flat price, no monthly fees.">

<script type="application/ld+json">
{
	"@context": "https://schema.org",
	"@type": "SoftwareApplication",
	"name": "CartScout",
	"alternateName": "CartScout — AI Chatbot for WooCommerce",
	"applicationCategory": "BusinessApplication",
	"applicationSubCategory": "AI Shopping Assistant for WooCommerce",
	"operatingSystem": "WordPress with WooCommerce",
	"url": "<?php echo esc_url( home_url( '/' ) ); ?>",
	"description": "CartScout is an AI chatbot for WooCommerce that finds products, answers shopper questions from real store data, adds items to the cart, tracks orders, and hands off to humans — with fully managed AI and no API key required.",
	"offers": [
		{ "@type": "Offer", "name": "Starter — one store, yearly", "price": "99", "priceCurrency": "USD" },
		{ "@type": "Offer", "name": "Forever — one store, pay once", "price": "299", "priceCurrency": "USD" },
		{ "@type": "Offer", "name": "Unlimited — unlimited stores, yearly", "price": "499", "priceCurrency": "USD" }
	],
	"featureList": "AI product search and recommendations, side-by-side product comparison, add to cart and checkout in chat, order tracking, coupon and sale surfacing, FAQ/CSV/site-content training, human handoff, conversation insights, 12 languages, proactive engagement, full branding controls",
	"softwareHelp": { "@type": "CreativeWork", "url": "<?php echo esc_url( home_url( '/#faq' ) ); ?>" }
}
</script>
<script type="application/ld+json">
{
	"@context": "https://schema.org",
	"@type": "FAQPage",
	"mainEntity": [
		{
			"@type": "Question",
			"name": "Why is there no monthly fee?",
			"acceptedAnswer": { "@type": "Answer", "text": "We charge one flat yearly price — or once, with Forever. No per-seat fees, no per-conversation fees, no AI credits. The AI is included: every plan covers thousands of shopper conversations a month, which is far more than a typical store uses." }
		},
		{
			"@type": "Question",
			"name": "Do I need an OpenAI account or API key?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. CartScout ships with the AI built in. Install the plugin, activate it, and it works — there's nothing to sign up for and no key to paste anywhere." }
		},
		{
			"@type": "Question",
			"name": "Will it make things up about my products?",
			"acceptedAnswer": { "@type": "Answer", "text": "CartScout only answers from your actual catalog, your pages and your policies. If it doesn't know, it says so and offers a human handoff — it never invents prices, stock or shipping promises." }
		},
		{
			"@type": "Question",
			"name": "Can it actually add products to the cart?",
			"acceptedAnswer": { "@type": "Answer", "text": "Yes — it's built on WooCommerce's own cart. It handles variations (size, color), respects stock levels, and drops the shopper at your normal checkout. Nothing about your order flow changes." }
		},
		{
			"@type": "Question",
			"name": "Will it slow down my store?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. The widget is a few kilobytes and loads after your page does. The AI runs on our servers, not yours — your hosting does no extra work." }
		},
		{
			"@type": "Question",
			"name": "What happens if CartScout shuts down?",
			"acceptedAnswer": { "@type": "Answer", "text": "Your store keeps working — the plugin degrades gracefully and never breaks your site. Forever licenses include a guarantee: if we ever wind down, we ship a final self-hosted release." }
		},
		{
			"@type": "Question",
			"name": "What languages does it support?",
			"acceptedAnswer": { "@type": "Answer", "text": "Twelve, auto-detected from the shopper's browser — including English, Spanish, German, French, Italian, Portuguese and Dutch. One store, every shopper in their own language." }
		},
		{
			"@type": "Question",
			"name": "Is it GDPR-friendly?",
			"acceptedAnswer": { "@type": "Answer", "text": "Yes. Conversations are stored in your WordPress database, not sold or used to train outside models. There's a built-in consent notice, data export, and one-click deletion." }
		},
		{
			"@type": "Question",
			"name": "What's the difference between the three plans?",
			"acceptedAnswer": { "@type": "Answer", "text": "Starter is one store, billed yearly. Forever is the same single store but you pay once and own it — updates for life. Unlimited covers as many stores or client sites as you want, billed yearly. All three have every feature." }
		}
	]
}
</script>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-paper font-body text-tx-dark antialiased' ); ?>>

<a class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-[100] focus:rounded-lg focus:bg-acc focus:px-4 focus:py-2 focus:font-bold focus:text-tx-dark" href="#main">Skip to content</a>

<!-- ════════════════════════════════ NAV ════════════════════════════════ -->
<header class="site-nav sticky top-0 z-50 border-b border-white/10 bg-ink/90 backdrop-blur-md">
	<nav class="mx-auto flex h-[72px] max-w-[1180px] items-center justify-between px-6 sm:px-8" aria-label="Main">
		<a href="#" class="flex items-center gap-2.5 font-display text-[21px] font-bold text-tx-light">
			<span class="grid h-8 w-8 place-items-center rounded-[9px] bg-acc text-base font-extrabold text-tx-dark">C</span>
			CartScout
			<span class="hidden rounded bg-acc2 px-1.5 py-[3px] text-[10px] font-bold uppercase tracking-wider text-white sm:inline-block">for WooCommerce</span>
		</a>
		<div class="hidden items-center gap-[30px] md:flex">
			<a class="text-[15px] font-medium text-tx-light/60 transition-colors hover:text-tx-light" href="#features">Features</a>
			<a class="text-[15px] font-medium text-tx-light/60 transition-colors hover:text-tx-light" href="#compare">Compare</a>
			<a class="text-[15px] font-medium text-tx-light/60 transition-colors hover:text-tx-light" href="#pricing">Pricing</a>
			<a class="text-[15px] font-medium text-tx-light/60 transition-colors hover:text-tx-light" href="#faq">FAQ</a>
			<a class="btn-press rounded-full bg-acc px-5 py-2.5 font-display text-sm font-bold text-tx-dark" href="#pricing">See pricing</a>
		</div>
		<button data-menu-button aria-expanded="false" aria-controls="mobile-menu" aria-label="Menu" class="flex h-10 w-10 items-center justify-center rounded-full border border-white/20 text-tx-light md:hidden">
			<svg width="18" height="12" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path d="M1 1h16M1 6h16M1 11h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
		</button>
	</nav>
	<div data-menu-panel id="mobile-menu" class="hidden border-t border-white/10 bg-ink px-6 py-4 md:hidden">
		<div class="flex flex-col gap-4 text-base font-semibold text-tx-light">
			<a href="#features">Features</a>
			<a href="#compare">Compare</a>
			<a href="#pricing">Pricing</a>
			<a href="#faq">FAQ</a>
			<a class="rounded-full bg-acc px-5 py-3 text-center text-tx-dark" href="#pricing">See pricing</a>
		</div>
	</div>
</header>

<main id="main">

<!-- ════════════════════════════════ HERO ════════════════════════════════ -->
<header class="relative overflow-hidden bg-ink pb-24 pt-[84px] text-tx-light">
	<div aria-hidden="true" class="pointer-events-none absolute -right-44 -top-44 h-[560px] w-[560px] rounded-full" style="background:radial-gradient(circle, rgba(139,92,246,0.32), transparent 70%)"></div>
	<div class="relative z-10 mx-auto grid max-w-[1180px] items-center gap-16 px-6 sm:px-8 lg:grid-cols-[1.05fr_0.95fr]">
		<div>
			<span class="reveal mb-7 inline-flex items-center gap-2 rounded-full border border-white/20 px-4 py-2 text-[13px] font-semibold uppercase tracking-wider text-tx-light/65">
				<span class="h-2 w-2 rounded-full bg-acc"></span> Built for WooCommerce
			</span>
			<h1 class="reveal text-[clamp(44px,5.4vw,76px)] font-bold leading-[1.02]" style="--reveal-delay:80ms">
				More WooCommerce <span class="hl">sales.</span><br>Zero extra <span class="hl">work.</span>
			</h1>
			<p class="reveal mb-9 mt-7 max-w-[480px] text-[19px] leading-relaxed text-tx-light/65" style="--reveal-delay:160ms">
				CartScout chats with your shoppers, finds the right product, and adds it to their cart — 24/7, in 12 languages. No API keys, no monthly fees.
			</p>
			<div class="reveal mb-[22px] flex flex-wrap gap-3.5" style="--reveal-delay:240ms">
				<a class="btn-press inline-flex items-center gap-2.5 rounded-full bg-acc px-[30px] py-4 font-display text-[17px] font-bold text-tx-dark shadow-acc" href="#pricing">See pricing</a>
				<a class="btn-press inline-flex items-center gap-2.5 rounded-full border-[1.5px] border-white/30 px-[30px] py-4 font-display text-[17px] font-bold text-tx-light" href="#compare">Why it's cheaper →</a>
			</div>
			<p class="reveal flex flex-wrap gap-x-[18px] gap-y-2 text-[13.5px] text-tx-light/65" style="--reveal-delay:300ms">
				<span class="inline-flex items-center gap-1.5"><b class="text-acc">✓</b> 30-day guarantee</span>
				<span class="inline-flex items-center gap-1.5"><b class="text-acc">✓</b> No API keys</span>
				<span class="inline-flex items-center gap-1.5"><b class="text-acc">✓</b> 5-minute setup</span>
				<span class="inline-flex items-center gap-1.5"><b class="text-acc">✓</b> No monthly fees</span>
			</p>
		</div>

		<!-- Live animated chat demo (built by main.js initChatDemo). -->
		<div class="reveal w-full max-w-[420px] justify-self-center overflow-hidden rounded-[22px] bg-card shadow-chat lg:justify-self-end" style="--reveal-delay:200ms" aria-hidden="true">
			<div class="flex items-center gap-3 bg-acc2 px-5 py-4 text-white">
				<span class="grid h-[38px] w-[38px] shrink-0 place-items-center rounded-full bg-acc font-display text-base font-extrabold text-tx-dark">S</span>
				<div>
					<div class="font-display text-base font-bold">Scout</div>
					<div class="flex items-center gap-1.5 text-xs text-white/80"><span class="h-[7px] w-[7px] animate-pulse-dot rounded-full bg-online"></span> Shopping assistant — online</div>
				</div>
			</div>
			<div id="hero-chat" class="cd-body flex h-[380px] flex-col gap-2.5 overflow-hidden bg-[#FAFAFD] p-[18px]"></div>
			<div class="flex items-center gap-2.5 border-t border-line bg-white px-[18px] py-[13px] text-sm text-tx-dark/40">
				Ask anything about the store… <span class="ml-auto grid h-8 w-8 place-items-center rounded-full bg-acc2 text-sm text-white">↑</span>
			</div>
		</div>
	</div>
</header>

<!-- ════════════════════════════════ STATS ════════════════════════════════ -->
<section class="bg-ink2 text-tx-light">
	<div class="mx-auto grid max-w-[1180px] grid-cols-2 px-6 sm:px-8 lg:grid-cols-4" data-reveal-group>
		<div class="px-7 py-11">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">70%</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/65">of carts are abandoned. CartScout answers before shoppers leave.</div>
		</div>
		<div class="border-l border-white/10 px-7 py-11">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">24/7</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/65">It never misses a shopper, even when you're asleep or on holiday.</div>
		</div>
		<div class="border-t border-white/10 px-7 py-11 lg:border-l lg:border-t-0">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">12</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/65">languages, auto-detected. Sell to shoppers in their own words.</div>
		</div>
		<div class="border-l border-t border-white/10 px-7 py-11 lg:border-t-0">
			<div class="font-display text-[clamp(34px,3.4vw,52px)] font-bold leading-none text-acc">$0</div>
			<div class="mt-2.5 text-sm leading-relaxed text-tx-light/65">monthly fee. One flat price — the AI is included.</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ MARQUEE ════════════════════════════════ -->
<div class="relative z-10 -my-1 overflow-hidden bg-acc py-3.5" style="transform:rotate(-1deg) scale(1.02)" aria-hidden="true">
	<div class="marquee-track items-center gap-12 pr-12">
		<?php for ( $i = 0; $i < 2; $i++ ) :
			foreach ( array( 'Built for WooCommerce', 'Finds products', 'Adds to cart', 'Answers shipping questions', 'Tracks orders', 'Speaks 12 languages' ) as $phrase ) : ?>
			<span class="font-display text-base font-bold text-tx-dark"><?php echo esc_html( $phrase ); ?></span><span class="text-[13px] text-tx-dark">✦</span>
		<?php endforeach; endfor; ?>
	</div>
</div>

<!-- ════════════════════════════════ FEATURES ════════════════════════════════ -->
<section id="features" class="bg-paper py-26">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal mx-auto mb-16 max-w-[640px] text-center lg:mb-[84px]">
			<span class="kicker">What it does</span>
			<h2 class="my-[18px] text-[clamp(36px,4.2vw,58px)] font-bold leading-[1.06]">Watch it <span class="hl">sell.</span></h2>
			<p class="text-lg leading-relaxed text-tx-dark/65">The five features that make stores money.</p>
		</div>

		<!-- 01 ─ catalog → cart → checkout -->
		<article class="reveal grid items-center gap-10 py-14 lg:grid-cols-2 lg:gap-[72px]">
			<div>
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc2">01</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(28px,2.8vw,40px)] font-bold leading-[1.1]">From "just browsing" to checkout. One chat.</h3>
				<p class="mb-[22px] max-w-[440px] text-[16.5px] leading-relaxed text-tx-dark/65">Shoppers say what they want. CartScout shows real products from your catalog, adds the right one to the cart — size, color and all — and drops a one-tap checkout button.</p>
				<div class="flex flex-wrap gap-2">
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Real catalog search</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Stock &amp; variations</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">One-tap checkout</span>
				</div>
			</div>
			<div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-[18px] bg-ink p-[30px] shadow-visual" data-shot="sell">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="pointer-events-none absolute -bottom-7 right-[18px] font-display text-[140px] font-extrabold leading-none text-tx-light/[0.06]" aria-hidden="true">01</span>
				<div class="relative z-10 w-full max-w-[360px] overflow-hidden rounded-[14px] bg-white text-[12.5px] shadow-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-user">Waterproof jacket, women's M, under $150</div>
						<div class="mini-msg mini-bot">Two in stock that fit the bill:</div>
						<div class="flex gap-2">
							<div class="mini-card"><div class="mini-thumb" style="background:linear-gradient(135deg,#8B5CF6,#5B3FD4)"></div><b>Stormline W</b><div>$129</div></div>
							<div class="mini-card"><div class="mini-thumb" style="background:linear-gradient(135deg,#34B3E4,#2563EB)"></div><b>Drift Shell</b><div>$144</div></div>
						</div>
						<div class="mini-msg mini-user">First one, in navy</div>
						<div class="mini-msg mini-bot">In your cart — Stormline W, navy, size M. ✓</div>
						<div class="mini-btn">Checkout — $129.00</div>
					</div>
				</div>
			</div>
		</article>

		<!-- 02 ─ comparison table -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-14 lg:grid-cols-2 lg:gap-[72px]">
			<div class="lg:order-2">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc2">02</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(28px,2.8vw,40px)] font-bold leading-[1.1]">It closes the "which one?" moment.</h3>
				<p class="mb-[22px] max-w-[440px] text-[16.5px] leading-relaxed text-tx-dark/65">Side-by-side comparison tables with real specs and live prices — and an add-to-cart button right in the table. Shoppers decide and buy in the same breath.</p>
				<div class="flex flex-wrap gap-2">
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Compare 2–4 products</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Real-time prices</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Buy from the table</span>
				</div>
			</div>
			<div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-[18px] bg-ink p-[30px] shadow-visual lg:order-1" data-shot="compare">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="pointer-events-none absolute -bottom-7 right-[18px] font-display text-[140px] font-extrabold leading-none text-tx-light/[0.06]" aria-hidden="true">02</span>
				<div class="relative z-10 w-full max-w-[360px] overflow-hidden rounded-[14px] bg-white text-[12.5px] shadow-mock">
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
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-14 lg:grid-cols-2 lg:gap-[72px]">
			<div>
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc2">03</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(28px,2.8vw,40px)] font-bold leading-[1.1]">It makes the first move.</h3>
				<p class="mb-[22px] max-w-[440px] text-[16.5px] leading-relaxed text-tx-dark/65">A perfectly timed hello invites shoppers in. Today's deals and coupon codes surface on request, and every add-to-cart gets one smart cross-sell suggestion.</p>
				<div class="flex flex-wrap gap-2">
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Page-targeted greeting</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Coupon surfacing</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Cross-sells</span>
				</div>
			</div>
			<div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-[18px] bg-ink p-[30px] shadow-visual" data-shot="proactive">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="pointer-events-none absolute -bottom-7 right-[18px] font-display text-[140px] font-extrabold leading-none text-tx-light/[0.06]" aria-hidden="true">03</span>
				<div class="relative z-10 w-full max-w-[360px] overflow-hidden rounded-[14px] bg-white text-[12.5px] shadow-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-bot">That jacket pairs well with the Trailhead Beanie — $19, and it ships in the same box. Want it?</div>
						<div class="mini-msg mini-user">Any discounts running?</div>
						<div class="mini-msg mini-bot"><span class="mini-tag">SPRING15</span> &nbsp;15% off orders over $100 — applied at checkout.</div>
					</div>
				</div>
				<div class="absolute bottom-[22px] right-[22px] z-20 max-w-[200px] rounded-xl bg-acc px-3.5 py-3 font-display text-[12.5px] font-bold leading-snug text-tx-dark shadow-pop after:absolute after:-bottom-[7px] after:right-6 after:border-[7px] after:border-b-0 after:border-transparent after:border-t-acc after:content-['']">👋 Looking for a gift? I can narrow it down in 20 seconds.</div>
			</div>
		</article>

		<!-- 04 ─ wp-admin insights -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-14 lg:grid-cols-2 lg:gap-[72px]">
			<div class="lg:order-2">
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc2">04</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(28px,2.8vw,40px)] font-bold leading-[1.1]">Mission control, inside wp-admin.</h3>
				<p class="mb-[22px] max-w-[440px] text-[16.5px] leading-relaxed text-tx-dark/65">Chats → carts → checkouts, week over week. Full transcripts of every conversation. And a report of searches that found nothing — your next bestseller, spelled out.</p>
				<div class="flex flex-wrap gap-2">
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Weekly trends</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Chat logs</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Unmet-demand report</span>
				</div>
			</div>
			<div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-[18px] bg-ink p-[30px] shadow-visual lg:order-1" data-shot="insights">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="pointer-events-none absolute -bottom-7 right-[18px] font-display text-[140px] font-extrabold leading-none text-tx-light/[0.06]" aria-hidden="true">04</span>
				<div class="relative z-10 w-full max-w-[360px] overflow-hidden rounded-[14px] bg-white text-[12.5px] shadow-mock">
					<div class="flex gap-[5px] border-b border-tx-dark/10 px-3 py-2.5"><i class="h-2 w-2 rounded-full bg-tx-dark/15"></i><i class="h-2 w-2 rounded-full bg-tx-dark/15"></i><i class="h-2 w-2 rounded-full bg-tx-dark/15"></i></div>
					<div class="flex flex-col gap-2.5 p-4">
						<div class="flex gap-2">
							<div class="flex-1 rounded-[9px] bg-[#F4F2FB] p-[9px]"><b class="block font-display text-[17px]">1,284</b><i class="text-[10px] not-italic text-tx-dark/64">Chats this week</i></div>
							<div class="flex-1 rounded-[9px] bg-[#F4F2FB] p-[9px]"><b class="block font-display text-[17px]">312</b><i class="text-[10px] not-italic text-tx-dark/64">Carts built</i></div>
							<div class="flex-1 rounded-[9px] bg-[#F4F2FB] p-[9px]"><b class="block font-display text-[17px]">$9,140</b><i class="text-[10px] not-italic text-tx-dark/64">Chat revenue</i></div>
						</div>
						<div class="flex h-16 items-end gap-[7px] pt-1">
							<i class="flex-1 rounded-t-[4px] bg-acc2/85" style="height:38%"></i>
							<i class="flex-1 rounded-t-[4px] bg-acc2/85" style="height:52%"></i>
							<i class="flex-1 rounded-t-[4px] bg-acc2/85" style="height:44%"></i>
							<i class="flex-1 rounded-t-[4px] bg-acc2/85" style="height:66%"></i>
							<i class="flex-1 rounded-t-[4px] bg-acc2/85" style="height:58%"></i>
							<i class="flex-1 rounded-t-[4px] bg-acc2/85" style="height:82%"></i>
							<i class="flex-1 rounded-t-[4px] bg-acc" style="height:100%"></i>
						</div>
						<div class="text-[11px] text-tx-dark/55">Top unmet search: <b>"vegan trail boots"</b> — 41 asks, 0 results</div>
					</div>
				</div>
			</div>
		</article>

		<!-- 05 ─ support autopilot -->
		<article class="reveal grid items-center gap-10 border-t border-dashed border-tx-dark/15 py-14 lg:grid-cols-2 lg:gap-[72px]">
			<div>
				<div class="font-display text-[15px] font-extrabold tracking-[0.1em] text-acc2">05</div>
				<h3 class="mb-4 mt-3.5 text-[clamp(28px,2.8vw,40px)] font-bold leading-[1.1]">Support tickets answer themselves.</h3>
				<p class="mb-[22px] max-w-[440px] text-[16.5px] leading-relaxed text-tx-dark/65">Order status by email lookup, shipping and policy answers quoted from your own pages, and a human handoff that lands with the full conversation attached.</p>
				<div class="flex flex-wrap gap-2">
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Order tracking</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Policy answers</span>
					<span class="rounded-full border border-tx-dark/15 px-[13px] py-1.5 text-[12.5px] font-semibold text-tx-dark/65">Human handoff</span>
				</div>
			</div>
			<div class="relative flex min-h-[320px] items-center justify-center overflow-hidden rounded-[18px] bg-ink p-[30px] shadow-visual" data-shot="support">
				<!-- TODO: swap this mock for an <img> when product shots are ready -->
				<span class="pointer-events-none absolute -bottom-7 right-[18px] font-display text-[140px] font-extrabold leading-none text-tx-light/[0.06]" aria-hidden="true">05</span>
				<div class="relative z-10 w-full max-w-[360px] overflow-hidden rounded-[14px] bg-white text-[12.5px] shadow-mock">
					<div class="flex flex-col gap-2.5 p-4">
						<div class="mini-msg mini-user">Where's my order? anna@mail.com</div>
						<div class="mini-msg mini-bot">Order <b>#4182</b> shipped Tuesday — arriving <b>tomorrow</b> via DHL. 📦</div>
						<div class="mini-msg mini-user">Can I still change the address?</div>
						<div class="mini-msg mini-bot">That one needs a human — I've sent your chat to the store team. They reply within a few hours.</div>
					</div>
				</div>
			</div>
		</article>
	</div>
</section>

<!-- ════════════════════════════════ SETUP STEPS ════════════════════════════════ -->
<section class="bg-ink py-26 text-tx-light">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal mx-auto mb-16 max-w-[640px] text-center lg:mb-[84px]">
			<span class="kicker text-tx-light">Setup</span>
			<h2 class="my-[18px] text-[clamp(36px,4.2vw,58px)] font-bold leading-[1.06]">Live before your <span class="hl">coffee cools.</span></h2>
			<p class="text-lg leading-relaxed text-tx-light/65">If you've installed a WordPress plugin, you already know how.</p>
		</div>
		<div class="grid gap-[22px] lg:grid-cols-3" data-reveal-group>
			<div class="rounded-[18px] border border-white/10 bg-ink2 p-[30px] lg:p-[34px]">
				<div class="grid h-11 w-11 place-items-center rounded-xl bg-acc font-display text-[19px] font-extrabold text-tx-dark">1</div>
				<h3 class="mb-2.5 mt-[22px] text-[22px] font-bold">Install the plugin</h3>
				<p class="text-[15px] leading-relaxed text-tx-light/65">Upload, activate, done — like any WordPress plugin. The AI comes with it. No accounts to create, no API keys to paste.</p>
				<span class="mt-4 inline-block text-xs font-bold uppercase tracking-wider text-acc">~2 minutes</span>
			</div>
			<div class="rounded-[18px] border border-white/10 bg-ink2 p-[30px] lg:p-[34px]">
				<div class="grid h-11 w-11 place-items-center rounded-xl bg-acc font-display text-[19px] font-extrabold text-tx-dark">2</div>
				<h3 class="mb-2.5 mt-[22px] text-[22px] font-bold">Make it yours</h3>
				<p class="text-[15px] leading-relaxed text-tx-light/65">Your logo, colors, tone and FAQs — with a live preview. CartScout reads your catalog and policies on its own.</p>
				<span class="mt-4 inline-block text-xs font-bold uppercase tracking-wider text-acc">~3 minutes</span>
			</div>
			<div class="rounded-[18px] border border-white/10 bg-ink2 p-[30px] lg:p-[34px]">
				<div class="grid h-11 w-11 place-items-center rounded-xl bg-acc font-display text-[19px] font-extrabold text-tx-dark">3</div>
				<h3 class="mb-2.5 mt-[22px] text-[22px] font-bold">Watch orders grow</h3>
				<p class="text-[15px] leading-relaxed text-tx-light/65">It sells while you sleep. The dashboard shows the receipts: chats, carts, checkouts, revenue.</p>
				<span class="mt-4 inline-block text-xs font-bold uppercase tracking-wider text-acc">From day one</span>
			</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ COMPARISON ════════════════════════════════ -->
<section id="compare" class="bg-paper py-26">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal mx-auto mb-16 max-w-[640px] text-center lg:mb-[84px]">
			<span class="kicker">The math</span>
			<h2 class="my-[18px] text-[clamp(36px,4.2vw,58px)] font-bold leading-[1.06]">Stop <span class="hl">renting</span> your chatbot.</h2>
			<p class="text-lg leading-relaxed text-tx-dark/65">Most AI chatbots bill monthly, per seat, or per conversation — and aren't built for WooCommerce. CartScout is one flat price. Everything included.</p>
		</div>
		<div class="reveal overflow-x-auto">
			<table class="w-full min-w-[680px] border-separate border-spacing-0 overflow-hidden rounded-[18px] bg-card text-left text-[15px] shadow-card">
				<thead>
					<tr class="bg-ink font-display text-[13px] uppercase tracking-[0.08em] text-tx-light">
						<th class="px-[22px] py-[18px] font-semibold">Chatbot</th>
						<th class="px-[22px] py-[18px] font-semibold">Pricing model</th>
						<th class="px-[22px] py-[18px] font-semibold">Cost, 1st year*</th>
						<th class="px-[22px] py-[18px] font-semibold">Seat fees</th>
						<th class="px-[22px] py-[18px] font-semibold">WooCommerce native</th>
					</tr>
				</thead>
				<tbody>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="bg-[#F5FFCD] px-[22px] py-[18px] font-display font-bold">CartScout</td>
						<td class="bg-[#F5FFCD] px-[22px] py-[18px] font-bold">Flat — yearly or once</td>
						<td class="bg-[#F5FFCD] px-[22px] py-[18px] font-display font-bold">$99</td>
						<td class="bg-[#F5FFCD] px-[22px] py-[18px] font-bold">None</td>
						<td class="bg-[#F5FFCD] px-[22px] py-[18px] font-bold text-green">✓ Cart, checkout, orders</td>
					</tr>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="px-[22px] py-[18px] font-semibold">Intercom + Fin AI</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Per seat + per resolution</td>
						<td class="px-[22px] py-[18px] font-semibold">~$1,400+</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">$29+/seat/mo</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic support bot</td>
					</tr>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="px-[22px] py-[18px] font-semibold">Tidio + Lyro AI</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Monthly + AI add-on</td>
						<td class="px-[22px] py-[18px] font-semibold">~$960+</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Per-operator tiers</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic chat</td>
					</tr>
					<tr class="[&>td]:border-b [&>td]:border-tx-dark/[0.07]">
						<td class="px-[22px] py-[18px] font-semibold">Chatbase</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Monthly + credits</td>
						<td class="px-[22px] py-[18px] font-semibold">~$480+</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Credit overages</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic, no cart</td>
					</tr>
					<tr>
						<td class="px-[22px] py-[18px] font-semibold">Crisp</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Monthly + AI credits</td>
						<td class="px-[22px] py-[18px] font-semibold">~$660+</td>
						<td class="px-[22px] py-[18px] text-tx-dark/65">Per-workspace</td>
						<td class="px-[22px] py-[18px] font-semibold text-danger">✗ Generic chat</td>
					</tr>
				</tbody>
			</table>
			<p class="mt-[18px] text-center text-[13px] text-tx-dark/65">*Typical published pricing for a small store with AI features enabled, billed for 12 months. Checked June 2026.</p>
		</div>
		<p class="reveal mt-11 text-center font-display text-[clamp(24px,2.6vw,34px)] font-bold">That's <span class="hl">$380 to $1,300+</span> back in your pocket. Every year.</p>
	</div>
</section>

<!-- ════════════════════════════════ PRICING ════════════════════════════════ -->
<section id="pricing" class="bg-ink py-26 text-tx-light">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal mx-auto mb-16 max-w-[640px] text-center lg:mb-[84px]">
			<span class="kicker text-tx-light">Pricing</span>
			<h2 class="my-[18px] text-[clamp(36px,4.2vw,58px)] font-bold leading-[1.06]">One price. <span class="hl">Everything included.</span></h2>
			<p class="text-lg leading-relaxed text-tx-light/65">Every feature. The AI. All updates. No meter running.</p>
		</div>
		<div class="grid items-stretch gap-[22px] lg:grid-cols-3" data-reveal-group>
			<!-- Starter -->
			<div class="flex flex-col rounded-[18px] border border-white/10 bg-ink2 p-9 lg:p-[36px]">
				<div class="font-display text-lg font-bold">Starter</div>
				<div class="mb-6 text-[13.5px] text-tx-light/65">For your first store</div>
				<div class="font-display text-[54px] font-bold leading-none">$99</div>
				<div class="mb-[26px] mt-2 text-sm text-tx-light/65">per year · 1 store</div>
				<ul class="mb-8 flex flex-1 flex-col gap-3 text-[14.5px] leading-snug">
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Every feature, AI included — no usage fees</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">12 languages, auto-detected</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Updates &amp; support while active</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">30-day money-back guarantee</li>
				</ul>
				<a class="btn-press js-buy rounded-full border-[1.5px] border-white/30 px-6 py-3.5 text-center font-display text-[15px] font-bold text-tx-light" href="#"><?php /* TODO: Freemius checkout link */ ?>Get started</a>
			</div>

			<!-- Forever (most popular) -->
			<div class="relative flex flex-col rounded-[18px] bg-acc2 p-9 shadow-plan lg:scale-[1.04] lg:p-[36px]">
				<span class="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-acc px-4 py-1.5 font-display text-xs font-extrabold uppercase tracking-wider text-tx-dark">Most popular — pay once</span>
				<div class="font-display text-lg font-bold">Forever</div>
				<div class="mb-6 text-[13.5px] text-white/75">Own it. No renewals, ever.</div>
				<div class="font-display text-[54px] font-bold leading-none">$299</div>
				<div class="mb-[26px] mt-2 text-sm text-white/75">one-time · 1 store · lifetime updates</div>
				<ul class="mb-8 flex flex-1 flex-col gap-3 text-[14.5px] leading-snug">
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Everything in Starter</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Pay once — keeps working even if you never spend another cent</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Lifetime updates &amp; AI included</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Priority email support</li>
				</ul>
				<a class="btn-press js-buy rounded-full bg-acc px-6 py-3.5 text-center font-display text-[15px] font-bold text-tx-dark shadow-acc" href="#"><?php /* TODO: Freemius checkout link */ ?>Get Forever — $299</a>
			</div>

			<!-- Unlimited -->
			<div class="flex flex-col rounded-[18px] border border-white/10 bg-ink2 p-9 lg:p-[36px]">
				<div class="font-display text-lg font-bold">Unlimited</div>
				<div class="mb-6 text-[13.5px] text-tx-light/65">For agencies &amp; multi-store brands</div>
				<div class="font-display text-[54px] font-bold leading-none">$499</div>
				<div class="mb-[26px] mt-2 text-sm text-tx-light/65">per year · unlimited stores</div>
				<ul class="mb-8 flex flex-1 flex-col gap-3 text-[14.5px] leading-snug">
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Everything in Forever</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">Unlimited stores &amp; client sites</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">All updates &amp; priority support</li>
					<li class="relative pl-[26px] before:absolute before:left-0 before:font-extrabold before:text-acc before:content-['✓']">30-day money-back guarantee</li>
				</ul>
				<a class="btn-press js-buy rounded-full border-[1.5px] border-white/30 px-6 py-3.5 text-center font-display text-[15px] font-bold text-tx-light" href="#"><?php /* TODO: Freemius checkout link */ ?>Get started</a>
			</div>
		</div>
		<p class="reveal mt-9 text-center text-sm text-tx-light/65">All plans: 30-day money-back guarantee · the AI runs on us — thousands of shopper conversations a month included.</p>
	</div>
</section>

<!-- ════════════════════════════════ FAQ ════════════════════════════════ -->
<section id="faq" class="bg-paper py-26">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="reveal mx-auto mb-16 max-w-[640px] text-center lg:mb-[84px]">
			<span class="kicker">FAQ</span>
			<h2 class="mt-[18px] text-[clamp(36px,4.2vw,58px)] font-bold leading-[1.06]">Fair questions.</h2>
		</div>
		<div class="reveal mx-auto flex max-w-[780px] flex-col gap-3">
			<?php
			$faqs = array(
				array( 'Why is there no monthly fee?', 'We charge one flat yearly price — or once, with Forever. No per-seat fees, no per-conversation fees, no AI credits. The AI is included: every plan covers thousands of shopper conversations a month, which is far more than a typical store uses.' ),
				array( 'Do I need an OpenAI account or API key?', "No. CartScout ships with the AI built in. Install the plugin, activate it, and it works — there's nothing to sign up for and no key to paste anywhere." ),
				array( 'Will it make things up about my products?', "CartScout only answers from your actual catalog, your pages and your policies. If it doesn't know, it says so and offers a human handoff — it never invents prices, stock or shipping promises." ),
				array( 'Can it actually add products to the cart?', "Yes — it's built on WooCommerce's own cart. It handles variations (size, color), respects stock levels, and drops the shopper at your normal checkout. Nothing about your order flow changes." ),
				array( 'Will it slow down my store?', 'No. The widget is a few kilobytes and loads after your page does. The AI runs on our servers, not yours — your hosting does no extra work.' ),
				array( 'What happens if CartScout shuts down?', 'Your store keeps working — the plugin degrades gracefully and never breaks your site. Forever licenses include a guarantee: if we ever wind down, we ship a final self-hosted release.' ),
				array( 'What languages does it support?', "Twelve, auto-detected from the shopper's browser — including English, Spanish, German, French, Italian, Portuguese and Dutch. One store, every shopper in their own language." ),
				array( 'Is it GDPR-friendly?', "Yes. Conversations are stored in your WordPress database, not sold or used to train outside models. There's a built-in consent notice, data export, and one-click deletion." ),
				array( "What's the difference between the three plans?", 'Starter is one store, billed yearly. Forever is the same single store but you pay once and own it — updates for life. Unlimited covers as many stores or client sites as you want, billed yearly. All three have every feature.' ),
			);
			foreach ( $faqs as $i => $faq ) :
			?>
			<details class="faq-item overflow-hidden rounded-[14px] border border-tx-dark/[0.08] bg-card" <?php echo 0 === $i ? 'open' : ''; ?>>
				<summary class="flex items-center justify-between gap-4 px-6 py-5 font-display text-[16.5px] font-bold">
					<?php echo esc_html( $faq[0] ); ?>
				</summary>
				<p class="px-6 pb-[22px] text-[15px] leading-relaxed text-tx-dark/65"><?php echo esc_html( $faq[1] ); ?></p>
			</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ FINAL CTA ════════════════════════════════ -->
<section class="bg-ink py-26 text-center text-tx-light">
	<div class="reveal mx-auto max-w-[1180px] px-6 sm:px-8">
		<h2 class="mb-[34px] text-[clamp(38px,4.6vw,64px)] font-bold leading-[1.05]">Your store is open 24/7.<br><span class="hl">Your salesperson isn't.</span></h2>
		<a class="btn-press inline-flex items-center gap-2.5 rounded-full bg-acc px-[30px] py-4 font-display text-[17px] font-bold text-tx-dark shadow-acc" href="#pricing">See pricing</a>
		<p class="mt-5 text-sm text-tx-light/65">5-minute setup · 30-day guarantee · no monthly fees</p>
	</div>
</section>

</main>

<!-- ════════════════════════════════ FOOTER ════════════════════════════════ -->
<footer class="border-t border-white/10 bg-ink py-9 text-tx-light/65">
	<div class="mx-auto max-w-[1180px] px-6 sm:px-8">
		<div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
			<a href="#" class="flex items-center gap-2.5 font-display text-[17px] font-bold text-tx-light">
				<span class="grid h-[26px] w-[26px] place-items-center rounded-lg bg-acc text-[13px] font-extrabold text-tx-dark">C</span>
				CartScout
			</a>
			<nav class="flex flex-wrap justify-center gap-6 text-sm" aria-label="Footer">
				<a class="transition-colors hover:text-tx-light" href="#features">Features</a>
				<a class="transition-colors hover:text-tx-light" href="#compare">Compare</a>
				<a class="transition-colors hover:text-tx-light" href="#pricing">Pricing</a>
				<a class="transition-colors hover:text-tx-light" href="#faq">FAQ</a>
				<?php /* TODO: add Privacy Policy + Terms pages and link them here */ ?>
			</nav>
			<span class="text-sm">© <?php echo esc_html( gmdate( 'Y' ) ); ?> CartScout. Built for WooCommerce.</span>
		</div>
		<p class="mt-7 border-t border-white/10 pt-7 text-center text-xs leading-relaxed text-tx-light/60">
			CartScout is an independent product and is not affiliated with or endorsed by Automattic. “WooCommerce” and “WordPress” are trademarks of their respective owners, used for descriptive purposes only.
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
