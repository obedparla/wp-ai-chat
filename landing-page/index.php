<?php
/**
 * CartScout landing page.
 *
 * Asset swap points (search for "TODO:"):
 *   - Showcase video  → section#showcase
 *   - og:image        → 1200x630 social card
 *   - Checkout links  → every .js-buy anchor
 *   - Privacy/Terms   → footer
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CartScout — AI Chatbot for WooCommerce | Sell More, 24/7</title>
<meta name="description" content="The AI salesperson for your WooCommerce store. Answers shoppers, recommends products, and adds to cart — 24/7, in 12 languages. One flat yearly price, no monthly fees. Free trial.">
<link rel="canonical" href="<?php echo esc_url( home_url( '/' ) ); ?>">
<meta name="theme-color" content="#FFFFFF">

<meta property="og:type" content="website">
<meta property="og:site_name" content="CartScout">
<meta property="og:title" content="CartScout — AI Chatbot for WooCommerce | Sell More, 24/7">
<meta property="og:description" content="The AI salesperson for your WooCommerce store. Finds products, answers questions, fills carts — around the clock. No monthly fees.">
<meta property="og:url" content="<?php echo esc_url( home_url( '/' ) ); ?>">
<?php /* TODO: add og:image — 1200x630 → <meta property="og:image" content="...assets/img/og.png"> */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="CartScout — AI Chatbot for WooCommerce">
<meta name="twitter:description" content="The AI salesperson for your WooCommerce store. No monthly fees.">

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
		{ "@type": "Offer", "name": "Pro — one store, yearly", "price": "299", "priceCurrency": "USD" },
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
			"acceptedAnswer": { "@type": "Answer", "text": "We charge one flat yearly price — no monthly invoices, no per-seat pricing, no per-conversation fees. The AI is included. Every plan covers thousands of shopper conversations a month; a typical store uses a few hundred." }
		},
		{
			"@type": "Question",
			"name": "Do I need an OpenAI account or API key?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. CartScout ships with fully managed AI. Install the plugin, start your free trial, and the chatbot works immediately — we host the AI infrastructure, route every request through our servers, and keep the models current for you." }
		},
		{
			"@type": "Question",
			"name": "Will it make things up about my products?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. CartScout only states facts returned by your store — live prices, stock, attributes, shipping zones, coupon codes, and your own pages. If it can't find something, it says so and offers the closest real alternative instead of inventing specs or shipping times." }
		},
		{
			"@type": "Question",
			"name": "Can it actually add products to the cart?",
			"acceptedAnswer": { "@type": "Answer", "text": "Yes. Shoppers search, compare, pick a variation like size or color, and add to cart without leaving the chat. When they're ready, the bot shows a one-tap checkout button. Removing items always asks for confirmation first." }
		},
		{
			"@type": "Question",
			"name": "What happens if CartScout shuts down?",
			"acceptedAnswer": { "@type": "Answer", "text": "Your store keeps its data — the plugin, settings, transcripts, and insights all live on your server. And if we ever sunset the managed AI service, we commit to shipping a bring-your-own-key mode first, so your chatbot keeps running on your own OpenAI account." }
		},
		{
			"@type": "Question",
			"name": "Will it slow down my store?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. Your storefront pages load only a ~6KB stub; the full widget loads when a shopper interacts with it. Styles are fully scoped, so it never collides with your theme." }
		},
		{
			"@type": "Question",
			"name": "What is CartScout?",
			"acceptedAnswer": { "@type": "Answer", "text": "CartScout is an AI chatbot for WooCommerce stores. It chats with shoppers, searches your real catalog, recommends and compares products, adds items to the cart, tracks orders, and hands off to a human when needed. It installs like any WordPress plugin and works with any theme." }
		},
		{
			"@type": "Question",
			"name": "What languages does CartScout support?",
			"acceptedAnswer": { "@type": "Answer", "text": "CartScout auto-detects each shopper's language, or you can lock it to one of 12: English, Spanish, French, German, Italian, Portuguese, Dutch, Russian, Chinese, Japanese, Korean, and Arabic. It searches your catalog in your store's language while replying in the shopper's." }
		},
		{
			"@type": "Question",
			"name": "Is CartScout GDPR-friendly?",
			"acceptedAnswer": { "@type": "Answer", "text": "Yes. Conversation retention is configurable with automatic deletion, IP anonymization is on by default, shopper conversations are never retained by the AI provider or used for training, and uninstalling removes every table and option the plugin created." }
		},
		{
			"@type": "Question",
			"name": "What's the difference between Pro and Unlimited?",
			"acceptedAnswer": { "@type": "Answer", "text": "Both include every feature and the managed AI. Pro covers one WooCommerce store for $299 per year. Unlimited covers as many stores as you run for $499 per year — built for agencies and multi-shop owners." }
		}
	]
}
</script>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'bg-page text-ink font-body antialiased' ); ?>>

