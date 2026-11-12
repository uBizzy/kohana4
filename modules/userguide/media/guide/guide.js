/*
 * Behaviour of the user guide: current page in the menu, menu button on
 * small screens, "Copy" on code blocks, the filter of the API list, and
 * the search box. Plain JavaScript, no library.
 */
(function () {
	'use strict';

	var topics = document.getElementById('kodoc-topics');

	// Mark the link of the current page in the menu
	topics.querySelectorAll('a').forEach(function (link) {
		if (link.pathname === location.pathname) {
			link.classList.add('current');
			link.setAttribute('aria-current', 'page');
		}
	});

	// Menu button (small screens)
	var menuButton = document.getElementById('kodoc-menu-button');

	menuButton.addEventListener('click', function () {
		menuButton.setAttribute('aria-expanded', topics.classList.toggle('open') ? 'true' : 'false');
	});

	// "Copy" button on every code block
	document.querySelectorAll('#kodoc-body pre > code').forEach(function (code) {
		var button = document.createElement('button');

		button.type = 'button';
		button.className = 'kodoc-copy';
		button.textContent = 'Copy';

		button.addEventListener('click', function () {
			var done = function () {
				button.textContent = 'Copied';
				setTimeout(function () { button.textContent = 'Copy'; }, 1500);
			};

			if (navigator.clipboard && window.isSecureContext) {
				navigator.clipboard.writeText(code.textContent).then(done);
			} else {
				// Pages that are not served over HTTPS
				var range = document.createRange();
				range.selectNodeContents(code);
				getSelection().removeAllRanges();
				getSelection().addRange(range);
				document.execCommand('copy');
				getSelection().removeAllRanges();
				done();
			}
		});

		code.parentNode.appendChild(button);
	});

	// API list: show only the classes and methods that hold the typed text
	var filter = document.getElementById('kodoc-api-filter');

	if (filter) {
		filter.addEventListener('input', function () {
			var text = filter.value.trim().toLowerCase();

			document.querySelectorAll('.class-list section').forEach(function (section) {
				var inName = section.querySelector('h2').textContent.toLowerCase().indexOf(text) !== -1;
				var any = false;

				section.querySelectorAll('li').forEach(function (item) {
					var show = inName || item.textContent.toLowerCase().indexOf(text) !== -1;
					item.hidden = !show;
					any = any || show;
				});

				section.hidden = !(inName || any);
			});
		});
	}

	// ---- Search -------------------------------------------------------

	var box = document.getElementById('kodoc-search-box');
	var results = document.getElementById('kodoc-search-results');
	var entries = null;   // all pages and classes, loaded at the first search
	var selected = -1;    // result chosen with the arrow keys

	function escapeHtml(text) {
		return text.replace(/[&<>"]/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
		});
	}

	// Escaped text, with the searched words marked
	function mark(text, words) {
		var pattern = new RegExp('(' + words.map(function (w) {
			return escapeHtml(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		}).join('|') + ')', 'gi');

		return escapeHtml(text).replace(pattern, '<mark>$1</mark>');
	}

	// The part of a text around the first searched word
	function snippet(text, words) {
		var lower = text.toLowerCase();
		var at = -1;

		words.forEach(function (w) {
			var i = lower.indexOf(w);
			if (i !== -1 && (at === -1 || i < at)) { at = i; }
		});

		if (at === -1) { return ''; }

		var start = Math.max(0, at - 60);

		return (start ? '…' : '') + text.substr(start, 160) + (start + 160 < text.length ? '…' : '');
	}

	function search() {
		var words = box.value.toLowerCase().split(/\s+/).filter(Boolean);

		selected = -1;

		if (!words.length || !entries) {
			results.hidden = true;
			return;
		}

		var found = [];

		entries.forEach(function (entry) {
			var score = 0;

			// Every word must be somewhere in the entry; the title counts most
			var all = words.every(function (w) {
				if (entry.t.indexOf(w) !== -1) { score += (entry.t === w ? 40 : 20); return true; }
				if (entry.h.indexOf(w) !== -1) { score += 8; return true; }
				if (entry.x.indexOf(w) !== -1) { score += 1; return true; }
				return false;
			});

			if (all) { found.push({ entry: entry, score: score }); }
		});

		found.sort(function (a, b) { return b.score - a.score; });

		results.innerHTML = found.slice(0, 12).map(function (item) {
			var e = item.entry;
			var detail = snippet(e.text, words) || snippet(e.headings, words);

			return '<li><a href="' + escapeHtml(e.url) + '"><strong>' + mark(e.title, words) + ' <small>' + escapeHtml(e.section) + '</small></strong>'
				+ (detail ? '<span>' + mark(detail, words) + '</span>' : '') + '</a></li>';
		}).join('') || '<li class="empty">Nothing found</li>';

		results.hidden = false;
	}

	// The list is loaded once, when the search box is first used
	function load() {
		if (entries !== null || load.started) { return; }

		load.started = true;

		fetch(document.body.dataset.search, { headers: { 'Accept': 'application/json' } })
			.then(function (response) { return response.json(); })
			.then(function (list) {
				// Lowercase copies, made once, to search in
				entries = list.map(function (e) {
					e.t = e.title.toLowerCase();
					e.h = e.headings.toLowerCase();
					e.x = e.text.toLowerCase();
					return e;
				});
				search();
			});
	}

	box.addEventListener('focus', load);
	box.addEventListener('input', function () { load(); search(); });

	box.addEventListener('keydown', function (event) {
		var items = results.querySelectorAll('li a');

		if (event.key === 'Escape') {
			results.hidden = true;
			box.blur();
		} else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
			event.preventDefault();
			if (!items.length) { return; }
			selected = (selected + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
			items.forEach(function (a, i) { a.parentNode.classList.toggle('selected', i === selected); });
			items[selected].scrollIntoView({ block: 'nearest' });
		} else if (event.key === 'Enter' && items.length) {
			location.href = items[Math.max(selected, 0)].href;
		}
	});

	document.addEventListener('click', function (event) {
		if (!event.target.closest('#kodoc-search')) { results.hidden = true; }
	});

	// "/" goes to the search box
	document.addEventListener('keydown', function (event) {
		if (event.key === '/' && !/^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) {
			event.preventDefault();
			box.focus();
		}
	});
})();
