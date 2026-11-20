/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Options search part: "Search or add an option…"
 * finds a row in all three tabs of the Options panel by its label, its
 * section's name, the CSS property it writes and its class prefix, Turkish
 * letters folded (window.PGOptionsSearch), and lists the rows found with
 * their path; Up and Down move, Enter or a click opens the row (its tab,
 * its section, the focus in its control). When nothing is found it offers
 * the typed word as a class. Not needed to open a page: the core runs this
 * file the first time the box is searched (_sdPartLoad(),
 * pg_designer_parts()) and hands it the panel through a host
 * (_sdOptSearchHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('optsearch', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var OS = window.PGOptionsSearch;

    // At most this many results are drawn.
    var OS_MAX = 50;
    var _osState = { host: null, hits: [], active: 0, typed: '' };

    // The rows of the drawn panel as entries of the search, built once per
    // drawing of the panel (kept on its first child, which a redraw
    // replaces). A row's card is its nearest [data-sd-card]; `nth` counts
    // the card's own rows, as _sdOptSearchGo() finds the row again. A card
    // without rows of its own (Translation, Quick Layouts) is one entry
    // named by its title.
    function _osIndex(host) {
        var panel = host.panel;
        var top = panel && panel.firstElementChild;
        if (!top) return [];
        if (top._sdOsIdx) return top._sdOsIdx;
        var entries = [], counts = {};
        var titleOf = function (card) {
            var t = card.querySelector(':scope > .sd-sect-hdr .sd-sect-title');
            return t ? t.textContent.replace(/\s+/g, ' ').trim() : '';
        };
        var iconOf = function (card) {
            var i = card.querySelector(':scope > .sd-sect-hdr .sd-sect-title .bi');
            var m = i ? /\bbi-[a-z0-9-]+/.exec(i.className) : null;
            return m ? m[0] : 'bi-sliders';
        };
        var tabOf = function (el) {
            var pane = el.closest('[data-sd-otab]');
            return pane ? pane.getAttribute('data-sd-otab') : host.tab;
        };
        panel.querySelectorAll('.sd-prop-row, .sd-sp-row').forEach(function (osRow) {
            var card = osRow.closest('[data-sd-card]');
            var lbl = osRow.querySelector('.sd-prop-label, .sd-sp-label');
            if (!card || !lbl) return;
            var sid = card.getAttribute('data-sd-card');
            var nth = counts[sid] = (counts[sid] === undefined) ? 0 : counts[sid] + 1;
            var label = lbl.textContent.replace(/\s+/g, ' ').trim();
            if (!label) return;
            var grp = osRow.querySelector('[data-stylegroup]');
            var util = osRow.querySelector('[data-util]');
            var classes = grp ? grp.getAttribute('data-stylegroup') : (util ? [util.getAttribute('data-util') + '0', util.getAttribute('data-util') + '1'] : '');
            var prop = osRow.querySelector('[data-prop]');
            var prefix = OS.prefixOf(classes);
            var tab = tabOf(card);
            entries.push({
                label: label, section: titleOf(card), tab: tab, tabName: host.tabName(tab), sid: sid, nth: nth,
                prefix: prefix, css: OS.cssOf(prefix, classes, prop ? prop.getAttribute('data-prop') : ''),
                row: osRow, icon: iconOf(card)
            });
        });
        panel.querySelectorAll('[data-sd-card]').forEach(function (card) {
            var sid = card.getAttribute('data-sd-card');
            var title = titleOf(card);
            if (counts[sid] !== undefined || !title) return;
            var tab = tabOf(card);
            entries.push({ label: title, section: title, tab: tab, tabName: host.tabName(tab), sid: sid, nth: -1,
                prefix: '', css: '', row: null, icon: iconOf(card) });
        });
        top._sdOsIdx = entries;
        return entries;
    }

    // Shows a row the label filter hid (found by its CSS property or class
    // prefix) and the cards around it, their closed bodies opened for the
    // search as filterPropertiesPanel() opens them.
    function _osShowRow(panel, osRow) {
        if (!osRow) return;
        osRow.style.display = '';
        for (var p = osRow.parentElement; p && p !== panel; p = p.parentElement) {
            if (!p.classList.contains('sd-props-section') && !p.classList.contains('sd-app-sect')) continue;
            p.style.display = '';
            var body = p.querySelector(':scope > .sd-sect-body');
            if (body && body.style.display === 'none' && !body.hasAttribute('data-search-expanded')) {
                body.setAttribute('data-search-expanded', '1');
                body.style.display = '';
            }
        }
    }

    // `text` with the folded words marked where they stand.
    function _osMark(text, at, len) {
        text = String(text || '');
        if (at < 0 || !len) return esc(text);
        return esc(text.slice(0, at)) + '<mark>' + esc(text.slice(at, at + len)) + '</mark>' + esc(text.slice(at + len));
    }

    // The line under the "nothing found" line: "+ Add the class “…”", made on
    // first use after it; hidden while there is no class to offer.
    function _osAddLine(host, cls) {
        var line = document.getElementById('sd-props-search-add');
        if (!cls || !host.canAdd()) {
            if (line) line.hidden = true;
            return;
        }
        var empty = document.getElementById('sd-props-search-empty');
        if (!line) {
            if (!empty || !empty.parentElement) return;
            line = document.createElement('div');
            line.id = 'sd-props-search-add';
            line.className = 'sd-props-search-add';
            empty.parentElement.insertBefore(line, empty.nextSibling);
            line.addEventListener('click', function (e) {
                var b = e.target.closest ? e.target.closest('button[data-cls]') : null;
                if (b && _osState.host) _osState.host.addClass(b.getAttribute('data-cls'));
            });
        }
        line.innerHTML = '<button type="button" class="sd-osr-add" data-cls="' + esc(cls) + '">' +
            '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' + esc(_sdT('Add the class “{var}”', cls)) + '</button>';
        line.hidden = false;
    }

    function _osDraw() {
        var host = _osState.host, list = host.list, box = host.box;
        var hits = _osState.hits;
        if (!hits.length) {
            list.hidden = true;
            list.innerHTML = '';
            box.setAttribute('aria-expanded', 'false');
            box.removeAttribute('aria-activedescendant');
            return;
        }
        var h = '';
        hits.slice(0, OS_MAX).forEach(function (r, i) {
            var on = i === _osState.active;
            var code = OS.code(r, _osState.typed);
            var cAt = code ? OS.fold(code).indexOf(OS.words(_osState.typed).join(' ')) : -1;
            h += '<div class="sd-osr-it' + (on ? ' is-on' : '') + '" role="option" id="sd-osr-' + i + '" data-i="' + i + '" aria-selected="' + (on ? 'true' : 'false') + '">' +
                '<i class="bi ' + esc(r.e.icon) + ' sd-osr-ic" aria-hidden="true"></i>' +
                '<span class="sd-osr-tx"><b>' + _osMark(r.e.label, r.field === 'label' ? r.at : -1, r.len) + '</b>' +
                '<span>' + esc(OS.path(r.e)) + '</span></span>' +
                (code ? '<code>' + _osMark(code, cAt, cAt === -1 ? 0 : OS.words(_osState.typed).join(' ').length) + '</code>' : '') +
                (on ? '<kbd>Enter</kbd>' : '') +
            '</div>';
        });
        h += '<div class="sd-osr-ft" aria-hidden="true"><span><kbd>↑</kbd> <kbd>↓</kbd> ' + esc(_sdT('move')) + '</span>' +
            '<span><kbd>Enter</kbd> ' + esc(_sdT('open the row')) + '</span></div>';
        list.innerHTML = h;
        list.hidden = false;
        box.setAttribute('aria-expanded', 'true');
        box.setAttribute('aria-activedescendant', 'sd-osr-' + _osState.active);
        var act = list.querySelector('.sd-osr-it.is-on');
        if (act && act.scrollIntoView) act.scrollIntoView({ block: 'nearest' });
        if (!list._sdOs) {
            list._sdOs = true;
            // The pointer picks without taking the focus from the box.
            list.addEventListener('mousedown', function (e) { e.preventDefault(); });
            list.addEventListener('click', function (e) {
                var it = e.target.closest ? e.target.closest('.sd-osr-it') : null;
                if (it) _osPick(parseInt(it.getAttribute('data-i'), 10));
            });
        }
    }

    // Searches the drawn panel for what the box holds; a late call (the box
    // holds other words by now) does nothing.
    function search(host) {
        if (!host || !host.live()) return;
        _osState.host = host;
        _osState.typed = host.typed;
        var hits = OS.match(_osIndex(host), host.typed, 0);
        _osState.hits = hits;
        _osState.active = 0;
        hits.forEach(function (r) { _osShowRow(host.panel, r.e.row); });
        if (hits.length) {
            host.empty('');
            _osAddLine(host, '');
        } else {
            var nm = OS.noMatch(host.typed, host.has());
            host.empty(nm.say);
            _osAddLine(host, nm.add);
        }
        _osDraw();
    }

    // Up and Down move the active result, round the ends; Enter opens it.
    function key(k) {
        var n = Math.min(_osState.hits.length, OS_MAX);
        if (!n || !_osState.host) return;
        if (k === 'ArrowDown' || k === 'ArrowUp') {
            _osState.active = (_osState.active + (k === 'ArrowDown' ? 1 : n - 1)) % n;
            _osDraw();
        } else if (k === 'Enter') {
            _osPick(_osState.active);
        }
    }

    // Opens result `i`: what was open before the search decides whether its
    // card is marked "just opened" (a body opened only for the search was
    // closed).
    function _osPick(i) {
        var r = _osState.hits[i], host = _osState.host;
        if (!r || !host) return;
        var open = {};
        host.panel.querySelectorAll('[data-sd-card]').forEach(function (card) {
            var body = card.querySelector(':scope > .sd-sect-body');
            open[card.getAttribute('data-sd-card')] = !!body && !body.hasAttribute('data-search-expanded') && body.style.display !== 'none';
        });
        _osState.hits = [];
        host.go(OS.target(r.e, { tab: host.tab, open: open }), OS.boxSide(r.e.prefix));
    }

    return { search: search, key: key };
});
