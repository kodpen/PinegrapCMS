/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the Pages window: every page of the design, listed
 * by group with its address and state. The Pages button beside the page
 * strip and Ctrl+P open it. Its head holds "Add Page"; a search finds a
 * page by name or address with Turkish letters folded; the filters All,
 * Changed, Draft and Recently opened narrow the list. Up and Down walk the
 * rows, Enter opens one, F2 renames it, and a row dragged onto a group
 * moves the page into it (the strip's layout, saved through
 * designer/tab_layout). On a phone the window is a sheet from the bottom.
 * Not needed to open a page: the core runs this file the first time the
 * window is asked for (_sdPartLoad(), pg_designer_parts()) and hands it the
 * pages through a host (_pgPagesHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('pages', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // A group lists this many pages before its "N more pages" row, while
    // nothing is searched or filtered.
    var GROUP_ROWS = 8;
    var FILTERS = ['all', 'changed', 'draft', 'recent'];
    // win: the window element; rows: the rows drawn, in order; active: the
    // index of the row the keys act on; more: groups listed whole; folded:
    // groups closed with their head.
    var state = { host: null, win: null, filter: 'all', q: '', rows: [], active: 0, more: {}, folded: {}, renaming: '', dragKey: '' };

    // Folds a text for matching, one letter for one: lower case, the
    // Turkish dotted and dotless i alike, the letters with a mark as the
    // plain letter ("urun" finds "Ürün"). The same fold as the tree
    // search's.
    function _pwFold(text) {
        var from = 'ıİIşŞğĞçÇöÖüÜâÂîÎûÛ', to = 'iiissggccoouuaaiiuu';
        var s = String(text == null ? '' : text), out = '';
        for (var i = 0; i < s.length; i++) {
            var c = s.charAt(i), k = from.indexOf(c);
            if (k !== -1) { out += to.charAt(k); continue; }
            var low = c.toLowerCase();
            if (low.length !== 1) low = c;
            if (low.normalize) {
                var plain = low.normalize('NFD').charAt(0);
                if (plain) low = plain;
            }
            out += low;
        }
        return out;
    }

    // Whether a page answers the search: its name or its address holds the
    // words (folded). An empty search takes every page.
    function _pwMatch(row, q) {
        q = _pwFold(String(q || '').trim());
        if (!q) return true;
        return _pwFold(row.name).indexOf(q) !== -1 || _pwFold(row.address).indexOf(q) !== -1;
    }

    // What the window lists. rows: the pages in strip order ({key, id,
    // name, address, group, dirty, draft}); groups: [{id, name, color}] in
    // strip order, the pages without a group last as "Other" (id '');
    // recent: page keys, the latest first. opts: {filter, q}.
    // Answers {counts: {all, changed, draft, recent}, sections: [{id, name,
    // color, rows}]}: one section per group that keeps a page, or, for
    // Recently opened, one section without a head in the order they were
    // opened.
    function _pwList(rows, groups, recent, opts) {
        opts = opts || {};
        var filter = opts.filter || 'all';
        var byKey = {};
        rows.forEach(function (r) { byKey[r.key] = r; });
        var recentRows = [];
        (recent || []).forEach(function (k) { if (byKey[k] && recentRows.indexOf(byKey[k]) === -1) recentRows.push(byKey[k]); });
        var counts = {
            all: rows.length,
            changed: rows.filter(function (r) { return r.dirty; }).length,
            draft: rows.filter(function (r) { return r.draft; }).length,
            recent: recentRows.length
        };
        var keep = function (r) {
            if (filter === 'changed' && !r.dirty) return false;
            if (filter === 'draft' && !r.draft) return false;
            return _pwMatch(r, opts.q);
        };
        if (filter === 'recent') {
            var list = recentRows.filter(keep);
            return { counts: counts, sections: list.length ? [{ id: null, name: '', color: '', rows: list }] : [] };
        }
        var known = {};
        groups.forEach(function (g) { if (g.id) known[g.id] = true; });
        var sections = groups.map(function (g) {
            return {
                id: g.id, name: g.name, color: g.color,
                rows: rows.filter(function (r) { return (known[r.group] ? r.group : '') === g.id && keep(r); })
            };
        });
        return { counts: counts, sections: sections.filter(function (s) { return s.rows.length; }) };
    }

    // What a key does in the window, from the search box. n: rows drawn;
    // i: the active row. Answers {act: 'go', to}, {act: 'open'},
    // {act: 'rename'} or null (the box keeps the key).
    function _pwKeyAction(key, i, n, canRename) {
        if (!n) return null;
        if (key === 'ArrowDown') return { act: 'go', to: Math.min(n - 1, i + 1) };
        if (key === 'ArrowUp') return { act: 'go', to: Math.max(0, i - 1) };
        if (key === 'Enter') return { act: 'open' };
        if (key === 'F2') return canRename ? { act: 'rename' } : null;
        return null;
    }

    // A name typed into the rename box: trimmed; Escape keeps the old one.
    function _pwRenameValue(old, typed, commit) {
        return commit ? String(typed == null ? '' : typed).trim() : String(old == null ? '' : old);
    }

    // The group a row is dropped on: the section under the pointer, '' for
    // "Other". Null when the drop changes nothing (the page's own group, a
    // list without groups).
    function _pwDropGroup(row, sectionId) {
        if (!row || sectionId === null || sectionId === undefined) return null;
        return (row.group || '') === sectionId ? null : sectionId;
    }

    // ── Drawing ──
    function _pwFilterLabel(f) {
        return f === 'changed' ? _sdT('Changed') : f === 'draft' ? _sdT('Draft') : f === 'recent' ? _sdT('Recently opened') : _sdT('All');
    }

    function _pwRowHtml(r, idx) {
        var h = state.host;
        var cls = 'sd-pw-row' + (r.active ? ' sd-pw-current' : '') + (r.locked ? ' sd-pw-locked' : '');
        return '<div class="' + cls + '" role="option" id="sd-pw-row-' + idx + '" data-i="' + idx + '" data-key="' + esc(r.key) + '"' +
               (r.locked ? ' aria-disabled="true"' : '') +
               (h.canMove() && !r.locked ? ' draggable="true"' : '') + '>' +
               '<span class="sd-pw-dirty"' + (r.dirty ? ' title="' + esc(_sdT('Unsaved Changes')) + '"' : '') + ' aria-hidden="true"></span>' +
               '<span class="bi ' + (r.home ? 'bi-house' : 'bi-file-earmark') + ' sd-pw-icon"' + (r.home ? ' title="' + esc(_sdT('Home page')) + '"' : '') + ' aria-hidden="true"></span>' +
               '<span class="sd-pw-name">' + esc(r.name || _sdT('New Page')) + '</span>' +
               '<span class="sd-pw-addr">' + esc(r.address) + (r.active ? ' · ' + esc(_sdT('open')) : '') + '</span>' +
               '<span class="sd-pw-state">' +
                   (r.holder ? '<span class="sd-pw-holder"><span class="bi bi-lock" aria-hidden="true"></span>' + esc(_sdT('Open for editing by {name}').replace('{name}', r.holder)) + '</span>' : '') +
                   (r.draft ? '<span class="sd-tab-draft-badge">' + esc(_sdT('Draft')) + '</span>' : '') +
                   (r.badge || '') +
                   (r.locked ? '<span class="bi bi-lock-fill sd-pw-lock" aria-hidden="true"></span>' : '') +
                   '<kbd class="sd-pw-enter" aria-hidden="true">Enter</kbd>' +
               '</span>' +
               (r.locked ? '' : '<button type="button" class="sd-pw-more" data-more="' + esc(r.key) + '" title="' + esc(_sdT('Page options')) + '" aria-label="' + esc(_sdT('Page options')) + '"><span class="bi bi-three-dots-vertical" aria-hidden="true"></span></button>') +
               '</div>';
    }

    function _pwDraw() {
        var h = state.host, win = state.win;
        if (!h || !win) return;
        var rows = h.pages();
        var res = _pwList(rows, h.groups(), h.recent(), { filter: state.filter, q: state.q });
        var narrow = !!(state.q.trim() || state.filter !== 'all');
        var count = win.querySelector('.sd-pw-count');
        if (count) count.textContent = String(rows.length);
        var chips = '';
        FILTERS.forEach(function (f) {
            if (f === 'draft' && !h.draftsReady()) return;
            var n = res.counts[f];
            chips += '<button type="button" class="sd-pw-filter' + (state.filter === f ? ' active' : '') + '" data-filter="' + f + '" aria-pressed="' + (state.filter === f) + '">' +
                     (f === 'changed' ? '<span class="sd-pw-filter-dot" aria-hidden="true"></span>' : '') +
                     esc(_pwFilterLabel(f)) + (f === 'recent' ? '' : ' <span class="sd-pw-filter-n">' + n + '</span>') + '</button>';
        });
        win.querySelector('.sd-pw-filters').innerHTML = chips;
        var html = '', drawn = [];
        res.sections.forEach(function (s) {
            var folded = !narrow && s.id !== null && !!state.folded[s.id];
            if (s.id !== null) {
                html += '<div class="sd-pw-group" data-group="' + esc(s.id) + '">' +
                        '<button type="button" class="sd-pw-group-head" data-fold="' + esc(s.id) + '" aria-expanded="' + !folded + '">' +
                        '<span class="bi bi-chevron-right sd-pw-chev" aria-hidden="true"></span>' +
                        '<span class="sd-tab-color-dot sd-pw-group-dot" data-color="' + esc(s.id ? (s.color || 'grey') : 'grey') + '" aria-hidden="true"></span>' +
                        '<span class="sd-pw-group-name">' + esc(s.id ? s.name : _sdT('Other')) + '</span>' +
                        '<span class="sd-pw-group-n">' + s.rows.length + '</span></button>';
            } else {
                html += '<div class="sd-pw-group sd-pw-flat">';
            }
            if (!folded) {
                var list = (narrow || s.id === null || state.more[s.id]) ? s.rows : s.rows.slice(0, GROUP_ROWS);
                list.forEach(function (r) { html += _pwRowHtml(r, drawn.length); drawn.push(r); });
                if (list.length < s.rows.length) {
                    html += '<button type="button" class="sd-pw-rest" data-rest="' + esc(s.id) + '"><span class="bi bi-three-dots" aria-hidden="true"></span>' +
                            esc(_sdT('{n} more pages').replace('{n}', s.rows.length - list.length)) + '</button>';
                }
            }
            html += '</div>';
        });
        var box = win.querySelector('.sd-pw-list');
        box.innerHTML = html || '<div class="sd-pw-empty">' + esc(_sdT('No page matches.')) + '</div>';
        state.rows = drawn;
        if (state.active >= drawn.length) state.active = Math.max(0, drawn.length - 1);
        _pwActive(state.active, false);
    }

    // Marks the active row for the keys and the screen reader, and scrolls
    // it into view.
    function _pwActive(i, scroll) {
        var win = state.win;
        if (!win) return;
        state.active = i;
        var input = win.querySelector('.sd-pw-search');
        var prev = win.querySelector('.sd-pw-row.sd-pw-on');
        if (prev) prev.classList.remove('sd-pw-on');
        var el = win.querySelector('#sd-pw-row-' + i);
        if (!el) { input.removeAttribute('aria-activedescendant'); return; }
        el.classList.add('sd-pw-on');
        input.setAttribute('aria-activedescendant', el.id);
        if (scroll !== false && el.scrollIntoView) el.scrollIntoView({ block: 'nearest' });
    }

    function _pwOpenRow(i) {
        var r = state.rows[i];
        if (!r || r.locked) return;
        var h = state.host;
        close();
        h.open(r.key);
    }

    // F2: the row's name becomes a box; Enter or leaving it renames,
    // Escape keeps the name.
    function _pwRename(i) {
        var r = state.rows[i], h = state.host;
        if (!r || r.locked || !h.canRename()) return;
        var el = state.win.querySelector('#sd-pw-row-' + i + ' .sd-pw-name');
        if (!el || el.querySelector('input')) return;
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'sd-pw-rename';
        // The page_name column's length; the save refuses a longer name.
        inp.maxLength = 100;
        inp.value = r.name;
        inp.setAttribute('aria-label', _sdT('Rename'));
        el.textContent = '';
        el.appendChild(inp);
        state.renaming = r.key;
        var done = false;
        var finish = function (commit) {
            if (done) return;
            done = true;
            state.renaming = '';
            var val = _pwRenameValue(r.name, inp.value, commit);
            if (val !== r.name) h.rename(r.key, val);
            _pwDraw();
            var s = state.win && state.win.querySelector('.sd-pw-search');
            if (s) s.focus();
        };
        inp.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Enter') { e.preventDefault(); finish(true); }
            else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        });
        inp.addEventListener('blur', function () { finish(true); });
        inp.addEventListener('click', function (e) { e.stopPropagation(); });
        inp.focus();
        inp.select();
    }

    // The row's three-dot menu: Rename (F2's way on a phone), the groups to
    // move the page to (dragging's), and the strip's page menu.
    function _pwRowMenu(key, anchor) {
        _pwMenuClose();
        var h = state.host, win = state.win;
        var r = h.pages().filter(function (x) { return x.key === key; })[0];
        if (!r) return;
        var html = '';
        if (h.canRename()) html += '<button type="button" class="sd-pw-menu-item" data-act="rename"><span class="bi bi-pencil" aria-hidden="true"></span>' + esc(_sdT('Rename')) + '<kbd>F2</kbd></button>';
        if (h.canMove()) {
            html += '<div class="sd-pw-menu-head">' + esc(_sdT('Move to group')) + '</div>';
            h.groups().forEach(function (g) {
                var here = (r.group || '') === g.id;
                html += '<button type="button" class="sd-pw-menu-item" data-group-to="' + esc(g.id) + '"' + (here ? ' disabled aria-current="true"' : '') + '>' +
                        '<span class="sd-tab-color-dot" data-color="' + esc(g.id ? (g.color || 'grey') : 'grey') + '" aria-hidden="true"></span>' +
                        esc(g.id ? g.name : _sdT('Other')) + (here ? '<span class="bi bi-check2 ms-auto" aria-hidden="true"></span>' : '') + '</button>';
            });
        }
        html += '<div class="sd-pw-menu-sep"></div><button type="button" class="sd-pw-menu-item" data-act="page-menu"><span class="bi bi-three-dots" aria-hidden="true"></span>' + esc(_sdT('Page options')) + '</button>';
        var m = document.createElement('div');
        m.className = 'sd-pw-menu';
        m.setAttribute('role', 'menu');
        m.innerHTML = html;
        win.appendChild(m);
        var wr = win.getBoundingClientRect(), ar = anchor.getBoundingClientRect();
        m.style.top = Math.max(4, Math.min(ar.bottom - wr.top + 2, wr.height - m.offsetHeight - 4)) + 'px';
        m.style.right = Math.max(4, wr.right - ar.right) + 'px';
        m.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b || b.disabled) return;
            _pwMenuClose();
            if (b.dataset.act === 'rename') {
                for (var i = 0; i < state.rows.length; i++) if (state.rows[i].key === key) { _pwActive(i); _pwRename(i); return; }
            } else if (b.dataset.act === 'page-menu') {
                close();
                h.pageMenu(key, null);
            } else if (b.hasAttribute('data-group-to')) {
                h.move(key, b.getAttribute('data-group-to'));
                _pwDraw();
                win.querySelector('.sd-pw-search').focus();
            }
        });
        var first = m.querySelector('button:not([disabled])');
        if (first) first.focus();
        m.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); _pwMenuClose(); anchor.focus(); return; }
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            e.preventDefault();
            var btns = [].slice.call(m.querySelectorAll('button:not([disabled])'));
            var at = btns.indexOf(document.activeElement) + (e.key === 'ArrowDown' ? 1 : -1);
            btns[(at + btns.length) % btns.length].focus();
        });
    }
    function _pwMenuClose() {
        var m = state.win && state.win.querySelector('.sd-pw-menu');
        if (m) m.remove();
    }

    // Under the Pages button on a wide screen; a sheet from the bottom on
    // a phone (the stylesheet's .sd-pw-sheet).
    function _pwPlace() {
        var win = state.win, anchor = state.host.anchor();
        var sheet = window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches;
        win.classList.toggle('sd-pw-sheet', !!sheet);
        if (sheet) { win.style.left = win.style.top = ''; return; }
        var w = win.offsetWidth;
        var r = (anchor && anchor.offsetParent) ? anchor.getBoundingClientRect() : null;
        var left = r ? r.right - w : (window.innerWidth - w) / 2;
        win.style.left = Math.max(8, Math.min(left, window.innerWidth - w - 8)) + 'px';
        win.style.top = (r ? r.bottom + 6 : 56) + 'px';
    }

    function _pwBuild() {
        var h = state.host;
        var win = document.createElement('div');
        win.className = 'sd-pw';
        win.id = 'sd-pages-window';
        win.setAttribute('role', 'dialog');
        win.setAttribute('aria-label', _sdT('Pages'));
        var mac = /Mac|iPhone|iPad/.test(navigator.platform || '');
        win.innerHTML =
            '<div class="sd-pw-handle" aria-hidden="true"></div>' +
            '<div class="sd-pw-head">' +
                '<span class="bi bi-files sd-pw-head-icon" aria-hidden="true"></span>' +
                '<span class="sd-pw-title">' + esc(_sdT('Pages')) + '</span><span class="sd-pw-count"></span>' +
                (h.canAdd() ? '<button type="button" class="sd-pw-add"><span class="bi bi-plus-lg" aria-hidden="true"></span>' + esc(_sdT('Add Page')) + '</button>' : '') +
                '<button type="button" class="sd-pw-close" title="' + esc(_sdT('Close')) + '" aria-label="' + esc(_sdT('Close')) + '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>' +
            '</div>' +
            '<div class="sd-pw-find"><span class="bi bi-search" aria-hidden="true"></span>' +
                '<input type="search" class="sd-pw-search" role="combobox" aria-expanded="true" aria-controls="sd-pw-list" aria-autocomplete="list" autocomplete="off" spellcheck="false"' +
                ' placeholder="' + esc(_sdT('Type a name or an address…')) + '" aria-label="' + esc(_sdT('Type a name or an address…')) + '">' +
                '<kbd class="sd-pw-kbd" aria-hidden="true">' + (mac ? '⌘ P' : 'Ctrl P') + '</kbd></div>' +
            '<div class="sd-pw-filters" role="group" aria-label="' + esc(_sdT('Pages')) + '"></div>' +
            '<div class="sd-pw-list" id="sd-pw-list" role="listbox" aria-label="' + esc(_sdT('Pages')) + '"></div>' +
            '<div class="sd-pw-foot" aria-hidden="true"><kbd>↑</kbd><kbd>↓</kbd> ' + esc(_sdT('go through')) +
                ' <kbd>Enter</kbd> ' + esc(_sdT('to open')) + ' <kbd>F2</kbd> ' + esc(_sdT('to rename')) +
                (h.canMove() ? '<span class="sd-pw-foot-drag">' + esc(_sdT('drag: move to group')) + '</span>' : '') + '</div>';
        document.body.appendChild(win);
        state.win = win;
        var input = win.querySelector('.sd-pw-search');
        input.addEventListener('input', function () { state.q = input.value; state.active = 0; _pwDraw(); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                if (input.value) { input.value = ''; state.q = ''; _pwDraw(); }
                else close();
                return;
            }
            // The context-menu key or Shift+F10: the active row's page
            // menu, as a right-click.
            if (e.key === 'ContextMenu' || (e.key === 'F10' && e.shiftKey)) {
                var cur = state.rows[state.active];
                e.preventDefault();
                e.stopPropagation();
                if (cur && !cur.locked) { close(); h.pageMenu(cur.key, null); }
                return;
            }
            var a = _pwKeyAction(e.key, state.active, state.rows.length, h.canRename());
            if (!a) return;
            e.preventDefault();
            e.stopPropagation();
            if (a.act === 'go') _pwActive(a.to);
            else if (a.act === 'open') _pwOpenRow(state.active);
            else if (a.act === 'rename') _pwRename(state.active);
        });
        win.addEventListener('keydown', function (e) {
            // Editor shortcuts never see a key typed in the window.
            e.stopPropagation();
            if (e.key === 'Escape') { e.preventDefault(); close(); }
        });
        win.addEventListener('click', function (e) {
            var t = e.target;
            if (!t.closest('.sd-pw-menu')) _pwMenuClose();
            if (t.closest('.sd-pw-close')) { close(); return; }
            if (t.closest('.sd-pw-add')) { close(); h.add(); return; }
            var b = t.closest('[data-filter]');
            if (b) { state.filter = b.getAttribute('data-filter'); state.active = 0; _pwDraw(); input.focus(); return; }
            b = t.closest('[data-fold]');
            if (b) { var g = b.getAttribute('data-fold'); state.folded[g] = !state.folded[g]; _pwDraw(); input.focus(); return; }
            b = t.closest('[data-rest]');
            if (b) { state.more[b.getAttribute('data-rest')] = true; _pwDraw(); input.focus(); return; }
            b = t.closest('[data-more]');
            if (b) { e.stopPropagation(); _pwRowMenu(b.getAttribute('data-more'), b); return; }
            var row = t.closest('.sd-pw-row');
            if (row && !t.closest('input')) _pwOpenRow(parseInt(row.getAttribute('data-i'), 10));
        });
        // Right-click on a row: the strip's page menu, as the list before it.
        win.addEventListener('contextmenu', function (e) {
            var row = e.target.closest('.sd-pw-row');
            if (!row || row.classList.contains('sd-pw-locked')) return;
            e.preventDefault();
            var key = row.getAttribute('data-key');
            close();
            h.pageMenu(key, { x: e.clientX, y: e.clientY });
        });
        win.addEventListener('mousemove', function (e) {
            var row = e.target.closest('.sd-pw-row');
            if (row) { var i = parseInt(row.getAttribute('data-i'), 10); if (i !== state.active) _pwActive(i, false); }
        });
        // A row dragged onto a group: the page moves into it.
        win.addEventListener('dragstart', function (e) {
            var row = e.target.closest && e.target.closest('.sd-pw-row');
            if (!row || !h.canMove()) return;
            state.dragKey = row.getAttribute('data-key');
            row.classList.add('sd-pw-dragging');
            try { e.dataTransfer.setData('text/plain', state.dragKey); e.dataTransfer.effectAllowed = 'move'; } catch (x) { /* older browsers */ }
        });
        var dropOn = function (e) {
            var g = e.target.closest && e.target.closest('.sd-pw-group[data-group]');
            var r = state.rows.filter(function (x) { return x.key === state.dragKey; })[0];
            var to = g ? _pwDropGroup(r, g.getAttribute('data-group')) : null;
            return to === null ? null : { el: g, to: to };
        };
        var lit = function (el) {
            var old = win.querySelector('.sd-pw-drop');
            if (old && old !== el) old.classList.remove('sd-pw-drop');
            if (el) el.classList.add('sd-pw-drop');
        };
        win.addEventListener('dragover', function (e) {
            if (!state.dragKey) return;
            var d = dropOn(e);
            lit(d ? d.el : null);
            if (d) { e.preventDefault(); try { e.dataTransfer.dropEffect = 'move'; } catch (x) { /* older browsers */ } }
        });
        win.addEventListener('drop', function (e) {
            var d = state.dragKey ? dropOn(e) : null;
            if (!d) return;
            e.preventDefault();
            var key = state.dragKey;
            state.dragKey = '';
            h.move(key, d.to);
            _pwDraw();
            input.focus();
        });
        win.addEventListener('dragend', function () {
            state.dragKey = '';
            lit(null);
            var r = win.querySelector('.sd-pw-dragging');
            if (r) r.classList.remove('sd-pw-dragging');
        });
        // The sheet's handle pulled down closes it.
        var handle = win.querySelector('.sd-pw-handle'), y0 = null;
        handle.addEventListener('pointerdown', function (e) { y0 = e.clientY; handle.setPointerCapture && handle.setPointerCapture(e.pointerId); });
        handle.addEventListener('pointerup', function (e) { if (y0 !== null && e.clientY - y0 > 60) close(); y0 = null; });
    }

    function _pwOutside(e) {
        if (state.win && !state.win.contains(e.target) && !(state.host.anchor() && state.host.anchor().contains(e.target))) close();
    }
    function _pwResize() { if (state.win) _pwPlace(); }

    function open(host) {
        state.host = host;
        if (state.win) { close(); return; }
        state.q = '';
        state.more = {};
        _pwBuild();
        _pwDraw();
        // The open page's row is the active one.
        for (var i = 0; i < state.rows.length; i++) if (state.rows[i].active) { state.active = i; break; }
        _pwPlace();
        _pwActive(state.active);
        var a = host.anchor();
        if (a) a.setAttribute('aria-expanded', 'true');
        state.win.querySelector('.sd-pw-search').focus({ preventScroll: true });
        setTimeout(function () { document.addEventListener('mousedown', _pwOutside, true); }, 0);
        window.addEventListener('resize', _pwResize);
    }

    function close() {
        if (!state.win) return;
        document.removeEventListener('mousedown', _pwOutside, true);
        window.removeEventListener('resize', _pwResize);
        state.win.remove();
        state.win = null;
        state.rows = [];
        var a = state.host && state.host.anchor();
        if (a) {
            a.setAttribute('aria-expanded', 'false');
            if (a.offsetParent) a.focus({ preventScroll: true });
        }
    }

    function isOpen() { return !!state.win; }

    return { open: open, close: close, isOpen: isOpen };
});
