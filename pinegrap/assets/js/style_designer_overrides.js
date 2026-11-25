/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Can change per place" part: inside a shared
 * component a text, picture, link, icon or an element's "Shown" is opened
 * to the places that use the component, with a label. The list of an
 * element's fields is opened from its menu, the command palette or the mark
 * after an Options row's label; the component's settings list its open
 * fields in the order every place shows them (drag by the grip, Alt+Up and
 * Alt+Down), with Rename, Show in Overview and Close the field. The marks
 * live in the component's tree (props._open on a node, the order in the
 * root's props._openOrder) and are saved with the component by Save. Not
 * needed to open a page: the core runs this file the first time one of
 * these is asked for (_sdPartLoad(), pg_designer_parts()); the pure model
 * (window.PGComponentOverrides) comes in the same bundle.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('overrides', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var CO = window.PGComponentOverrides;

    var KIND_ORDER = ['text', 'picture', 'link', 'icon', 'shown'];
    var KIND_ICON = { text: 'bi-type', picture: 'bi-image', link: 'bi-link-45deg', icon: 'bi-star', shown: 'bi-eye' };
    var FIRST_KEY = 'sd_co_first_seen';

    // The open list over the panel, the last Result line of the settings
    // list, and the element whose menu row is focused for the keyboard.
    var state = { pop: null, popNode: null, popSid: 0, result: null, focusId: '' };

    function kindName(kind) {
        return {
            text: _sdT('Text'), picture: _sdT('Picture'), link: _sdT('Link'), icon: _sdT('Icon'), shown: _sdT('Shown')
        }[kind] || kind;
    }

    function hasOwn(o, k) { return !!o && Object.prototype.hasOwnProperty.call(o, k); }

    function propsOf(node) {
        if (!node.props || typeof node.props !== 'object') node.props = {};
        return node.props;
    }

    // The node's open entries as stored (the list itself, created on demand).
    function openList(node, create) {
        var p = propsOf(node);
        if (!Array.isArray(p._open)) {
            if (!create) return [];
            p._open = [];
        }
        return p._open;
    }

    function entryOf(node, kind) {
        var list = openList(node, false);
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].kind === kind) return list[i];
        }
        return null;
    }

    // The fields an element has, in the list's order. The component's own
    // root is always drawn, so it offers no "Shown".
    function kindsOf(node, isRoot) {
        var p = node.props || {};
        var ct = String(p.contentType || '');
        var tag = String(p.tag || '').toLowerCase();
        var out = [];
        if (typeof p.text === 'string' || ct === 'heading' || ct === 'paragraph' || ct === 'button' || ct === 'link') out.push('text');
        if (typeof p.src === 'string' || ct === 'image' || node.type === 'image' || tag === 'img') out.push('picture');
        if (typeof p.href === 'string' || ct === 'link' || ct === 'button' || tag === 'a') out.push('link');
        if (typeof p.iconName === 'string' || ct === 'icon') out.push('icon');
        if (!isRoot) out.push('shown');
        return out;
    }

    // Why a field cannot be opened, or '' when it can: a field bound to data
    // already changes with the record.
    function blockedWhy(node, kind) {
        var b = (node.props && node.props._bindings) || {};
        var bound = { text: 'text', picture: 'src', link: 'href' }[kind];
        if (bound && b[bound]) return _sdT('Bound to data: it already changes with the record.');
        return '';
    }

    // The label a field starts with: the element's name, or with a second
    // field open on the same element the name and the field ("Button text").
    function defaultLabel(node, kind, many) {
        var name = K.getNodeLabel(node);
        return many ? name + ' ' + kindName(kind).toLowerCase() : name;
    }

    function isDefaultLabel(node, entry) {
        var l = String(entry.label || '');
        return l === '' || l === defaultLabel(node, entry.kind, false) || l === defaultLabel(node, entry.kind, true);
    }

    // After a field is opened or closed: labels still at their default take
    // the default that fits how many fields the element has open now.
    function relabel(node) {
        var list = openList(node, false);
        var many = list.length > 1;
        list.forEach(function (e) {
            if (isDefaultLabel(node, e)) e.label = defaultLabel(node, e.kind, many);
        });
    }

    // The component's tree and every id already used in it.
    function compTree(sid) {
        var c = K.sharedCache[sid];
        return c && c.tree ? c.tree : null;
    }

    function takenIds(root) {
        var ids = [];
        (function walk(n) {
            if (!n || typeof n !== 'object') return;
            (Array.isArray(n.props && n.props._open) ? n.props._open : []).forEach(function (e) { if (e && e.id) ids.push(String(e.id)); });
            if (n.type === 'shared_ref') return;
            (n.children || []).forEach(walk);
        })(root);
        return ids;
    }

    function findById(root, id) {
        var hit = null;
        (function walk(n) {
            if (hit || !n || typeof n !== 'object') return;
            if (String(n._id) === String(id)) { hit = n; return; }
            if (n.type === 'shared_ref') return;
            (n.children || []).forEach(walk);
        })(root);
        return hit;
    }

    // The root's stored order, kept only while it names a field.
    function orderOf(root, create) {
        var p = propsOf(root);
        if (!Array.isArray(p._openOrder)) {
            if (!create) return null;
            p._openOrder = CO.openFields(root).map(function (f) { return f.id; });
        }
        return p._openOrder;
    }

    function dropFromOrder(root, id) {
        var order = orderOf(root, false);
        if (!order) return -1;
        var at = order.indexOf(id);
        if (at !== -1) order.splice(at, 1);
        if (!order.length) delete root.props._openOrder;
        return at;
    }

    // One change to component `sid`: an undo step before, the component
    // marked changed and the editor drawn after (the core's _sdCoWrite()).
    function write(sid, fn) {
        K._sdCoWrite(sid, fn);
    }

    // ── The list of an element's fields ──────────────────────────────────

    function closePop() {
        if (state.pop) state.pop.remove();
        state.pop = null;
        state.popNode = null;
        document.removeEventListener('mousedown', onOutside, true);
    }

    function onOutside(e) {
        if (state.pop && !state.pop.contains(e.target)) closePop();
    }

    function firstSeen() {
        try { return localStorage.getItem(FIRST_KEY) === '1'; } catch (e) { return true; }
    }

    function popHtml(node, sid, first) {
        var root = compTree(sid);
        var kinds = kindsOf(node, node === root);
        var h = '<p class="sd-co-ph">' + esc(_sdT('Can change per place')) + '</p>';
        kinds.forEach(function (kind) {
            var e = entryOf(node, kind);
            var why = e ? '' : blockedWhy(node, kind);
            h += '<div class="sd-co-por' + (why ? ' sd-co-dis' : '') + '">' +
                '<span class="sd-co-pname">' + esc(kindName(kind)) + (why ? '<small>' + esc(why) + '</small>' : '') + '</span>' +
                '<label class="sd-prop-switch"><input type="checkbox" class="sd-sw-inp" data-co-kind="' + kind + '"' +
                    (e ? ' checked' : '') + (why ? ' disabled' : '') + ' aria-label="' + esc(kindName(kind)) + '">' +
                    '<span class="sd-sw-track"><span class="sd-sw-thumb"></span></span></label>' +
            '</div>';
            if (e) {
                h += '<div class="sd-co-pol"><span>' + esc(_sdT('Label')) + '</span>' +
                    '<input type="text" class="form-control form-control-sm" maxlength="80" data-co-label="' + kind + '" value="' + esc(e.label || '') + '" aria-label="' + esc(_sdT('Label')) + '"></div>';
            }
        });
        if (first) {
            var c = K.sharedCache[sid] || {};
            var e1 = openList(node, false)[0];
            var what = e1 && e1.kind === 'shown'
                ? _sdT('choice to show “{var}”', e1.label || K.getNodeLabel(node))
                : (e1 ? defaultLabel(node, e1.kind, true).toLowerCase() : '');
            h += '<div class="sd-co-first"><span>' +
                esc(_sdT('Each place of “{var:1}” can now have its own {var:2}. Select a place to change it under “This place”.', [c.name || '', what])) +
                '</span><button type="button" class="btn btn-sm btn-outline theme-secondary" data-co-got>' + esc(_sdT('Got it')) + '</button></div>';
        }
        return h;
    }

    function drawPop(focusKind, first) {
        var node = state.popNode, sid = state.popSid;
        if (!state.pop || !node) return;
        state.pop.innerHTML = popHtml(node, sid, first);
        var f = focusKind ? state.pop.querySelector('[data-co-kind="' + focusKind + '"]') : state.pop.querySelector('[data-co-kind]:not([disabled])');
        if (f) f.focus();
    }

    function setOpen(node, sid, kind, on) {
        var root = compTree(sid);
        if (!root) return;
        write(sid, function () {
            if (on) {
                if (entryOf(node, kind)) return;
                var id = CO.newFieldId(takenIds(root));
                openList(node, true).push({ id: id, kind: kind, label: '' });
                relabel(node);
                var order = orderOf(root, false);
                if (order) order.push(id);
            } else {
                var list = openList(node, false);
                var e = entryOf(node, kind);
                if (!e) return;
                list.splice(list.indexOf(e), 1);
                dropFromOrder(root, e.id);
                if (!list.length) delete node.props._open;
                relabel(node);
            }
        });
    }

    function setLabel(node, sid, kind, text) {
        var e = entryOf(node, kind);
        if (!e) return;
        var label = String(text || '').trim().slice(0, 80);
        if (!label) label = defaultLabel(node, kind, openList(node, false).length > 1);
        if (label === e.label) return;
        write(sid, function () { e.label = label; });
    }

    // Opens the list of `node`'s fields next to `anchor` (a rect).
    function openFor(node, anchor) {
        var sid = K._resolveSharedSid(node);
        if (!(sid > 0) || !compTree(sid)) return;
        closePop();
        var pop = document.createElement('div');
        pop.className = 'sd-co-pop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', _sdT('Can change per place'));
        state.pop = pop;
        state.popNode = node;
        state.popSid = sid;
        document.body.appendChild(pop);
        drawPop('', false);
        var r = anchor || { left: window.innerWidth / 2 - 150, bottom: 120 };
        var w = pop.offsetWidth, h = pop.offsetHeight;
        pop.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
        pop.style.top = Math.max(8, Math.min(r.bottom + 4, window.innerHeight - h - 8)) + 'px';

        pop.addEventListener('change', function (e) {
            var t = e.target;
            var kind = t.getAttribute('data-co-kind');
            if (kind) {
                var first = t.checked && !firstSeen();
                setOpen(state.popNode, state.popSid, kind, t.checked);
                drawPop(kind, first);
                return;
            }
            kind = t.getAttribute('data-co-label');
            if (kind) setLabel(state.popNode, state.popSid, kind, t.value);
        });
        pop.addEventListener('click', function (e) {
            if (!e.target.closest('[data-co-got]')) return;
            try { localStorage.setItem(FIRST_KEY, '1'); } catch (x) {}
            var line = pop.querySelector('.sd-co-first');
            if (line) line.remove();
        });
        pop.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                closePop();
                return;
            }
            if (e.key === 'Enter' && e.target.hasAttribute('data-co-label')) {
                e.preventDefault();
                e.target.blur();
                return;
            }
            if (e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            if (e.target.hasAttribute('data-co-label')) return;
            var sw = Array.prototype.slice.call(pop.querySelectorAll('[data-co-kind]:not([disabled])'));
            var at = sw.indexOf(e.target);
            if (!sw.length) return;
            e.preventDefault();
            var next = sw[(at + (e.key === 'ArrowDown' ? 1 : sw.length - 1)) % sw.length] || sw[0];
            next.focus();
        });
        setTimeout(function () { document.addEventListener('mousedown', onOutside, true); }, 0);
    }

    // ── The list in the component's settings ─────────────────────────────

    function fieldsOf(sid) {
        var root = compTree(sid);
        return root ? CO.openFields(root) : [];
    }

    function listHtml(sid) {
        var fields = fieldsOf(sid);
        var h = '';
        if (state.result && state.result.sid === sid) {
            h += '<div class="pg-result sd-co-result" role="status"><span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><i class="bi bi-check-lg"></i></span>' +
                '<span class="pg-result-line">' + esc(state.result.text) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-co-undo>' + esc(_sdT('Undo')) + '</button></div>';
        }
        if (!fields.length) {
            return h + '<p class="sd-co-none">' + esc(_sdT('No field yet. Select a text, picture, link or icon in the component and choose “Can change per place” in its menu.')) + '</p>';
        }
        h += '<div class="sd-co-rows" role="list">';
        fields.forEach(function (f) {
            h += '<div class="sd-co-row" role="listitem" tabindex="0" data-co-id="' + esc(f.id) + '" title="' + esc(_sdT('Alt+Up and Alt+Down move the field')) + '">' +
                '<span class="sd-co-grip bi bi-grip-vertical" aria-hidden="true"></span>' +
                '<span class="sd-co-ki bi ' + (KIND_ICON[f.kind] || 'bi-dot') + '" aria-hidden="true"></span>' +
                '<b class="sd-co-label">' + esc(f.label || kindName(f.kind)) + '</b>' +
                '<button type="button" class="sd-co-more bi bi-three-dots" data-co-more aria-haspopup="menu" aria-label="' + esc(_sdT('More actions')) + '"></button>' +
            '</div>';
        });
        return h + '</div>';
    }

    // Where field `id` is declared: { node, entry } in the component.
    function locate(sid, id) {
        var root = compTree(sid), hit = null;
        (function walk(n) {
            if (hit || !n || typeof n !== 'object') return;
            (Array.isArray(n.props && n.props._open) ? n.props._open : []).forEach(function (e) {
                if (!hit && e && e.id === id) hit = { node: n, entry: e };
            });
            if (n.type === 'shared_ref') return;
            (n.children || []).forEach(walk);
        })(root);
        return hit;
    }

    function moveTo(sid, id, to) {
        var root = compTree(sid);
        if (!root) return;
        var ids = fieldsOf(sid).map(function (f) { return f.id; });
        var from = ids.indexOf(id);
        if (from === -1 || to < 0 || to >= ids.length || to === from) return;
        state.result = null;
        state.focusId = id;
        write(sid, function () {
            ids.splice(from, 1);
            ids.splice(to, 0, id);
            propsOf(root)._openOrder = ids;
        });
    }

    function closeField(sid, id) {
        var root = compTree(sid);
        var at = locate(sid, id);
        if (!root || !at) return;
        var node = at.node, entry = at.entry;
        var nodeId = node._id;
        var keep = { entry: JSON.parse(JSON.stringify(entry)), index: openList(node, false).indexOf(entry), orderAt: -1 };
        write(sid, function () {
            var list = openList(node, false);
            list.splice(keep.index, 1);
            if (!list.length) delete node.props._open;
            keep.orderAt = dropFromOrder(root, id);
            relabel(node);
        });
        var label = entry.label || kindName(entry.kind);
        var text = {
            text: _sdT('“{var}” is closed. Places show the component\'s text again.', label),
            picture: _sdT('“{var}” is closed. Places show the component\'s picture again.', label),
            link: _sdT('“{var}” is closed. Places show the component\'s link again.', label),
            icon: _sdT('“{var}” is closed. Places show the component\'s icon again.', label),
            shown: _sdT('“{var}” is closed. Places show the element as the component does.', label)
        }[entry.kind];
        state.result = { sid: sid, text: text, undo: function () {
            var r = compTree(sid);
            var n = r ? findById(r, nodeId) : null;
            if (!n) return;
            write(sid, function () {
                var list = openList(n, true);
                list.splice(Math.min(keep.index, list.length), 0, keep.entry);
                var order = orderOf(r, false);
                if (order && keep.orderAt !== -1) order.splice(Math.min(keep.orderAt, order.length), 0, keep.entry.id);
                else if (order) order.push(keep.entry.id);
            });
        } };
        K.renderProperties();
    }

    function rename(sid, row) {
        var id = row.getAttribute('data-co-id');
        var at = locate(sid, id);
        var b = row.querySelector('.sd-co-label');
        if (!at || !b) return;
        var inp = document.createElement('input');
        inp.type = 'text';
        inp.className = 'form-control form-control-sm sd-co-rename';
        inp.maxLength = 80;
        inp.value = at.entry.label || '';
        inp.setAttribute('aria-label', _sdT('Label'));
        b.replaceWith(inp);
        inp.focus();
        inp.select();
        var done = false;
        function finish(keepIt) {
            if (done) return;
            done = true;
            var text = inp.value.trim().slice(0, 80);
            if (keepIt && text && text !== at.entry.label) {
                state.result = null;
                write(sid, function () { at.entry.label = text; });
            } else {
                K.renderProperties();
            }
        }
        inp.addEventListener('keydown', function (e) {
            e.stopPropagation();
            if (e.key === 'Enter') { e.preventDefault(); finish(true); }
            else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
        });
        inp.addEventListener('blur', function () { finish(true); });
    }

    function rowMenu(sid, row, btn) {
        var id = row.getAttribute('data-co-id');
        var r = btn.getBoundingClientRect();
        K.showCtxMenu(r.left, r.bottom + 4, [
            { icon: 'bi-pencil', label: esc(_sdT('Rename')), handler: function () { rename(sid, row); } },
            { icon: 'bi-list-nested', label: esc(_sdT('Show in Overview')), handler: function () {
                var at = locate(sid, id);
                if (!at) return;
                K.selectedNode = at.node;
                K.selectedNodes = [];
                K._sdSelectRender();
                K.scrollTreeToNode(at.node);
            } },
            { sep: true },
            { icon: 'bi-x-lg', label: esc(_sdT('Close the field')), danger: true, handler: function () { closeField(sid, id); } }
        ]);
    }

    // Drags a row by its grip: the row follows the pointer among the rows,
    // and the order is written once, when it is let go.
    function dragRow(sid, list, row, e) {
        e.preventDefault();
        var id = row.getAttribute('data-co-id');
        row.classList.add('sd-co-dragging');
        function move(ev) {
            var rows = Array.prototype.slice.call(list.querySelectorAll('.sd-co-row'));
            for (var i = 0; i < rows.length; i++) {
                if (rows[i] === row) continue;
                var b = rows[i].getBoundingClientRect();
                if (ev.clientY < b.top + b.height / 2) {
                    if (rows[i].previousElementSibling !== row) list.insertBefore(row, rows[i]);
                    return;
                }
            }
            if (list.lastElementChild !== row) list.appendChild(row);
        }
        function up() {
            document.removeEventListener('pointermove', move);
            document.removeEventListener('pointerup', up);
            row.classList.remove('sd-co-dragging');
            var to = Array.prototype.indexOf.call(list.querySelectorAll('.sd-co-row'), row);
            moveTo(sid, id, to);
        }
        document.addEventListener('pointermove', move);
        document.addEventListener('pointerup', up);
    }

    // Draws the list into `el` (the "Shared Component" section's holder).
    function fillList(el, sid) {
        if (state.result && state.result.sid !== sid) state.result = null;
        el.innerHTML = listHtml(sid);
        if (state.focusId) {
            var again = el.querySelector('.sd-co-row[data-co-id="' + state.focusId + '"]');
            state.focusId = '';
            if (again) again.focus();
        }
        if (el._sdCoBound) return;
        el._sdCoBound = true;
        el.addEventListener('click', function (e) {
            if (e.target.closest('[data-co-undo]')) {
                var res = state.result;
                state.result = null;
                if (res && res.sid === sid) res.undo();
                return;
            }
            var more = e.target.closest('[data-co-more]');
            if (more) rowMenu(sid, more.closest('.sd-co-row'), more);
        });
        el.addEventListener('pointerdown', function (e) {
            var grip = e.target.closest('.sd-co-grip');
            if (grip && e.button === 0) dragRow(sid, grip.closest('.sd-co-rows'), grip.closest('.sd-co-row'), e);
        });
        el.addEventListener('keydown', function (e) {
            var row = e.target.classList && e.target.classList.contains('sd-co-row') ? e.target : null;
            if (!row || !e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
            e.preventDefault();
            var id = row.getAttribute('data-co-id');
            var ids = fieldsOf(sid).map(function (f) { return f.id; });
            moveTo(sid, id, ids.indexOf(id) + (e.key === 'ArrowDown' ? 1 : -1));
        });
    }

    return {
        openFor: openFor,
        fillList: fillList,
        close: closePop,
        kindsOf: kindsOf,
        defaultLabel: defaultLabel
    };
});
