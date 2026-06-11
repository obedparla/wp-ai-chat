<?php
/**
 * CartScout landing page.
 *
 * Asset swap points (search for "TODO:"):
 *   - Showcase video  → section#showcase
 *   - Real widget screenshot (optional, replaces the animated demo) → section#hero
 *   - Checkout links  → every .js-buy anchor
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>CartScout — AI Chatbot for WooCommerce That Turns Browsers Into Buyers</title>
<meta name="description" content="CartScout is the AI shopping assistant for WooCommerce. It finds products, answers questions, and fills carts — 24/7, in 12 languages. No monthly fees. Free 14-day trial.">
<link rel="canonical" href="<?php echo esc_url( home_url( '/' ) ); ?>">
<meta name="theme-color" content="#FBF6EC">

<meta property="og:type" content="website">
<meta property="og:site_name" content="CartScout">
<meta property="og:title" content="CartScout — AI Chatbot for WooCommerce That Turns Browsers Into Buyers">
<meta property="og:description" content="The AI shopping assistant that knows your catalog, answers every shopper instantly, and walks them to checkout. Pay yearly or once — never monthly.">
<meta property="og:url" content="<?php echo esc_url( home_url( '/' ) ); ?>">
<?php /* TODO: add og:image — 1200x630 → <meta property="og:image" content="...assets/img/og.png"> */ ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="CartScout — AI Chatbot for WooCommerce">
<meta name="twitter:description" content="Finds products, answers questions, fills carts. 24/7, 12 languages, no monthly fees.">

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
		{ "@type": "Offer", "name": "Starter — single site, yearly", "price": "99", "priceCurrency": "USD" },
		{ "@type": "Offer", "name": "Lifetime — single site, one-time", "price": "299", "priceCurrency": "USD" },
		{ "@type": "Offer", "name": "Unlimited — unlimited sites, one-time", "price": "499", "priceCurrency": "USD" }
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
			"name": "What is CartScout?",
			"acceptedAnswer": { "@type": "Answer", "text": "CartScout is an AI chatbot for WooCommerce stores. It chats with shoppers, searches your real catalog, recommends and compares products, adds items to the cart, tracks orders, and hands off to a human when needed. It installs like any WordPress plugin and works with any theme." }
		},
		{
			"@type": "Question",
			"name": "Do I need an OpenAI account or API key?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. CartScout ships with fully managed AI. Install the plugin, start your free trial, and the chatbot works immediately — we host the AI infrastructure, route every request through our servers, and keep the models current for you." }
		},
		{
			"@type": "Question",
			"name": "How can there be no monthly fee? Who pays for the AI?",
			"acceptedAnswer": { "@type": "Answer", "text": "We do — and we engineered it to be sustainable. CartScout puts every conversation on a token diet, caches its prompts, and routes each request to the most efficient model. Our AI cost per conversation is pennies, so a yearly or one-time license covers it. Every plan includes thousands of shopper conversations a month; a typical store uses a few hundred." }
		},
		{
			"@type": "Question",
			"name": "What happens to my Lifetime license if CartScout shuts down?",
			"acceptedAnswer": { "@type": "Answer", "text": "Your store keeps its data — the plugin, settings, transcripts, and insights all live on your server. And if we ever sunset the managed AI service, we commit to shipping a bring-your-own-key mode first, so your chatbot keeps running on your own OpenAI account." }
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
			"name": "Will it slow down my store?",
			"acceptedAnswer": { "@type": "Answer", "text": "No. Your storefront pages load only a ~6KB stub; the full widget loads when a shopper interacts with it. Styles are fully scoped, so it never collides with your theme." }
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
			"name": "How long does setup take?",
			"acceptedAnswer": { "@type": "Answer", "text": "About five minutes. Install the plugin, start the free trial, set your name, logo, and brand color, then paste your FAQs. No code, no API keys, no embed snippets." }
		},
		{
			"@type": "Question",
			"name": "What's the difference between Starter, Lifetime, and Unlimited?",
			"acceptedAnswer": { "@type": "Answer", "text": "Starter is $99 per year for one site — every feature, AI included, billed once a year. Lifetime is a single $299 payment for one site, forever, including the managed AI and all future updates. Unlimited is $499 once for unlimited sites — built for agencies and portfolio owners." }
		}
	]
}
</script>
<?php wp_head(); ?>
</head>
<body <?php body_class( 'grain bg-paper text-ink font-body antialiased' ); ?>>

<a class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[100] focus:bg-ink focus:text-cream focus:px-4 focus:py-2 focus:rounded-lg" href="#main">Skip to content</a>

