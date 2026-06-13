(function () {
	'use strict';

	var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ------------------------------------------------ mobile menu */
	var menuButton = document.querySelector('[data-menu-button]');
	var menuPanel = document.querySelector('[data-menu-panel]');
	if (menuButton && menuPanel) {
		menuButton.addEventListener('click', function () {
			var open = menuPanel.classList.toggle('hidden') === false;
			menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		menuPanel.addEventListener('click', function (event) {
			if (event.target.closest('a')) {
				menuPanel.classList.add('hidden');
				menuButton.setAttribute('aria-expanded', 'false');
			}
		});
	}

	/* ------------------------------------------------ scroll reveals */
	// Expand [data-reveal-group] into staggered .reveal children.
	document.querySelectorAll('[data-reveal-group]').forEach(function (group) {
		Array.prototype.forEach.call(group.children, function (child, i) {
			child.classList.add('reveal');
			child.style.setProperty('--reveal-delay', (i * 80) + 'ms');
		});
	});

	var revealEls = document.querySelectorAll('.reveal');
	if (reducedMotion || !('IntersectionObserver' in window)) {
		revealEls.forEach(function (el) { el.classList.add('is-visible'); });
	} else {
		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (entry.isIntersecting) {
						entry.target.classList.add('is-visible');
						observer.unobserve(entry.target);
					}
				});
			},
			{ rootMargin: '0px 0px -8% 0px', threshold: 0.05 }
		);
		revealEls.forEach(function (el) { observer.observe(el); });
	}

	/* ------------------------------------------------ hero chat demo */
	// Builds a looping fake shopper conversation inside #hero-chat.
	var SCRIPT = [
		{ type: 'bot', text: "Hi! I'm Scout — ask me anything about the store." },
		{ type: 'user', text: 'Trail running shoes under $120?' },
		{ type: 'typing', ms: 950 },
		{ type: 'bot', text: 'In stock and ready to ship — three great picks:' },
		{ type: 'products', items: [
			{ name: 'Ridgerunner 2', price: '$89', badge: 'Sale', tone: 1 },
			{ name: 'Skyline Trail', price: '$112', tone: 2 },
			{ name: 'Cascade GTX', price: '$118', tone: 3 }
		] },
		{ type: 'user', text: 'Add the Ridgerunner 2, size 10' },
		{ type: 'typing', ms: 800 },
		{ type: 'bot', text: 'Done — Ridgerunner 2 (US 10) is in your cart.' },
		{ type: 'cta', label: 'Checkout — $89.00' }
	];

	function el(tag, cls, html) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (html !== undefined) n.innerHTML = html;
		return n;
	}

	function renderMessage(step) {
		if (step.type === 'bot' || step.type === 'user') {
			return el('div', 'cd-msg cd-' + step.type, step.text);
		}
		if (step.type === 'products') {
			var row = el('div', 'cd-products');
			step.items.forEach(function (p) {
				var card = el('div', 'cd-card');
				var thumb = el('div', 'cd-thumb cd-tone' + (p.tone || 1));
				if (p.badge) thumb.appendChild(el('span', 'cd-badge', p.badge));
				card.appendChild(thumb);
				card.appendChild(el('div', 'cd-name', p.name));
				card.appendChild(el('div', 'cd-price', p.price));
				row.appendChild(card);
			});
			return row;
		}
		if (step.type === 'cta') {
			var btn = el('button', 'cd-cta', step.label);
			btn.type = 'button';
			btn.tabIndex = -1;
			return btn;
		}
		return null;
	}

	function initChatDemo(body) {
		if (!body) return;

		// Reduced motion: render the whole conversation at once, no looping.
		if (reducedMotion) {
			SCRIPT.forEach(function (step) {
				if (step.type === 'typing') return;
				var node = renderMessage(step);
				if (node) body.appendChild(node);
			});
			body.scrollTop = body.scrollHeight;
			return;
		}

		var index = 0, timer = null, visible = true;

		try {
			var io = new IntersectionObserver(function (entries) {
				visible = entries[entries.length - 1].isIntersecting;
				if (visible && timer === null) tick(600);
			}, { threshold: 0.2 });
			io.observe(body);
		} catch (e) { /* keep running */ }

		function add(node) {
			node.classList.add('cd-in');
			body.appendChild(node);
			body.scrollTop = body.scrollHeight;
			setTimeout(function () { node.classList.remove('cd-in'); }, 500);
		}
		function tick(delay) { timer = setTimeout(step, delay); }

		function step() {
			timer = null;
			if (!visible) return;
			if (index >= SCRIPT.length) {
				index = 0;
				timer = setTimeout(function () {
					body.classList.add('cd-fade');
					timer = setTimeout(function () {
						body.innerHTML = '';
						body.classList.remove('cd-fade');
						timer = null;
						tick(500);
					}, 600);
				}, 3600);
				return;
			}
			var s = SCRIPT[index++];
			if (s.type === 'typing') {
				var t = el('div', 'cd-msg cd-bot cd-typing', '<span></span><span></span><span></span>');
				body.appendChild(t);
				body.scrollTop = body.scrollHeight;
				timer = setTimeout(function () { t.remove(); timer = null; step(); }, s.ms || 900);
				return;
			}
			var node = renderMessage(s);
			if (node) add(node);
			tick(s.type === 'user' ? 750 : 1250);
		}

		tick(800);
	}

	initChatDemo(document.getElementById('hero-chat'));
})();
