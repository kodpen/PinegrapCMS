/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Clean up" window part: the saved design's style
 * rules, files, shared components and variables that nothing uses, in four
 * lists, each row with why it is thought unused and "Show". Nothing is
 * ticked at the start and nothing leaves by itself. Opened from the command
 * palette's "Clean up" and from the Publish check's list of unused rules
 * (over the Publish sheet, the rules list open). Not needed to open a page:
 * the core runs this file the first time the window is asked for
 * (_sdPartLoad(), pg_designer_parts()) and hands it the design through a
 * host (_sdCleanupHost()). The lists come from designer/cleanup_list
 * (includes/fn/design_cleanup_list.php); their shape and the counts from
 * PgDesignCleanup (pg_design_cleanup.js).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('cleanup', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;
    var C = window.PgDesignCleanup;

    var state = { host: null, from: '', el: null, answer: null, lists: [], open: {}, ticked: {}, shown: {}, busy: false };

    var ICONS = { rules: 'bi-braces', files: 'bi-folder2', components: 'bi-boxes', variables: 'bi-sliders2' };

    function _cuTitle(kind) {
        return { rules: _sdT('Style rules'), files: _sdT('Files'), components: _sdT('Shared components'), variables: _sdT('Variables') }[kind];
    }

    function _cuQuote(names) {
        var q = names.map(function (n) { return '“' + n + '”'; });
        if (q.length < 2) return q[0] || '';
        return _sdT('{var:1} and {var:2}', [q.slice(0, -1).join(', '), q[q.length - 1]]);
    }

    // A media condition in words: "from 992 px", "up to 767 px", else as written.
    function _cuMedia(media) {
        var m = /^@media\s*\(\s*(min|max)-width\s*:\s*(\d+(?:\.\d+)?)px\s*\)$/i.exec(String(media || '').trim());
        if (!m) return String(media || '');
        return m[1].toLowerCase() === 'min' ? _sdT('from {var} px', m[2]) : _sdT('up to {var} px', m[2]);
    }

    function _cuDay(seconds) {
        var d = new Date(seconds * 1000);
        var o = { day: 'numeric', month: 'long' };
        if (d.getFullYear() !== new Date().getFullYear()) o.year = 'numeric';
        try { return d.toLocaleDateString(document.documentElement.lang || undefined, o); } catch (e) { return d.toDateString(); }
    }

    // One line of why a row is thought unused.
    function _cuWhy(kind, r) {
        if (kind === 'rules') {
            var why = r.classes.length > 1 ? _sdT('Nothing uses the classes {var}.', _cuQuote(r.classes)) : _sdT('Nothing uses the class {var}.', _cuQuote(r.classes));
            return why + ' · ' + r.sheet + (r.media ? ', ' + _cuMedia(r.media) : '');
        }
        if (kind === 'files') return _sdT('Nothing in the design refers to it.') + (r.time ? ' ' + _sdT('Uploaded {var}.', _cuDay(r.time)) : '');
        if (kind === 'components') return _sdT('Placed on no page, shared component or e-mail design.');
        return _sdT('No rule and no element reads it.') + ' · ' + r.value;
    }

    // The line at the rules list's end for a sheet the check could not tell about.
    function _cuNote(n) {
        if (n.reason === 'unreadable') return _sdT('{var} could not be read.', n.sheet);
        if (n.reason === 'bound_class' && n.at) return _sdT('No rule of {var:1} is listed: a class on “{var:2}” is set by a value, so the check cannot tell.', [n.sheet, n.at]);
        if (n.reason === 'dynamic_class' && n.at) return _sdT('No rule of {var:1} is listed: a class on “{var:2}” is written by code, so the check cannot tell.', [n.sheet, n.at]);
        if (n.reason === 'script_unread' && n.at) return _sdT('No rule of {var:1} is listed: the script {var:2} could not be read, and it may set any class.', [n.sheet, n.at]);
        return _sdT('No rule of {var} is listed: the check cannot tell.', n.sheet);
    }

    function _cuLead(kind, r) {
        if (kind === 'files') {
            return r.image ? '<img class="sd-cu-thumb" src="' + esc(r.url) + '" alt="" loading="lazy">'
                : '<span class="sd-cu-thumb bi bi-file-earmark" aria-hidden="true"></span>';
        }
        if (kind === 'components') return '<span class="sd-cu-thumb bi bi-box" aria-hidden="true"></span>';
        if (kind === 'variables') {
            return r.kind === 'color' ? '<span class="sd-cu-thumb sd-cu-swatch" style="background:' + esc(r.value) + '" aria-hidden="true"></span>'
                : '<span class="sd-cu-thumb bi bi-rulers" aria-hidden="true"></span>';
        }
        return '';
    }

    // What "Show" opens under a row: a rule's text, a picture, a shared
    // component drawn with the canvas's stylesheets.
    function _cuShow(kind, r) {
        if (kind === 'rules') return '<pre class="sd-cu-show sd-cu-code"><code>' + esc(r.text) + '</code></pre>';
        if (kind === 'files') return '<div class="sd-cu-show"><img src="' + esc(r.url) + '" alt="' + esc(r.name) + '"></div>';
        var html = r.tree ? state.host.draw(r.tree) : '';
        if (!html) return '<p class="sd-cu-show sd-cu-muted">' + esc(_sdT('This component cannot be drawn here.')) + '</p>';
        var doc = '<!doctype html><html><head><meta charset="utf-8">' + state.host.styles() + '</head><body>' + html + '</body></html>';
        return '<div class="sd-cu-show"><iframe class="sd-cu-frame" sandbox="" title="' + esc(r.name) + '" srcdoc="' + esc(doc) + '"></iframe></div>';
    }

    function _cuRow(l, r) {
        var on = !!state.ticked[r.key];
        var shown = !!state.shown[r.key];
        var name = l.kind === 'rules' ? '<code>' + esc(r.selector) + '</code>' : (l.kind === 'variables' ? '<code>' + esc(r.token) + '</code>' : esc(r.name));
        var show = l.kind === 'variables' ? '' : '<button type="button" class="pg-button pg-button-quiet pg-button-sm" data-cu="show" data-key="' + esc(r.key) + '" aria-expanded="' + shown + '">' + esc(shown ? _sdT('Hide') : _sdT('Show')) + '</button>';
        return '<div class="sd-cu-item"><label class="sd-cu-row">' +
            '<input type="checkbox" class="check m-0" data-cu="tick" data-key="' + esc(r.key) + '"' + (on ? ' checked' : '') + (state.busy ? ' disabled' : '') + '>' +
            _cuLead(l.kind, r) +
            '<span class="sd-cu-main"><span class="sd-cu-name">' + name + '</span><span class="sd-cu-why">' + esc(_cuWhy(l.kind, r)) + '</span></span>' +
            '<span class="sd-cu-end">' + (r.bytes != null ? '<span class="sd-cu-size">' + esc(C.size(r.bytes)) + '</span>' : '') + show + '</span>' +
            '</label>' + (shown ? _cuShow(l.kind, r) : '') + '</div>';
    }

    function _cuList(l) {
        var open = !!state.open[l.kind];
        var tick = C.tickState(l, state.ticked);
        var head = '<div class="sd-cu-head">' +
            '<button type="button" class="sd-cu-fold" data-cu="fold" data-kind="' + l.kind + '" aria-expanded="' + open + '" aria-label="' + esc(_cuTitle(l.kind)) + '"><span class="bi bi-chevron-' + (open ? 'down' : 'right') + '" aria-hidden="true"></span></button>' +
            '<input type="checkbox" class="check m-0" data-cu="all" data-kind="' + l.kind + '" aria-label="' + esc(_sdT('Tick the whole list')) + '"' +
                (tick === 'all' ? ' checked' : '') + (l.rows.length && !state.busy ? '' : ' disabled') + '>' +
            '<span class="bi ' + ICONS[l.kind] + '" aria-hidden="true"></span>' +
            '<span class="sd-cu-title">' + esc(_cuTitle(l.kind)) + '</span><span class="badge theme-secondary">' + l.rows.length + '</span>' +
            (l.bytes != null && l.rows.length ? '<span class="sd-cu-size ms-auto">' + esc(C.size(l.bytes)) + '</span>' : '') +
            '</div>';
        var body = '';
        if (open) {
            body = '<div class="sd-cu-rows">' + (l.rows.length ? l.rows.map(function (r) { return _cuRow(l, r); }).join('') : '<p class="sd-cu-muted sd-cu-none">' + esc(_sdT('Nothing unused.')) + '</p>') +
                l.notes.map(function (n) { return '<p class="sd-cu-note"><span class="bi bi-info-circle" aria-hidden="true"></span><span>' + esc(_cuNote(n)) + '</span></p>'; }).join('') + '</div>';
        }
        return '<section class="sd-cu-list" data-kind="' + l.kind + '">' + head + body + '</section>';
    }

    function _cuState(icon, head, sub, button) {
        return '<div class="sd-cu-state">' + icon + '<p class="sd-cu-state-head">' + esc(head) + '</p><p class="sd-cu-muted">' + esc(sub) + '</p>' + (button || '') + '</div>';
    }

    function _cuDraw() {
        var d = state.el;
        if (!d) return;
        var v = C.view(state.host.dirty(), state.busy && !state.answer, state.answer);
        var body = '', foot = '';
        var close = '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-bs-dismiss="dialog">' + esc(_sdT('Close')) + '</button>';
        if (v === 'save') {
            body = _cuState('<span class="bi bi-floppy sd-cu-state-icon" aria-hidden="true"></span>', _sdT('Save the design first'),
                _sdT('Clean up reads the saved design. With unsaved changes it could call something unused that you have just put to use.'),
                '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-cu="save">' + esc(_sdT('Save and look again')) + '</button>');
        } else if (v === 'busy') {
            body = _cuState('<span class="spinner-border sd-cu-state-icon" role="status" aria-hidden="true"></span>', _sdT('Looking through the design…'),
                _sdT('Its pages, shared components, e-mail designs, scripts and stylesheets are read once.'));
        } else if (v === 'failed') {
            body = _cuState('<span class="bi bi-exclamation-triangle sd-cu-state-icon" aria-hidden="true"></span>', _sdT('The design could not be read'),
                (state.answer && state.answer.message) || _sdT('Nothing was changed.'),
                '<button type="button" class="btn btn-sm btn-outline theme-secondary" data-cu="again">' + esc(_sdT('Try again')) + '</button>');
        } else if (v === 'empty') {
            body = _cuState('<span class="bi bi-check2-circle sd-cu-state-icon" aria-hidden="true"></span>', _sdT('This design has nothing unused'),
                _sdT('No style rule, file, shared component or variable is left over.'));
            foot = '<span class="me-auto"></span>' + close;
        } else {
            body = '<p class="sd-cu-lead">' + esc(_sdT('What nothing in this design uses any more. Nothing is ticked and nothing is removed until you say so; a row the check is unsure of is not listed.')) + '</p>' +
                state.lists.map(_cuList).join('');
            var t = C.ticked(state.lists, state.ticked);
            var words = !t.count ? _sdT('Nothing is ticked.')
                : (t.bytes ? _sdT('{var:1} ticked · {var:2} lighter.', [String(t.count), C.size(t.bytes)]) : _sdT('{var} ticked.', String(t.count)));
            // Removing what is ticked is the next step's; until then the
            // button stays off.
            foot = '<span class="sd-cu-ticked me-auto" aria-live="polite">' + esc(words) + '</span>' + close +
                '<button type="button" class="btn btn-sm btn-solid theme-primary" data-cu="remove" disabled>' + esc(t.count ? _sdT('Remove {var}', String(t.count)) : _sdT('Remove')) + '</button>';
        }
        d.querySelector('.sd-cu-sub').textContent = (state.answer && state.answer.design) || '';
        d.querySelector('.sd-cu-body').innerHTML = body;
        d.querySelector('.sd-cu-foot').innerHTML = foot;
        d.querySelector('.sd-cu-foot').hidden = !foot;
        _cuDashes();
    }

    // Reads the saved design; the lists come together, not one by one.
    function _cuRead() {
        if (state.busy || !state.el) return;
        if (state.host.dirty()) { _cuDraw(); return; }
        state.busy = true;
        state.answer = null;
        _cuDraw();
        K._pgApiPost('cleanup_list', { style_id: state.host.styleId }).then(function (res) {
            _cuTake(res || { status: 'error' });
        }, function () {
            _cuTake({ status: 'error', message: _sdT('Network error.') });
        });
    }

    function _cuTake(res) {
        state.busy = false;
        if (!state.el) return;
        state.answer = res;
        state.lists = res.status === 'success' ? C.lists(res) : [];
        state.open = C.firstOpen(state.lists, state.from);
        state.ticked = {};
        state.shown = {};
        _cuDraw();
    }

    function _cuFind(key) {
        for (var i = 0; i < state.lists.length; i++) {
            for (var j = 0; j < state.lists[i].rows.length; j++) {
                if (state.lists[i].rows[j].key === key) return { list: state.lists[i], row: state.lists[i].rows[j] };
            }
        }
        return null;
    }

    // Redraws and gives the focus back to the control that had it.
    function _cuRedraw(query) {
        _cuDraw();
        var el = query && state.el && state.el.querySelector(query);
        if (el) el.focus();
    }

    function _cuClick(e) {
        var b = e.target.closest('[data-cu]');
        if (!b || b.disabled || !state.el) return;
        var what = b.getAttribute('data-cu');
        var k = b.getAttribute('data-key') || '';
        var kind = b.getAttribute('data-kind') || '';
        if (what === 'fold') {
            state.open[kind] = !state.open[kind];
            _cuRedraw('[data-cu="fold"][data-kind="' + kind + '"]');
        } else if (what === 'show') {
            e.preventDefault();
            var f = _cuFind(k);
            if (!f) return;
            if (f.list.kind === 'files' && !f.row.image) { window.open(f.row.url, '_blank', 'noopener'); return; }
            state.shown[k] = !state.shown[k];
            _cuRedraw('[data-cu="show"][data-key="' + k + '"]');
        } else if (what === 'save') {
            b.disabled = true;
            state.host.save(function () { _cuRead(); });
        } else if (what === 'again') {
            _cuRead();
        }
    }

    function _cuChange(e) {
        var b = e.target.closest('[data-cu]');
        if (!b) return;
        var what = b.getAttribute('data-cu');
        if (what === 'tick') {
            state.ticked[b.getAttribute('data-key')] = b.checked;
            _cuRedraw('[data-cu="tick"][data-key="' + b.getAttribute('data-key') + '"]');
        } else if (what === 'all') {
            var kind = b.getAttribute('data-kind');
            state.lists.forEach(function (l) {
                if (l.kind === kind) l.rows.forEach(function (r) { state.ticked[r.key] = b.checked; });
            });
            _cuRedraw('[data-cu="all"][data-kind="' + kind + '"]');
        }
    }

    function _cuBuild() {
        var d = document.createElement('dialog');
        d.className = 'dialog sd-settings-modal-dark sd-modal-content pg-dialog-centered sd-cu-dialog';
        d.tabIndex = -1;
        d.setAttribute('aria-labelledby', 'sd-cu-title');
        d.innerHTML =
            '<div class="dialog-header sd-modal-header">' +
                '<h5 class="dialog-title" id="sd-cu-title"><span class="bi bi-stars me-2" aria-hidden="true"></span>' + esc(_sdT('Clean up')) + '<span class="sd-cu-sub"></span></h5>' +
                '<button type="button" class="btn-close fg-white" data-bs-dismiss="dialog" aria-label="' + esc(_sdT('Close')) + '"></button>' +
            '</div>' +
            '<div class="dialog-body sd-modal-body sd-cu-body" aria-live="polite"></div>' +
            '<div class="dialog-footer sd-modal-footer sd-cu-foot"></div>';
        document.body.appendChild(d);
        d.addEventListener('click', _cuClick);
        d.addEventListener('change', _cuChange);
        return d;
    }

    // The head boxes of lists ticked in part show a dash.
    function _cuDashes() {
        if (!state.el) return;
        state.lists.forEach(function (l) {
            var box = state.el.querySelector('[data-cu="all"][data-kind="' + l.kind + '"]');
            if (box) box.indeterminate = C.tickState(l, state.ticked) === 'some';
        });
    }

    // Opens the window. from: 'publishcheck' opens it over the Publish
    // sheet with the rules list open; closing gives the sheet back.
    function open(host, from) {
        if (state.el || !window.pgUI || !C) return;
        state.host = host;
        state.from = from || '';
        state.answer = null;
        state.lists = [];
        state.el = _cuBuild();
        pgUI.dialog.on(state.el, 'hidden', function () {
            if (state.el) state.el.remove();
            state.el = null;
            state.answer = null;
            state.lists = [];
            state.host = null;
        });
        pgUI.dialog.show(state.el);
        _cuRead();
    }

    return { open: open };
});
