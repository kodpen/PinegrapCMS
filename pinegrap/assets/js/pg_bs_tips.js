/**
 * Pinegrap - Enterprise Website Platform
 *
 * Bootstrap tooltips and popovers for the pages of the visual editor.
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

    document.addEventListener('mouseover', start, true);
    document.addEventListener('focusin', start, true);
    document.addEventListener('click', start, true);
})();
