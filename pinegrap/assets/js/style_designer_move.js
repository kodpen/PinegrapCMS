/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Move…" window part: moves the selected elements
 * without dragging. The target is searched by name and listed with its
 * path; Before, Into or After picks the place, "Move" moves. A place the
 * editor refuses is said in words before anything moves. Opened from an
 * Overview row's button or menu, the selected element's canvas toolbar and
 * the M key. Not needed to open a page: the core runs this file the first
 * time the window is asked for (_sdPartLoad(), pg_designer_parts()) and
 * hands it the page and the move through a host (_sdMoveHost()). On a
 * phone the window is a sheet from the bottom.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('move', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // At most this many targets are listed; the search narrows the rest.
    var MAX_SHOWN = 80;
    var state = { host: null, entries: [], hits: [], active: -1, target: null, pos: 'after', el: null };

    // Folds a name for matching: lower case, the Turkish dotted and dotless
    // i alike, a letter with a mark as the plain letter.
    function _mvFold(text) {
        var s = String(text == null ? '' : text).replace(/[İIı]/g, 'i').toLowerCase();
        return s.normalize ? s.normalize('NFD').replace(/[̀-ͯ]/g, '') : s;
    }

    // Every element the moved ones could go next to or into, in the
    // Overview's order, each with its name and its path (the names from its
    // section down to its parent). The page body comes first; the moved
    // elements and what they hold are left out.
    function _mvIndex(root, kidsOf, nameOf, skip) {
        var out = [{ node: root, name: '<body>', fold: _mvFold('<body>'), path: [], depth: 0 }];
        (function walk(n, path, depth) {
            kidsOf(n).forEach(function (c) {
                if (skip.indexOf(c) !== -1) return;
                var name = nameOf(c);
                out.push({ node: c, name: name, fold: _mvFold(name), path: path, depth: depth });
                walk(c, path.concat([name]), depth + 1);
            });
        })(root, [], 1);
        return out;
    }

    // The entries whose name holds every word of `q`, a name that starts
    // with the words first; each hit is { i, at, len }: the entry and where
    // the first word stands in its name.
    function _mvMatch(entries, q) {
        var words = _mvFold(q).split(/\s+/).filter(Boolean);
        if (!words.length) return entries.map(function (e, i) { return { i: i, at: -1, len: 0 }; });
        var starts = [], holds = [];
        entries.forEach(function (e, i) {
            for (var w = 0; w < words.length; w++) if (e.fold.indexOf(words[w]) === -1) return;
            var at = e.fold.indexOf(words[0]);
            (at === 0 ? starts : holds).push({ i: i, at: at, len: words[0].length });
        });
        return starts.concat(holds);
    }

    // A path longer than four steps: its section, a gap and the last two.
    function _mvPathCut(path) {
        return (path.length > 4) ? [path[0], '…'].concat(path.slice(path.length - 2)) : path.slice();
    }

    // `text` escaped, with `len` letters from `at` marked.
    function _mvMark(text, at, len) {
        if (at < 0 || !len) return esc(text);
        return esc(text.slice(0, at)) + '<mark>' + esc(text.slice(at, at + len)) + '</mark>' + esc(text.slice(at + len));
    }

    function _mvBuild() {
        var d = document.createElement('dialog');
        d.className = 'dialog sd-settings-modal-dark sd-modal-content sd-move-dialog';
        d.tabIndex = -1;
        d.setAttribute('aria-labelledby', 'sd-move-title');
        var places = [['before', _sdT('Before')], ['inside', _sdT('Into')], ['after', _sdT('After')]];
        d.innerHTML =
            '<div class="sd-move-handle" aria-hidden="true"></div>' +
            '<div class="dialog-header sd-modal-header sd-move-head">' +
                '<span class="bi bi-arrows-move sd-move-icon" aria-hidden="true"></span>' +
                '<h5 class="dialog-title sd-move-title" id="sd-move-title"></h5>' +
                '<button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button>' +
            '</div>' +
            '<div class="dialog-body sd-modal-body sd-move-body">' +
                '<div class="sd-move-search"><span class="bi bi-search" aria-hidden="true"></span>' +
                    '<input type="search" class="sd-move-input" role="combobox" aria-expanded="true" aria-controls="sd-move-list" autocomplete="off" ' +
                    'placeholder="' + esc(_sdT('Search by name…')) + '" aria-label="' + esc(_sdT('Search by name…')) + '"></div>' +
                '<ul class="sd-move-list" id="sd-move-list" role="listbox" aria-label="' + esc(_sdT('Where to')) + '"></ul>' +
            '</div>' +
            '<div class="dialog-footer sd-modal-footer sd-move-foot">' +
                '<div class="sd-move-places" role="radiogroup" aria-label="' + esc(_sdT('Place')) + '">' +
                    places.map(function (p) {
                        return '<button type="button" class="sd-move-place" role="radio" data-pos="' + p[0] + '">' + esc(p[1]) + '</button>';
                    }).join('') +
                '</div>' +
                '<div class="sd-move-why" aria-live="polite"></div>' +
                '<button type="button" class="btn btn-sm btn-solid theme-primary sd-move-go">' + esc(_sdT('Move')) + '</button>' +
            '</div>';
        document.body.appendChild(d);
        var input = d.querySelector('.sd-move-input');
        input.addEventListener('input', function () { _mvSearch(input.value); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                _mvActivate(state.active + (e.key === 'ArrowDown' ? 1 : -1));
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (state.target) _mvGo();
                else if (state.hits[state.active]) _mvPick(state.hits[state.active].i);
            }
        });
        d.querySelector('.sd-move-list').addEventListener('click', function (e) {
            var li = e.target.closest('li[data-i]');
            if (li) _mvPick(parseInt(li.getAttribute('data-i'), 10));
        });
        d.querySelector('.sd-move-places').addEventListener('click', function (e) {
            var b = e.target.closest('[data-pos]');
            if (b) { state.pos = b.getAttribute('data-pos'); _mvJudge(); }
        });
        d.querySelector('.sd-move-go').addEventListener('click', _mvGo);
        // The sheet's handle pulled down 60 px closes it.
        var handle = d.querySelector('.sd-move-handle'), y0 = null;
        handle.addEventListener('pointerdown', function (e) { y0 = e.clientY; });
        d.addEventListener('pointerup', function (e) {
            if (y0 !== null && e.clientY - y0 > 60) _mvClose();
            y0 = null;
        });
        return d;
    }

    function _mvClose() {
        var d = state.el;
        if (!d) return;
        if (window.pgUI && pgUI.dialog.find && pgUI.dialog.find(d)) pgUI.dialog.hide(d);
        else if (d.open && d.close) d.close();
    }

    function _mvSearch(q) {
        state.hits = _mvMatch(state.entries, q);
        var ul = state.el.querySelector('.sd-move-list');
        var shown = state.hits.slice(0, MAX_SHOWN);
        ul.innerHTML = shown.map(function (h, k) {
            var e = state.entries[h.i];
            var path = _mvPathCut(e.path);
            var on = state.target === e.node;
            return '<li role="option" id="sd-move-o' + k + '" data-i="' + h.i + '" aria-selected="' + (on ? 'true' : 'false') + '"' +
                (on ? ' class="sd-move-on"' : '') + '>' +
                '<span class="sd-move-tile">' + (e.node === state.host.root ? '<span class="bi bi-code-slash"></span>' : state.host.icon(e.node)) + '</span>' +
                '<span class="sd-move-txt"><span class="sd-move-name">' + _mvMark(e.name, h.at, h.len) + '</span>' +
                (path.length ? '<span class="sd-move-path">' + path.map(esc).join(' › ') + '</span>' : '') + '</span>' +
                (on ? '<span class="bi bi-check-lg sd-move-check" aria-hidden="true"></span>' : '') +
                '</li>';
        }).join('') + (state.hits.length > MAX_SHOWN
            ? '<li class="sd-move-more" aria-disabled="true">' + esc(_sdT('{var} more: type to narrow', state.hits.length - MAX_SHOWN)) + '</li>'
            : (state.hits.length ? '' : '<li class="sd-move-more" aria-disabled="true">' + esc(_sdT('No element by that name')) + '</li>'));
        _mvActivate(state.hits.length ? 0 : -1);
    }

    function _mvActivate(k) {
        var n = Math.min(state.hits.length, MAX_SHOWN);
        if (!n) { state.active = -1; return; }
        state.active = Math.max(0, Math.min(n - 1, k));
        var input = state.el.querySelector('.sd-move-input');
        state.el.querySelectorAll('.sd-move-list li.sd-move-act').forEach(function (x) { x.classList.remove('sd-move-act'); });
        var li = state.el.querySelector('#sd-move-o' + state.active);
        if (!li) return;
        li.classList.add('sd-move-act');
        input.setAttribute('aria-activedescendant', li.id);
        if (li.scrollIntoView) li.scrollIntoView({ block: 'nearest' });
    }

    function _mvPick(i) {
        var e = state.entries[i];
        if (!e) return;
        state.target = e.node;
        // Nothing goes before or after the page body.
        if (e.node === state.host.root) state.pos = 'inside';
        _mvSearch(state.el.querySelector('.sd-move-input').value);
        _mvJudge();
    }

    // The place buttons, the reason a place is refused and the Move button.
    function _mvJudge() {
        var d = state.el;
        d.querySelectorAll('.sd-move-place').forEach(function (b) {
            var on = b.getAttribute('data-pos') === state.pos;
            b.classList.toggle('sd-move-place-on', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        var why = d.querySelector('.sd-move-why');
        var go = d.querySelector('.sd-move-go');
        if (!state.target) {
            why.textContent = _sdT('Choose where to move it');
            why.classList.remove('sd-move-refused');
            go.disabled = true;
            return;
        }
        var c = state.host.check(state.target, state.pos);
        why.textContent = c.code ? c.why : '';
        why.classList.toggle('sd-move-refused', !!c.code);
        go.disabled = !!c.code;
    }

    function _mvGo() {
        if (!state.target) return;
        var c = state.host.check(state.target, state.pos);
        if (c.code) { _mvJudge(); return; }
        _mvClose();
        state.host.move(state.target, state.pos);
    }

    function open(host) {
        state.host = host;
        state.target = null;
        state.pos = 'after';
        state.entries = _mvIndex(host.root, host.kids, function (n) {
            var f = host.fields(n);
            return f.label || f.name;
        }, host.nodes);
        if (!state.el) state.el = _mvBuild();
        var d = state.el;
        d.querySelector('.sd-move-title').textContent = _sdT('Where should {var} move?', host.name);
        var input = d.querySelector('.sd-move-input');
        input.value = '';
        _mvSearch('');
        _mvJudge();
        if (window.pgUI) pgUI.dialog.show(d);
        else if (d.showModal) d.showModal();
        setTimeout(function () { input.focus(); }, 0);
    }

    return { open: open };
});