<!-- ════════════════════════════════ NAV ════════════════════════════════ -->
<header class="site-nav fixed inset-x-0 top-0 z-50 transition-colors duration-300">
	<nav class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6" aria-label="Main">
		<a href="#" class="font-display text-2xl font-semibold tracking-tight">
			Cart<span class="italic text-accent">Scout</span>
		</a>
		<div class="hidden items-center gap-8 text-sm font-medium md:flex">
			<a class="transition-colors hover:text-accent" href="#features">Features</a>
			<a class="transition-colors hover:text-accent" href="#how">How it works</a>
			<a class="transition-colors hover:text-accent" href="#pricing">Pricing</a>
			<a class="transition-colors hover:text-accent" href="#faq">FAQ</a>
			<a class="btn-press js-buy rounded-full bg-ink px-5 py-2.5 text-cream shadow-lift hover:bg-accent" href="#pricing">Start free trial</a>
		</div>
		<button data-menu-button aria-expanded="false" aria-label="Menu" class="flex h-10 w-10 items-center justify-center rounded-full border border-line md:hidden">
			<svg width="18" height="12" viewBox="0 0 18 12" fill="none" aria-hidden="true"><path d="M1 1h16M1 6h16M1 11h16" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
		</button>
	</nav>
	<div data-menu-panel class="hidden border-t border-line bg-paper px-6 py-4 md:hidden">
		<div class="flex flex-col gap-4 text-base font-medium">
			<a href="#features">Features</a>
			<a href="#how">How it works</a>
			<a href="#pricing">Pricing</a>
			<a href="#faq">FAQ</a>
			<a class="js-buy rounded-full bg-ink px-5 py-3 text-center text-cream" href="#pricing">Start free trial</a>
		</div>
	</div>
</header>

<main id="main">