<a class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-ink focus:text-white focus:px-4 focus:py-2 focus:rounded-lg" href="#main">Skip to content</a>

<!-- ════════════════════════════════ NAV ════════════════════════════════ -->
<header class="site-nav fixed inset-x-0 top-0 z-50 transition-colors duration-300">
	<nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6" aria-label="Main">
		<a href="#" class="flex items-center gap-2.5">
			<span class="font-display text-2xl font-bold tracking-tight">CartScout</span>
			<span class="hidden rounded-full bg-woo-tint px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-woo-deep sm:inline-block">for WooCommerce</span>
		</a>
		<div class="hidden items-center gap-8 text-sm font-semibold md:flex">
			<a class="transition-colors hover:text-woo" href="#features">Features</a>
			<a class="transition-colors hover:text-woo" href="#compare">Compare</a>
			<a class="transition-colors hover:text-woo" href="#pricing">Pricing</a>
			<a class="transition-colors hover:text-woo" href="#faq">FAQ</a>
			<a class="btn-press js-buy rounded-full bg-woo px-5 py-2.5 text-white shadow-lift hover:bg-woo-deep" href="#pricing">Try it free</a>
		</div>
		<button data-menu-button aria-expanded="false" aria-label="Menu" class="flex h-10 w-10 items-center justify-center rounded-full border border-line md:hidden">
			<svg width="18" height="12" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path d="M1 1h16M1 6h16M1 11h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
		</button>
	</nav>
	<div data-menu-panel class="hidden border-t border-line bg-page px-6 py-4 md:hidden">
		<div class="flex flex-col gap-4 text-base font-semibold">
			<a href="#features">Features</a>
			<a href="#compare">Compare</a>
			<a href="#pricing">Pricing</a>
			<a href="#faq">FAQ</a>
			<a class="js-buy rounded-full bg-woo px-5 py-3 text-center text-white" href="#pricing">Try it free</a>
		</div>
	</div>
</header>

<main id="main">

