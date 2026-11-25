/**
 * Pinegrap - Enterprise Website Platform
 *
 * The visual editor's "Back to top" and "Scroll progress" components. Their
 * markup carries a marker and no script: data-pg-to-top on the button,
 * data-pg-scroll-progress on the bar. The server adds this file to a page
 * whose HTML holds either marker once every shared component is drawn in
 * (pg_page_scroll_inject()); the editor's Preview does the same. The
 * editor's canvas does not load it, so both stay in view there to be
 * selected.
 *
 * Back to top: hidden (Bootstrap's invisible and opacity-0) until the page
 * is scrolled half a window down; a press scrolls to the top, at once under
 * prefers-reduced-motion. Without this file the button is always shown and
 * its #top address still leads to the top of the page.
 *
 * Scroll progress: the bar's width follows how far the page is scrolled, as
 * a percentage of what can be scrolled.
 *
 * One passive scroll listener for the page, its work done once a frame.
 * Plain ES5 and no jQuery: it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Loaded twice (a page that also lists the file itself): the first copy
    // already listens.
    if (window.pgPageScrollBound) {
        return;
    }
    window.pgPageScrollBound = true;

    var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var queued = false;

    function scrolled() {
        return window.pageYOffset || document.documentElement.scrollTop || 0;
    }

    // visibility: hidden also takes the button out of the tab order and
    // out of what a screen reader reads.
    function setHidden(el, off) {
        var has = el.classList.contains('invisible');
        if (off === has) {
            return;
        }
        el.classList.toggle('invisible', off);
        el.classList.toggle('opacity-0', off);
    }

    function update() {
        queued = false;

        var top  = scrolled();
        var root = document.documentElement;
        var max  = Math.max(0, root.scrollHeight - window.innerHeight);
        var show = top > Math.max(200, window.innerHeight / 2);
        var i;

        var buttons = document.querySelectorAll('[data-pg-to-top]');
        for (i = 0; i < buttons.length; i++) {
            setHidden(buttons[i], !show);
        }

        var pct  = max > 0 ? Math.min(100, Math.round(top / max * 1000) / 10) : 0;
        var bars = document.querySelectorAll('[data-pg-scroll-progress]');
        for (i = 0; i < bars.length; i++) {
            var bar = bars[i].querySelector('.progress-bar') || bars[i];
            bar.style.width = pct + '%';
        }
    }

    function later() {
        if (queued) {
            return;
        }
        queued = true;
        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(update);
        } else {
            setTimeout(update, 50);
        }
    }

    document.addEventListener('click', function (e) {
        var button = (e.target && e.target.closest) ? e.target.closest('[data-pg-to-top]') : null;
        if (!button) {
            return;
        }
        e.preventDefault();
        try {
            window.scrollTo({ top: 0, behavior: (reduce && reduce.matches) ? 'auto' : 'smooth' });
        } catch (err) {
            window.scrollTo(0, 0);
        }
    });

    var passive = false;
    try {
        window.addEventListener('pgtest', null, Object.defineProperty({}, 'passive', {
            get: function () { passive = true; return true; }
        }));
    } catch (err) {}

    window.addEventListener('scroll', later, passive ? { passive: true } : false);
    window.addEventListener('resize', later);
    window.addEventListener('load', later);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', update);
    } else {
        update();
    }
})();