<!-- ════════════════════════════════ HERO ════════════════════════════════ -->
<section id="hero" class="relative overflow-hidden pt-32 pb-20 lg:pt-40 lg:pb-28">
	<div class="relative mx-auto grid max-w-6xl items-center gap-16 px-6 lg:grid-cols-[1.05fr_0.95fr]">
		<div>
			<p class="reveal mb-5 text-xs font-bold uppercase tracking-[0.22em] text-accent-deep">AI shopping assistant for WooCommerce</p>
			<h1 class="reveal font-display text-5xl font-semibold leading-[1.02] tracking-tight sm:text-6xl lg:text-7xl" style="--reveal-delay:80ms">
				Stop losing sales<br>to <em class="italic text-accent">silence.</em>
			</h1>
			<p class="reveal mt-6 max-w-xl text-lg leading-relaxed text-ink-soft sm:text-xl" style="--reveal-delay:160ms">
				Most shoppers leave because nobody answered them. CartScout — the AI chatbot that knows your entire WooCommerce catalog — answers every single one, recommends the right product, and walks them to checkout. <strong class="font-semibold text-ink">24/7, in 12 languages.</strong>
			</p>
			<div class="reveal mt-9 flex flex-wrap items-center gap-4" style="--reveal-delay:240ms">
				<a href="#pricing" class="btn-press js-buy rounded-full bg-accent-deep px-8 py-4 text-base font-semibold text-cream shadow-lift hover:bg-accent-ink">
					Start free 14-day trial
				</a>
				<a href="#showcase" class="btn-press group rounded-full border border-line bg-cream px-8 py-4 text-base font-semibold hover:border-ink">
					See it in action <span class="inline-block transition-transform group-hover:translate-y-0.5">↓</span>
				</a>
			</div>
			<p class="reveal mt-5 text-sm text-ink-soft" style="--reveal-delay:300ms">
				No credit card &nbsp;·&nbsp; No API key &nbsp;·&nbsp; 5-minute setup &nbsp;·&nbsp; <strong class="text-ink">No monthly fees, ever</strong>
			</p>
		</div>

		<!-- Animated product demo. TODO: optionally replace with a real widget screenshot. -->
		<div class="reveal relative mx-auto w-full max-w-md" style="--reveal-delay:200ms" data-chat-demo>
			<div class="overflow-hidden rounded-3xl border border-line bg-cream shadow-card">
				<div class="flex items-center gap-3 bg-pine px-5 py-4 text-cream">
					<div class="flex h-10 w-10 items-center justify-center rounded-full bg-butter font-display text-lg font-bold text-ink">S</div>
					<div class="min-w-0">
						<p class="text-sm font-bold leading-tight">Scout</p>
						<p class="flex items-center gap-1.5 text-xs text-cream/70">
							<span class="inline-block h-2 w-2 animate-pulse-dot rounded-full bg-green-400"></span> Shopping assistant — online
						</p>
					</div>
				</div>

				<div data-chat-scroll class="flex h-[430px] flex-col gap-3 overflow-y-auto px-4 py-5">
					<div class="chat-step max-w-[85%] self-end rounded-2xl rounded-br-md bg-pine px-4 py-2.5 text-sm text-cream">
						I need trail running shoes under $80
					</div>

					<div class="chat-step max-w-[92%] self-start">
						<div class="rounded-2xl rounded-bl-md bg-parchment px-4 py-2.5 text-sm">
							Easy — these two are shopper favorites under budget, and the Vela Knit is <strong>20% off today</strong>:
						</div>
						<div class="mt-2 grid grid-cols-2 gap-2">
							<div class="rounded-xl border border-line bg-cream p-2.5 shadow-sm">
								<div class="relative mb-2 flex h-20 items-center justify-center rounded-lg bg-gradient-to-br from-pine-mist via-butter/40 to-parchment">
									<span class="absolute left-1.5 top-1.5 rounded bg-accent-deep px-1.5 py-0.5 text-[9px] font-bold text-cream">SALE</span>
									<svg width="56" height="32" viewBox="0 0 56 32" fill="none" aria-hidden="true"><path d="M3 24c0-3 2-9 5-12l6 4c2 1 4 1 6-1l4-5c8 5 18 7 26 8 3 .5 4 3 3 6H3Z" stroke="#1C4435" stroke-width="2" stroke-linejoin="round"/><path d="M3 24v4h50v-4M20 17l3 3M26 13l3 3" stroke="#1C4435" stroke-width="2" stroke-linecap="round"/></svg>
								</div>
								<p class="truncate text-xs font-bold">Vela Knit Runner</p>
								<p class="text-xs"><span class="font-semibold text-accent-deep">$64</span> <s class="text-ink-soft/70">$79</s></p>
								<button class="demo-add mt-1.5 w-full rounded-md border border-ink px-2 py-1 text-[10px] font-bold tracking-wide" type="button" tabindex="-1">
									<span class="add">ADD</span><span class="added items-center justify-center gap-1">✓ ADDED</span>
								</button>
							</div>
							<div class="rounded-xl border border-line bg-cream p-2.5 shadow-sm">
								<div class="mb-2 flex h-20 items-center justify-center rounded-lg bg-gradient-to-br from-parchment via-pine-mist to-butter/40">
									<svg width="56" height="32" viewBox="0 0 56 32" fill="none" aria-hidden="true"><path d="M4 25c1-5 3-10 7-13l5 3c2 1 4 0 5-2l3-6c4 1 7 4 11 6s8 3 13 4c4 1 6 4 5 8H4Z" stroke="#A33108" stroke-width="2" stroke-linejoin="round"/><path d="M17 14l3 3M23 9l3 3M4 25h49" stroke="#A33108" stroke-width="2" stroke-linecap="round"/></svg>
								</div>
								<p class="truncate text-xs font-bold">Terra Grip Trail</p>
								<p class="text-xs font-semibold">$72</p>
								<button class="mt-1.5 w-full rounded-md border border-ink px-2 py-1 text-[10px] font-bold tracking-wide" type="button" tabindex="-1">ADD</button>
							</div>
						</div>
					</div>

					<div class="chat-step max-w-[85%] self-end rounded-2xl rounded-br-md bg-pine px-4 py-2.5 text-sm text-cream">
						Add the Vela in a US 9
					</div>

					<div class="chat-step max-w-[92%] self-start">
						<div class="rounded-2xl rounded-bl-md bg-parchment px-4 py-2.5 text-sm">
							Done! <strong>Vela Knit Runner (US 9)</strong> is in your cart. Ready when you are:
						</div>
						<button class="btn-press mt-2 rounded-xl bg-pine px-5 py-2.5 text-xs font-bold tracking-wide text-cream" type="button" tabindex="-1">CHECKOUT →</button>
					</div>

					<div class="chat-typing items-center gap-1 self-start rounded-2xl rounded-bl-md bg-parchment px-4 py-3">
						<span></span><span></span><span></span>
					</div>
				</div>

				<div class="border-t border-line px-4 py-3">
					<div class="flex items-center justify-between rounded-full border border-line bg-paper px-4 py-2.5">
						<span class="text-sm text-ink-soft">Ask anything about the store…</span>
						<span class="flex h-7 w-7 items-center justify-center rounded-full bg-accent-deep text-cream" aria-hidden="true">↑</span>
					</div>
				</div>
			</div>
			<p class="mt-4 text-center text-xs font-semibold tracking-wide text-ink-soft">
				Streams live&ensp;·&ensp;Real catalog data&ensp;·&ensp;Adds to cart itself
			</p>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ MARQUEE ════════════════════════════════ -->