<!-- ════════════════════════════════ HERO ════════════════════════════════ -->
<section id="hero" class="pt-28 pb-16 lg:pt-36 lg:pb-24">
	<div class="mx-auto grid max-w-6xl items-center gap-14 px-6 lg:grid-cols-[1.05fr_0.95fr]">
		<div>
			<p class="reveal inline-flex items-center gap-2 rounded-full bg-woo px-4 py-2 text-xs font-bold uppercase tracking-wider text-white">
				<svg width="13" height="13" viewBox="0 0 13 13" fill="currentColor" aria-hidden="true"><path d="M6.5 0l1.8 4.2L13 5l-3.3 3 .9 4.5L6.5 10 2.4 12.5 3.3 8 0 5l4.7-.8L6.5 0Z"/></svg>
				Built for WooCommerce
			</p>
			<h1 class="reveal mt-6 font-display text-5xl font-bold leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl" style="--reveal-delay:80ms">
				More WooCommerce <span class="mark-lime">sales.</span><br>Zero extra work.
			</h1>
			<p class="reveal mt-6 max-w-lg text-lg leading-relaxed text-ink-soft sm:text-xl" style="--reveal-delay:160ms">
				CartScout chats with your shoppers, finds the right product, and adds it to their cart — 24/7, in 12 languages.
			</p>
			<div class="reveal mt-9 flex flex-wrap items-center gap-4" style="--reveal-delay:240ms">
				<a href="#pricing" class="btn-press js-buy rounded-full bg-woo px-8 py-4 text-base font-bold text-white shadow-lift hover:bg-woo-deep">
					Try it free for 14 days
				</a>
				<a href="#compare" class="btn-press group rounded-full border-2 border-ink px-8 py-4 text-base font-bold hover:bg-ink hover:text-white">
					Why it's cheaper →
				</a>
			</div>
			<p class="reveal mt-5 text-sm font-medium text-ink-soft" style="--reveal-delay:300ms">
				No credit card &nbsp;·&nbsp; No API keys &nbsp;·&nbsp; 5-minute setup &nbsp;·&nbsp; <span class="font-bold text-ink">No monthly fees</span>
			</p>
		</div>

		<!-- Animated product demo. TODO: optionally replace with a real widget screenshot. -->
		<div class="reveal relative mx-auto w-full max-w-md" style="--reveal-delay:200ms" data-chat-demo>
			<div class="overflow-hidden rounded-3xl border border-line bg-page shadow-card">
				<div class="flex items-center gap-3 bg-woo px-5 py-4 text-white">
					<div class="flex h-10 w-10 items-center justify-center rounded-full bg-lime font-display text-lg font-bold text-ink">S</div>
					<div class="min-w-0">
						<p class="text-sm font-bold leading-tight">Scout</p>
						<p class="flex items-center gap-1.5 text-xs font-medium text-white/90">
							<span class="inline-block h-2 w-2 animate-pulse-dot rounded-full bg-lime"></span> Shopping assistant — online
						</p>
					</div>
				</div>

				<div data-chat-scroll class="flex h-[430px] flex-col gap-3 overflow-y-auto px-4 py-5">
					<div class="chat-step max-w-[85%] self-end rounded-2xl rounded-br-md bg-woo px-4 py-2.5 text-sm text-white">
						I need trail running shoes under $80
					</div>

					<div class="chat-step max-w-[92%] self-start">
						<div class="rounded-2xl rounded-bl-md bg-mist px-4 py-2.5 text-sm">
							Easy — these two are shopper favorites under budget, and the Vela Knit is <strong>20% off today</strong>:
						</div>
						<div class="mt-2 grid grid-cols-2 gap-2">
							<div class="rounded-xl border border-line bg-page p-2.5 shadow-sm">
								<div class="relative mb-2 flex h-20 items-center justify-center rounded-lg bg-woo-tint">
									<span class="absolute left-1.5 top-1.5 rounded bg-green px-1.5 py-0.5 text-[9px] font-bold text-white">SALE</span>
									<svg width="56" height="32" viewBox="0 0 56 32" fill="none" aria-hidden="true"><path d="M3 24c0-3 2-9 5-12l6 4c2 1 4 1 6-1l4-5c8 5 18 7 26 8 3 .5 4 3 3 6H3Z" stroke="#5C3792" stroke-width="2" stroke-linejoin="round"/><path d="M3 24v4h50v-4M20 17l3 3M26 13l3 3" stroke="#5C3792" stroke-width="2" stroke-linecap="round"/></svg>
								</div>
								<p class="truncate text-xs font-bold">Vela Knit Runner</p>
								<p class="text-xs"><span class="font-bold text-green">$64</span> <s class="text-ink-soft/70">$79</s></p>
								<button class="demo-add mt-1.5 w-full rounded-md border border-ink px-2 py-1 text-[10px] font-bold tracking-wide" type="button" tabindex="-1">
									<span class="add">ADD</span><span class="added items-center justify-center gap-1">✓ ADDED</span>
								</button>
							</div>
							<div class="rounded-xl border border-line bg-page p-2.5 shadow-sm">
								<div class="mb-2 flex h-20 items-center justify-center rounded-lg bg-mist">
									<svg width="56" height="32" viewBox="0 0 56 32" fill="none" aria-hidden="true"><path d="M4 25c1-5 3-10 7-13l5 3c2 1 4 0 5-2l3-6c4 1 7 4 11 6s8 3 13 4c4 1 6 4 5 8H4Z" stroke="#0B9E63" stroke-width="2" stroke-linejoin="round"/><path d="M17 14l3 3M23 9l3 3M4 25h49" stroke="#0B9E63" stroke-width="2" stroke-linecap="round"/></svg>
								</div>
								<p class="truncate text-xs font-bold">Terra Grip Trail</p>
								<p class="text-xs font-bold">$72</p>
								<button class="mt-1.5 w-full rounded-md border border-ink px-2 py-1 text-[10px] font-bold tracking-wide" type="button" tabindex="-1">ADD</button>
							</div>
						</div>
					</div>

					<div class="chat-step max-w-[85%] self-end rounded-2xl rounded-br-md bg-woo px-4 py-2.5 text-sm text-white">
						Add the Vela in a US 9
					</div>

					<div class="chat-step max-w-[92%] self-start">
						<div class="rounded-2xl rounded-bl-md bg-mist px-4 py-2.5 text-sm">
							Done! <strong>Vela Knit Runner (US 9)</strong> is in your cart. Ready when you are:
						</div>
						<button class="btn-press mt-2 rounded-xl bg-green px-5 py-2.5 text-xs font-bold tracking-wide text-white" type="button" tabindex="-1">CHECKOUT →</button>
					</div>

					<div class="chat-typing items-center gap-1 self-start rounded-2xl rounded-bl-md bg-mist px-4 py-3">
						<span></span><span></span><span></span>
					</div>
				</div>

				<div class="border-t border-line px-4 py-3">
					<div class="flex items-center justify-between rounded-full border border-line bg-mist px-4 py-2.5">
						<span class="text-sm text-ink-soft">Ask anything about the store…</span>
						<span class="flex h-7 w-7 items-center justify-center rounded-full bg-woo text-white" aria-hidden="true">↑</span>
					</div>
				</div>
			</div>
			<p class="mt-4 text-center text-xs font-semibold tracking-wide text-ink-soft">
				Streams live&ensp;·&ensp;Real catalog data&ensp;·&ensp;Adds to cart itself
			</p>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ STAT BAND ════════════════════════════════ -->
