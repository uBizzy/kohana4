/*
 * The theme of the pages the framework prints: applies what the visitor
 * chose (auto, light or dark) before the page is drawn, and makes the
 * buttons that change it work. See Theme::script().
 */
(function () {
	// A page can carry this script twice (debug bar and notices)
	if (window.kTheme)
		return;

	window.kTheme = true;

	var root = document.documentElement;
	var choices = ['auto', 'light', 'dark'];

	// The browser can refuse to store anything: then the choice lasts for this page
	var read = function () {
		try { return localStorage.getItem('k-theme'); } catch (e) { return null; }
	};
	var write = function (choice) {
		try {
			if (choice === 'auto') { localStorage.removeItem('k-theme'); } else { localStorage.setItem('k-theme', choice); }
		} catch (e) {}
	};

	var current = function () {
		var choice = root.getAttribute('data-k-theme');

		return (choice === 'light' || choice === 'dark') ? choice : 'auto';
	};

	var apply = function (choice) {
		if (choice === 'light' || choice === 'dark') {
			root.setAttribute('data-k-theme', choice);
		} else {
			root.removeAttribute('data-k-theme');
		}
	};

	var label = function () {
		var buttons = document.querySelectorAll('[data-k-theme-button]');

		for (var i = 0; i < buttons.length; i++) {
			buttons[i].hidden = false;
			buttons[i].textContent = 'Theme: ' + current();
		}
	};

	apply(read());

	document.addEventListener('click', function (event) {
		var button = event.target.closest ? event.target.closest('[data-k-theme-button]') : null;

		if ( ! button)
			return;

		var next = choices[(choices.indexOf(current()) + 1) % choices.length];

		apply(next);
		write(next);
		label();
	});

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', label);
	} else {
		label();
	}

	// The debug bar is added to the page after this script has run
	window.addEventListener('load', label);
})();
