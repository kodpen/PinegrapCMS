/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the row of tools of My Notes' note, drawn as a page: one line
 * that never wraps. The tools that do not fit its width are hidden from the
 * end and offered, in the order they stand on the row, by a More button at
 * its end; whenever the row's width changes they are counted again. A line
 * of the menu clicks the hidden tool itself, so the writing box behaves as
 * if the tool had been pressed on the row.
 *
 * assets/js/workspace.js folds the notes' row through
 * window.PGWsNotePage.fold() (startNotes(), toolbar()) and opens its own
 * context menu for More. A channel's note and the other writing boxes keep
 * their wrapping rows. How many tools stand and a tool's icon are worked
 * out by the pure helpers in window.PGWsNotePage.pure
 * (tests/workspace_note_page_test.php).
 *
 * Served minified inside assets/js/workspace.min.js (pg_js_bundles(),
 * includes/fn/output.php); after editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The class of a tool or group folded into the menu (backend.src.css,
    // under .ws-nb-page).
    var FOLDED = 'ws-nb-folded';

    // How many tools stand on the row, given where each ends (rights, from
    // the row's left edge, in its order), the row's inner width (limit) and
    // the room the More button takes (room, its width and the gap before
    // it). All of them when the last ends inside the row; else the leading
    // ones that end before the More button. A row not drawn (no width)
    // keeps every tool.
    function fit(rights, limit, room) {
        var count = rights.length;
        var stand = 0;

        if (!count || !(limit > 0) || rights[count - 1] <= limit) {
            return count;
        }

        while (stand < count && rights[stand] <= limit - room) {
            stand++;
        }

        return stand;
    }

    // The Bootstrap icon name in an icon's class list ('bi bi-type-bold'
    // gives 'bi-type-bold'); '' when there is none.
    function iconOf(className) {
        var found = /(?:^|\s)(bi-[\w-]+)/.exec(String(className || ''));

        return found ? found[1] : '';
    }

    // A hidden tool as a line of the menu: its icon (the caller's
    // data-ws-icon first, for an icon that is not a Bootstrap one), its name
    // and whether it is off now.
    function entry(node) {
        var glyph = node.querySelector('i');

        return {
            node: node,
            icon: node.getAttribute('data-ws-icon') || iconOf(glyph ? glyph.className : ''),
            label: node.getAttribute('aria-label') || node.title || node.textContent.trim(),
            disabled: !!node.disabled
        };
    }

    /**
     * Folds a row of tools (PGWsEditor.toolbar()) into one line.
     *
     * options: more (the More button, made by the caller and appended here
     * at the row's end) and open(more, entries), called on its click with
     * the hidden tools in order, each { node, icon, label, disabled }.
     */
    function fold(row, options) {
        var opts = options || {};
        var more = opts.more;
        var queued = false;
        var watcher = null;

        function tools() {
            return Array.prototype.filter.call(row.querySelectorAll('button'), function (node) {
                return node !== more;
            });
        }

        function groups() {
            return Array.prototype.filter.call(row.children, function (node) {
                return node.getAttribute('role') === 'group';
            });
        }

        function measure() {
            queued = false;

            if (!document.body.contains(row)) {
                if (watcher) {
                    watcher.disconnect();
                } else {
                    window.removeEventListener('resize', later);
                }

                return;
            }

            var all = tools();
            var parts = groups();

            all.concat(parts).forEach(function (node) {
                node.classList.remove(FOLDED);
            });
            more.classList.remove(FOLDED);

            var style = window.getComputedStyle(row);
            var padLeft = parseFloat(style.paddingLeft) || 0;
            var limit = row.clientWidth - padLeft - (parseFloat(style.paddingRight) || 0);
            var start = row.getBoundingClientRect().left + row.clientLeft + padLeft;
            var rights = all.map(function (node) {
                return node.getBoundingClientRect().right - start;
            });
            var stand = fit(rights, limit, more.offsetWidth + (parseFloat(style.columnGap) || 0));

            all.forEach(function (node, index) {
                node.classList.toggle(FOLDED, index >= stand);
            });

            parts.forEach(function (group) {
                group.classList.toggle(FOLDED, !group.querySelector('button:not(.' + FOLDED + ')'));
            });

            more.classList.toggle(FOLDED, stand >= all.length);
        }

        // Once a frame: a drag of the window's edge sends many.
        function later() {
            if (!queued) {
                queued = true;
                window.requestAnimationFrame(measure);
            }
        }

        more.classList.add('ws-nb-more', FOLDED);
        row.appendChild(more);
        more.addEventListener('click', function (event) {
            event.stopPropagation();

            if (opts.open) {
                opts.open(more, tools().filter(function (node) {
                    return node.classList.contains(FOLDED);
                }).map(entry));
            }
        });

        if (window.ResizeObserver) {
            watcher = new window.ResizeObserver(later);
            watcher.observe(row);
        } else {
            window.addEventListener('resize', later);
            later();
        }

        return row;
    }

    window.PGWsNotePage = {
        fold: fold,
        pure: {
            fit: fit,
            iconOf: iconOf
        }
    };
}());