<section class="bg-ink py-14 text-white">
	<div class="mx-auto grid max-w-6xl grid-cols-2 gap-10 px-6 text-center lg:grid-cols-4">
		<div class="reveal">
			<p class="font-display text-5xl font-bold text-lime">70%</p>
			<p class="mt-2 text-sm font-medium text-white/70">of carts are abandoned.<br>CartScout answers first.</p>
		</div>
		<div class="reveal" style="--reveal-delay:80ms">
			<p class="font-display text-5xl font-bold text-lime">24/7</p>
			<p class="mt-2 text-sm font-medium text-white/70">never misses a shopper,<br>never takes a day off</p>
		</div>
		<div class="reveal" style="--reveal-delay:160ms">
			<p class="font-display text-5xl font-bold text-lime">12</p>
			<p class="mt-2 text-sm font-medium text-white/70">languages,<br>auto-detected</p>
		</div>
		<div class="reveal" style="--reveal-delay:240ms">
			<p class="font-display text-5xl font-bold text-lime">$0</p>
			<p class="mt-2 text-sm font-medium text-white/70">monthly fees.<br>One flat yearly price.</p>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ MARQUEE ════════════════════════════════ -->
<div class="marquee overflow-hidden bg-woo py-3.5 text-white" aria-hidden="true">
	<div class="marquee-track flex w-max items-center gap-8 whitespace-nowrap pr-8">
		<?php for ( $i = 0; $i < 2; $i++ ) : ?>
		<span class="font-display text-lg font-semibold">Finds products</span><span class="text-lime">✦</span>
		<span class="font-display text-lg font-semibold">Adds to cart</span><span class="text-lime">✦</span>
		<span class="font-display text-lg font-semibold">Answers shipping questions</span><span class="text-lime">✦</span>
		<span class="font-display text-lg font-semibold">Tracks orders</span><span class="text-lime">✦</span>
		<span class="font-display text-lg font-semibold">Compares specs</span><span class="text-lime">✦</span>
		<span class="font-display text-lg font-semibold">Built for WooCommerce</span><span class="text-lime">✦</span>
		<?php endfor; ?>
	</div>
</div>

<!-- ════════════════════════════════ FEATURES ════════════════════════════════ -->
<section id="features" class="bg-mist py-20 lg:py-28">
	<div class="mx-auto max-w-6xl px-6">
		<div class="mx-auto max-w-2xl text-center">
			<h2 class="reveal font-display text-4xl font-bold tracking-tight sm:text-5xl">
				It does the selling.<br><span class="mark-lime">You keep the margin.</span>
			</h2>
		</div>

		<div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
			<?php
			$features = array(
				array( 'cart', 'Sells straight from the chat', 'Finds products, adds them to cart, one tap to checkout.' ),
				array( 'catalog', 'Knows your entire catalog', 'Live WooCommerce prices, stock, and variations. Never makes things up.' ),
				array( 'bolt', 'Answers before they bounce', 'Shipping, returns, sizing — answered instantly, around the clock.' ),
				array( 'globe', 'Sells in 12 languages', "Auto-detects every shopper's language. You set up nothing." ),
				array( 'chart', 'Shows you the money', 'Chats → carts → checkouts, tracked in one simple dashboard.' ),
				array( 'zap', 'Live in 5 minutes', 'No code. No API keys. Works with any WordPress theme.' ),
			);
			$icons = array(
				'cart'    => '<path d="M3 5h3l2.4 12.2a2 2 0 0 0 2 1.8h8.9a2 2 0 0 0 2-1.6L23 9H7.6" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="11" cy="24" r="1.8"/><circle cx="19" cy="24" r="1.8"/>',
				'catalog' => '<path d="M5 4h16a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z" stroke-width="2.2" stroke-linejoin="round"/><path d="M8 9h10M8 13h10M8 17h6" stroke-width="2.2" stroke-linecap="round"/>',
				'bolt'    => '<path d="M14 2 4 16h8l-2 10L22 11h-8l2-9Z" stroke-width="2.2" stroke-linejoin="round"/>',
				'globe'   => '<circle cx="13" cy="13" r="11" stroke-width="2.2"/><path d="M2 13h22M13 2c3 3.5 4.5 7 4.5 11S16 21.5 13 24c-3-2.5-4.5-7-4.5-11S10 5.5 13 2Z" stroke-width="2.2"/>',
				'chart'   => '<path d="M3 23h22" stroke-width="2.2" stroke-linecap="round"/><path d="M6 23v-7m6 7V9m6 14v-5m6 5V5" stroke-width="2.2" stroke-linecap="round"/>',
				'zap'     => '<circle cx="13" cy="13" r="11" stroke-width="2.2"/><path d="M13 7v6l4 3" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>',
			);
			foreach ( $features as $i => $feature ) :
			?>
			<article class="reveal rounded-2xl border border-line bg-page p-7 shadow-sm transition-shadow hover:shadow-lift" style="--reveal-delay:<?php echo ( $i % 3 ) * 80; ?>ms">
				<span class="flex h-11 w-11 items-center justify-center rounded-xl bg-woo-tint text-woo-deep">
					<svg width="22" height="22" viewBox="0 0 26 26" fill="none" stroke="currentColor" aria-hidden="true"><?php echo $icons[ $feature[0] ]; // phpcs:ignore ?></svg>
				</span>
				<h3 class="mt-4 font-display text-xl font-bold"><?php echo esc_html( $feature[1] ); ?></h3>
				<p class="mt-2 leading-relaxed text-ink-soft"><?php echo esc_html( $feature[2] ); ?></p>
			</article>
			<?php endforeach; ?>
		</div>

		<p class="reveal mt-9 text-center text-sm text-ink-soft">
			Also: order tracking · human handoff · coupon surfacing · proactive greeting · your name, logo &amp; colors
		</p>
		<div class="reveal mt-8 text-center">
			<a href="#pricing" class="btn-press js-buy inline-block rounded-full bg-woo px-8 py-4 text-sm font-bold text-white shadow-lift hover:bg-woo-deep">Put it on your store — free for 14 days</a>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ HOW IT WORKS ════════════════════════════════ -->
