/**
 * Pinegrap - Enterprise Website Platform
 *
 * Workspace - the Work Calendar's cells (workspace_calendar.php,
 * startCalendar() in assets/js/workspace.js): how many items a day shows
 * before "+N more", the floating card of the day's list that "+N more"
 * opens beside its cell, and the side column of figures folded away behind
 * the bar's "Figures" button, its choice kept per browser.
 *
 * assets/js/workspace.js calls it with its helpers (partHelpers()).
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

    // The items a day shows before "+N more".
    var LIMIT = 3;

    var FIGURES_KEY = 'pg.ws.calendar.figures';

    // Below this width the side column stands under the grid (xl:col-*):
    // there the figures start folded and the choice is not kept.
    var STACKED = '(max-width: 1279.98px)';

    var open = null;

    function stacked() {
        return !!(window.matchMedia && window.matchMedia(STACKED).matches);
    }

    function readFigures() {
        try {
            return window.localStorage.getItem(FIGURES_KEY) || '';
        } catch (error) {
            return '';
        }
    }

    function keepFigures(value) {
        try {
            window.localStorage.setItem(FIGURES_KEY, value);
        } catch (error) {
            // A private window keeps no choice; the figures show next time.
        }
    }

    // The "Figures" button of the bar folds the side column away and gives
    // its width to the grid. Wide, the choice is kept per browser (shown by
    // default); with the column under the grid it starts folded each time.
    function figures(h, mainCol, sideCol) {
        var toggle = h.button('pg-button pg-button-quiet pg-button-sm ws-cal-figures-toggle', '', 'bi-bar-chart-line');
        var label = h.el('span', 'ws-bar-label', h.t('cal_figures'));
        var shown = stacked() ? false : (readFigures() !== 'hidden');

        toggle.appendChild(label);
        sideCol.id = sideCol.id || 'ws-cal-figures';
        toggle.setAttribute('aria-controls', sideCol.id);

        function apply() {
            sideCol.classList.toggle('d-none', !shown);
            mainCol.classList.toggle('xl:col-9', shown);
            mainCol.classList.toggle('xl:col-12', !shown);
            toggle.setAttribute('aria-expanded', shown ? 'true' : 'false');
            toggle.title = shown ? h.t('cal_figures_hide') : h.t('cal_figures_show');
        }

        toggle.addEventListener('click', function () {
            shown = !shown;

            if (!stacked()) {
                keepFigures(shown ? 'shown' : 'hidden');
            }

            apply();

            if (shown && stacked()) {
                sideCol.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            }
        });

        apply();

        return toggle;
    }

    function close(giveFocus) {
        if (!open) {
            return;
        }

        var was = open;

        open = null;
        was.card.remove();
        document.removeEventListener('mousedown', was.outside, true);
        document.removeEventListener('keydown', was.keys, true);
        window.removeEventListener('resize', was.closeNow);
        window.removeEventListener('scroll', was.closeNow, true);
        was.opener.setAttribute('aria-expanded', 'false');

        if (giveFocus && document.body.contains(was.opener)) {
            was.opener.focus();
        }
    }

    // Beside the cell: to its right where the window has room, else to its
    // left, its top at the cell's top and kept inside the window.
    function place(card, cell) {
        var box = cell.getBoundingClientRect();
        var width = card.offsetWidth;
        var height = card.offsetHeight;
        var gap = 6;
        var left = box.right + gap;

        if (left + width > window.innerWidth - 8) {
            left = box.left - gap - width;
        }

        if (left < 8) {
            left = Math.max(8, Math.min(window.innerWidth - width - 8, box.left));
        }

        var top = Math.max(8, Math.min(box.top, window.innerHeight - height - 8));

        card.style.left = Math.round(left) + 'px';
        card.style.top = Math.round(top) + 'px';
    }

    // One line of the card: look A's list row, its icon, title, time and the
    // line under it; Enter or a click opens what it names.
    function row(h, item) {
        var line = h.el(item.href ? 'a' : 'div', 'pg-list-row ws-cal-more-row' + (item.done ? ' is-done' : ''));
        var main = h.el('div', 'pg-list-row-main');
        var head = h.el('div', 'pg-list-row-head');

        if (item.href) {
            line.href = item.href;
        }

        line.appendChild(item.icon);
        head.appendChild(h.el('span', 'pg-list-row-title', item.title));

        if (item.time) {
            head.appendChild(h.el('span', 'pg-list-row-time pg-tabular', item.time));
        }

        main.appendChild(head);

        if (item.sub) {
            main.appendChild(h.el('div', 'pg-list-row-text', item.sub));
        }

        line.appendChild(main);

        if (item.open) {
            var go = function () {
                close(false);
                item.open();
            };

            line.addEventListener('click', go);
            h.pressKeys(line, go);
        } else if (item.href) {
            line.addEventListener('click', function () { close(false); });
        }

        if (item.menu && h.onContext) {
            h.onContext(line, item.menu);
        }

        return line;
    }

    // "+N more" of a day: a floating card beside the cell with the day's
    // whole list, each line opening what it names. A second press closes it.
    function more(h, opener, cell, title, items, showDay) {
        if (open && open.opener === opener) {
            close(true);
            return;
        }

        close(false);

        var card = h.el('div', 'pg-card ws-cal-more-card');
        var head = h.el('div', 'ws-cal-more-head');
        var heading = h.el('h3', 'pg-title-section ws-grow', title);
        var shut = h.el('button', 'pg-icon-button pg-icon-button-sm');
        var list = h.el('div', 'ws-cal-more-list');

        heading.id = 'ws-cal-more-title';
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-labelledby', heading.id);
        shut.type = 'button';
        shut.setAttribute('aria-label', h.t('close'));
        shut.appendChild(h.icon('bi-x-lg'));
        shut.addEventListener('click', function () { close(true); });
        head.appendChild(heading);
        head.appendChild(shut);
        card.appendChild(head);

        items.forEach(function (item) {
            list.appendChild(row(h, item));
        });

        card.appendChild(list);

        if (showDay) {
            var all = h.button('pg-button pg-button-ghost pg-button-sm ws-cal-more-day', h.t('show_day'), 'bi-list-ul');

            all.addEventListener('click', function () {
                close(false);
                showDay();
            });
            card.appendChild(all);
        }

        (window.pgUI ? window.pgUI.overlayHost(cell) : document.body).appendChild(card);
        place(card, cell);

        open = {
            card: card,
            opener: opener,
            outside: function (event) {
                var target = event.target;

                // A right-click menu of a line stands outside the card.
                if (card.contains(target) || opener.contains(target) || (target.closest && target.closest('.ws-ctx'))) {
                    return;
                }

                close(false);
            },
            keys: function (event) {
                if (event.key === 'Escape' && !document.querySelector('.ws-ctx')) {
                    event.preventDefault();
                    event.stopPropagation();
                    close(true);
                }
            },
            closeNow: function (event) {
                // A scroll inside the card's own list keeps it open.
                if (event && event.type === 'scroll' && event.target && event.target.nodeType === 1 && card.contains(event.target)) {
                    return;
                }

                close(false);
            }
        };

        opener.setAttribute('aria-expanded', 'true');
        document.addEventListener('mousedown', open.outside, true);
        document.addEventListener('keydown', open.keys, true);
        window.addEventListener('resize', open.closeNow);
        window.addEventListener('scroll', open.closeNow, true);

        var first = list.querySelector('.pg-list-row');

        (first || shut).focus();
    }

    // The "+N more" button of a cell: it opens the card, not the cell's day.
    function moreButton(h, hidden, onOpen) {
        var button = h.el('button', 'ws-cal-more', h.t('more_count', hidden));

        button.type = 'button';
        button.setAttribute('aria-haspopup', 'dialog');
        button.setAttribute('aria-expanded', 'false');
        button.addEventListener('click', function (event) {
            event.stopPropagation();
            onOpen(button);
        });
        button.addEventListener('keydown', function (event) {
            // The cell's own Enter would open the day's drawer as well.
            event.stopPropagation();
        });

        return button;
    }

    window.PGWsCalendarCells = { LIMIT: LIMIT, figures: figures, more: more, moreButton: moreButton, close: close };
})();