<div class="marquee overflow-hidden border-y border-ink/20 bg-ink py-4 text-cream" aria-hidden="true">
	<div class="marquee-track flex w-max items-center gap-10 whitespace-nowrap pr-10">
		<?php for ( $i = 0; $i < 2; $i++ ) : ?>
		<span class="font-display text-xl italic">Finds products</span><span class="text-butter">✺</span>
		<span class="font-display text-xl italic">Answers questions</span><span class="text-butter">✺</span>
		<span class="font-display text-xl italic">Fills carts</span><span class="text-butter">✺</span>
		<span class="font-display text-xl italic">Compares specs</span><span class="text-butter">✺</span>
		<span class="font-display text-xl italic">Tracks orders</span><span class="text-butter">✺</span>
		<span class="font-display text-xl italic">Speaks 12 languages</span><span class="text-butter">✺</span>
		<span class="font-display text-xl italic">Never sleeps</span><span class="text-butter">✺</span>
		<?php endfor; ?>
	</div>
</div>

<!-- ════════════════════════════════ SHOWCASE ════════════════════════════════ -->
<section id="showcase" class="py-24 lg:py-32">
	<div class="mx-auto max-w-6xl px-6">
		<div class="mx-auto max-w-2xl text-center">
			<p class="reveal text-xs font-bold uppercase tracking-[0.22em] text-accent-deep">Watch it work</p>
			<h2 class="reveal mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl" style="--reveal-delay:80ms">
				Ninety seconds from <em class="italic">“just browsing”</em> to checkout.
			</h2>
		</div>
		<!-- TODO: replace this placeholder with the real showcase video (keep the rounded frame). -->
		<div class="reveal mt-14" style="--reveal-delay:160ms">
			<div class="relative mx-auto aspect-video max-w-4xl overflow-hidden rounded-3xl border border-line bg-gradient-to-br from-pine-deep via-pine to-pine-deep shadow-card">
				<div class="absolute inset-0 flex flex-col items-center justify-center gap-5 text-cream">
					<button class="btn-press group flex h-20 w-20 items-center justify-center rounded-full bg-cream/10 backdrop-blur transition hover:bg-accent" type="button" aria-label="Play showcase video">
						<svg class="ml-1" width="26" height="30" viewBox="0 0 26 30" fill="currentColor" aria-hidden="true"><path d="M24.5 12.4c2 1.16 2 4.04 0 5.2L4.25 29.29c-2 1.15-4.5-.29-4.5-2.6V3.31C-.25 1-2.25-.44 4.25.71L24.5 12.4Z" transform="translate(.25)"/></svg>
					</button>
					<p class="font-display text-xl italic text-cream/80">Full walkthrough premiering soon</p>
					<p class="text-sm text-cream/50">The chat above is the real widget — go play with it.</p>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ FEATURES ════════════════════════════════ -->
<section id="features" class="border-t border-line bg-parchment/60 py-24 lg:py-32">
	<div class="mx-auto max-w-6xl px-6">
		<div class="max-w-3xl">
			<p class="reveal text-xs font-bold uppercase tracking-[0.22em] text-accent-deep">Features</p>
			<h2 class="reveal mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl" style="--reveal-delay:80ms">
				Everything a great salesperson does. <em class="italic">None of the payroll.</em>
			</h2>
			<p class="reveal mt-5 text-lg leading-relaxed text-ink-soft" style="--reveal-delay:140ms">
				Seven in ten carts are abandoned — most over a question nobody was there to answer. CartScout is there for every one of them.
			</p>
		</div>

		<div class="mt-14 grid gap-5 md:grid-cols-2 lg:grid-cols-12">
			<article class="reveal group rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-7">
				<p class="font-display text-sm italic text-accent-deep">01</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">Recommendations that close</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Shoppers describe what they want — “a gift under $50”, “something waterproof” — and CartScout searches your live catalog, respects the budget, and answers with rich product cards, carousels, and side-by-side comparisons. Variations, sale badges, and stock included.</p>
				<div class="mt-6 flex flex-wrap gap-2 text-xs font-semibold">
					<span class="rounded-full border border-line bg-paper px-3 py-1.5">Budget-aware search</span>
					<span class="rounded-full border border-line bg-paper px-3 py-1.5">Comparison tables</span>
					<span class="rounded-full border border-line bg-paper px-3 py-1.5">Best-seller picks</span>
					<span class="rounded-full border border-line bg-paper px-3 py-1.5">Coupon &amp; sale surfacing</span>
				</div>
			</article>

			<article class="reveal rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-5" style="--reveal-delay:80ms">
				<p class="font-display text-sm italic text-accent-deep">02</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">Never makes things up</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Every answer is grounded in your real store data — live prices, stock, attributes, shipping zones, your policies. If it doesn't know, it says so and offers the closest real alternative. No invented specs. No imaginary shipping times.</p>
			</article>

			<article class="reveal rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-4" style="--reveal-delay:120ms">
				<p class="font-display text-sm italic text-accent-deep">03</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">Carts &amp; checkout, handled</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Add to cart — including picking the right size or color — remove items with confirmation, and a one-tap checkout button the moment intent shows. The sale never leaves the chat.</p>
			</article>

			<article class="reveal rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-4" style="--reveal-delay:160ms">
				<p class="font-display text-sm italic text-accent-deep">04</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">Trained in minutes</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Paste FAQs, upload a CSV, index your pages and policies. CartScout answers in your store's voice with your facts — refund policy, sizing guides, shipping rules.</p>
			</article>

			<article class="reveal rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-4" style="--reveal-delay:200ms">
				<p class="font-display text-sm italic text-accent-deep">05</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">12 languages, automatic</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Detects each shopper's language and replies in it — while searching your catalog in yours. A French shopper buys from your English store without noticing the seam.</p>
			</article>

			<article class="reveal rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-6" style="--reveal-delay:120ms">
				<p class="font-display text-sm italic text-accent-deep">06</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">Insights that earn their keep</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Weekly cards for chats, carts, checkouts, and handoffs — plus a knowledge-gap report of searches that found nothing, so you know exactly which product or page to add next. Full transcripts included.</p>
			</article>

			<article class="reveal rounded-2xl border border-line bg-cream p-8 shadow-sm transition-shadow hover:shadow-lift lg:col-span-6" style="--reveal-delay:160ms">
				<p class="font-display text-sm italic text-accent-deep">07</p>
				<h3 class="mt-3 font-display text-2xl font-semibold">Human handoff, with receipts</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">When a conversation needs a person, CartScout collects the shopper's details and hands your team the entire transcript. Order tracking is self-serve too — status and tracking links, verified by email.</p>
			</article>
		</div>

		<p class="reveal mt-10 text-center text-sm leading-relaxed text-ink-soft">
			Also in the box: <span class="font-semibold text-ink">proactive teaser bubble · order tracking · fully branded widget (name, logo, color, tone) · privacy controls &amp; IP anonymization · ~6KB storefront footprint</span>
		</p>
		<div class="reveal mt-8 text-center">
			<a href="#pricing" class="btn-press js-buy inline-block rounded-full bg-ink px-8 py-3.5 text-sm font-bold text-cream shadow-lift hover:bg-accent-deep">Put it on your store — free for 14 days</a>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ HOW IT WORKS ════════════════════════════════ -->