<section id="how" class="py-20 lg:py-28">
	<div class="mx-auto max-w-6xl px-6">
		<h2 class="reveal text-center font-display text-4xl font-bold tracking-tight sm:text-5xl">
			Live before your <span class="mark-lime">coffee cools.</span>
		</h2>
		<div class="mt-14 grid gap-8 md:grid-cols-3">
			<div class="reveal rounded-2xl bg-mist p-7">
				<p class="font-display text-5xl font-bold text-woo">1</p>
				<h3 class="mt-3 font-display text-xl font-bold">Install the plugin</h3>
				<p class="mt-2 text-ink-soft">Like any WordPress plugin. The AI comes with it.</p>
			</div>
			<div class="reveal rounded-2xl bg-mist p-7" style="--reveal-delay:100ms">
				<p class="font-display text-5xl font-bold text-woo">2</p>
				<h3 class="mt-3 font-display text-xl font-bold">Make it yours</h3>
				<p class="mt-2 text-ink-soft">Your logo, colors, and FAQs. Live preview included.</p>
			</div>
			<div class="reveal rounded-2xl bg-mist p-7" style="--reveal-delay:200ms">
				<p class="font-display text-5xl font-bold text-woo">3</p>
				<h3 class="mt-3 font-display text-xl font-bold">Watch orders grow</h3>
				<p class="mt-2 text-ink-soft">It sells while you sleep. The dashboard shows the receipts.</p>
			</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ SHOWCASE ════════════════════════════════ -->
<section id="showcase" class="pb-20 lg:pb-28">
	<div class="mx-auto max-w-6xl px-6">
		<!-- TODO: replace this placeholder with the real showcase video (keep the rounded frame). -->
		<div class="reveal relative mx-auto aspect-video max-w-4xl overflow-hidden rounded-3xl bg-ink shadow-card">
			<div class="absolute inset-0 flex flex-col items-center justify-center gap-4 text-white">
				<button class="btn-press flex h-20 w-20 items-center justify-center rounded-full bg-white/10 backdrop-blur transition hover:bg-woo" type="button" aria-label="Play showcase video">
					<svg class="ml-1" width="26" height="30" viewBox="0 0 26 30" fill="currentColor" aria-hidden="true"><path d="M24.5 12.4c2 1.16 2 4.04 0 5.2L4.25 29.29c-2 1.15-4.5-.29-4.5-2.6V3.31C-.25 1-2.25-.44 4.25.71L24.5 12.4Z" transform="translate(.25)"/></svg>
				</button>
				<p class="font-display text-xl font-semibold text-white/80">Watch it sell — video coming soon</p>
				<p class="text-sm text-white/50">The chat in the hero is the real widget. Go play with it.</p>
			</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ COMPARISON ════════════════════════════════ -->
