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
 * root's props._openOrder) and are saved with the component by Save.
 * Where the component is placed, "This place" lists the open fields for
 * this place only (props._place on the placing node, saved with the page),
 * with the changed dot and "Reset to the component", and an open text is
 * typed in place on the canvas. Variants: named sets of the component's
 * classes and options (props._variants on its root), listed, added and
 * edited in its settings; a place picks one by name (props._variant). Not
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
    // list, the element whose menu row is focused for the keyboard, and
    // what had the focus when the list opened.
    var state = { pop: null, popNode: null, popSid: 0, result: null, focusId: '', opener: null, focusUndo: false };

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

    // The element of the open list in the component's tree as the editor
    // has it now: Undo puts a new copy of the tree in place, and a write
    // into the element as it was would be lost. Null when it is gone.
    function popNodeNow() {
        var node = state.popNode, root = compTree(state.popSid);
        if (!node || !root || node._id == null) return node;
        state.popNode = findById(root, node._id);
        return state.popNode;
    }

    // Closes the list. A label typed and not yet left is kept (a click
    // outside, another list opened), since removing its field fires no
    // change; `discard` (Escape) drops it.
    function closePop(discard) {
        var pop = state.pop, node = pop ? popNodeNow() : null, sid = state.popSid;
        state.pop = null;
        state.popNode = null;
        state.opener = null;
        document.removeEventListener('mousedown', onOutside, true);
        if (!pop) return;
        var typed = discard === true ? [] : Array.prototype.filter.call(pop.querySelectorAll('[data-co-label]'), function (inp) {
            return inp.value !== inp.defaultValue;
        });
        pop.remove();
        typed.forEach(function (inp) {
            if (node) setLabel(node, sid, inp.getAttribute('data-co-label'), inp.value);
        });
    }

    function onOutside(e) {
        if (state.pop && !state.pop.contains(e.target)) closePop();
    }

    function firstSeen() {
        if (K._sdTipsOff()) return true;
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
        var opener = document.activeElement;
        closePop();
        state.opener = opener;
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
            if (state.pop !== pop) return;
            var node = popNodeNow();
            if (!node) { closePop(true); return; }
            var kind = t.getAttribute('data-co-kind');
            if (kind) {
                var first = t.checked && !firstSeen();
                setOpen(node, state.popSid, kind, t.checked);
                drawPop(kind, first);
                return;
            }
            kind = t.getAttribute('data-co-label');
            if (kind) setLabel(node, state.popSid, kind, t.value);
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
                var back = state.opener;
                closePop(true);
                // Back to what opened the list; the element's three-dot
                // button when that was redrawn meanwhile.
                if (!(back && back !== document.body && back.isConnected && back.focus && back.getClientRects().length)) back = document.getElementById('sd-id-more');
                if (back) back.focus();
                return;
            }
            if (e.key === 'Enter' && e.target.hasAttribute('data-co-label')) {
                // Keeps the label (its change runs on leaving the field) and
                // stays in the list, on the field's switch.
                e.preventDefault();
                var sw = pop.querySelector('[data-co-kind="' + e.target.getAttribute('data-co-label') + '"]');
                if (sw) sw.focus(); else e.target.blur();
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
            h += '<div class="sd-co-row" role="listitem" tabindex="0" data-co-id="' + esc(f.id) + '" title="' + esc(_sdT('Alt+Up and Alt+Down move the field')) + '" aria-keyshortcuts="F2 Delete Alt+ArrowUp Alt+ArrowDown">' +
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
            state.focusId = keep.entry.id;
            write(sid, function () {
                var list = openList(n, true);
                list.splice(Math.min(keep.index, list.length), 0, keep.entry);
                var order = orderOf(r, false);
                if (order && keep.orderAt !== -1) order.splice(Math.min(keep.orderAt, order.length), 0, keep.entry.id);
                else if (order) order.push(keep.entry.id);
            });
        } };
        state.focusUndo = true;
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
        if (state.focusUndo && el.isConnected) {
            // After "Close the field" the keyboard is on its Undo (in the
            // drawing that is in the page, not one queued before it).
            state.focusUndo = false;
            var undo = el.querySelector('[data-co-undo]');
            if (undo) undo.focus();
        }
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
            if (!row) return;
            // F2 and Delete as in the variants' list: the row's menu is
            // reached by the pointer only.
            if (e.key === 'F2' && !e.altKey) { e.preventDefault(); rename(sid, row); return; }
            if ((e.key === 'Delete' || e.key === 'Backspace') && !e.altKey) {
                e.preventDefault();
                e.stopPropagation();
                closeField(sid, row.getAttribute('data-co-id'));
                return;
            }
            if (!e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
            e.preventDefault();
            var id = row.getAttribute('data-co-id');
            var ids = fieldsOf(sid).map(function (f) { return f.id; });
            moveTo(sid, id, ids.indexOf(id) + (e.key === 'ArrowDown' ? 1 : -1));
        });
    }


    // ── "This place": a placed component's open fields ───────────────────
    // A place keeps its own values on the placing node (props._place, field
    // id => value); an empty field shows the component's value as its hint
    // and draws it. Written to the page's tree: Save sends the page.

    function plainOf(v) {
        return String(v == null ? '' : v).replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    function placeValuesOf(ref) {
        var v = ref && ref.props ? ref.props._place : null;
        return (v && typeof v === 'object' && !Array.isArray(v)) ? v : {};
    }

    function copyOf(values) {
        var o = {};
        Object.keys(values || {}).forEach(function (k) { o[k] = values[k]; });
        return o;
    }

    // Whether a row differs from the component: a value of its kind is kept
    // ("Shown" only when it hides the element here).
    function placeChanged(values, field) {
        if (!hasOwn(values, field.id)) return false;
        var v = CO.cleanValue(field.kind, values[field.id]);
        if (v === null) return false;
        return field.kind === 'shown' ? v === false : true;
    }

    // The values after `raw` is given to `field`: { values, refused }.
    // `values` is null when none is left; an empty value or the component's
    // own resets the row; a link or picture that would run a script is
    // refused (`refused` says why) and nothing is kept.
    function placeSet(values, field, raw) {
        var out = copyOf(values);
        var comp = field.value;
        var v;
        if (field.kind === 'shown') {
            v = raw !== false;
            if (v) delete out[field.id]; else out[field.id] = false;
        } else {
            v = String(raw == null ? '' : raw).trim();
            var own = field.kind === 'picture' ? String((comp && comp.src) || '') : String(comp == null ? '' : comp);
            if ((field.kind === 'link' || field.kind === 'picture') && v !== '' && CO.addressRunsScript(v, field.kind === 'picture')) {
                return { values: null, refused: _sdT('This address cannot be used here.') };
            }
            if (v === '' || v === own) delete out[field.id];
            else out[field.id] = field.kind === 'picture' ? { src: v } : v;
        }
        return { values: Object.keys(out).length ? out : null, refused: '' };
    }

    // The values with one row back at the component's, null when none is left.
    function placeReset(values, id) {
        var out = copyOf(values);
        delete out[id];
        return Object.keys(out).length ? out : null;
    }

    // One change to a place: an undo step, the values, the editor drawn.
    function placeWrite(ref, values) {
        K.saveState();
        if (!ref.props) ref.props = {};
        if (values) ref.props._place = values;
        else delete ref.props._place;
        K.render();
    }

    function placeFields(sid) {
        return fieldsOf(sid);
    }

    function placeControl(f, values, ro) {
        var dis = ro ? ' disabled' : '';
        var mine = hasOwn(values, f.id) ? CO.cleanValue(f.kind, values[f.id]) : null;
        var idAttr = ' data-co-field="' + esc(f.id) + '" id="sd-co-f-' + esc(f.id) + '"';
        if (f.kind === 'shown') {
            var on = mine !== false;
            return '<label class="sd-prop-switch"><input type="checkbox" class="sd-sw-inp"' + idAttr + (on ? ' checked' : '') + dis + '>' +
                '<span class="sd-sw-track"><span class="sd-sw-thumb"></span></span>' +
                '<span class="sd-co-shown">' + esc(on ? _sdT('Shown') : _sdT('Hidden')) + '</span></label>';
        }
        if (f.kind === 'text') {
            return '<textarea class="form-control form-control-sm" rows="2"' + idAttr + dis +
                ' placeholder="' + esc(plainOf(f.value)) + '">' + esc(mine === null ? '' : mine) + '</textarea>';
        }
        var hint = f.kind === 'picture' ? String((f.value && f.value.src) || '') : String(f.value || '');
        var cur = f.kind === 'picture' ? (mine ? mine.src : '') : (mine === null ? '' : mine);
        var input = '<input type="text" class="form-control form-control-sm"' + idAttr + dis +
            ' placeholder="' + esc(hint) + '" value="' + esc(cur) + '">';
        if (f.kind === 'icon') return input;
        return '<div class="input-group input-group-sm">' + input +
            '<button class="btn btn-sm btn-outline theme-secondary" type="button" data-co-pick="' + esc(f.id) + '"' + dis + '>' +
            esc(_sdT('Choose')) + '</button></div><div class="invalid-feedback" data-co-why="' + esc(f.id) + '"></div>';
    }

    // Draws the rows of `ref` (a place of component `sid`) into `holder`.
    // opts: { readOnly, mayEdit, focusId }.
    function fillPlace(holder, ref, sid, opts) {
        opts = opts || {};
        var ro = !!opts.readOnly;
        var fields = placeFields(sid);
        var values = placeValuesOf(ref);
        var changed = [];
        var vrow = opts.variants ? placeVariantRow(ref, sid, ro) : { html: '', changed: false };
        var h = vrow.html;
        if (vrow.changed) changed.push({ id: '_variant', kind: 'variant', label: _sdT('Variant') });
        fields.forEach(function (f) {
            var ch = placeChanged(values, f);
            if (ch) changed.push(f);
            var label = esc(f.label || kindName(f.kind));
            h += '<div class="sd-prop-row sd-co-prow" data-co-row="' + esc(f.id) + '">' +
                (ch ? '<button type="button" class="sd-chg-reset" data-co-reset="' + esc(f.id) + '"' + (ro ? ' disabled' : '') +
                    ' title="' + esc(_sdT('Reset to the component')) + '" aria-label="' + esc(_sdT('Reset {var} to the component', f.label || kindName(f.kind))) + '"></button>' : '') +
                '<label class="sd-prop-label' + (ch ? ' sd-prop-modified' : '') + '" for="sd-co-f-' + esc(f.id) + '">' + label + '</label>' +
                '<div class="sd-prop-control">' + placeControl(f, values, ro) + '</div></div>';
        });
        h += '<div class="sd-co-foot"><span>' + esc(_sdT('The rest is the same in every place.')) + '</span>' +
            (opts.mayEdit ? '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-co-edit>' + esc(_sdT('Edit the component')) + '</button>' : '') +
            '</div>';
        holder.innerHTML = h;
        headSum(holder, changed, values, ref, sid);
        if (opts.focusId) {
            var want = holder.querySelector('[data-co-field="' + opts.focusId + '"]');
            if (want) want.focus();
        }
        if (holder._sdCoPlace) { holder._sdCoPlace = { ref: ref, sid: sid }; return; }
        holder._sdCoPlace = { ref: ref, sid: sid };
        var fieldOf = function (id) {
            var all = placeFields(holder._sdCoPlace.sid);
            for (var i = 0; i < all.length; i++) if (all[i].id === id) return all[i];
            return null;
        };
        var give = function (id, raw) {
            var at = holder._sdCoPlace, f = fieldOf(id);
            if (!f) return;
            var r = placeSet(placeValuesOf(at.ref), f, raw);
            var why = holder.querySelector('[data-co-why="' + id + '"]');
            var ctl = holder.querySelector('[data-co-field="' + id + '"]');
            if (r.refused) {
                if (ctl) ctl.classList.add('is-invalid');
                if (why) { why.textContent = r.refused; why.style.display = 'block'; }
                return;
            }
            placeWrite(at.ref, r.values);
        };
        // The panel's own reset of the changed dot is not run for these rows.
        holder.addEventListener('click', function (e) {
            var t = e.target;
            var vr = t.closest('[data-co-vreset]');
            if (vr) {
                e.stopPropagation();
                if (!vr.disabled) placeVariant(holder._sdCoPlace.ref, '');
                return;
            }
            var rb = t.closest('[data-co-reset]');
            if (rb) {
                e.stopPropagation();
                if (rb.disabled) return;
                placeWrite(holder._sdCoPlace.ref, placeReset(placeValuesOf(holder._sdCoPlace.ref), rb.getAttribute('data-co-reset')));
                return;
            }
            if (t.closest('[data-co-edit]')) { K._sdCoEditComponent(holder._sdCoPlace.ref); return; }
            var pick = t.closest('[data-co-pick]');
            if (!pick || pick.disabled || !K._sdPickerReady()) return;
            var id = pick.getAttribute('data-co-pick'), f = fieldOf(id);
            var box = holder.querySelector('[data-co-field="' + id + '"]');
            var cur = box ? (box.value || box.getAttribute('placeholder') || '') : '';
            var picker = window.pgEditorPicker;
            var p = f && f.kind === 'picture' ? picker.image(cur) : picker.link(cur);
            Promise.resolve(p).then(function (v) { if (v) give(id, v); });
        });
        holder.addEventListener('change', function (e) {
            if (e.target.hasAttribute && e.target.hasAttribute('data-co-variant')) {
                e.stopPropagation();
                placeVariant(holder._sdCoPlace.ref, e.target.value);
                return;
            }
            var id = e.target.getAttribute && e.target.getAttribute('data-co-field');
            if (!id) return;
            e.stopPropagation();
            give(id, e.target.type === 'checkbox' ? e.target.checked : e.target.value);
        });
        holder.addEventListener('input', function (e) {
            if (e.target.getAttribute && e.target.getAttribute('data-co-field')) e.stopPropagation();
        });
        holder.addEventListener('keydown', function (e) {
            var t = e.target;
            if (!t.getAttribute || !t.getAttribute('data-co-field')) return;
            if (e.key === 'Escape') {
                // Leaves the field with what it held, the refusal's message
                // gone with its mark.
                e.preventDefault();
                e.stopPropagation();
                t.value = t.defaultValue;
                t.classList.remove('is-invalid');
                var why = holder.querySelector('[data-co-why="' + t.getAttribute('data-co-field') + '"]');
                if (why) { why.textContent = ''; why.style.display = ''; }
                t.blur();
            } else if (e.key === 'Enter' && t.tagName === 'INPUT') {
                e.preventDefault();
                t.blur();
            }
        });
    }

    // The section head: the count of changed fields (none at none) and, while
    // closed, the first changed value.
    function headSum(holder, changed, values, ref, sid) {
        var card = holder.closest('[data-sd-card]');
        var hdr = card ? card.querySelector(':scope > .sd-sect-hdr') : null;
        if (!hdr) return;
        hdr.querySelectorAll(':scope > .sd-sect-count, :scope > .sd-sect-sum').forEach(function (el) { el.remove(); });
        var sum = null;
        if (changed.length) {
            var first = changed[0], v = values[first.id];
            var text = first.kind === 'variant' ? (placeVariantName(sid, ref) || CO.placeVariant(ref))
                : (first.kind === 'shown' ? _sdT('Hidden') : plainOf(first.kind === 'picture' ? (v && v.src) || v : v));
            sum = { count: changed.length, kind: 'value', text: text, swatch: '' };
        }
        hdr.insertAdjacentHTML('beforeend', K._sdSectHeadExtras(sum));
    }

    // Typing in place on the canvas: `el` is the open text's element in the
    // place `ref` of component `sid`, `id` its text field. Escape leaves
    // with what was there, Enter or a click outside keeps the text; an
    // emptied text shows the component's again.
    function typeIn(el, ref, sid, id) {
        var field = placeFields(sid).filter(function (f) { return f.id === id && f.kind === 'text'; })[0];
        if (!el || !field || el.isContentEditable) return;
        var before = el.innerHTML;
        var done = false;
        el.setAttribute('contenteditable', 'true');
        el.classList.add('sd-co-typing');
        el.focus();
        var finish = function (keep) {
            if (done) return;
            done = true;
            el.removeAttribute('contenteditable');
            el.classList.remove('sd-co-typing');
            el.removeEventListener('keydown', onKey, true);
            el.removeEventListener('blur', onBlur);
            if (!keep) { el.innerHTML = before; K.render(); return; }
            var text = el.innerHTML.replace(/(?:\s*<br\s*\/?>)+\s*$/i, '').trim();
            if (text === before.trim()) { K.render(); return; }
            var r = placeSet(placeValuesOf(ref), field, text);
            if (r.refused) { el.innerHTML = before; K.render(); return; }
            placeWrite(ref, r.values);
        };
        var onKey = function (e) {
            if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); finish(false); }
            else if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); e.stopPropagation(); finish(true); }
        };
        var onBlur = function () { finish(true); };
        el.addEventListener('keydown', onKey, true);
        el.addEventListener('blur', onBlur);
    }

    // ── Variants ─────────────────────────────────────────────────────────
    // Named sets of the component's nodes' classes and options on its root
    // (props._variants, read through the model's variants()); a place keeps
    // only the name (props._variant). A variant is edited on the component
    // itself: its props are put on the nodes, today's editing changes them,
    // and "Done" keeps what differs from the component's own
    // (variantNodes()) and puts that back, as one undo step.

    // The Result line after "Delete", the field of a variant being added or
    // renamed, its message, and the row to focus after a draw.
    var vstate = { result: null, adding: 0, renaming: '', err: '', focus: null, keyOn: false, focusUndo: false };

    function variantsOf(sid) {
        var root = compTree(sid);
        return root ? CO.variants(root) : [];
    }

    function rawVariants(root, create) {
        var p = propsOf(root);
        if (!Array.isArray(p._variants)) {
            if (!create) return [];
            p._variants = [];
        }
        return p._variants;
    }

    function rawIndex(root, name) {
        var list = rawVariants(root, false);
        for (var i = 0; i < list.length; i++) {
            if (list[i] && CO.cleanVariantName(list[i].name) === name) return i;
        }
        return -1;
    }

    // A name a variant takes stops being any variant's former name, so a
    // place on that name follows the variant that has it now, also after
    // that one is renamed again.
    function forgetFormer(root, name) {
        var low = name.toLowerCase();
        rawVariants(root, false).forEach(function (v) {
            if (!v || !Array.isArray(v.was)) return;
            v.was = v.was.filter(function (w) { return CO.cleanVariantName(w).toLowerCase() !== low; });
            if (!v.was.length) delete v.was;
        });
    }

    // Why a name cannot be used, or '' when it can.
    function nameWhy(sid, name, except) {
        if (name === '') return _sdT('This name is taken.');
        var low = name.toLowerCase();
        // "Default" is the list's first row in every language.
        if (low === 'default' || low === _sdT('Default').toLowerCase()) return _sdT('This name is taken.');
        var taken = variantsOf(sid).some(function (v) { return v.name !== except && v.name.toLowerCase() === low; });
        return taken ? _sdT('This name is taken.') : '';
    }

    function changesText(n) {
        return n === 1 ? _sdT('1 change') : _sdT('{var} changes', n);
    }

    function nameField(value) {
        return '<div class="sd-co-vname"><input type="text" class="form-control form-control-sm' + (vstate.err ? ' is-invalid' : '') + '" maxlength="60" data-co-vname' +
            ' placeholder="' + esc(_sdT('Name of the variant')) + '" aria-label="' + esc(_sdT('Name of the variant')) + '" value="' + esc(value) + '">' +
            (vstate.err ? '<div class="invalid-feedback d-block">' + esc(vstate.err) + '</div>' : '') + '</div>';
    }

    function varsHtml(sid) {
        var ed = K.coVar;
        var cur = (ed && ed.sid === sid) ? ed.name : '';
        var h = '';
        if (vstate.result && vstate.result.sid === sid) {
            h += '<div class="pg-result sd-co-result" role="status"><span class="pg-icon-tile pg-tone-teal" aria-hidden="true"><i class="bi bi-check-lg"></i></span>' +
                '<span class="pg-result-line">' + esc(vstate.result.text) + '</span>' +
                '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-co-vundo>' + esc(_sdT('Undo')) + '</button></div>';
        }
        h += '<div class="sd-co-rows" role="listbox" aria-label="' + esc(_sdT('Variants')) + '">';
        [{ name: '', nodes: {} }].concat(variantsOf(sid)).forEach(function (v) {
            var on = v.name === cur;
            if (v.name && vstate.renaming === v.name) { h += nameField(v.name); return; }
            h += '<div class="sd-co-row sd-co-vrow' + (on ? ' is-on' : '') + '" role="option" tabindex="0" aria-selected="' + (on ? 'true' : 'false') + '" data-co-var="' + esc(v.name) + '">' +
                '<span class="sd-co-tick bi' + (on ? ' bi-check-lg' : '') + '" aria-hidden="true"></span>' +
                '<span class="sd-co-ki bi bi-layers" aria-hidden="true"></span>' +
                '<b class="sd-co-label">' + esc(v.name || _sdT('Default')) + '</b>' +
                (v.name ? '<small class="sd-co-vcount">' + esc(changesText(CO.variantChanges(v))) + '</small>' +
                    '<button type="button" class="sd-co-more bi bi-three-dots" data-co-vmore aria-haspopup="menu" aria-label="' + esc(_sdT('More actions')) + '"></button>' : '') +
            '</div>';
        });
        if (vstate.adding === sid) h += nameField('');
        h += '</div>';
        return h + '<button type="button" class="btn btn-sm btn-outline theme-secondary sd-co-vadd" data-co-var-add><span class="bi bi-plus-lg" aria-hidden="true"></span> ' + esc(_sdT('Add a variant')) + '</button>';
    }

    // Draws the variant list into `el` (the "Shared Component" section's holder).
    function fillVariants(el, sid) {
        if (vstate.result && vstate.result.sid !== sid) vstate.result = null;
        if (vstate.adding && vstate.adding !== sid) vstate.adding = 0;
        el.innerHTML = varsHtml(sid);
        var field = el.querySelector('[data-co-vname]');
        // A drawing queued before the last one fills a box no longer in the
        // page: the focus waits for the one that is.
        var vundo = (vstate.focusUndo && el.isConnected) ? el.querySelector('[data-co-vundo]') : null;
        if (el.isConnected) vstate.focusUndo = false;
        if (vundo) {
            // After "Delete" the keyboard is on its Undo.
            vundo.focus();
        } else if (field) {
            field.focus();
            field.setSelectionRange(field.value.length, field.value.length);
        } else if (vstate.focus !== null) {
            var again = el.querySelector('.sd-co-vrow[data-co-var="' + (window.CSS && CSS.escape ? CSS.escape(vstate.focus) : vstate.focus) + '"]');
            if (again) again.focus();
        }
        vstate.focus = null;
        if (el._sdCoVars === sid) return;
        var bound = !!el._sdCoVars;
        el._sdCoVars = sid;
        if (bound) return;
        var sidOf = function () { return el._sdCoVars; };
        el.addEventListener('click', function (e) {
            var t = e.target, id = sidOf();
            if (t.closest('[data-co-vundo]')) {
                var res = vstate.result;
                vstate.result = null;
                if (res && res.sid === id) res.undo();
                return;
            }
            if (t.closest('[data-co-var-add]')) { addVariant(el, id); return; }
            if (t.closest('[data-co-vname]')) return;
            var more = t.closest('[data-co-vmore]');
            var row = t.closest('.sd-co-vrow');
            if (more && row) { variantMenu(id, row.getAttribute('data-co-var'), more); return; }
            if (row) chooseVariant(id, row.getAttribute('data-co-var'));
        });
        el.addEventListener('keydown', function (e) {
            var id = sidOf();
            var t = e.target;
            if (t.hasAttribute && t.hasAttribute('data-co-vname')) {
                e.stopPropagation();
                if (e.key === 'Enter') { e.preventDefault(); nameDone(id, t.value, true); }
                else if (e.key === 'Escape') { e.preventDefault(); nameDone(id, '', false); }
                return;
            }
            var row = t.classList && t.classList.contains('sd-co-vrow') ? t : null;
            if (!row) return;
            var name = row.getAttribute('data-co-var');
            var rows = Array.prototype.slice.call(el.querySelectorAll('.sd-co-vrow'));
            var at = rows.indexOf(row);
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var to = rows[at + (e.key === 'ArrowDown' ? 1 : -1)];
                if (to) to.focus();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                chooseVariant(id, name);
            } else if (e.key === 'F2' && name) {
                e.preventDefault();
                renameVariant(id, name);
            } else if ((e.key === 'Delete' || e.key === 'Backspace') && name) {
                e.preventDefault();
                e.stopPropagation();
                deleteVariant(id, name);
            }
        });
        el.addEventListener('focusout', function (e) {
            var t = e.target;
            if (t.hasAttribute && t.hasAttribute('data-co-vname') && !vstate.err) {
                // A click elsewhere keeps a valid name and drops an empty one.
                setTimeout(function () { if (document.activeElement !== t && t.isConnected) nameDone(sidOf(), t.value, t.value.trim() !== ''); }, 0);
            }
        });
    }

    // "Add a variant": the name field in the list.
    function addVariant(el, sid) {
        vstate.adding = sid;
        vstate.renaming = '';
        vstate.err = '';
        vstate.result = null;
        if (el && el.isConnected) fillVariants(el, sid);
        else K.renderProperties();
    }

    // From the command palette: the place's panel, with the name field.
    function addVariantFor(sid) {
        var node = K.selectedNode;
        if (!(node && node.type === 'shared_ref' && (parseInt(node.props && node.props.sharedId, 10) || 0) === sid)) {
            var ref = null;
            (function walk(n) {
                if (ref || !n || typeof n !== 'object') return;
                if (n.type === 'shared_ref' && (parseInt(n.props && n.props.sharedId, 10) || 0) === sid) { ref = n; return; }
                (n.children || []).forEach(walk);
            })(K.tree);
            if (!ref) return;
            if (K.coVar) variantDone();
            K.selectedNode = ref;
            K.selectedNodes = [];
        }
        vstate.adding = sid;
        vstate.renaming = '';
        vstate.err = '';
        K._sdSelectRender();
    }

    // The name field's Enter (keep) or Escape: a new variant is added and its
    // editing starts; a renamed one takes the name. A taken or empty name
    // keeps the field with its message.
    function nameDone(sid, raw, keep) {
        var renaming = vstate.renaming;
        if (!keep) {
            vstate.adding = 0;
            vstate.renaming = '';
            vstate.err = '';
            vstate.focus = renaming || null;
            K.renderProperties();
            return;
        }
        var name = CO.cleanVariantName(raw);
        if (renaming && name === renaming) { nameDone(sid, '', false); return; }
        var why = nameWhy(sid, name, renaming);
        if (why) {
            vstate.err = why;
            K.renderProperties();
            return;
        }
        vstate.adding = 0;
        vstate.renaming = '';
        vstate.err = '';
        var root = compTree(sid);
        if (!root) return;
        if (renaming) {
            var i = rawIndex(root, renaming);
            if (i === -1) return;
            vstate.focus = name;
            if (K.coVar && K.coVar.sid === sid && K.coVar.name === renaming) K.coVar.name = name;
            // The old name stays a former name: places on other pages keep
            // it and still draw this variant.
            write(sid, function () {
                var v = rawVariants(root, false)[i];
                forgetFormer(root, name);
                v.was = (Array.isArray(v.was) ? v.was : []).concat([renaming]);
                v.name = name;
            });
            drawLine();
            return;
        }
        write(sid, function () {
            forgetFormer(root, name);
            rawVariants(root, true).push({ name: name, nodes: {} });
        });
        startVariant(sid, name);
    }

    function renameVariant(sid, name) {
        vstate.renaming = name;
        vstate.adding = 0;
        vstate.err = '';
        K.renderProperties();
    }

    function duplicateVariant(sid, name) {
        var root = compTree(sid);
        var i = root ? rawIndex(root, name) : -1;
        if (i === -1) return;
        // The original name is shortened so that the copy's word and number
        // fit in a name's 60 characters: cut by the limit, the copy took a
        // name already taken and the search never ended.
        var base = Array.from(name), copy, n = 1;
        for (;;) {
            copy = _sdT('{var} copy', base.join('').trim()) + (n > 1 ? ' ' + n : '');
            if (Array.from(copy).length > 60 && base.length > 1) { base.pop(); continue; }
            copy = CO.cleanVariantName(copy);
            if (!nameWhy(sid, copy, '')) break;
            n++;
        }
        vstate.focus = copy;
        write(sid, function () {
            var v = CO.variant(root, name);
            rawVariants(root, false).splice(i + 1, 0, { name: copy, nodes: JSON.parse(JSON.stringify(v ? v.nodes : {})) });
        });
    }

    // "Delete" asks nothing: a place keeps only the name and shows the
    // default when it is gone. The Result line's Undo puts it back.
    function deleteVariant(sid, name) {
        var root = compTree(sid);
        var i = root ? rawIndex(root, name) : -1;
        if (i === -1) return;
        if (K.coVar && K.coVar.sid === sid) variantDone();
        root = compTree(sid);
        i = root ? rawIndex(root, name) : -1;
        if (i === -1) return;
        var kept = JSON.parse(JSON.stringify(rawVariants(root, false)[i]));
        write(sid, function () {
            var list = rawVariants(root, false);
            list.splice(i, 1);
            if (!list.length) delete root.props._variants;
        });
        vstate.result = { sid: sid, text: _sdT('The variant “{var}” was deleted. Places that used it show the default.', name), undo: function () {
            var r = compTree(sid);
            if (!r) return;
            vstate.focus = name;
            write(sid, function () {
                var list = rawVariants(r, true);
                list.splice(Math.min(i, list.length), 0, kept);
            });
        } };
        vstate.focusUndo = true;
        K.renderProperties();
    }

    function variantMenu(sid, name, btn) {
        var r = btn.getBoundingClientRect();
        K.showCtxMenu(r.left, r.bottom + 4, [
            { icon: 'bi-pencil', label: esc(_sdT('Rename')), handler: function () { renameVariant(sid, name); } },
            { icon: 'bi-copy', label: esc(_sdT('Duplicate')), handler: function () { duplicateVariant(sid, name); } },
            { sep: true },
            { icon: 'bi-trash', label: esc(_sdT('Delete')), danger: true, handler: function () { deleteVariant(sid, name); } }
        ]);
    }

    // A row chosen: "Default" ends the editing of a variant, a variant starts it.
    function chooseVariant(sid, name) {
        vstate.focus = name;
        if (!name) { if (K.coVar && K.coVar.sid === sid) variantDone(); else K.renderProperties(); return; }
        if (K.coVar && K.coVar.sid === sid && K.coVar.name === name) return;
        startVariant(sid, name);
    }

    // The node of `root` with id `id` (a placed component inside not entered).
    function eachNode(root, fn) {
        (function walk(n, top) {
            if (!n || typeof n !== 'object') return;
            fn(n);
            if (!top && n.type === 'shared_ref') return;
            (n.children || []).forEach(function (k) { walk(k, false); });
        })(root, true);
    }

    function nodeKey(n) {
        var id = n._id != null ? n._id : n.id;
        return (typeof id === 'string' || typeof id === 'number') ? String(id) : '';
    }

    // The component's shape: its nodes' ids, nested as they are.
    function shapeOf(root) {
        return JSON.stringify((function walk(n, top) {
            if (!n || typeof n !== 'object') return null;
            var kids = (!top && n.type === 'shared_ref') ? [] : (n.children || []).map(function (k) { return walk(k, false); });
            return [nodeKey(n), kids];
        })(root, true));
    }

    function startVariant(sid, name) {
        var root = compTree(sid);
        var v = root ? CO.variant(root, name) : null;
        if (!v) return;
        if (K.coVar) variantDone();
        root = compTree(sid);
        if (!root) return;
        var node = K.selectedNode;
        // The selection moves into the component, so its elements are edited.
        if (!node || node.type === 'shared_ref' || K._resolveSharedSid(node) !== sid) {
            var first = Array.isArray(root.children) ? root.children[0] : null;
            K.selectedNode = first || root;
            K.selectedNodes = [];
        }
        var base = JSON.stringify(root);
        K._sdCoWrite(sid, function () {
            eachNode(root, function (n) {
                var p = v.nodes[nodeKey(n)];
                if (!p) return;
                if (!n.props || typeof n.props !== 'object') n.props = {};
                Object.keys(p).forEach(function (k) { n.props[k] = p[k]; });
            });
            K.coVar = { sid: sid, name: name, base: base, sig: shapeOf(root), depth: K.undoDepth };
            drawLine();
            listen(true);
        });
        // The list the variant was chosen in is gone with the selection:
        // the keyboard goes on with "Done" over the canvas.
        var done = document.querySelector('.sd-co-vline [data-co-vdone]');
        if (done) done.focus();
    }

    // "Done": the variant keeps what differs from the component's own, which
    // is put back on the nodes.
    function variantDone() {
        var ed = K.coVar;
        if (!ed) return;
        K.coVar = null;
        listen(false);
        var root = compTree(ed.sid);
        if (!root) { K.render(); return; }
        var base = JSON.parse(ed.base);
        var own = {};
        eachNode(base, function (n) { own[nodeKey(n)] = n.props || {}; });
        var kept = CO.variantNodes(base, root);
        vstate.focus = ed.name;
        K._sdCoVarCommit(ed.sid, ed.depth, function () {
            eachNode(root, function (n) {
                var was = own[nodeKey(n)];
                if (!was || !n.props) return;
                Object.keys(n.props).forEach(function (k) { if (CO.variantPropOk(k) && !hasOwn(was, k)) delete n.props[k]; });
                Object.keys(was).forEach(function (k) { if (CO.variantPropOk(k)) n.props[k] = was[k]; });
            });
            var i = rawIndex(root, ed.name);
            if (i !== -1) rawVariants(root, false)[i].nodes = kept;
        });
    }

    // After a draw while a variant is edited: an element added, removed or
    // moved in the component is taken back, with the amber line.
    function variantGuard() {
        var ed = K.coVar;
        var root = ed ? compTree(ed.sid) : null;
        if (!root || shapeOf(root) === ed.sig || ed.undoing) return;
        ed.undoing = true;
        setTimeout(function () {
            ed.undoing = false;
            var r = compTree(ed.sid);
            if (K.coVar !== ed || !r || shapeOf(r) === ed.sig) return;
            K.undo();
            drawLine(_sdT('A variant keeps classes and options only. Choose “Done” to add, remove or move an element.'));
        }, 0);
    }

    // The line over the canvas while a variant is edited, with "Done"; an
    // amber line under it when something was refused.
    function drawLine(warn) {
        var ed = K.coVar;
        var line = document.querySelector('.sd-co-vline');
        if (!ed) { if (line) line.remove(); return; }
        var wrap = document.querySelector('.sd-canvas-wrapper');
        if (!wrap) return;
        if (!line) {
            line = document.createElement('div');
            line.className = 'sd-co-vline';
            line.setAttribute('role', 'status');
            wrap.insertBefore(line, wrap.firstChild);
            line.addEventListener('click', function (e) { if (e.target.closest('[data-co-vdone]')) variantDone(); });
        }
        var c = K.sharedCache[ed.sid];
        line.innerHTML = '<div class="sd-co-vline-main"><span class="bi bi-layers" aria-hidden="true"></span><span>' +
            esc(_sdT('Editing the variant “{var:1}” of “{var:2}”. Classes and options you change are kept in it.', [ed.name, (c && c.name) || ('#' + ed.sid)])) +
            '</span><button type="button" class="btn btn-sm btn-outline theme-secondary" data-co-vdone>' + esc(_sdT('Done')) + '</button></div>' +
            (warn ? '<div class="sd-co-vwarn"><span class="bi bi-exclamation-triangle" aria-hidden="true"></span><span>' + esc(warn) + '</span></div>' : '');
    }

    // Escape ends the editing, but not inside a field; with the editor's
    // menu open (showCtxMenu(), a row's three-dot menu) it closes the menu.
    function onEscape(e) {
        if (e.key !== 'Escape' || !K.coVar) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName || ''))) return;
        e.preventDefault();
        e.stopPropagation();
        var menu = document.getElementById('sd-tree-ctx-menu');
        if (menu) { menu.remove(); return; }
        variantDone();
    }

    function listen(on) {
        if (on === vstate.keyOn) return;
        vstate.keyOn = on;
        if (on) document.addEventListener('keydown', onEscape, true);
        else document.removeEventListener('keydown', onEscape, true);
    }

    // ── A place's variant ────────────────────────────────────────────────

    // The variant row of "This place": { html, changed, gone }.
    function placeVariantRow(ref, sid, ro) {
        var list = variantsOf(sid);
        var stored = CO.placeVariant(ref);
        if (!list.length && !stored) return { html: '', changed: false, gone: '' };
        // A name kept from before a rename shows the variant's name now.
        var mine = placeVariantName(sid, ref);
        var known = mine !== '';
        var gone = (stored && !known) ? stored : '';
        var changed = stored !== '';
        var opts = '<option value=""' + (!known ? ' selected' : '') + '>' + esc(_sdT('Default')) + '</option>';
        list.forEach(function (v) {
            opts += '<option value="' + esc(v.name) + '"' + (v.name === mine ? ' selected' : '') + '>' + esc(v.name) + '</option>';
        });
        var html = (gone ? '<div class="sd-co-vwarn" role="status"><span class="bi bi-exclamation-triangle" aria-hidden="true"></span><span>' +
                esc(_sdT('The variant “{var}” no longer exists. This place shows the default.', gone)) + '</span></div>' : '') +
            '<div class="sd-prop-row sd-co-prow" data-co-row="_variant">' +
            (changed ? '<button type="button" class="sd-chg-reset" data-co-vreset' + (ro ? ' disabled' : '') +
                ' title="' + esc(_sdT('Reset to the component')) + '" aria-label="' + esc(_sdT('Reset {var} to the component', _sdT('Variant'))) + '"></button>' : '') +
            '<label class="sd-prop-label' + (changed ? ' sd-prop-modified' : '') + '" for="sd-co-variant">' + esc(_sdT('Variant')) + '</label>' +
            '<div class="sd-prop-control"><select class="form-control form-control-sm" id="sd-co-variant" data-co-variant' + (ro ? ' disabled' : '') + '>' + opts + '</select></div></div>';
        return { html: html, changed: changed, gone: gone };
    }

    // The name of the variant a place draws now, '' for none or a gone one.
    function placeVariantName(sid, ref) {
        var root = compTree(sid), stored = CO.placeVariant(ref);
        var v = (root && stored) ? CO.variant(root, stored) : null;
        return v ? v.name : '';
    }

    // A place picks a variant ('' is "Default"): one undo step, the page changed.
    function placeVariant(ref, name) {
        if (!ref || ref.type !== 'shared_ref') return;
        name = CO.cleanVariantName(name);
        if (CO.placeVariant(ref) === name) return;
        K.saveState();
        if (!ref.props) ref.props = {};
        if (name) ref.props._variant = name;
        else delete ref.props._variant;
        K.render();
    }

    return {
        openFor: openFor,
        fillList: fillList,
        close: closePop,
        kindsOf: kindsOf,
        defaultLabel: defaultLabel,
        fillPlace: fillPlace,
        typeIn: typeIn,
        placeChanged: placeChanged,
        placeSet: placeSet,
        placeReset: placeReset,
        fillVariants: fillVariants,
        addVariant: addVariant,
        addVariantFor: addVariantFor,
        placeVariant: placeVariant,
        placeVariantRow: placeVariantRow,
        renameVariant: renameVariant,
        duplicateVariant: duplicateVariant,
        nameDone: nameDone,
        nameWhy: nameWhy,
        variantDone: variantDone,
        variantGuard: variantGuard,
        startVariant: startVariant
    };
});