<section id="how" class="py-24 lg:py-32">
	<div class="mx-auto max-w-6xl px-6">
		<div class="mx-auto max-w-2xl text-center">
			<p class="reveal text-xs font-bold uppercase tracking-[0.22em] text-accent-deep">How it works</p>
			<h2 class="reveal mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl" style="--reveal-delay:80ms">
				Live before your <em class="italic">coffee cools.</em>
			</h2>
		</div>

		<div class="mt-16 grid gap-10 md:grid-cols-3">
			<div class="reveal text-center md:text-left">
				<p class="font-display text-7xl font-light italic text-accent/80">1</p>
				<div class="rule-dotted my-5"></div>
				<h3 class="font-display text-2xl font-semibold">Install the plugin</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">One plugin, like any other. No OpenAI account, no API key, no embed snippet — the managed AI comes with it and works the moment your trial starts.</p>
			</div>
			<div class="reveal text-center md:text-left" style="--reveal-delay:120ms">
				<p class="font-display text-7xl font-light italic text-accent/80">2</p>
				<div class="rule-dotted my-5"></div>
				<h3 class="font-display text-2xl font-semibold">Make it yours</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">Name it, drop in your logo, pick a color and tone of voice. Paste your FAQs and index your pages — the live preview shows exactly what shoppers will see.</p>
			</div>
			<div class="reveal text-center md:text-left" style="--reveal-delay:240ms">
				<p class="font-display text-7xl font-light italic text-accent/80">3</p>
				<div class="rule-dotted my-5"></div>
				<h3 class="font-display text-2xl font-semibold">Watch it sell</h3>
				<p class="mt-3 leading-relaxed text-ink-soft">It greets, recommends, fills carts, and nudges checkout around the clock. You watch chats become carts in the Insights dashboard.</p>
			</div>
		</div>

		<p class="reveal mt-14 text-center font-display text-xl italic text-ink-soft">Average setup time: under five minutes. No code. Works with any theme.</p>
		<div class="reveal mt-7 text-center">
			<a href="#pricing" class="btn-press js-buy inline-block rounded-full border border-ink px-8 py-3.5 text-sm font-bold hover:bg-ink hover:text-cream">Start your free trial</a>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ MANIFESTO ════════════════════════════════ -->