<section id="compare" class="bg-ink py-20 text-white lg:py-28">
	<div class="mx-auto max-w-6xl px-6">
		<div class="mx-auto max-w-2xl text-center">
			<h2 class="reveal font-display text-4xl font-bold tracking-tight sm:text-5xl">
				Stop <span class="mark-lime text-ink">renting</span> your chatbot.
			</h2>
			<p class="reveal mt-5 text-lg text-white/70" style="--reveal-delay:100ms">
				Most AI chatbots bill monthly, per seat, or per conversation — and aren't built for WooCommerce. CartScout is one flat price a year, everything included.
			</p>
		</div>

		<!-- COMPARISON_TABLE: rows updated from competitor research -->
		<div class="reveal mt-12 overflow-x-auto rounded-2xl border border-white/15" style="--reveal-delay:160ms">
			<table class="w-full min-w-[640px] text-left text-sm">
				<thead>
					<tr class="border-b border-white/15 text-xs uppercase tracking-wider text-white/60">
						<th class="px-5 py-4 font-semibold">Chatbot</th>
						<th class="px-5 py-4 font-semibold">Pricing model</th>
						<th class="px-5 py-4 font-semibold">Cost per year*</th>
						<th class="px-5 py-4 font-semibold">AI usage fees</th>
						<th class="px-5 py-4 font-semibold">WooCommerce-native</th>
					</tr>
				</thead>
				<tbody class="divide-y divide-white/10">
					<tr class="bg-lime text-ink">
						<td class="px-5 py-4 font-display text-base font-bold">CartScout</td>
						<td class="px-5 py-4 font-bold">Flat yearly</td>
						<td class="px-5 py-4 font-display text-base font-bold">$299</td>
						<td class="px-5 py-4 font-bold">None</td>
						<td class="px-5 py-4 font-bold">✓ Cart, checkout, orders</td>
					</tr>
					<tr>
						<td class="px-5 py-4 font-semibold">Intercom + Fin AI</td>
						<td class="px-5 py-4 text-white/70">Per seat + per resolution</td>
						<td class="px-5 py-4 font-semibold">~$2,130</td>
						<td class="px-5 py-4 text-white/70">$0.99 every AI resolution</td>
						<td class="px-5 py-4 text-white/70">✗ Generic support</td>
					</tr>
					<tr>
						<td class="px-5 py-4 font-semibold">Tidio + Lyro AI</td>
						<td class="px-5 py-4 text-white/70">Monthly + AI add-on</td>
						<td class="px-5 py-4 font-semibold">~$1,900</td>
						<td class="px-5 py-4 text-white/70">Pay per AI conversation</td>
						<td class="px-5 py-4 text-white/70">✗ Generic chat</td>
					</tr>
					<tr>
						<td class="px-5 py-4 font-semibold">Chatbase</td>
						<td class="px-5 py-4 text-white/70">Monthly + credits</td>
						<td class="px-5 py-4 font-semibold">$1,440</td>
						<td class="px-5 py-4 text-white/70">Credit overages</td>
						<td class="px-5 py-4 text-white/70">✗ Generic</td>
					</tr>
					<tr>
						<td class="px-5 py-4 font-semibold">Crisp</td>
						<td class="px-5 py-4 text-white/70">Monthly + AI credits</td>
						<td class="px-5 py-4 font-semibold">$1,140</td>
						<td class="px-5 py-4 text-white/70">AI credits on top</td>
						<td class="px-5 py-4 text-white/70">✗ Generic chat</td>
					</tr>
					<tr>
						<td class="px-5 py-4 font-semibold">ChatBot.com</td>
						<td class="px-5 py-4 text-white/70">Per seat + quota</td>
						<td class="px-5 py-4 font-semibold">$948</td>
						<td class="px-5 py-4 text-white/70">$0.99 per extra resolution</td>
						<td class="px-5 py-4 text-white/70">✗ Generic</td>
					</tr>
				</tbody>
			</table>
		</div>
		<p class="reveal mt-8 text-center font-display text-3xl font-bold sm:text-4xl" style="--reveal-delay:200ms">
			That's <span class="mark-lime text-ink">$649 to $1,800+</span> back in your pocket. Every year.
		</p>
		<p class="reveal mt-4 text-center text-xs text-white/50" style="--reveal-delay:240ms">
			*Realistic plan for one store with AI answers enabled (~200–300 conversations/month), billed annually. Competitor prices from their public pricing pages, June 2026.
		</p>
	</div>
</section>

