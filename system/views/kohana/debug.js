/*
 * Behaviour of the error page, the notices panel and the profiler.
 * Plain JavaScript, no library, no inline event handlers (so it works
 * under a strict Content-Security-Policy).
 */
(function () {
	'use strict';

	// Bars of the profiler: their position is set here, because a strict
	// Content-Security-Policy does not allow style attributes in the HTML
	document.querySelectorAll('[data-k-left]').forEach(function (bar) {
		bar.style.left = bar.dataset.kLeft + '%';
	});

	// This script can be on a page more than once (error page, notices,
	// profiler): the click handler below must only be set once
	if (window.kohanaDebug)
		return;

	window.kohanaDebug = true;

	// Parts marked "k-collapsed" are only hidden when this script runs
	document.documentElement.classList.add('k-js');

	document.addEventListener('click', function (event) {
		var button = event.target.closest('[data-k-toggle], [data-k-copy]');

		if ( ! button)
			return;

		// Open or close the part named by the button
		if (button.dataset.kToggle) {
			var target = document.getElementById(button.dataset.kToggle);

			if (target) {
				var closed = target.classList.toggle('k-collapsed');
				button.setAttribute('aria-expanded', closed ? 'false' : 'true');
			}

			return;
		}

		// Copy the text of the element named by the button
		var source = document.getElementById(button.dataset.kCopy);

		if ( ! source)
			return;

		var done = function () {
			var label = button.textContent;
			button.textContent = button.dataset.kCopied || 'Copied';
			setTimeout(function () { button.textContent = label; }, 1500);
		};

		// Older browsers, pages that are not served over HTTPS, and
		// browsers that refuse the first way
		var select_and_copy = function () {
			source.hidden = false;
			source.select();
			document.execCommand('copy');
			source.hidden = true;
			done();
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(source.value).then(done, select_and_copy);
		} else {
			select_and_copy();
		}
	});

})();
