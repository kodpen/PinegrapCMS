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
 * A menu's "After scrolling" (data-pg-scrolled="<px>" with
 * data-pg-scrolled-class="<Bootstrap classes>"): once the page is scrolled
 * that far, the menu takes those classes, and the classes of the same kind
 * it was drawn with (its background, shadow, vertical padding, navbar
 * theme) step aside until the page is back above that point.
 *
 * A menu's "Mark the section in view" (data-pg-spy): the first such menu
 * becomes the target of Bootstrap's own scrollspy on the body
 * (data-bs-spy="scroll"), which marks the link of the section on screen
 * active. Bootstrap starts it when the page has loaded; this file only
 * writes the two attributes before that, and gives the menu an id when it
 * has none.
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

    // The kind of a class that "After scrolling" may swap: two classes of
    // one kind fight over the same property, so the drawn one steps aside.
    function kind(c) {
        if (/^bg-(?!opacity|gradient)/.test(c)) {
            return 'bg';
        }
        if (/^shadow(?:-|$)/.test(c)) {
            return 'shadow';
        }
        if (/^p[ytb]-/.test(c)) {
            return 'py';
        }
        if (/^navbar-(?:dark|light)$/.test(c)) {
            return 'theme';
        }
        return '';
    }

    // The class list a menu shows, scrolled or not: `drawn` is the list it
    // was printed with, `add` the scrolled classes. Pure, so it is tested.
    function scrolledClasses(drawn, add, on) {
        var own = String(drawn || '').split(/\s+/).filter(Boolean);
        if (!on) {
            return own.join(' ');
        }
        var extra = String(add || '').split(/\s+/).filter(Boolean);
        var kinds = {};
        var i;
        for (i = 0; i < extra.length; i++) {
            kinds[kind(extra[i])] = true;
        }
        var out = [];
        for (i = 0; i < own.length; i++) {
            var k = kind(own[i]);
            if (k === '' || !kinds[k]) {
                out.push(own[i]);
            }
        }
        for (i = 0; i < extra.length; i++) {
            if (out.indexOf(extra[i]) === -1) {
                out.push(extra[i]);
            }
        }
        return out.join(' ');
    }

    function setScrolled(el, top) {
        var at = parseInt(el.getAttribute('data-pg-scrolled'), 10);
        var on = top >= (isNaN(at) ? 80 : Math.max(0, at));
        if (el.pgScrolledOn === on) {
            return;
        }
        if (el.pgScrolledDrawn === undefined) {
            el.pgScrolledDrawn = el.className;
        }
        el.pgScrolledOn = on;
        el.className = scrolledClasses(el.pgScrolledDrawn, el.getAttribute('data-pg-scrolled-class'), on);
        el.classList.toggle('pg-scrolled', on);
    }

    function spy() {
        var nav = document.querySelector('[data-pg-spy]');
        var body = document.body;
        if (!nav || !body || body.hasAttribute('data-bs-spy')) {
            return;
        }
        if (!nav.id) {
            nav.id = 'pg-spy-menu';
        }
        body.setAttribute('data-bs-spy', 'scroll');
        body.setAttribute('data-bs-target', '#' + nav.id);
        // Bootstrap's data API has already run when the page loaded first.
        if (document.readyState === 'complete' && window.bootstrap && window.bootstrap.ScrollSpy) {
            window.bootstrap.ScrollSpy.getOrCreateInstance(body);
        }
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

        var menus = document.querySelectorAll('[data-pg-scrolled]');
        for (i = 0; i < menus.length; i++) {
            setScrolled(menus[i], top);
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
        document.addEventListener('DOMContentLoaded', function () {
            spy();
            update();
        });
    } else {
        spy();
        update();
    }
})();