<!-- ════════════════════════════════ PRICING ════════════════════════════════ -->
<section id="pricing" class="py-20 lg:py-28">
	<div class="mx-auto max-w-5xl px-6">
		<div class="mx-auto max-w-2xl text-center">
			<h2 class="reveal font-display text-4xl font-bold tracking-tight sm:text-5xl">
				One price. <span class="mark-lime">Everything included.</span>
			</h2>
			<p class="reveal mt-4 text-lg text-ink-soft" style="--reveal-delay:100ms">Every feature, the AI, all updates. Billed once a year.</p>
		</div>

		<div class="mt-14 grid items-stretch gap-6 md:grid-cols-2">
			<!-- Pro (featured) -->
			<article class="reveal relative flex flex-col rounded-3xl bg-woo p-8 text-white shadow-card">
				<p class="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-lime px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-ink">Most popular</p>
				<h3 class="font-display text-xl font-bold">Pro</h3>
				<p class="mt-1 text-sm text-white/70">For one WooCommerce store</p>
				<p class="mt-6 font-display text-6xl font-bold tracking-tight">$299<span class="text-xl font-medium text-white/70"> / year</span></p>
				<ul class="mt-8 flex-1 space-y-3 text-sm font-medium">
					<li class="flex gap-2.5"><span class="text-lime">✓</span> Every feature, AI included — no usage fees</li>
					<li class="flex gap-2.5"><span class="text-lime">✓</span> 1 WooCommerce store</li>
					<li class="flex gap-2.5"><span class="text-lime">✓</span> All updates &amp; priority support</li>
					<li class="flex gap-2.5"><span class="text-lime">✓</span> 30-day money-back guarantee</li>
				</ul>
				<a href="#" class="btn-press js-buy mt-8 rounded-full bg-lime px-6 py-4 text-center text-sm font-bold text-ink hover:bg-white">Start free trial</a><?php /* TODO: Freemius checkout link */ ?>
				<p class="mt-3 text-center text-xs text-white/70">14 days free, no card — then $299/year</p>
			</article>

			<!-- Unlimited -->
			<article class="reveal flex flex-col rounded-3xl border border-line bg-page p-8 shadow-sm" style="--reveal-delay:120ms">
				<h3 class="font-display text-xl font-bold">Unlimited</h3>
				<p class="mt-1 text-sm text-ink-soft">For agencies &amp; multi-store owners</p>
				<p class="mt-6 font-display text-6xl font-bold tracking-tight">$499<span class="text-xl font-medium text-ink-soft"> / year</span></p>
				<ul class="mt-8 flex-1 space-y-3 text-sm font-medium">
					<li class="flex gap-2.5"><span class="text-green">✓</span> Everything in Pro</li>
					<li class="flex gap-2.5"><span class="text-green">✓</span> Unlimited WooCommerce stores</li>
					<li class="flex gap-2.5"><span class="text-green">✓</span> All updates &amp; priority support</li>
					<li class="flex gap-2.5"><span class="text-green">✓</span> 30-day money-back guarantee</li>
				</ul>
				<a href="#" class="btn-press js-buy mt-8 rounded-full border-2 border-ink px-6 py-4 text-center text-sm font-bold hover:bg-ink hover:text-white">Start free trial</a><?php /* TODO: Freemius checkout link */ ?>
				<p class="mt-3 text-center text-xs text-ink-soft">14 days free, no card — then $499/year</p>
			</article>
		</div>

		<p class="reveal mt-9 text-center text-sm text-ink-soft">
			AI included on every plan — thousands of shopper conversations a month. <a class="font-semibold underline decoration-woo decoration-2 underline-offset-2" href="#faq">Details in the FAQ</a>
		</p>
	</div>
</section>

