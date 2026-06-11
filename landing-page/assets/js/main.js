(function () {
	'use strict';

	var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ------------------------------------------------ nav scroll state */
	var nav = document.querySelector('.site-nav');
	if (nav) {
		var onScroll = function () {
			nav.classList.toggle('nav-scrolled', window.scrollY > 24);
		};
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}

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
	var demo = document.querySelector('[data-chat-demo]');
	if (!demo) return;

	var steps = Array.prototype.slice.call(demo.querySelectorAll('.chat-step'));
	var typing = demo.querySelector('.chat-typing');
	var scroller = demo.querySelector('[data-chat-scroll]');
	var addButton = demo.querySelector('.demo-add');

	if (reducedMotion) {
		steps.forEach(function (step) { step.classList.add('is-on'); });
		if (addButton) addButton.classList.add('is-added');
		return;
	}

	// [delayBeforeMs, showTypingFirst]
	var timeline = [
		[900, false],   // user: trail shoes under $80
		[1700, true],   // bot: picks + product cards
		[2600, false],  // user: add the Vela in a 9
		[1600, true],   // bot: done + checkout CTA
	];
	var ADD_FLIP_STEP = 3; // flip ADD -> ADDED right as the confirmation lands
	var RESTART_PAUSE = 5200;

	function scrollToEnd() {
		if (scroller) scroller.scrollTo({ top: scroller.scrollHeight, behavior: 'smooth' });
	}

	function showTyping(on) {
		if (typing) typing.classList.toggle('is-on', on);
		if (on) scrollToEnd();
	}

	var index = 0;
	var started = false;

	function playNext() {
		if (index >= steps.length) {
			window.setTimeout(reset, RESTART_PAUSE);
			return;
		}
		var step = timeline[index];
		var typingLead = step[1] ? 950 : 0;

		window.setTimeout(function () {
			if (typingLead) showTyping(true);
			window.setTimeout(function () {
				showTyping(false);
				if (index === ADD_FLIP_STEP && addButton) addButton.classList.add('is-added');
				steps[index].classList.add('is-on');
				scrollToEnd();
				index += 1;
				playNext();
			}, typingLead);
		}, step[0]);
	}

	function reset() {
		steps.forEach(function (step) { step.classList.remove('is-on'); });
		if (addButton) addButton.classList.remove('is-added');
		if (scroller) scroller.scrollTo({ top: 0 });
		index = 0;
		window.setTimeout(playNext, 700);
	}

	// Start when the demo scrolls into view.
	if ('IntersectionObserver' in window) {
		var demoObserver = new IntersectionObserver(function (entries) {
			if (entries[0].isIntersecting && !started) {
				started = true;
				demoObserver.disconnect();
				playNext();
			}
		}, { threshold: 0.3 });
		demoObserver.observe(demo);
	} else {
		playNext();
	}
})();
