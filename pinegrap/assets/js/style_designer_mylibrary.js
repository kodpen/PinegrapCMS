/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - My library: "Save to my library" (an element's menu
 * and the command palette) with its window, and "My library" as the first
 * group of the Blocks list (the Components list in a design without
 * Bootstrap): its items under their own groups, found by the list's search,
 * shown in the hover card with a drawing of their tree, added by drag or
 * double-click as a copy the page owns. A copy is made ready for this
 * design on the server (designer/library_prepare): a class rule that
 * differs gets a new name, a missing rule or variable is added to the User
 * Styles, a binding the site lacks is cleared and marked. "Manage
 * library" (the group's last row, the command palette) renames, regroups,
 * orders and deletes items in the part 'librarymanage'.
 * Not needed to open a page: the core runs this file when the Blocks list
 * is first shown or a save is asked for (_sdPartLoad(), pg_designer_parts()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('mylibrary', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    var GROUP_KEY = 'pinegrap_designer_library_group';
    var FOLD_KEY = 'pinegrap_designer_library_folded';
    var NAME_MAX = 60;
    // items: the list's cards in the library's order (null until loaded);
    // ready: id => the prepared copy of an item without rows to make, for
    // the css it was made against.
    var state = { host: null, items: null, loading: null, ready: {}, mayManage: false };

    function _post(sub, extra) {
        return K._pgApiPost(sub, extra).then(function (r) {
            if (!r || r.status !== 'success') throw new Error((r && r.message) || _sdT('The operation failed.'));
            return r;
        });
    }

    function _load(force) {
        if (state.loading && !force) return state.loading;
        state.loading = _post('library_list').then(function (r) {
            state.items = Array.isArray(r.items) ? r.items : [];
            return state.items;
        }, function (err) {
            state.loading = null;
            throw err;
        });
        return state.loading;
    }

    function _fold(s) {
        var out = String(s == null ? '' : s).replace(/İ/g, 'i').toLowerCase();
        if (out.normalize) out = out.normalize('NFD').replace(/[̀-ͯ]/g, '');
        return out.replace(/ı/g, 'i');
    }

    function _find(id) {
        id = parseInt(id, 10);
        return (state.items || []).filter(function (it) { return it.id === id; })[0] || null;
    }

    // ── What an element is ─────────────────────────────────────────────

    var SECTIONS = ['section', 'header', 'footer', 'main', 'article', 'aside', 'nav'];

    function _classes(node) {
        return String((node && node.props && node.props.cssClass) || '').split(/\s+/).filter(Boolean);
    }

    // section, row, card or group: the same reading as the server's
    // pg_library_root_kind().
    function _kind(node) {
        var c = _classes(node);
        var tag = String((node.props && node.props.tag) || '').toLowerCase();
        if (node.type === 'row' || c.indexOf('row') !== -1) return 'row';
        if (c.indexOf('card') !== -1) return 'card';
        if (SECTIONS.indexOf(node.type) !== -1 || SECTIONS.indexOf(tag) !== -1) return 'section';
        return 'group';
    }

    function _kindLabel(kind) {
        return { section: _sdT('Section'), row: _sdT('Row'), card: _sdT('Card') }[kind] || _sdT('Group');
    }

    function _count(node) {
        var n = 1;
        (node && node.children || []).forEach(function (c) { n += _count(c); });
        return n;
    }

    function _countLine(kind, count) {
        return _kindLabel(kind) + ' · ' + (count === 1 ? _sdT('1 element') : _sdT('{var} elements', count));
    }

    // Whether an element may be saved: one that holds others (a section, a
    // row, a card, a group), not a text, a picture or a button.
    function offered(node) {
        return !!(node && node.type !== 'root' && node.type !== 'shared_ref' && node.children && node.children.length);
    }

    // ── The design as the server needs it ──────────────────────────────

    function _userStyles() {
        return K._userStylesGet() || '';
    }

    // The values the canvas gives the variables named, as the design
    // defines them (its User Styles, look, palette or variables).
    function _canvasVars(names) {
        var out = {};
        var doc = K.canvasDoc;
        var cs = null;
        try { cs = doc && doc.documentElement ? doc.defaultView.getComputedStyle(doc.documentElement) : null; } catch (e) { cs = null; }
        if (!cs) return out;
        names.forEach(function (n) {
            var v = String(cs.getPropertyValue(n) || '').trim();
            if (v && !/[{};<>]/.test(v)) out[n] = v;
        });
        return out;
    }

    function _varNames(text) {
        var seen = {}, out = [], re = /var\(\s*(--[_a-zA-Z0-9-]+)/g, m;
        while ((m = re.exec(text))) {
            if (m[1].indexOf('--bs-') === 0 || seen[m[1]]) continue;
            seen[m[1]] = true;
            out.push(m[1]);
        }
        return out;
    }

    // ── Save to my library ─────────────────────────────────────────────

    function save(node) {
        if (!offered(node)) {
            K.sdToast(esc(_sdT('Choose a section, row, card or group first.')), 'info');
            return;
        }
        _load().then(function () { _saveWindow(node); }, function (err) {
            K.sdToast(esc(err.message), 'error');
        });
    }

    function _groups() {
        var seen = {}, out = [];
        (state.items || []).forEach(function (it) {
            if (it.group && !seen[it.group]) { seen[it.group] = true; out.push(it.group); }
        });
        return out;
    }

    function _saveWindow(node) {
        var lastGroup = '';
        try { lastGroup = localStorage.getItem(GROUP_KEY) || ''; } catch (e) {}
        if (_groups().indexOf(lastGroup) === -1) lastGroup = '';
        var name = String(K.getNodeLabel(node) || '').slice(0, NAME_MAX);
        var el = document.createElement('dialog');
        el.className = 'dialog pg-dialog-centered sd-lib-save';
        el.tabIndex = -1;
        el.setAttribute('aria-labelledby', 'sd-lib-save-title');
        el.innerHTML =
            '<div class="dialog-header py-3"><div class="min-w-0"><h6 class="dialog-title mb-0" id="sd-lib-save-title">' + esc(_sdT('Save to my library')) + '</h6>' +
                    '<div class="small fg-secondary">' + esc(_countLine(_kind(node), _count(node))) + '</div></div>' +
                '<button type="button" class="btn-close" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button></div>' +
            '<div class="dialog-body small">' +
                '<label class="form-label" for="sd-lib-name">' + esc(_sdT('Name')) + '</label>' +
                '<input type="text" class="form-control form-control-sm" id="sd-lib-name" maxlength="' + NAME_MAX + '" autocomplete="off" value="' + esc(name) + '">' +
                '<div class="sd-lib-taken fg-warning mt-1" hidden></div>' +
                '<label class="form-label mt-3" for="sd-lib-group">' + esc(_sdT('Group')) + '</label>' +
                '<div class="sd-lib-combo">' +
                    '<input type="text" class="form-control form-control-sm" id="sd-lib-group" maxlength="' + NAME_MAX + '" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="sd-lib-group-list" aria-autocomplete="list" value="' + esc(lastGroup) + '">' +
                    '<ul class="sd-lib-offers" id="sd-lib-group-list" role="listbox" hidden></ul>' +
                '</div>' +
                '<p class="fg-secondary mt-3 mb-0"><span class="bi bi-info-circle me-2" aria-hidden="true"></span>' +
                    esc(_sdT('A copy you start from. To keep every place the same, make it a shared component.')) + '</p>' +
                '<div class="fg-danger mt-3" data-lib-error role="alert" hidden></div>' +
            '</div>' +
            '<div class="dialog-footer py-3">' +
                '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-bs-dismiss="dialog">' + esc(_sdT('Cancel')) + '</button>' +
                '<button type="button" class="btn btn-sm btn-solid theme-primary" data-lib-save>' + esc(_sdT('Save')) + '</button>' +
            '</div>';
        document.body.appendChild(el);

        var nameIn = el.querySelector('#sd-lib-name');
        var groupIn = el.querySelector('#sd-lib-group');
        var offers = el.querySelector('.sd-lib-offers');
        var taken = el.querySelector('.sd-lib-taken');
        var errEl = el.querySelector('[data-lib-error]');
        var okBtn = el.querySelector('[data-lib-save]');
        var busy = false, pick = -1;

        function check() {
            var v = nameIn.value.trim();
            okBtn.disabled = busy || !v;
            var same = (state.items || []).filter(function (it) { return _fold(it.name) === _fold(v); })[0];
            taken.hidden = !same;
            if (same) {
                taken.textContent = same.group
                    ? _sdT('The library has an item of this name in "{var}". Both are kept.', same.group)
                    : _sdT('The library has an item of this name. Both are kept.');
            }
        }

        // The groups in use, the nearest match first; a text that matches
        // none is offered as a new group.
        function drawOffers() {
            var q = _fold(groupIn.value.trim());
            var list = _groups().filter(function (g) { return !q || _fold(g).indexOf(q) !== -1; })
                .sort(function (a, b) { return _fold(a).indexOf(q) - _fold(b).indexOf(q); });
            var rows = list.map(function (g) { return { value: g, html: esc(g) }; });
            var typed = groupIn.value.trim();
            if (typed && !list.some(function (g) { return _fold(g) === q; })) {
                rows.push({ value: typed, html: '<span class="bi bi-plus me-2" aria-hidden="true"></span>' + esc(_sdT('New group "{var}"', typed)) });
            }
            pick = -1;
            offers.innerHTML = rows.map(function (r, i) {
                return '<li role="option" id="sd-lib-offer-' + i + '" data-value="' + esc(r.value) + '">' + r.html + '</li>';
            }).join('');
            var open = rows.length > 0 && document.activeElement === groupIn;
            offers.hidden = !open;
            groupIn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeOffers() {
            offers.hidden = true;
            groupIn.setAttribute('aria-expanded', 'false');
            groupIn.removeAttribute('aria-activedescendant');
        }

        function choose(li) {
            groupIn.value = li.getAttribute('data-value');
            closeOffers();
        }

        function submit() {
            if (busy || !nameIn.value.trim()) return;
            busy = true;
            check();
            errEl.hidden = true;
            var css = _userStyles();
            var vars = _canvasVars(_varNames(JSON.stringify(node) + '\n' + css));
            var group = groupIn.value.trim().slice(0, NAME_MAX);
            _post('library_save', { tree: node, name: nameIn.value.trim(), group: group, css: css, vars: vars }).then(function (r) {
                try { localStorage.setItem(GROUP_KEY, group); } catch (e) {}
                if (state.items && r.item) state.items.push(r.item);
                pgUI.dialog.hide(el);
                _redraw();
                _savedLine(r.item);
            }, function (err) {
                busy = false;
                check();
                // The server's reason, in the window, which stays open.
                errEl.textContent = err.message;
                errEl.hidden = false;
            });
        }

        nameIn.addEventListener('input', check);
        groupIn.addEventListener('input', drawOffers);
        groupIn.addEventListener('focus', drawOffers);
        groupIn.addEventListener('blur', function () { setTimeout(closeOffers, 120); });
        offers.addEventListener('mousedown', function (e) {
            var li = e.target.closest('li');
            if (li) { e.preventDefault(); choose(li); }
        });
        el.addEventListener('keydown', function (e) {
            var lis = offers.hidden ? [] : offers.querySelectorAll('li');
            if (e.target === groupIn && lis.length && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault();
                pick = (pick + (e.key === 'ArrowDown' ? 1 : -1) + lis.length) % lis.length;
                Array.prototype.forEach.call(lis, function (li, i) { li.classList.toggle('active', i === pick); li.setAttribute('aria-selected', i === pick ? 'true' : 'false'); });
                groupIn.setAttribute('aria-activedescendant', lis[pick].id);
                return;
            }
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                e.preventDefault();
                if (e.target === groupIn && pick >= 0 && lis[pick]) { choose(lis[pick]); return; }
                submit();
                return;
            }
            if (e.key === 'Escape' && !offers.hidden) { e.preventDefault(); e.stopPropagation(); closeOffers(); }
        });
        okBtn.addEventListener('click', submit);
        check();
        pgUI.dialog.on(el, 'shown', function () { nameIn.focus(); nameIn.select(); });
        pgUI.dialog.on(el, 'hidden', function () { el.remove(); });
        pgUI.dialog.show(el);
    }

    // One line once saved; "Show" opens the list on the new item.
    function _savedLine(item) {
        if (!item) return;
        var id = 'sd-lib-show-' + item.id;
        var text = item.group
            ? esc(_sdT('Saved to my library, in {var}.', item.group))
            : esc(_sdT('Saved to my library.'));
        K.sdToast(text + ' <button type="button" class="btn btn-link btn-sm p-0 align-baseline" id="' + id + '">' + esc(_sdT('Show')) + '</button>', 'success');
        var btn = document.getElementById(id);
        if (btn) btn.addEventListener('click', function () { show(item.id); });
    }

    // ── The group in the list ──────────────────────────────────────────

    function mount(host) {
        if (host) state.host = host;
        if (!state.host) return Promise.resolve();
        return _load().then(_redraw, function () { /* the next showing asks again */ });
    }

    function _folded() {
        try { return localStorage.getItem(FOLD_KEY) === '1'; } catch (e) { return false; }
    }

    function _rowHTML(it) {
        var html = K._sdPaletteItemHTML({ type: 'library', label: esc(it.name), icon: it.kind === 'row' ? 'bi-layout-three-columns' : 'bi-bookmark',
            extra: { libraryId: it.id } }, 'sd-lib-item');
        // The list's search reads the item's name and its group.
        return html.replace(/ data-search="[^"]*"/, ' data-search="' + esc(' ' + _fold(it.name + ' ' + (it.group || ''))) + '"');
    }

    function _redraw() {
        var host = state.host;
        if (!host || !state.items) return;
        var old = host.querySelector('.sd-lib-cat');
        var items = state.items;
        var maySave = K._sdAccess() === 'full';
        // An empty library has no group for a content role.
        if (!items.length && !maySave) { if (old) old.remove(); return; }
        var body = '';
        if (!items.length) {
            body = '<p class="sd-lib-empty">' + esc(_sdT('Nothing saved yet. Choose "Save to my library" in the menu of a section to start from it again.')) + '</p>';
        } else {
            items.filter(function (it) { return !it.group; }).forEach(function (it) { body += _rowHTML(it); });
            _groups().forEach(function (g) {
                body += '<div class="sd-lib-group" data-lib-group="' + esc(g) + '">' + esc(g) + '</div>';
                items.filter(function (it) { return it.group === g; }).forEach(function (it) { body += _rowHTML(it); });
            });
        }
        // "Manage library" is the group's last row, for the roles that
        // may manage.
        if (items.length && maySave) {
            body += '<button type="button" class="sd-lib-manage-row" data-lib-manage><span class="bi bi-collection me-2" aria-hidden="true"></span>' + esc(_sdT('Manage library')) + '</button>';
        }
        var sec = document.createElement('div');
        sec.className = 'sd-category sd-lib-cat' + (_folded() ? ' collapsed' : '');
        sec.innerHTML = '<div class="sd-category-header"><button type="button" class="sd-category-toggle" aria-expanded="' + (_folded() ? 'false' : 'true') + '">' +
            '<span class="bi bi-chevron-down"></span>' + esc(_sdT('My library')) + '</button>' +
            (items.length ? '<span class="sd-lib-count">' + items.length + '</span>' : '') + '</div>' +
            '<div class="sd-category-items">' + body + '</div>';
        if (old) old.replaceWith(sec); else host.insertBefore(sec, host.firstChild);
        K._sdWirePaletteItems(sec, document.getElementById('sd-comp-list'));
        sec.querySelector('.sd-category-header').addEventListener('click', function () {
            try { localStorage.setItem(FOLD_KEY, sec.classList.contains('collapsed') ? '1' : '0'); } catch (e) {}
        });
        var manageRow = sec.querySelector('[data-lib-manage]');
        if (manageRow) manageRow.addEventListener('click', manage);
        _wireSearch(host);
        _searchHeads(host);
    }

    // A group's name inside "My library" shows while one of its items does.
    function _searchHeads(host) {
        var sec = host.querySelector('.sd-lib-cat');
        if (!sec) return;
        sec.querySelectorAll('.sd-lib-group').forEach(function (head) {
            var on = false;
            for (var el = head.nextElementSibling; el && !el.classList.contains('sd-lib-group'); el = el.nextElementSibling) {
                if (el.style.display !== 'none') on = true;
            }
            head.hidden = !on;
        });
        // While a search is typed the row "Manage library" is not drawn.
        var manageRow = sec.querySelector('[data-lib-manage]');
        var pane = host.closest('.sd-pal-list');
        var input = pane && pane.querySelector('input[type="text"]');
        if (manageRow) manageRow.hidden = !!(input && input.value.trim());
    }

    function _wireSearch(host) {
        var pane = host.closest('.sd-pal-list');
        var input = pane && pane.querySelector('input[type="text"]');
        if (!input || input._sdLibWired) return;
        input._sdLibWired = true;
        // After the list's own handler has shown and hidden the rows.
        input.addEventListener('input', function () { _searchHeads(host); });
    }

    // Opens the list on "My library" with an item in view.
    function show(id) {
        K._sdPalSwitchShow(document.getElementById('sd-comp-outer'), 'blocks', true);
        mount().then(function () {
            var sec = state.host && state.host.querySelector('.sd-lib-cat');
            if (!sec) return;
            sec.classList.remove('collapsed');
            var row = sec.querySelector('.sd-drag-item[data-extra*=\'"libraryId":' + parseInt(id, 10) + '}\']');
            if (row) { row.scrollIntoView({ block: 'nearest' }); row.focus(); }
        });
    }

    // ── The hover card ─────────────────────────────────────────────────

    function tip(row) {
        var extra = {};
        try { extra = JSON.parse(row.dataset.extra || '{}'); } catch (e) { extra = {}; }
        var it = _find(extra.libraryId);
        if (!it) return '';
        // The copy is made ready while the card is read.
        _prefetch(it);
        var svg = K._sdBlockSketch(it.tree);
        var kindText = { section: _sdT('Your saved section, {var} elements.', it.count), row: _sdT('Your saved row, {var} elements.', it.count),
            card: _sdT('Your saved card, {var} elements.', it.count) }[it.kind] || _sdT('Your saved group, {var} elements.', it.count);
        return (svg ? '<div class="sd-pal-tip-preview">' + svg + '</div>' : '') +
            '<div class="sd-pal-tip-head"><span class="bi bi-bookmark" aria-hidden="true"></span>' +
                '<span class="sd-pal-tip-name">' + esc(it.name) + '</span>' +
                '<span class="sd-pal-tip-en">' + esc(it.group ? _sdT('My library, {var}', it.group) : _sdT('My library')) + '</span></div>' +
            '<div class="sd-pal-tip-desc">' + esc(kindText + ' ' + _sdT('It is added as a copy: the page owns it from then on.')) + '</div>' +
            (it.bootstrap && !K._sdUsesBootstrap() ? '<div class="sd-pal-tip-desc">' + esc(_sdT('Made with Bootstrap, which this design does not load.')) + '</div>' : '');
    }

    // ── Adding a copy ──────────────────────────────────────────────────

    function _designFor(it) {
        var css = _userStyles();
        return { css: css, vars: _canvasVars(it.variables || []) };
    }

    // An item without rows to make is made ready ahead, for the drop to
    // take at once.
    function _prefetch(it) {
        if (!it || it.rows) return;
        var d = _designFor(it);
        var have = state.ready[it.id];
        if (have && have.css === d.css) return;
        var entry = state.ready[it.id] = { css: d.css, res: null };
        _post('library_prepare', { id: it.id, css: d.css, vars: d.vars }).then(function (r) { entry.res = r; },
            function () { if (state.ready[it.id] === entry) delete state.ready[it.id]; });
    }

    function _adopt(res) {
        var t = JSON.parse(JSON.stringify(res.tree));
        K._backfillIds(t);
        return t;
    }

    // What a copy brings with it: its rules and variables into the User
    // Styles (inside the insert's undo step), and a line only when a class
    // was renamed, a binding cleared or pictures are missing.
    function _settle(res) {
        if (res.css) {
            var css = _userStyles();
            K._userStylesSet((css ? css.replace(/\s*$/, '\n') : '') + res.css);
        }
        if (res.made && Object.keys(res.made).length) K._refreshSharedPalette();
        var lines = [];
        var renamed = Object.keys(res.renamed || {});
        if (renamed.length > 3) {
            lines.push(_sdT('Added from my library. {var} classes of the copy got new names; User Styles lists them.', renamed.length));
        } else if (renamed.length) {
            lines.push(_sdT('Added from my library. This design already has {var:1} with other values, so the copy uses {var:2}.', [
                renamed.map(function (c) { return '.' + c; }).join(', '),
                renamed.map(function (c) { return '.' + res.renamed[c]; }).join(', ')]));
        }
        if (res.cleared) {
            lines.push(_sdT('Added from my library. This site lacks what a widget of the copy was bound to: choose a form, collection or group for it.'));
        }
        // A picture's address stays as written: the file put at that
        // place in the file manager repairs it.
        if (res.pictures_missing === 1) {
            lines.push(_sdT('Added from my library. One picture of the copy is not on this site: put it in the file manager at the same place or choose another picture.'));
        } else if (res.pictures_missing > 1) {
            lines.push(_sdT('Added from my library. {var} pictures of the copy are not on this site: put them in the file manager at the same place or choose other pictures.', res.pictures_missing));
        }
        lines.forEach(function (l) { K.sdToast(esc(l), 'warning', 9000); });
    }

    // The node the list's row adds (createFromPalette('library')). A copy
    // made ready is put in place at once; otherwise a placeholder stands
    // where it lands until the server has made it ready (and made its
    // widgets and components), in the same undo step.
    function node(extra, dry) {
        var it = _find(extra && extra.libraryId);
        if (!it) return null;
        if (dry) return it.tree ? JSON.parse(JSON.stringify(it.tree)) : null;
        var have = state.ready[it.id];
        if (have && have.res && have.css === _userStyles()) {
            var n = _adopt(have.res);
            _settle(have.res);
            return n;
        }
        var ph = _placeholder(it);
        var d = _designFor(it);
        var req = { id: it.id, css: d.css, vars: d.vars };
        if (it.rows) req.make = 1;
        _post('library_prepare', req).then(function (res) {
            _replace(ph, res);
        }, function (err) {
            _replace(ph, null);
            K.sdToast(esc(err.message), 'error');
        });
        return ph;
    }

    // What stands on the page while a copy is made ready (site markup, a
    // Bootstrap 5 class).
    /* @pg-site-output */
    function _placeholder(it) {
        return K.createNode('semantic', { tag: 'div', cssClass: 'py-4 text-center text-body-secondary', customName: it.name,
            text: _sdT('Adding "{var}"…', it.name), _libraryPending: it.id }, []);
    }

    function _replace(ph, res) {
        var found = K._findNodeAnywhereById(ph._id);
        var owner = found ? K._sdAnyParent(found.node) : null;
        if (!owner || !owner.parent || !owner.parent.children) return;
        var list = owner.parent.children;
        var i = list.indexOf(found.node);
        if (i === -1) return;
        if (res) {
            var n = _adopt(res);
            list.splice(i, 1, n);
            _settle(res);
            if (K.selectedNode === found.node) K.selectedNode = n;
        } else {
            list.splice(i, 1);
            if (K.selectedNode === found.node) K.selectedNode = null;
        }
        K.render();
    }

    // ── Manage library ─────────────────────────────────────────────────
    // The window is the part 'librarymanage', run the first time it is
    // asked for; it reads and changes the library through this handle.
    var lib = {
        NAME_MAX: NAME_MAX,
        items: function () { return state.items || []; },
        setItems: function (list) {
            state.items = list;
            Object.keys(state.ready).forEach(function (id) { if (!_find(id)) delete state.ready[id]; });
        },
        find: _find,
        fold: _fold,
        countLine: _countLine,
        post: _post,
        redraw: function () { _redraw(); },
        // Set by the window while it is open: drawn again after a reload.
        changed: null
    };

    function manage() {
        if (K._sdAccess() !== 'full') return;
        _load().then(function () {
            return K._sdPartCall('librarymanage', 'open', [lib]);
        }, function (err) { K.sdToast(esc(err.message), 'error'); }).catch(K._sdPartFailed);
    }

    // Another part changed the library (an import): read it again.
    document.addEventListener('sd-library-changed', function () {
        _load(true).then(function () { _redraw(); if (lib.changed) lib.changed(); }, function () { /* the next showing asks again */ });
    });

    return { offered: offered, save: save, mount: mount, show: show, tip: tip, node: node, manage: manage };
});
