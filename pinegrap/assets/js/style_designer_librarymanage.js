/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - "Manage library": one window over My library in the
 * order the Blocks list draws it, to rename an item, move items to a group,
 * order them by the grip or Alt with an arrow, and delete them (the only
 * step that asks). Export and Import call the part 'libraryfile'. Every
 * change is sent as it is made (designer/library_rename, library_move,
 * library_order, library_delete) and the answer is the list drawn again.
 * Not needed to open a page: the part 'mylibrary' runs this file the first
 * time "Manage library" is chosen (_sdPartLoad(), pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('librarymanage', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // ── Manage library ─────────────────────────────────────────────────
    // One window over the library in its drawn order: rename, move to a
    // group, order by the grip or Alt with an arrow, delete (the only step
    // that asks), and the file's Export and Import, which are the part
    // 'libraryfile''s when the screen hands it over. Every change is sent
    // as it is made; the server's answer is the list drawn again.

    // mg: the open window, null while shut. chosen: id => true. L: the
    // library's handle from the part 'mylibrary'.
    var mg = null;
    var L = null;

    // The items as both lists draw them: no group first, then each group
    // where its first item stands (pg_library_drawn_order()).
    function _drawn(items) {
        var out = (items || []).filter(function (it) { return !it.group; });
        _groupsOf(items).forEach(function (g) {
            (items || []).forEach(function (it) { if (it.group === g) out.push(it); });
        });
        return out;
    }

    function _groupsOf(items) {
        var seen = {}, out = [];
        (items || []).forEach(function (it) {
            if (it.group && !seen[it.group]) { seen[it.group] = true; out.push(it.group); }
        });
        return out;
    }

    function _hasFile() {
        return !!(K._sdPartUrls && K._sdPartUrls('libraryfile'));
    }

    function _file(method, args) {
        return K._sdPartCall('libraryfile', method, args).catch(K._sdPartFailed);
    }

    function _chosenIds() {
        return _drawn(L.items()).filter(function (it) { return mg && mg.chosen[it.id]; }).map(function (it) { return it.id; });
    }

    // The window over the library the part 'mylibrary' hands over (L).
    function open(handle) {
        if (handle) L = handle;
        if (!L || K._sdAccess() !== 'full') return;
        if (mg) { mg.el.focus(); return; }
        _mgOpen();
    }

    function _mgOpen() {
        var el = document.createElement('dialog');
        el.className = 'dialog pg-dialog-centered dialog-scrollable sd-lib-manage';
        el.tabIndex = -1;
        el.setAttribute('aria-labelledby', 'sd-lib-manage-title');
        el.innerHTML =
            '<div class="dialog-header py-3"><div class="min-w-0"><h6 class="dialog-title mb-0" id="sd-lib-manage-title">' + esc(_sdT('Manage library')) + '</h6>' +
                    '<div class="small fg-secondary" data-mg-count></div></div>' +
                '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button></div>' +
            '<div class="dialog-body small">' +
                '<input type="search" class="pg-input" data-mg-search autocomplete="off" placeholder="' + esc(_sdT('Search the library…')) + '" aria-label="' + esc(_sdT('Search the library…')) + '">' +
                '<p class="fg-secondary mt-2 mb-0" data-mg-none hidden>' + esc(_sdT('No item matches')) + '</p>' +
                '<div class="fg-danger mt-2" data-mg-error role="alert" hidden></div>' +
                '<div class="sd-lib-mlist mt-2" data-mg-list role="listbox" aria-multiselectable="true" aria-label="' + esc(_sdT('My library')) + '"></div>' +
            '</div>' +
            '<div class="dialog-footer py-3" data-mg-foot></div>';
        document.body.appendChild(el);
        mg = { el: el, chosen: {}, last: null, query: '', busy: false };
        var list = el.querySelector('[data-mg-list]');
        var search = el.querySelector('[data-mg-search]');

        search.addEventListener('input', function () { mg.query = L.fold(search.value.trim()); _mgFilter(); });
        list.addEventListener('click', _mgClick);
        list.addEventListener('dblclick', function (e) {
            var name = e.target.closest('.sd-lib-mname');
            if (name) _mgRename(name.closest('.sd-lib-mrow'));
        });
        list.addEventListener('keydown', _mgKey);
        list.addEventListener('pointerdown', _mgGrip);
        el.querySelector('[data-mg-foot]').addEventListener('click', _mgFoot);
        el.addEventListener('keydown', function (e) {
            // Escape shuts an open menu or a rename first, not the window.
            if (e.key === 'Escape' && (el.querySelector('.sd-lib-pop') || el.querySelector('.sd-lib-mrename'))) {
                e.preventDefault();
                e.stopPropagation();
                _mgPopClose();
            }
        });
        pgUI.dialog.on(el, 'shown', function () { search.focus(); });
        L.changed = function () { _mgDraw(); };
        pgUI.dialog.on(el, 'hidden', function () { el.remove(); mg = null; L.changed = null; });
        _mgDraw();
        pgUI.dialog.show(el);
    }

    function _mgRowHTML(it) {
        var on = !!mg.chosen[it.id];
        return '<div class="sd-lib-mrow' + (on ? ' is-chosen' : '') + '" role="option" tabindex="-1" aria-selected="' + (on ? 'true' : 'false') + '" data-id="' + it.id + '"' +
                ' data-search="' + esc(L.fold(it.name + ' ' + (it.group || ''))) + '">' +
            '<span class="sd-lib-grip bi bi-grip-vertical" title="' + esc(_sdT('Drag to change the order')) + '" aria-hidden="true"></span>' +
            '<input type="checkbox" class="check m-0" tabindex="-1"' + (on ? ' checked' : '') + ' aria-label="' + esc(_sdT('Choose "{var}"', it.name)) + '">' +
            '<span class="sd-lib-thumb" aria-hidden="true">' + (K._sdBlockSketch(it.tree) || '') + '</span>' +
            '<span class="sd-lib-mtext"><span class="sd-lib-mname">' + esc(it.name) + '</span>' +
                '<span class="sd-lib-mmeta">' + esc(L.countLine(it.kind, it.count)) + '</span></span>' +
            '<button type="button" class="pg-button pg-button-ghost pg-button-icon sd-lib-mmore" data-mg-more tabindex="-1" aria-label="' + esc(_sdT('More for "{var}"', it.name)) + '">' +
                '<span class="bi bi-three-dots" aria-hidden="true"></span></button>' +
        '</div>';
    }

    function _mgHeadHTML(group, count) {
        return '<div class="sd-lib-mgroup" data-group="' + esc(group) + '">' + esc(group || _sdT('No group')) +
            '<span class="sd-lib-count">' + count + '</span></div>';
    }

    // The whole list from the library's items; the focused row keeps the focus.
    function _mgDraw(focusId) {
        if (!mg) return;
        var items = L.items();
        var list = mg.el.querySelector('[data-mg-list]');
        if (focusId == null && list.contains(document.activeElement)) {
            var was = document.activeElement.closest('.sd-lib-mrow');
            if (was) focusId = parseInt(was.getAttribute('data-id'), 10);
        }
        Object.keys(mg.chosen).forEach(function (id) { if (!L.find(id)) delete mg.chosen[id]; });
        var loose = items.filter(function (it) { return !it.group; });
        var html = '';
        if (loose.length) html += _mgHeadHTML('', loose.length) + loose.map(_mgRowHTML).join('');
        _groupsOf(items).forEach(function (g) {
            var rows = items.filter(function (it) { return it.group === g; });
            html += _mgHeadHTML(g, rows.length) + rows.map(_mgRowHTML).join('');
        });
        list.innerHTML = html;
        mg.el.querySelector('[data-mg-count]').textContent = items.length === 1 ? _sdT('1 item') : _sdT('{var} items', items.length);
        var first = list.querySelector('.sd-lib-mrow[data-id="' + focusId + '"]') || list.querySelector('.sd-lib-mrow');
        if (first) first.tabIndex = 0;
        if (focusId != null && first) first.focus();
        _mgFilter();
        _mgFootDraw();
    }

    // Search narrows the rows by name and group; a group's head stays
    // while one of its rows does. Ordering waits while a search is typed.
    function _mgFilter() {
        var list = mg.el.querySelector('[data-mg-list]');
        var q = mg.query, shown = 0;
        list.classList.toggle('is-searching', !!q);
        list.querySelectorAll('.sd-lib-mrow').forEach(function (row) {
            var on = !q || row.getAttribute('data-search').indexOf(q) !== -1;
            row.hidden = !on;
            if (on) shown++;
        });
        list.querySelectorAll('.sd-lib-mgroup').forEach(function (head) {
            var on = false;
            for (var el = head.nextElementSibling; el && !el.classList.contains('sd-lib-mgroup'); el = el.nextElementSibling) {
                if (!el.hidden) on = true;
            }
            head.hidden = !on;
        });
        mg.el.querySelector('[data-mg-none]').hidden = !(q && !shown && L.items().length);
    }

    // The foot follows the choice: nothing chosen, Import and Export all;
    // items chosen, their count with Export, Move to group and Delete.
    function _mgFootDraw() {
        var n = _chosenIds().length;
        var file = _hasFile();
        var btn = function (act, label, cls) {
            return '<button type="button" class="pg-button ' + (cls || 'pg-button-quiet') + '" data-mg="' + act + '">' + esc(label) + '</button>';
        };
        var html = n
            ? '<span class="sd-lib-mnote me-auto fw-semibold">' + esc(n === 1 ? _sdT('1 selected') : _sdT('{var} selected', n)) + '</span>' +
                (file ? btn('export', _sdT('Export')) : '') + btn('move', _sdT('Move to group')) + btn('delete', _sdT('Delete'), 'pg-button-danger')
            : '<span class="sd-lib-mnote me-auto small fg-secondary">' + esc(_sdT('Changes are saved as you make them.')) + '</span>' +
                (file ? btn('import', _sdT('Import')) + (L.items().length ? btn('export-all', _sdT('Export all')) : '') : '');
        mg.el.querySelector('[data-mg-foot]').innerHTML = html + btn('done', _sdT('Done'), 'pg-button-fill sd-lib-mdone');
    }

    function _mgFoot(e) {
        var b = e.target.closest('[data-mg]');
        if (!b || mg.busy) return;
        var act = b.getAttribute('data-mg');
        if (act === 'done') { pgUI.dialog.hide(mg.el); return; }
        if (act === 'import') { _file('importFile', []); return; }
        if (act === 'export-all') { _file('exportItems', [[]]); return; }
        if (act === 'export') { _file('exportItems', [_chosenIds()]); return; }
        if (act === 'move') { _mgMoveMenu(b, _chosenIds()); return; }
        if (act === 'delete') _mgDelete(_chosenIds());
    }

    function _mgRowOf(target) {
        var row = target && target.closest && target.closest('.sd-lib-mrow');
        return row ? { row: row, id: parseInt(row.getAttribute('data-id'), 10) } : null;
    }

    // The checkbox chooses; Shift with a click chooses the rows between.
    function _mgChoose(id, on, range) {
        var rows = _drawn(L.items()).map(function (it) { return it.id; });
        if (range && mg.last != null && rows.indexOf(mg.last) !== -1) {
            var a = rows.indexOf(mg.last), b = rows.indexOf(id);
            rows.slice(Math.min(a, b), Math.max(a, b) + 1).forEach(function (r) {
                var row = mg.el.querySelector('.sd-lib-mrow[data-id="' + r + '"]');
                if (row && !row.hidden) { if (on) mg.chosen[r] = true; else delete mg.chosen[r]; }
            });
        } else if (on) {
            mg.chosen[id] = true;
        } else {
            delete mg.chosen[id];
        }
        mg.last = id;
        mg.el.querySelectorAll('.sd-lib-mrow').forEach(function (row) {
            var c = !!mg.chosen[row.getAttribute('data-id')];
            row.classList.toggle('is-chosen', c);
            row.setAttribute('aria-selected', c ? 'true' : 'false');
            row.querySelector('input[type="checkbox"]').checked = c;
        });
        _mgFootDraw();
    }

    function _mgClick(e) {
        var r = _mgRowOf(e.target);
        if (!r || mg.busy) return;
        _mgFocus(r.row);
        if (e.target.matches('input[type="checkbox"]')) {
            _mgChoose(r.id, e.target.checked, e.shiftKey);
            return;
        }
        if (e.target.closest('[data-mg-more]')) _mgRowMenu(e.target.closest('[data-mg-more]'), r.id);
    }

    function _mgFocus(row) {
        mg.el.querySelectorAll('.sd-lib-mrow[tabindex="0"]').forEach(function (r) { r.tabIndex = -1; });
        row.tabIndex = 0;
        if (document.activeElement !== row && !row.contains(document.activeElement)) row.focus();
    }

    function _mgKey(e) {
        var r = _mgRowOf(e.target);
        if (!r || e.target !== r.row) return;
        if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown')) {
            e.preventDefault();
            if (!mg.query) _mgStep(r.id, e.key === 'ArrowUp' ? -1 : 1);
            return;
        }
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
            e.preventDefault();
            var rows = Array.prototype.filter.call(mg.el.querySelectorAll('.sd-lib-mrow'), function (x) { return !x.hidden; });
            var next = rows[rows.indexOf(r.row) + (e.key === 'ArrowUp' ? -1 : 1)];
            if (next) _mgFocus(next);
            return;
        }
        if (e.key === ' ') { e.preventDefault(); _mgChoose(r.id, !mg.chosen[r.id], e.shiftKey); return; }
        if (e.key === 'F2' || e.key === 'Enter') { e.preventDefault(); _mgRename(r.row); return; }
        if (e.key === 'Delete') { e.preventDefault(); _mgDelete([r.id]); }
    }

    // ── A change sent ──────────────────────────────────────────────────

    // The list is drawn as asked at once; the server's answer is the list
    // drawn again, and a refusal puts back what it holds.
    function _mgSend(sub, payload, focusId, before) {
        var errEl = mg && mg.el.querySelector('[data-mg-error]');
        if (errEl) errEl.hidden = true;
        before = before || L.items().slice();
        return L.post(sub, payload).then(function (r) {
            if (Array.isArray(r.items)) L.setItems(r.items);
            L.redraw();
            _mgDraw(focusId);
        }, function (err) {
            L.setItems(before);
            L.redraw();
            _mgDraw(focusId);
            if (mg) {
                var e2 = mg.el.querySelector('[data-mg-error]');
                e2.textContent = err.message;
                e2.hidden = false;
            }
        });
    }

    function _mgOrder(next, focusId) {
        var before = L.items();
        L.setItems(_drawn(next));
        L.redraw();
        _mgDraw(focusId);
        _mgSend('library_order', { order: L.items().map(function (it) { return { id: it.id, group: it.group || '' }; }) }, focusId, before);
    }

    function _withGroup(it, group) {
        var copy = {};
        Object.keys(it).forEach(function (k) { copy[k] = it[k]; });
        copy.group = group;
        return copy;
    }

    // Alt with Arrow up or down: past a neighbour of the same group, or
    // across a group's edge into the group beside it (above the first
    // group, no group).
    function _mgStep(id, dir) {
        var list = _drawn(L.items());
        var i = list.map(function (it) { return it.id; }).indexOf(id);
        if (i === -1) return;
        var me = list[i], near = list[i + dir];
        if (near && (near.group || '') === (me.group || '')) {
            list[i] = near;
            list[i + dir] = me;
        } else if (near) {
            list[i] = _withGroup(me, near.group || '');
        } else if (dir < 0 && me.group) {
            list[i] = _withGroup(me, '');
        } else {
            return;
        }
        _mgOrder(list, id);
    }

    // A row dragged by its grip, with the mouse or a finger: a line shows
    // where it lands; dropped on a row it takes that row's group, on a
    // group's head it goes first in that group.
    function _mgGrip(e) {
        var grip = e.target.closest('.sd-lib-grip');
        var r = grip && _mgRowOf(grip);
        if (!r || mg.query || mg.busy || e.button > 0) return;
        e.preventDefault();
        var list = mg.el.querySelector('[data-mg-list]');
        var mark = document.createElement('div');
        mark.className = 'sd-lib-mdrop';
        var drop = null;
        r.row.classList.add('is-dragged');
        try { grip.setPointerCapture(e.pointerId); } catch (x) { /* the list still follows the pointer */ }

        function move(ev) {
            var at = document.elementFromPoint(ev.clientX, ev.clientY);
            var box = list.getBoundingClientRect();
            if (ev.clientY < box.top + 24) list.scrollTop -= 12;
            else if (ev.clientY > box.bottom - 24) list.scrollTop += 12;
            var head = at && at.closest && at.closest('.sd-lib-mgroup');
            var row = at && at.closest && at.closest('.sd-lib-mrow');
            if (head && list.contains(head)) {
                drop = { head: head.getAttribute('data-group') };
                head.after(mark);
            } else if (row && list.contains(row) && row !== r.row) {
                var b = row.getBoundingClientRect();
                var after = ev.clientY > b.top + b.height / 2;
                drop = { id: parseInt(row.getAttribute('data-id'), 10), after: after };
                if (after) row.after(mark); else row.before(mark);
            }
        }

        function end(ev) {
            grip.removeEventListener('pointermove', move);
            grip.removeEventListener('pointerup', end);
            grip.removeEventListener('pointercancel', end);
            r.row.classList.remove('is-dragged');
            mark.remove();
            if (ev.type !== 'pointerup' || !drop) return;
            var list2 = _drawn(L.items()).filter(function (it) { return it.id !== r.id; });
            var me = L.find(r.id);
            var at, group;
            if (drop.head != null) {
                group = drop.head;
                at = list2.map(function (it) { return it.group || ''; }).indexOf(group);
                if (at === -1 || group === '') at = group === '' ? 0 : list2.length;
            } else {
                var ids = list2.map(function (it) { return it.id; });
                var j = ids.indexOf(drop.id);
                if (j === -1) return;
                group = list2[j].group || '';
                at = j + (drop.after ? 1 : 0);
            }
            list2.splice(at, 0, _withGroup(me, group));
            _mgOrder(list2, r.id);
        }

        grip.addEventListener('pointermove', move);
        grip.addEventListener('pointerup', end);
        grip.addEventListener('pointercancel', end);
    }

    // ── Rename, move, delete ───────────────────────────────────────────

    // The name becomes a field in its row: Enter or a click elsewhere
    // saves, Escape puts the old name back; an empty name is not saved.
    function _mgRename(row) {
        var id = parseInt(row.getAttribute('data-id'), 10);
        var it = L.find(id);
        var name = row.querySelector('.sd-lib-mname');
        if (!it || !name || row.querySelector('.sd-lib-mrename')) return;
        _mgPopClose();
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'pg-input sd-lib-mrename';
        input.maxLength = L.NAME_MAX;
        input.value = it.name;
        input.setAttribute('aria-label', _sdT('Name'));
        name.replaceWith(input);
        input.focus();
        input.select();
        var done = false;
        function finish(keep) {
            if (done) return;
            done = true;
            var v = input.value.trim();
            if (keep && v && v !== it.name) {
                var before = L.items();
                L.setItems(before.map(function (x) {
                    if (x.id !== id) return x;
                    var copy = _withGroup(x, x.group);
                    copy.name = v;
                    return copy;
                }));
                L.redraw();
                _mgSend('library_rename', { id: id, name: v }, id, before);
            }
            _mgDraw(id);
        }
        input.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Enter') { e.preventDefault(); finish(true); }
            if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        });
        input.addEventListener('blur', function () { finish(true); });
    }

    function _mgPopClose() {
        if (!mg) return;
        mg.el.querySelectorAll('.sd-lib-pop').forEach(function (p) { p.remove(); });
        var field = mg.el.querySelector('.sd-lib-mrename');
        if (field) field.blur();
    }

    // A small menu beside the button that opened it, inside the window.
    function _mgPop(anchor, rows) {
        _mgPopClose();
        var pop = document.createElement('div');
        pop.className = 'sd-lib-pop';
        pop.setAttribute('role', 'menu');
        pop.innerHTML = rows.map(function (r, i) {
            return '<button type="button" role="menuitem" class="sd-lib-popitem' + (r.danger ? ' fg-danger' : '') + '" data-i="' + i + '">' +
                (r.icon ? '<span class="bi ' + r.icon + ' me-2" aria-hidden="true"></span>' : '') + esc(r.label) + '</button>';
        }).join('');
        mg.el.appendChild(pop);
        var a = anchor.getBoundingClientRect(), d = mg.el.getBoundingClientRect();
        pop.style.top = Math.max(4, Math.min(a.bottom - d.top + 4, d.height - pop.offsetHeight - 4)) + 'px';
        pop.style.right = Math.max(4, d.right - a.right) + 'px';
        pop.addEventListener('click', function (e) {
            var b = e.target.closest('[data-i]');
            if (b) rows[parseInt(b.getAttribute('data-i'), 10)].act(pop);
        });
        pop.addEventListener('keydown', function (e) {
            var items = Array.prototype.slice.call(pop.querySelectorAll('button'));
            var i = items.indexOf(document.activeElement);
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                items[(i + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length].focus();
            }
        });
        setTimeout(function () {
            var first = pop.querySelector('button');
            if (first) first.focus();
            document.addEventListener('pointerdown', function off(e) {
                if (pop.contains(e.target)) return;
                pop.remove();
                document.removeEventListener('pointerdown', off, true);
            }, true);
        }, 0);
        return pop;
    }

    function _mgRowMenu(anchor, id) {
        var rows = [
            { label: _sdT('Rename'), icon: 'bi-pencil', act: function () {
                var row = mg.el.querySelector('.sd-lib-mrow[data-id="' + id + '"]');
                if (row) _mgRename(row);
            } },
            { label: _sdT('Move to group'), icon: 'bi-folder-symlink', act: function () { _mgMoveMenu(anchor, [id]); } }
        ];
        if (_hasFile()) rows.push({ label: _sdT('Export'), icon: 'bi-box-arrow-up', act: function () { _mgPopClose(); _file('exportItems', [[id]]); } });
        rows.push({ label: _sdT('Delete'), icon: 'bi-trash', danger: true, act: function () { _mgPopClose(); _mgDelete([id]); } });
        _mgPop(anchor, rows);
    }

    // "Move to group": the groups in use, no group, or a new one typed.
    function _mgMoveMenu(anchor, ids) {
        if (!ids.length) return;
        var move = function (group) {
            _mgPopClose();
            var before = L.items();
            L.setItems(before.map(function (it) { return ids.indexOf(it.id) !== -1 ? _withGroup(it, group) : it; }));
            L.redraw();
            _mgDraw();
            _mgSend('library_move', { ids: ids, group: group }, null, before);
        };
        var rows = _groupsOf(L.items()).map(function (g) { return { label: g, act: function () { move(g); } }; });
        rows.push({ label: _sdT('No group'), act: function () { move(''); } });
        rows.push({ label: _sdT('New group…'), icon: 'bi-plus', act: function (pop) {
            pop.innerHTML = '<div class="p-2"><input type="text" class="pg-input" maxlength="' + L.NAME_MAX + '" aria-label="' + esc(_sdT('Group')) + '"></div>';
            var input = pop.querySelector('input');
            input.focus();
            input.addEventListener('keydown', function (e) {
                e.stopPropagation();
                if (e.key === 'Enter' && input.value.trim()) { e.preventDefault(); move(input.value.trim().slice(0, L.NAME_MAX)); }
                if (e.key === 'Escape') { e.preventDefault(); _mgPopClose(); }
            });
        } });
        _mgPop(anchor, rows);
    }

    // Delete asks once, naming the item when it is one; there is no bin.
    function _mgDelete(ids) {
        if (!ids.length || !mg) return;
        var one = ids.length === 1 ? L.find(ids[0]) : null;
        var ask = typeof window.pgConfirm === 'function'
            ? window.pgConfirm({
                title: one ? _sdT('Delete "{var}" from the library?', one.name) : _sdT('Delete {var} items from the library?', ids.length),
                message: _sdT('Pages that already hold a copy keep it. The items leave the Blocks list and Design Files.'),
                confirmText: _sdT('Delete'), cancelText: _sdT('Cancel'), variant: 'danger' })
            : Promise.resolve(window.confirm(_sdT('Delete {var} items from the library?', ids.length)));
        Promise.resolve(ask).then(function (yes) {
            if (!yes || !mg) return;
            ids.forEach(function (id) { delete mg.chosen[id]; });
            mg.busy = true;
            _mgSend('library_delete', { ids: ids }).then(function () { if (mg) mg.busy = false; });
        });
    }

    return { open: open };
});
