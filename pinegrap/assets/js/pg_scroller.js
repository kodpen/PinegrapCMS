/**
 * Pinegrap - Enterprise Website Platform
 *
 * Previous / next buttons of a sideways row (the visual editor's Scroller
 * block). The row carries a data-pg-scroller marker, each button
 * data-pg-scroll="prev" or "next"; neither carries a script. The server adds
 * this file to a page whose HTML holds the marker once every shared
 * component is drawn in (pg_scroller_inject()), the editor's Preview does
 * the same, and the editor's canvas loads it and binds again after every
 * render (window.pgScrollerBind).
 *
 * A button finds its row through aria-controls, or as the nearest marked
 * row around it (a copied block keeps the original's id). One press moves
 * the row by one item: the distance between the first two items, which is
 * an item's width plus the gap. Under prefers-reduced-motion the jump is
 * instant. On a right-to-left row "next" moves left. A button is disabled at
 * its end of the row; the row itself keeps scrolling by hand and with the
 * arrow keys. On the editor's canvas (window.pgScrollerEditing) a button at
 * the end is only marked aria-disabled, so the operator can still select it.
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

    // Loaded twice (a page that also lists the file itself): the first copy
    // already listens.
    if (typeof window.pgScrollerBind === 'function') {
        return;
    }

    var reduce = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    var rows   = [];
    var queued = false;

    function isRtl(row) {
        var style = window.getComputedStyle ? window.getComputedStyle(row) : null;
        return !!style && style.direction === 'rtl';
    }

    // The row's items as laid-out boxes. A child without a box of its own
    // (display: contents, as the editor's canvas wraps every element) stands
    // for its first child that has one.
    function items(row) {
        var out = [];

        for (var i = 0; i < row.children.length && out.length < 2; i++) {
            var el = row.children[i];

            if (el.getBoundingClientRect().width > 0) {
                out.push(el);
                continue;
            }

            for (var j = 0; j < el.children.length; j++) {
                if (el.children[j].getBoundingClientRect().width > 0) {
                    out.push(el.children[j]);
                    break;
                }
            }
        }

        return out;
    }

    // One item: from one item's start to the next one's (its width plus the
    // gap); a lone item's width; the visible width when the row is empty.
    function step(row) {
        var list = items(row);

        if (list.length > 1) {
            var a = list[0].getBoundingClientRect();
            var b = list[1].getBoundingClientRect();
            var d = isRtl(row) ? Math.abs(a.right - b.right) : Math.abs(b.left - a.left);

            if (d > 0) {
                return d;
            }
        }

        if (list.length) {
            return list[0].getBoundingClientRect().width;
        }

        return row.clientWidth;
    }

    // The row a button moves: the row its aria-controls names among the
    // marked rows nearest to it, else the first of those.
    function rowOf(button) {
        var id = button.getAttribute('aria-controls') || '';

        for (var el = button.parentElement; el; el = el.parentElement) {
            var found = el.querySelectorAll('[data-pg-scroller]');

            if (!found.length) {
                continue;
            }

            for (var i = 0; i < found.length; i++) {
                if (id && found[i].id === id) {
                    return found[i];
                }
            }

            return found[0];
        }

        return null;
    }

    function buttonsOf(row) {
        var all = (row.ownerDocument || document).querySelectorAll('[data-pg-scroll]');
        var out = [];

        for (var i = 0; i < all.length; i++) {
            if (rowOf(all[i]) === row) {
                out.push(all[i]);
            }
        }

        return out;
    }

    function setOff(button, off, others) {
        if (window.pgScrollerEditing) {
            button.setAttribute('aria-disabled', off ? 'true' : 'false');
            return;
        }

        if (off && !button.disabled && button.ownerDocument.activeElement === button) {
            // Focus would fall to the page: it moves to the other button.
            for (var i = 0; i < others.length; i++) {
                if (others[i] !== button && !others[i].disabled) {
                    others[i].focus();
                    break;
                }
            }
        }

        button.disabled = off;
    }

    // The scrolled distance from the row's start; in a right-to-left row
    // scrollLeft runs from 0 to negative values.
    function sync(row) {
        var at      = Math.abs(row.scrollLeft);
        var end     = row.scrollWidth - row.clientWidth;
        var buttons = buttonsOf(row);

        for (var i = 0; i < buttons.length; i++) {
            var prev = buttons[i].getAttribute('data-pg-scroll') === 'prev';
            setOff(buttons[i], prev ? at <= 1 : at >= end - 1, buttons);
        }
    }

    // Every bound row still in its document; rows the canvas threw away on
    // a render are dropped here.
    function syncAll() {
        queued = false;

        var live = [];

        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];

            if (row.ownerDocument && row.ownerDocument.documentElement.contains(row)) {
                sync(row);
                live.push(row);
            }
        }

        rows = live;
    }

    function later() {
        if (queued) {
            return;
        }

        queued = true;

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(syncAll);
        } else {
            setTimeout(syncAll, 50);
        }
    }

    function move(button) {
        if (button.disabled || button.getAttribute('aria-disabled') === 'true') {
            return;
        }

        var row = rowOf(button);

        if (!row) {
            return;
        }

        var dir  = (button.getAttribute('data-pg-scroll') === 'prev') ? -1 : 1;
        var left = dir * (isRtl(row) ? -1 : 1) * step(row);

        if (typeof row.scrollBy === 'function') {
            try {
                row.scrollBy({ left: left, behavior: (reduce && reduce.matches) ? 'auto' : 'smooth' });
                return;
            } catch (e) {}
        }

        row.scrollLeft += left;
    }

    // Listens in the capture phase: on the editor's canvas the icon inside a
    // button is an element of its own whose click does not bubble out.
    document.addEventListener('click', function (e) {
        var button = (e.target && e.target.closest) ? e.target.closest('[data-pg-scroll]') : null;

        if (button) {
            move(button);
        }
    }, true);

    window.addEventListener('resize', later);
    window.addEventListener('load', later);

    // Binds every marked row under scope once and brings every button up to
    // date; the canvas calls it again after each render, when the old
    // elements are gone.
    function bindAll(scope) {
        var root  = scope || document;
        var found = root.querySelectorAll('[data-pg-scroller]');

        for (var i = 0; i < found.length; i++) {
            var row = found[i];

            if (row.pgScrollerBound) {
                continue;
            }

            row.pgScrollerBound = true;
            row.addEventListener('scroll', later);
            row.addEventListener('scrollend', later);
            rows.push(row);
        }

        later();
    }

    window.pgScrollerBind = bindAll;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindAll(document);
        });
    } else {
        bindAll(document);
    }
})();