<section id="manifesto" class="relative overflow-hidden bg-pine-deep py-24 text-cream lg:py-32">
	<div class="pointer-events-none absolute -right-24 -top-24 h-[420px] w-[420px] rounded-full bg-pine blur-3xl" aria-hidden="true"></div>
	<div class="relative mx-auto max-w-6xl px-6">
		<div class="grid items-center gap-14 lg:grid-cols-[1.2fr_0.8fr]">
			<div>
				<p class="reveal text-xs font-bold uppercase tracking-[0.22em] text-butter">Our small rebellion</p>
				<h2 class="reveal mt-4 font-display text-4xl font-semibold leading-[1.05] tracking-tight sm:text-5xl lg:text-6xl" style="--reveal-delay:80ms">
					We hate monthly fees. <em class="italic text-butter">So we don't charge any.</em>
				</h2>
				<div class="reveal mt-8 max-w-xl space-y-5 text-lg leading-relaxed text-cream/85" style="--reveal-delay:160ms">
					<p>Most AI chatbots rent you the same models we use — wrapped in a $49-to-$299-a-month invoice — because their AI bills are out of control, and yours is how they pay them.</p>
					<p>We went the other way. We put CartScout on a strict token diet, cache every prompt, and route each request to the most efficient model that can do the job. Our cost per conversation is measured in <em class="italic text-butter">pennies</em>.</p>
					<p>So you pay once a year — or once, full stop. No usage meters running while you sleep. No per-conversation surprises. No “contact sales.”</p>
				</div>
			</div>
			<div class="reveal flex flex-col items-center gap-10" style="--reveal-delay:240ms">
				<div class="stamp px-8 py-5 text-center font-display text-2xl font-bold uppercase text-butter sm:text-3xl">
					No monthly<br>fees — ever
				</div>
				<dl class="w-full max-w-sm space-y-5 text-sm">
					<div class="border-b border-cream/20 pb-4">
						<dt class="text-xs font-bold uppercase tracking-[0.18em] text-butter">Token diet</dt>
						<dd class="mt-1.5 leading-relaxed text-cream/90">The AI reads a slimmed payload — a fraction of what shoppers see on screen.</dd>
					</div>
					<div class="border-b border-cream/20 pb-4">
						<dt class="text-xs font-bold uppercase tracking-[0.18em] text-butter">Prompt caching</dt>
						<dd class="mt-1.5 leading-relaxed text-cream/90">Repeated context is billed at cached rates, not full price.</dd>
					</div>
					<div class="border-b border-cream/20 pb-4">
						<dt class="text-xs font-bold uppercase tracking-[0.18em] text-butter">Smart routing</dt>
						<dd class="mt-1.5 leading-relaxed text-cream/90">Every request gets the most efficient model that can do the job.</dd>
					</div>
				</dl>
			</div>
		</div>
	</div>
</section>

