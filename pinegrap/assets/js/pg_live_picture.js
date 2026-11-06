/**
 * Pinegrap - Enterprise Website Platform
 *
 * Live pictures of designs and templates (window.PGLivePicture): a box
 * printed by pg_design_live_picture() (includes/fn/design_previews.php)
 * shows the real page, drawn at 1280x800 in a frame and scaled to the box.
 *
 * - The frame is made only when its box comes into view
 *   (IntersectionObserver), script-free (sandbox=""), not focusable
 *   (tabindex="-1"), hidden from assistive technology (the box carries the
 *   label) and never takes a click (the box's parent handles it).
 * - The box keeps its scale as it resizes (ResizeObserver).
 * - Until the page arrives the box shows a quiet wait state; after 12
 *   seconds without it, the box's fallback (a drawing) takes its place.
 * - set(el, src) swaps a box to another page (another look or palette).
 * - scan(scope) finds the boxes in markup added later; it runs once on load.
 *
 * Served minified as assets/js/pg_live_picture.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 */
(function (window, document) {
	'use strict';

	var WAIT_MS = 12000;
	var observer = null;
	var sizer = null;

	function frameSize(box) {
		return {
			width: parseInt(box.getAttribute('data-width'), 10) || 1280,
			height: parseInt(box.getAttribute('data-height'), 10) || 800
		};
	}

	// The frame drawn at its full size, scaled down to the box's width.
	function fit(box) {
		var frame = box.querySelector('iframe');
		if (!frame || !box.clientWidth) {
			return;
		}
		var size = frameSize(box);
		frame.style.transform = 'scale(' + (box.clientWidth / size.width) + ')';
	}

	function fail(box) {
		box.classList.remove('pg-loading');
		box.classList.add('pg-failed');
		var frame = box.querySelector('iframe');
		if (frame) {
			frame.parentNode.removeChild(frame);
		}
		var fallback = box.querySelector('.pg-live-picture-fallback');
		if (fallback) {
			fallback.hidden = false;
		}
	}

	// Makes the box's frame for its address (data-src).
	function load(box) {
		var src = box.getAttribute('data-src') || '';
		var old = box.querySelector('iframe');
		if (old) {
			old.parentNode.removeChild(old);
		}
		clearTimeout(box._pgLiveTimer);
		box.classList.remove('pg-failed', 'pg-loaded');
		var fallback = box.querySelector('.pg-live-picture-fallback');
		if (fallback) {
			fallback.hidden = true;
		}
		if (!src) {
			fail(box);
			return;
		}
		var size = frameSize(box);
		var frame = document.createElement('iframe');
		frame.setAttribute('sandbox', '');
		frame.setAttribute('tabindex', '-1');
		frame.setAttribute('aria-hidden', 'true');
		frame.setAttribute('referrerpolicy', 'same-origin');
		frame.style.width = size.width + 'px';
		frame.style.height = size.height + 'px';
		box.classList.add('pg-loading');
		frame.addEventListener('load', function () {
			if (box.querySelector('iframe') !== frame) {
				return;
			}
			clearTimeout(box._pgLiveTimer);
			box.classList.remove('pg-loading');
			box.classList.add('pg-loaded');
		});
		box._pgLiveTimer = setTimeout(function () {
			if (box.querySelector('iframe') === frame && !box.classList.contains('pg-loaded')) {
				fail(box);
			}
		}, WAIT_MS);
		box.insertBefore(frame, box.firstChild);
		fit(box);
		frame.src = src;
	}

	function seen(box) {
		if (box._pgLiveSeen) {
			return;
		}
		box._pgLiveSeen = true;
		if (observer) {
			observer.unobserve(box);
		}
		load(box);
	}

	function watch(box) {
		if (box._pgLiveWatched) {
			return;
		}
		box._pgLiveWatched = true;
		if (!box.getAttribute('data-src')) {
			fail(box);
			return;
		}
		if (sizer) {
			sizer.observe(box);
		}
		if (observer) {
			observer.observe(box);
		} else {
			seen(box);
		}
	}

	function scan(scope) {
		var root = scope || document;
		var boxes = root.querySelectorAll ? root.querySelectorAll('[data-pg-live-picture]') : [];
		for (var i = 0; i < boxes.length; i++) {
			watch(boxes[i]);
		}
		if (root.nodeType === 1 && root.hasAttribute('data-pg-live-picture')) {
			watch(root);
		}
	}

	// Points a box at another page; drawn now when the box was already seen.
	function set(box, src) {
		if (!box) {
			return;
		}
		if ((box.getAttribute('data-src') || '') === (src || '') && box._pgLiveSeen) {
			return;
		}
		box.setAttribute('data-src', src || '');
		if (!box.querySelector('.pg-live-picture-wait')) {
			var wait = document.createElement('div');
			wait.className = 'pg-live-picture-wait';
			wait.setAttribute('aria-hidden', 'true');
			box.appendChild(wait);
		}
		if (!box._pgLiveWatched) {
			watch(box);
		} else if (box._pgLiveSeen) {
			load(box);
		} else if (!src) {
			fail(box);
		} else {
			box.classList.remove('pg-failed');
			if (sizer) {
				sizer.observe(box);
			}
			if (observer) {
				observer.observe(box);
			}
		}
	}

	if ('IntersectionObserver' in window) {
		observer = new IntersectionObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				if (entries[i].isIntersecting) {
					seen(entries[i].target);
				}
			}
		}, { rootMargin: '200px 0px' });
	}
	if ('ResizeObserver' in window) {
		sizer = new ResizeObserver(function (entries) {
			for (var i = 0; i < entries.length; i++) {
				fit(entries[i].target);
			}
		});
	} else {
		window.addEventListener('resize', function () {
			var boxes = document.querySelectorAll('[data-pg-live-picture]');
			for (var i = 0; i < boxes.length; i++) {
				fit(boxes[i]);
			}
		});
	}

	window.PGLivePicture = { scan: scan, set: set, fit: fit };

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', function () { scan(document); });
	} else {
		scan(document);
	}
})(window, document);
