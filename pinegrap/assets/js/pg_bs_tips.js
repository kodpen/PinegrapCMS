/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bootstrap tooltips and popovers for the pages of the visual editor, and
 * the tab that holds the address's anchor.
 *
 * Bootstrap ships no automatic start for these two: an element marked
 * data-bs-toggle="tooltip" (the Tooltip option of the editor's panel) or
 * data-bs-toggle="popover" (Components > Popover) does nothing until a
 * script creates its instance. The server adds this file to a page whose
 * HTML holds either marker once every shared component and system widget
 * is drawn in (pg_bs_tips_inject()); the editor's Preview loads it the same
 * way.
 *
 * An instance is made the first time the element is pointed at, focused or
 * clicked, from a listener on the document that runs before the element's
 * own: the event that made it then reaches the listeners Bootstrap has just
 * bound, so the first hover shows the tooltip and the first click opens the
 * popover. Elements that arrive later are covered without a rescan, and an
 * instance the design's own script made at load is kept, never doubled.
 * Without Bootstrap's script (a design with another framework) nothing
 * happens and a tooltip's title stays the browser's own.
 *
 * A link to an element inside a tab that is not shown (page#session-x, a
 * session on the second day of a programme) would open the page on its
 * first tab with nothing in view: Bootstrap opens no tab from the address.
 * When the page loads, and when its anchor changes, the tab whose pane
 * holds the anchor's element is shown through Bootstrap's own Tab (so its
 * buttons and ARIA state follow) and the element is scrolled to. The
 * markers data-bs-toggle="tab", "pill" and "list" bring the file too.
 *
 * Plain ES5 and no jQuery: it has to work whatever the design loads.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    if (window.pgBsTipsInit) {
        return;
    }
    window.pgBsTipsInit = true;

    var SELECTOR = '[data-bs-toggle="tooltip"], [data-bs-toggle="popover"]';

    function start(event) {
        var target = event.target;

        if (!target || target.nodeType !== 1 || !target.closest) {
            return;
        }

        var el = target.closest(SELECTOR);
        var bs = window.bootstrap;

        if (!el || !bs) {
            return;
        }

        var Kind = (el.getAttribute('data-bs-toggle') === 'popover') ? bs.Popover : bs.Tooltip;

        if (!Kind || Kind.getInstance(el)) {
            return;
        }

        // Popper missing (bootstrap.min.js without the bundle) throws here;
        // the element then stays as the markup draws it.
        try {
            new Kind(el);
        } catch (e) {}
    }

    // The toggle of a tab pane: the button or link naming it by
    // data-bs-target or href.
    function paneToggle(pane) {
        var id = pane.id;
        if (!id) {
            return null;
        }
        var toggles = document.querySelectorAll('[data-bs-toggle="tab"], [data-bs-toggle="pill"], [data-bs-toggle="list"]');
        for (var i = 0; i < toggles.length; i++) {
            var named = toggles[i].getAttribute('data-bs-target') || toggles[i].getAttribute('href') || '';
            if (named === '#' + id) {
                return toggles[i];
            }
        }
        return null;
    }

    // Show every closed tab pane around the element the address's anchor
    // names, outermost first, then bring the element into view.
    function showAnchorTab() {
        var bs = window.bootstrap;
        var id = '';

        if (!bs || !bs.Tab || !window.location.hash) {
            return;
        }
        try {
            id = decodeURIComponent(window.location.hash.slice(1));
        } catch (e) {
            return;
        }

        var target = id ? document.getElementById(id) : null;
        if (!target) {
            return;
        }

        var panes = [];
        for (var p = target; p && p !== document.body; p = p.parentNode) {
            if (p.nodeType === 1 && p.classList.contains('tab-pane') && !p.classList.contains('active')) {
                panes.unshift(p);
            }
        }
        if (!panes.length) {
            return;
        }

        for (var i = 0; i < panes.length; i++) {
            var toggle = paneToggle(panes[i]);
            if (!toggle) {
                return;
            }
            try {
                bs.Tab.getOrCreateInstance(toggle).show();
            } catch (e) {
                return;
            }
        }
        // The pane is laid out as soon as it is active; the fade only
        // changes its opacity.
        if (target.scrollIntoView) {
            target.scrollIntoView({ block: 'start' });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', showAnchorTab);
    } else {
        showAnchorTab();
    }
    window.addEventListener('hashchange', showAnchorTab);

    document.addEventListener('mouseover', start, true);
    document.addEventListener('focusin', start, true);
    document.addEventListener('click', start, true);
})();