<!-- ════════════════════════════════ FAQ ════════════════════════════════ -->
<section id="faq" class="bg-mist py-20 lg:py-28">
	<div class="mx-auto max-w-3xl px-6">
		<h2 class="reveal text-center font-display text-4xl font-bold tracking-tight sm:text-5xl">
			Fair questions.
		</h2>

		<div class="mt-12 space-y-3">
			<?php
			$faqs = array(
				array( 'Why is there no monthly fee?', 'We charge one flat yearly price — no monthly invoices, no per-seat pricing, no per-conversation fees. The AI is included. Every plan covers thousands of shopper conversations a month; a typical store uses a few hundred.' ),
				array( 'Do I need an OpenAI account or API key?', 'No. CartScout ships with fully managed AI. Install the plugin, start your free trial, and the chatbot works immediately — we host the AI infrastructure, route every request through our servers, and keep the models current for you.' ),
				array( 'Will it make things up about my products?', "No. CartScout only states facts returned by your store — live prices, stock, attributes, shipping zones, coupon codes, and your own pages. If it can't find something, it says so and offers the closest real alternative instead of inventing specs or shipping times." ),
				array( 'Can it actually add products to the cart?', "Yes. Shoppers search, compare, pick a variation like size or color, and add to cart without leaving the chat. When they're ready, the bot shows a one-tap checkout button. Removing items always asks for confirmation first." ),
				array( 'What happens if CartScout shuts down?', 'Your store keeps its data — the plugin, settings, transcripts, and insights all live on your server. And if we ever sunset the managed AI service, we commit to shipping a bring-your-own-key mode first, so your chatbot keeps running on your own OpenAI account.' ),
				array( 'Will it slow down my store?', 'No. Your storefront pages load only a ~6KB stub; the full widget loads when a shopper interacts with it. Styles are fully scoped, so it never collides with your theme.' ),
				array( 'What is CartScout?', 'CartScout is an AI chatbot for WooCommerce stores. It chats with shoppers, searches your real catalog, recommends and compares products, adds items to the cart, tracks orders, and hands off to a human when needed. It installs like any WordPress plugin and works with any theme.' ),
				array( 'What languages does CartScout support?', "CartScout auto-detects each shopper's language, or you can lock it to one of 12: English, Spanish, French, German, Italian, Portuguese, Dutch, Russian, Chinese, Japanese, Korean, and Arabic. It searches your catalog in your store's language while replying in the shopper's." ),
				array( 'Is CartScout GDPR-friendly?', 'Yes. Conversation retention is configurable with automatic deletion, IP anonymization is on by default, shopper conversations are never retained by the AI provider or used for training, and uninstalling removes every table and option the plugin created.' ),
				array( "What's the difference between Pro and Unlimited?", 'Both include every feature and the managed AI. Pro covers one WooCommerce store for $299 per year. Unlimited covers as many stores as you run for $499 per year — built for agencies and multi-shop owners.' ),
			);
			foreach ( $faqs as $i => $faq ) :
			?>
			<details class="faq-item reveal rounded-2xl border border-line bg-page px-6 py-5" <?php echo 0 === $i ? 'open' : ''; ?> style="--reveal-delay:<?php echo $i * 40; ?>ms">
				<summary class="flex items-center justify-between gap-4">
					<h3 class="font-display text-lg font-bold sm:text-xl"><?php echo esc_html( $faq[0] ); ?></h3>
					<span class="faq-icon flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-line text-lg" aria-hidden="true">+</span>
				</summary>
				<div class="faq-body">
					<div><p class="pt-4 leading-relaxed text-ink-soft"><?php echo esc_html( $faq[1] ); ?></p></div>
				</div>
			</details>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ FINAL CTA ════════════════════════════════ -->
<section class="py-20 lg:py-32">
	<div class="mx-auto max-w-4xl px-6 text-center">
		<h2 class="reveal font-display text-5xl font-bold leading-[1.04] tracking-tight sm:text-6xl">
			Your store is open 24/7.<br><span class="mark-lime">Your salesperson isn't.</span>
		</h2>
		<div class="reveal mt-10" style="--reveal-delay:160ms">
			<a href="#pricing" class="btn-press js-buy inline-block rounded-full bg-woo px-10 py-5 text-lg font-bold text-white shadow-lift hover:bg-woo-deep">Try CartScout free for 14 days</a>
			<p class="mt-4 text-sm font-medium text-ink-soft">5-minute setup · No credit card · No monthly fees</p>
		</div>
	</div>
</section>

</main>

<!-- ════════════════════════════════ FOOTER ════════════════════════════════ -->
<footer class="bg-ink py-12 text-white">
	<div class="mx-auto max-w-6xl px-6">
		<div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
			<a href="#" class="flex items-center gap-2.5">
				<span class="font-display text-xl font-bold">CartScout</span>
				<span class="rounded-full bg-white/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-lime">for WooCommerce</span>
			</a>
			<nav class="flex flex-wrap justify-center gap-6 text-sm font-medium text-white/80" aria-label="Footer">
				<a class="hover:text-lime" href="#features">Features</a>
				<a class="hover:text-lime" href="#compare">Compare</a>
				<a class="hover:text-lime" href="#pricing">Pricing</a>
				<a class="hover:text-lime" href="#faq">FAQ</a>
				<a class="hover:text-lime" href="mailto:support@cartscout.ai">support@cartscout.ai</a>
				<?php /* TODO: add Privacy Policy + Terms pages and link them here */ ?>
			</nav>
		</div>
		<p class="mt-8 border-t border-white/10 pt-8 text-center text-xs leading-relaxed text-white/50">
			© <?php echo esc_html( gmdate( 'Y' ) ); ?> CartScout. The AI chatbot for WooCommerce stores.<br>
			CartScout is an independent product and is not affiliated with or endorsed by Automattic. “WooCommerce” and “WordPress” are trademarks of their respective owners, used for descriptive purposes only.
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