<!-- ════════════════════════════════ PRICING ════════════════════════════════ -->
<section id="pricing" class="border-t border-line py-24 lg:py-32">
	<div class="mx-auto max-w-6xl px-6">
		<div class="mx-auto max-w-2xl text-center">
			<p class="reveal text-xs font-bold uppercase tracking-[0.22em] text-accent-deep">Pricing</p>
			<h2 class="reveal mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl" style="--reveal-delay:80ms">
				Pricing that respects <em class="italic">your margins.</em>
			</h2>
			<p class="reveal mt-5 text-lg text-ink-soft" style="--reveal-delay:140ms">Every plan includes every feature and the managed AI. The only question is how many stores — and how many times you'd like to pay.</p>
			<p class="reveal mt-3 font-display text-lg italic" style="--reveal-delay:180ms">Save one $80 cart a month and Starter pays for itself ten times a year.</p>
		</div>

		<div class="mt-16 grid items-stretch gap-6 lg:grid-cols-3">
			<!-- Starter -->
			<article class="reveal flex flex-col rounded-3xl border border-line bg-cream p-8 shadow-sm">
				<h3 class="font-display text-xl font-semibold">Starter</h3>
				<p class="mt-1 text-sm text-ink-soft">For one growing store</p>
				<p class="mt-6 font-display text-6xl font-semibold tracking-tight">$99<span class="text-xl font-normal italic text-ink-soft"> / year</span></p>
				<p class="mt-2 text-sm text-ink-soft">That's $8.25 a month — billed once a year, like civilized software.</p>
				<ul class="mt-8 flex-1 space-y-3 text-sm">
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> 1 site, every feature included</li>
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> Managed AI included — no API key</li>
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> All updates &amp; priority support</li>
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> 30-day money-back guarantee</li>
				</ul>
				<a href="#" class="btn-press js-buy mt-8 rounded-full border border-ink px-6 py-3.5 text-center text-sm font-bold hover:bg-ink hover:text-cream">Start free trial</a><?php /* TODO: Freemius checkout link */ ?>
				<p class="mt-3 text-center text-xs text-ink-soft">14 days free, no card — then $99/year</p>
			</article>

			<!-- Lifetime (featured) -->
			<article class="reveal relative flex flex-col rounded-3xl bg-ink p-8 text-cream shadow-card lg:-my-4 lg:py-12" style="--reveal-delay:100ms">
				<p class="absolute -top-3.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-accent-deep px-4 py-1.5 text-xs font-bold uppercase tracking-wider text-cream">Most popular</p>
				<h3 class="font-display text-xl font-semibold">Lifetime</h3>
				<p class="mt-1 text-sm text-cream/60">Pay once. Own it forever.</p>
				<p class="mt-6 font-display text-6xl font-semibold tracking-tight">$299<span class="text-xl font-normal italic text-cream/60"> once</span></p>
				<p class="mt-2 text-sm text-cream/60">Costs less than three months of a typical “AI chatbot” subscription.</p>
				<ul class="mt-8 flex-1 space-y-3 text-sm">
					<li class="flex gap-2.5"><span class="text-butter">✓</span> 1 site — yours for life</li>
					<li class="flex gap-2.5"><span class="text-butter">✓</span> Managed AI included, forever</li>
					<li class="flex gap-2.5"><span class="text-butter">✓</span> Lifetime updates &amp; support</li>
					<li class="flex gap-2.5"><span class="text-butter">✓</span> 30-day money-back guarantee</li>
				</ul>
				<a href="#" class="btn-press js-buy mt-8 rounded-full bg-accent-deep px-6 py-3.5 text-center text-sm font-bold text-cream hover:bg-accent-ink">Start free trial</a><?php /* TODO: Freemius checkout link */ ?>
				<p class="mt-3 text-center text-xs text-cream/60">14 days free, no card — then $299, once</p>
			</article>

			<!-- Unlimited -->
			<article class="reveal flex flex-col rounded-3xl border border-line bg-cream p-8 shadow-sm" style="--reveal-delay:200ms">
				<h3 class="font-display text-xl font-semibold">Unlimited</h3>
				<p class="mt-1 text-sm text-ink-soft">For agencies &amp; portfolios</p>
				<p class="mt-6 font-display text-6xl font-semibold tracking-tight">$499<span class="text-xl font-normal italic text-ink-soft"> once</span></p>
				<p class="mt-2 text-sm text-ink-soft">Unlimited sites, one payment. Your clients' new favorite line item.</p>
				<ul class="mt-8 flex-1 space-y-3 text-sm">
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> Unlimited sites, forever</li>
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> Managed AI included on every site</li>
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> Lifetime updates &amp; support</li>
					<li class="flex gap-2.5"><span class="text-accent-deep">✓</span> 30-day money-back guarantee</li>
				</ul>
				<a href="#" class="btn-press js-buy mt-8 rounded-full border border-ink px-6 py-3.5 text-center text-sm font-bold hover:bg-ink hover:text-cream">Start free trial</a><?php /* TODO: Freemius checkout link */ ?>
				<p class="mt-3 text-center text-xs text-ink-soft">14 days free, no card — then $499, once</p>
			</article>
		</div>

		<p class="reveal mt-10 text-center text-sm text-ink-soft">
			Managed AI included on every plan: thousands of shopper conversations a month — a typical store uses a few hundred. <a class="underline decoration-accent decoration-2 underline-offset-2" href="#faq">Details in the FAQ</a>
		</p>
	</div>
</section>

