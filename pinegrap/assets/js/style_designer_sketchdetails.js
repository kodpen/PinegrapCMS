/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - "Page details" of a sketch page in the Prototype
 * view: one window over the dimmed map with seven rows made like the
 * Settings window's (name, address, page type, place in the menu, title,
 * description, who may see it). Every row has a value already, so Save
 * needs no choice; the name is focused, Tab walks the rows, Enter saves,
 * Escape cancels. A value that cannot be saved turns its row's line rose
 * and nothing is kept until every row is right; the address is asked of
 * the server too. Without the right to change the page every control is
 * dimmed and the foot has only Close. On a phone the window takes the
 * full screen. The values are kept with the sketch (the frame's head takes
 * the name at once); the build makes the page with them.
 * Not needed to open a page: the core runs this file the first time the
 * window is asked for (_sdPartLoad(), pg_designer_parts()) and hands it
 * the sketch page through a host (_sdSketchDetailsHost()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('sketchdetails', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var sdToast = K.sdToast;
    var D = window.PGSketchDetails;

    // host: the open page's host; back: the dimmed layer holding the
    // window; v: the values as they stand; menus: the site's menus once
    // read ([{id, name, items: [{id, name}]}]), null before; busy: a save
    // is waiting for the server; was: the element focused before.
    var state = { host: null, back: null, win: null, v: null, menus: null, busy: false, was: null };

    function _skdTypeLabel(key) {
        return {
            standard: _sdT('Standard page'),
            catalog: _sdT('Catalog'),
            blog: _sdT('Blog'),
            booking: _sdT('Booking')
        }[key] || key;
    }

    // The widget a page type brings and the sketch box it goes in.
    function _skdWidget(key) {
        return {
            catalog: [_sdT('Catalog listing'), _sdT('Catalog')],
            blog: [_sdT('Blog list'), _sdT('Blog list')],
            booking: [_sdT('Booking'), _sdT('Booking')]
        }[key] || null;
    }

    // Who may open a folder's pages, in the Settings window's words.
    function _skdAccess(type) {
        var t = {
            'public':       _sdT('Anyone can open the page.'),
            'guest':        _sdT('Visitors open it once they sign in, register or choose to go on as a guest.'),
            'registration': _sdT('Visitors open it once they sign in; anybody may register.'),
            'membership':   _sdT('Only signed-in members with a membership open it.'),
            'private':      _sdT('Only the people given access to the folder open it.')
        };
        return t[type] || t['public'];
    }

    function _skdError(e) {
        if (e.field === 'name') return _sdT('A page needs a name.');
        if (e.field === 'address') {
            if (e.code === 'empty') return _sdT('A page needs an address.');
            if (e.code === 'chars') return _sdT('A page name cannot contain ?, % or +: the page would not open at its address.');
            if (e.code === 'long') return _sdT('The page name can be at most {var:1} characters long.', D.LIMITS.address);
            if (e.code === 'used') return _sdT('This address is used by the page “{var:1}”.', e.page);
            if (e.code === 'taken') return _sdT('A file or an address of the site already answers at this address.');
            return e.page || _sdT('This address cannot be used.');
        }
        return _sdT('This text is too long.');
    }

    function _skdRow(id, name, help, control) {
        return '<div class="sd-set sd-skd-row" data-row="' + id + '">' +
            '<div class="sd-set-text"><label class="sd-set-name" for="sd-skd-' + id + '">' + esc(name) + '</label>' +
            '<div class="sd-set-help" id="sd-skd-' + id + '-help">' + esc(help) + '</div></div>' +
            '<div class="sd-set-ctrl">' + control + '<div class="sd-set-status" id="sd-skd-' + id + '-line" aria-live="polite"></div></div>' +
            '</div>';
    }

    function _skdBuild() {
        var h = state.host;
        var ro = !h.mayEdit;
        var off = ro ? ' disabled' : '';
        var back = document.createElement('div');
        back.className = 'sd-skd-back';
        var win = document.createElement('div');
        win.className = 'sd-skd';
        win.setAttribute('role', 'dialog');
        win.setAttribute('aria-modal', 'true');
        win.setAttribute('aria-labelledby', 'sd-skd-title');
        var types = D.TYPES.map(function (t) { return '<option value="' + t.key + '">' + esc(_skdTypeLabel(t.key)) + '</option>'; }).join('');
        var folders = h.folders().map(function (f) { return '<option value="' + f.id + '" data-access="' + esc(f.access) + '">' + esc(f.label) + '</option>'; }).join('');
        win.innerHTML =
            '<div class="sd-skd-head">' +
                '<span class="sd-skd-title" id="sd-skd-title">' + esc(_sdT('Page details')) + '</span>' +
                '<span class="sd-skd-sub">' + esc(_sdT('{var:1} · sketch page, not built yet', h.fallback)) + '</span>' +
                '<button type="button" class="sd-skd-x" data-act="cancel" title="' + esc(_sdT('Close')) + '" aria-label="' + esc(_sdT('Close')) + '"><span class="bi bi-x-lg" aria-hidden="true"></span></button>' +
            '</div>' +
            '<div class="sd-skd-body">' +
                _skdRow('name', _sdT('Name'), _sdT('Shown on the map and in the Pages window.'),
                    '<input type="text" id="sd-skd-name" class="form-control form-control-sm" maxlength="' + D.LIMITS.name + '" autocomplete="off"' + off + '>') +
                _skdRow('address', _sdT('Address'), _sdT('Made from the name until you type one.'),
                    '<input type="text" id="sd-skd-address" class="form-control form-control-sm" maxlength="' + D.LIMITS.address + '" autocomplete="off" spellcheck="false"' + off + '>') +
                _skdRow('type', _sdT('Page type'), _sdT('A type brings its widget; the page is built with it in place.'),
                    '<select id="sd-skd-type" class="form-control form-control-sm"' + off + '>' + types + '</select>') +
                _skdRow('menu', _sdT('In the menu'), _sdT('Where the page\'s link goes. The menu itself is edited as today.'),
                    '<div class="sd-skd-two"><select id="sd-skd-menu" class="form-control form-control-sm"' + off + '></select>' +
                    '<select id="sd-skd-after" class="form-control form-control-sm" aria-label="' + esc(_sdT('Comes after')) + '"' + off + '></select></div>') +
                _skdRow('title', _sdT('Title'), _sdT('For search engines and the browser tab. Empty, the name is used.'),
                    '<input type="text" id="sd-skd-title-field" class="form-control form-control-sm" maxlength="' + D.LIMITS.title + '"' + off + '>') +
                _skdRow('description', _sdT('Description'), _sdT('One or two sentences for search results.'),
                    '<textarea id="sd-skd-description" class="form-control form-control-sm" rows="3" maxlength="' + D.LIMITS.description + '"' + off + '></textarea>') +
                _skdRow('folder', _sdT('Who may see it'), _sdT('Access follows the folder the page is in.'),
                    '<select id="sd-skd-folder" class="form-control form-control-sm"' + off + '>' + folders + '</select>') +
            '</div>' +
            '<div class="sd-skd-foot">' +
                '<span class="sd-skd-note">' + esc(ro ? _sdT('You may see this page\'s details but not change them.') : _sdT('Links between pages are the map\'s arrows, as today.')) + '</span>' +
                (ro
                    ? '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-act="cancel">' + esc(_sdT('Close')) + '</button>'
                    : '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-act="cancel">' + esc(_sdT('Cancel')) + '</button>' +
                      '<button type="button" class="btn btn-sm btn-solid theme-primary" data-act="save">' + esc(_sdT('Save')) + '</button>') +
            '</div>';
        // The title field's label points at its own id (the head holds
        // sd-skd-title).
        win.querySelector('label[for="sd-skd-title"]').setAttribute('for', 'sd-skd-title-field');
        Array.prototype.forEach.call(win.querySelectorAll('.sd-skd-row'), function (r) {
            var id = r.getAttribute('data-row');
            var ctrl = r.querySelector('input, select, textarea');
            if (ctrl) ctrl.setAttribute('aria-describedby', 'sd-skd-' + id + '-help sd-skd-' + id + '-line');
        });
        back.appendChild(win);
        document.body.appendChild(back);
        state.back = back;
        state.win = win;
        _skdWire(win);
    }

    function _skdEl(id) {
        return state.win ? state.win.querySelector('#sd-skd-' + id) : null;
    }

    // The fields from the values, the menus as far as they are known.
    function _skdFill() {
        var v = state.v;
        _skdEl('name').value = v.name;
        _skdEl('address').value = v.address;
        _skdEl('type').value = v.type;
        _skdEl('title-field').value = v.title;
        _skdEl('description').value = v.description;
        var folder = _skdEl('folder');
        folder.value = String(v.folder);
        if (folder.selectedIndex < 0 && folder.options.length) { folder.selectedIndex = 0; v.folder = Number(folder.value) || 0; }
        _skdMenus();
        _skdLive();
    }

    // The menu select and the one beside it naming the item the link comes
    // after; the second is there only with a menu chosen.
    function _skdMenus() {
        var v = state.v;
        var menus = state.menus || (v.menu ? [{ id: v.menu, name: v.menuName, items: v.after ? [{ id: v.after, name: v.afterName }] : [] }] : []);
        var menu = _skdEl('menu');
        menu.innerHTML = '<option value="0">' + esc(_sdT('Not in a menu')) + '</option>' +
            menus.map(function (m) { return '<option value="' + m.id + '">' + esc(m.name) + '</option>'; }).join('');
        menu.value = String(v.menu);
        if (menu.selectedIndex < 0) { menu.value = '0'; v.menu = 0; }
        var chosen = menus.filter(function (m) { return m.id === v.menu; })[0] || null;
        var after = _skdEl('after');
        after.hidden = !chosen;
        if (!chosen) { v.after = 0; v.afterName = ''; v.menuName = ''; return; }
        v.menuName = chosen.name;
        after.innerHTML = '<option value="0">' + esc(_sdT('At the start')) + '</option>' +
            chosen.items.map(function (it) { return '<option value="' + it.id + '">' + esc(_sdT('After “{var:1}”', it.name)) + '</option>'; }).join('');
        after.value = String(v.after);
        if (after.selectedIndex < 0) {
            // A new place goes after the menu's last item.
            var last = chosen.items[chosen.items.length - 1];
            v.after = last ? last.id : 0;
            after.value = String(v.after);
        }
        var item = chosen.items.filter(function (it) { return it.id === v.after; })[0];
        v.afterName = item ? item.name : '';
    }

    function _skdLine(id, html, bad) {
        var line = _skdEl(id + '-line');
        if (!line) return;
        line.innerHTML = html;
        line.classList.toggle('is-bad', !!bad);
        var ctrl = state.win.querySelector('.sd-skd-row[data-row="' + id + '"] input, .sd-skd-row[data-row="' + id + '"] select, .sd-skd-row[data-row="' + id + '"] textarea');
        if (ctrl) {
            ctrl.classList.toggle('sd-skd-bad', !!bad);
            if (bad) ctrl.setAttribute('aria-invalid', 'true');
            else ctrl.removeAttribute('aria-invalid');
        }
    }

    // The lines under the rows that say what a choice does.
    function _skdLive() {
        var v = state.v;
        var site = String(state.host.site || '');
        _skdLine('name', '', false);
        _skdLine('address', v.address ? esc(site + v.address) : '', false);
        var w = _skdWidget(v.type);
        _skdLine('type', w ? esc(_sdT('Brings the widget {var:1}: built where the sketch has a {var:2} box, else under the header.')).replace('{var:1}', '<b>' + esc(w[0]) + '</b>').replace('{var:2}', esc(w[1])) : '', false);
        _skdLine('menu', '', false);
        _skdLine('title', '', false);
        _skdLine('description', '', false);
        _skdEl('title-field').placeholder = v.name;
        var folder = _skdEl('folder');
        var opt = folder.selectedIndex >= 0 ? folder.options[folder.selectedIndex] : null;
        _skdLine('folder', opt ? esc(_skdAccess(opt.getAttribute('data-access') || 'public')) : '', false);
    }

    // The rose lines of what cannot be saved; the first is focused.
    function _skdShowErrors(errors) {
        _skdLive();
        errors.forEach(function (e) {
            _skdLine(e.field, '<span class="bi bi-exclamation-circle me-1" aria-hidden="true"></span>' + esc(_skdError(e)), true);
        });
        if (!errors.length) return;
        var first = errors[0].field === 'title' ? 'title-field' : errors[0].field;
        var el = _skdEl(first);
        if (el) el.focus();
    }

    function _skdBusy(on) {
        state.busy = !!on;
        var foot = state.win.querySelector('.sd-skd-foot');
        var note = foot.querySelector('.sd-skd-note');
        note.innerHTML = on
            ? '<span class="sd-skd-spin" aria-hidden="true"></span>' + esc(_sdT('Saving…'))
            : esc(_sdT('Links between pages are the map\'s arrows, as today.'));
        Array.prototype.forEach.call(foot.querySelectorAll('button'), function (b) { b.disabled = !!on; });
        state.win.setAttribute('aria-busy', on ? 'true' : 'false');
    }

    function _skdSave() {
        var h = state.host;
        if (state.busy || !h.mayEdit) return;
        var v = state.v;
        var errors = D.check(v, { used: h.others() });
        if (errors.length) { _skdShowErrors(errors); return; }
        _skdBusy(true);
        h.post('sketch_details', { address: v.address }).then(function (res) {
            if (!state.win) return;
            _skdBusy(false);
            if (!res || res.status !== 'success') {
                sdToast(esc((res && res.message) || _sdT('The details could not be saved. Try again.')), 'error', 5000);
                return;
            }
            var server = { used: String(res.used || ''), taken: !!res.taken };
            errors = D.check(v, { used: h.others(), server: server });
            if (!errors.length && res.error) errors = [{ field: 'address', code: 'server', page: String(res.error) }];
            if (errors.length) { _skdShowErrors(errors); return; }
            h.save(D.stored(v));
            close();
        }).catch(function () {
            if (!state.win) return;
            _skdBusy(false);
            sdToast(esc(_sdT('The details could not be saved. Try again.')), 'error', 5000);
        });
    }

    function _skdWire(win) {
        var v = state.v;
        _skdEl('name').addEventListener('input', function () {
            v.name = this.value;
            if (!v.typed) { v.address = D.address(v.name); _skdEl('address').value = v.address; }
            _skdLive();
        });
        _skdEl('address').addEventListener('input', function () {
            v.address = this.value.trim();
            // Emptied, the address follows the name again.
            v.typed = v.address !== '';
            _skdLive();
        });
        _skdEl('address').addEventListener('blur', function () {
            if (!v.address) { v.typed = false; v.address = D.address(v.name); this.value = v.address; _skdLive(); }
        });
        _skdEl('type').addEventListener('change', function () { v.type = this.value; _skdLive(); });
        _skdEl('menu').addEventListener('change', function () { v.menu = Number(this.value) || 0; v.after = -1; _skdMenus(); });
        _skdEl('after').addEventListener('change', function () { v.after = Number(this.value) || 0; _skdMenus(); });
        _skdEl('title-field').addEventListener('input', function () { v.title = this.value; });
        _skdEl('description').addEventListener('input', function () { v.description = this.value; });
        _skdEl('folder').addEventListener('change', function () { v.folder = Number(this.value) || 0; _skdLive(); });
        win.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-act]') : null;
            if (!b) return;
            if (b.getAttribute('data-act') === 'save') _skdSave();
            else if (!state.busy) close();
        });
        win.addEventListener('keydown', function (e) {
            // The map's keys never see a key typed in the window.
            e.stopPropagation();
            if (e.key === 'Escape') { e.preventDefault(); if (!state.busy) close(); return; }
            if (e.key === 'Enter' && !e.shiftKey && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
                e.preventDefault();
                if (state.host.mayEdit) _skdSave();
                else close();
                return;
            }
            // Tab stays in the window.
            if (e.key === 'Tab') {
                var list = Array.prototype.filter.call(win.querySelectorAll('input, select, textarea, button'), function (el) { return !el.disabled && !el.hidden && el.offsetParent !== null; });
                if (!list.length) return;
                var first = list[0], last = list[list.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });
        state.back.addEventListener('pointerdown', function (e) {
            if (e.target === state.back && !state.busy) close();
        });
    }

    // The site's menus and their top items, read once per window; a
    // failure leaves "Not in a menu" alone.
    function _skdLoadMenus() {
        var h = state.host;
        if (!h.mayEdit) return;
        h.post('sketch_details', {}).then(function (res) {
            if (!state.win || !res || res.status !== 'success' || !Array.isArray(res.menus)) return;
            state.menus = res.menus.map(function (m) {
                return { id: Number(m.id) || 0, name: String(m.name || ''), items: (Array.isArray(m.items) ? m.items : []).map(function (it) { return { id: Number(it.id) || 0, name: String(it.name || '') }; }) };
            });
            _skdMenus();
        }).catch(function () {});
    }

    function close() {
        if (state.back && state.back.parentNode) state.back.parentNode.removeChild(state.back);
        var was = state.was;
        var host = state.host;
        state.back = state.win = state.host = state.v = state.menus = state.was = null;
        state.busy = false;
        if (host && host.closed) host.closed();
        if (was && was.isConnected) was.focus({ preventScroll: true });
    }

    // The window for a sketch page. host: {page, fallback, mayEdit, site,
    // folder, folders(), others(), post(sub, extra), save(stored), closed()}.
    function open(host) {
        if (state.win) close();
        state.host = host;
        state.was = document.activeElement;
        state.v = D.values(host.page, { fallback: host.fallback, folder: host.folder, folders: host.folders().map(function (f) { return f.id; }) });
        _skdBuild();
        _skdFill();
        _skdLoadMenus();
        var first = host.mayEdit ? _skdEl('name') : state.win.querySelector('[data-act="cancel"]');
        if (first) {
            first.focus({ preventScroll: true });
            if (first.select) first.select();
        }
    }

    return { open: open, close: close };
});