<!-- ════════════════════════════════ FAQ ════════════════════════════════ -->
<section id="faq" class="border-t border-line bg-parchment/60 py-24 lg:py-32">
	<div class="mx-auto max-w-3xl px-6">
		<div class="text-center">
			<p class="reveal text-xs font-bold uppercase tracking-[0.22em] text-accent-deep">FAQ</p>
			<h2 class="reveal mt-4 font-display text-4xl font-semibold tracking-tight sm:text-5xl" style="--reveal-delay:80ms">
				Fair <em class="italic">questions.</em>
			</h2>
		</div>

		<div class="mt-12 space-y-3">
			<?php
			$faqs = array(
				array( 'How can there be no monthly fee? Who pays for the AI?', 'We do — and we engineered it to be sustainable. CartScout puts every conversation on a token diet, caches its prompts, and routes each request to the most efficient model. Our AI cost per conversation is pennies, so a yearly or one-time license covers it. Every plan includes thousands of shopper conversations a month; a typical store uses a few hundred.' ),
				array( 'Do I need an OpenAI account or API key?', 'No. CartScout ships with fully managed AI. Install the plugin, start your free trial, and the chatbot works immediately — we host the AI infrastructure, route every request through our servers, and keep the models current for you.' ),
				array( 'Will it make things up about my products?', "No. CartScout only states facts returned by your store — live prices, stock, attributes, shipping zones, coupon codes, and your own pages. If it can't find something, it says so and offers the closest real alternative instead of inventing specs or shipping times." ),
				array( 'Can it actually add products to the cart?', "Yes. Shoppers search, compare, pick a variation like size or color, and add to cart without leaving the chat. When they're ready, the bot shows a one-tap checkout button. Removing items always asks for confirmation first." ),
				array( 'What happens to my Lifetime license if CartScout shuts down?', "Your store keeps its data — the plugin, settings, transcripts, and insights all live on your server. And if we ever sunset the managed AI service, we commit to shipping a bring-your-own-key mode first, so your chatbot keeps running on your own OpenAI account." ),
				array( 'Will it slow down my store?', 'No. Your storefront pages load only a ~6KB stub; the full widget loads when a shopper interacts with it. Styles are fully scoped, so it never collides with your theme.' ),
				array( 'What is CartScout?', 'CartScout is an AI chatbot for WooCommerce stores. It chats with shoppers, searches your real catalog, recommends and compares products, adds items to the cart, tracks orders, and hands off to a human when needed. It installs like any WordPress plugin and works with any theme.' ),
				array( 'What languages does CartScout support?', "CartScout auto-detects each shopper's language, or you can lock it to one of 12: English, Spanish, French, German, Italian, Portuguese, Dutch, Russian, Chinese, Japanese, Korean, and Arabic. It searches your catalog in your store's language while replying in the shopper's." ),
				array( 'Is CartScout GDPR-friendly?', 'Yes. Conversation retention is configurable with automatic deletion, IP anonymization is on by default, shopper conversations are never retained by the AI provider or used for training, and uninstalling removes every table and option the plugin created.' ),
				array( 'How long does setup take?', 'About five minutes. Install the plugin, start the free trial, set your name, logo, and brand color, then paste your FAQs. No code, no API keys, no embed snippets.' ),
				array( "What's the difference between Starter, Lifetime, and Unlimited?", 'Starter is $99 per year for one site — every feature, AI included, billed once a year. Lifetime is a single $299 payment for one site, forever, including the managed AI and all future updates. Unlimited is $499 once for unlimited sites — built for agencies and portfolio owners.' ),
			);
			foreach ( $faqs as $i => $faq ) :
			?>
			<details class="faq-item reveal rounded-2xl border border-line bg-cream px-6 py-5" <?php echo 0 === $i ? 'open' : ''; ?> style="--reveal-delay:<?php echo $i * 40; ?>ms">
				<summary class="flex items-center justify-between gap-4">
					<h3 class="font-display text-lg font-semibold sm:text-xl"><?php echo esc_html( $faq[0] ); ?></h3>
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
<section class="py-24 lg:py-36">
	<div class="mx-auto max-w-4xl px-6 text-center">
		<h2 class="reveal font-display text-5xl font-semibold leading-[1.05] tracking-tight sm:text-6xl">
			Your store is open 24/7.<br><em class="italic text-accent">Now your sales floor is too.</em>
		</h2>
		<p class="reveal mt-6 text-lg text-ink-soft" style="--reveal-delay:120ms">Install CartScout, brand it, and let it greet your very next visitor.</p>
		<div class="reveal mt-10" style="--reveal-delay:200ms">
			<a href="#pricing" class="btn-press js-buy inline-block rounded-full bg-accent-deep px-10 py-5 text-lg font-bold text-cream shadow-lift hover:bg-accent-ink">Start your free 14-day trial</a>
			<p class="mt-4 text-sm text-ink-soft">5-minute setup · No credit card · No monthly fees, ever</p>
		</div>
	</div>
</section>

</main>

<!-- ════════════════════════════════ FOOTER ════════════════════════════════ -->
<footer class="border-t border-line bg-parchment/60 py-12">
	<div class="mx-auto max-w-6xl px-6">
		<div class="flex flex-col items-center justify-between gap-6 sm:flex-row">
			<a href="#" class="font-display text-xl font-semibold">Cart<span class="italic text-accent-deep">Scout</span></a>
			<nav class="flex flex-wrap justify-center gap-6 text-sm font-medium" aria-label="Footer">
				<a class="hover:text-accent" href="#features">Features</a>
				<a class="hover:text-accent" href="#pricing">Pricing</a>
				<a class="hover:text-accent" href="#faq">FAQ</a>
				<a class="hover:text-accent" href="mailto:support@cartscout.ai">support@cartscout.ai</a>
				<?php /* TODO: add Privacy Policy + Terms pages and link them here */ ?>
			</nav>
		</div>
		<div class="rule-dotted my-8"></div>
		<p class="text-center text-xs leading-relaxed text-ink-soft">
			© <?php echo esc_html( gmdate( 'Y' ) ); ?> CartScout. The AI shopping assistant for WooCommerce.<br>
			CartScout is an independent product and is not affiliated with or endorsed by Automattic. “WooCommerce” and “WordPress” are trademarks of their respective owners, used for descriptive purposes only.
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
