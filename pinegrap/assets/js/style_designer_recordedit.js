/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "recordedit" part: "Edit content here" in the
 * Preview. A collection item's words and pictures, drawn by the page, are
 * corrected where they are seen and saved to the item through its own
 * writer (record_edit_read / record_edit_save, includes/fn/record_edit.php),
 * apart from the design: nothing here is part of Save, Publish or the
 * design's undo.
 *
 * - The switch is the last thing of the "Preview as" bar (html('bar'), a
 *   row of the narrow sheet with html('row')), shown when the read says the
 *   person may edit the item; where their change would wait for approval
 *   the link "Open the record" stands in its place, as it does for a
 *   product or an event the page draws. Off each time the
 *   Preview window opens. The canvas row "Edit this record's content"
 *   opens the Preview with the switch on and that element's frame focused
 *   (the core's _sdRecEditWant()).
 * - On, the core draws the Preview with node marks (data-pg-node) and the
 *   spots of PGRecordEditSpots get a dashed frame; a click (Enter from the
 *   keyboard) edits one with its field's control: a line of text, the
 *   small text bar, a number, the calendar, the picture menu.
 * - The foot bar counts the changed fields and saves them at once; a field
 *   someone else changed meanwhile is asked about, one at a time.
 *
 * Not needed to open a page: the core runs this file the first time the
 * Preview draws a collection item (_sdRecEditSync()).
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('recordedit', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    // win/doc: the Preview window and its document; sid/id: the detail
    // widget and the item it draws; read: the record_edit_read answer;
    // on: the switch; base: field id => the value the item holds; edits:
    // field id => the value typed; bad: field id => {msg, local} (local:
    // refused in place, not counted); names: field id => a picture's file
    // name; theirs: field id => the base a kept change is sent with; open:
    // the field being edited {fid, el, before}; mode: the foot bar's '' |
    // 'saving' | 'refused' | 'error' | 'ask' | 'conflict'; conflicts and ci:
    // the fields changed meanwhile and the one asked about; pending: what
    // the ask goes on with; other: a product or an event drawn where no
    // collection item is, {key, screen} (screen '' until the server says it
    // may be opened).
    var R = {
        win: null, doc: null, sid: 0, id: 0, read: null, reading: 0, on: false,
        base: {}, edits: {}, bad: {}, names: {}, theirs: {}, open: null,
        mode: '', conflicts: [], ci: 0, pending: null, refused: 0, pop: null,
        spots: [], hot: null, theirsName: {}, sent: {},
        other: null
    };

    var GREEN = '#16a34a';

    var CSS =
        'html{padding-bottom:var(--pg-re-h,0px)!important}' +
        '#pg-pv-hotspots{bottom:calc(var(--pg-re-h,0px) + 12px)!important}' +
        '#pg-pva .pg-re-sw{display:inline-flex;align-items:center;gap:8px;height:26px;padding:0 8px;border-radius:6px;font-size:12px;color:#c9d1d9}' +
        '#pg-pva .pg-re-sw:hover{background:#2a313b}#pg-pva .pg-re-sw.on{color:#3ecf8e}' +
        '.pg-re-tg{position:relative;width:26px;height:14px;border-radius:7px;background:#3d444d;transition:background .15s}' +
        '.pg-re-tg:after{content:"";position:absolute;top:2px;left:2px;width:10px;height:10px;border-radius:50%;background:#e6edf3;transition:left .15s}' +
        '.pg-re-sw.on .pg-re-tg{background:#2ea043}.pg-re-sw.on .pg-re-tg:after{left:14px}' +
        '#pg-pva a.pg-re-ln{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 8px;border-radius:6px;font-size:12px;color:#58a6ff;text-decoration:none}' +
        '#pg-pva a.pg-re-ln:hover{background:#2a313b}' +
        '.pg-re-f{outline:1.5px dashed ' + GREEN + '!important;outline-offset:2px;cursor:text}' +
        '.pg-re-f[data-re-c=picture]{cursor:pointer}' +
        '.pg-re-f:hover,.pg-re-f:focus,.pg-re-f.pg-re-ch{outline-style:solid!important}' +
        '.pg-re-f:focus{box-shadow:none}' +
        '.pg-re-f.pg-re-on{outline:2px solid ' + GREEN + '!important;background:#fff;color:#111;box-shadow:0 0 0 5px rgba(34,197,94,.22)}' +
        '.pg-re-f.pg-re-bad{outline:2px solid #dc2626!important}' +
        '#pg-re-layer{position:absolute;left:0;top:0;width:0;height:0;z-index:2147483640;font:12px/1.4 system-ui,-apple-system,"Segoe UI",sans-serif;text-align:left;letter-spacing:normal}' +
        '#pg-re-layer *{box-sizing:border-box}' +
        '.pg-re-tag{position:absolute;padding:1px 7px;border-radius:4px;background:' + GREEN + ';color:#fff;font-size:11px;font-weight:600;white-space:nowrap;pointer-events:none}' +
        '.pg-re-tag.bad{background:#dc2626}' +
        '.pg-re-dot{position:absolute;width:9px;height:9px;border-radius:50%;background:' + GREEN + ';box-shadow:0 0 0 2px #fff;pointer-events:none}' +
        '.pg-re-pic{position:absolute;display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 10px;border:0;border-radius:6px;background:rgba(22,27,34,.88);color:#fff;font:inherit;cursor:pointer}' +
        '.pg-re-pop{position:absolute;min-width:200px;padding:6px;border-radius:10px;background:#1f242c;color:#c9d1d9;box-shadow:inset 0 0 0 1px #343c48,0 14px 34px rgba(0,0,0,.45)}' +
        '.pg-re-pop button{font:inherit;color:inherit;background:none;border:0;margin:0;cursor:pointer}' +
        '.pg-re-pop .pg-re-row{display:flex;align-items:center;width:100%;min-height:30px;padding:4px 8px;border-radius:7px;text-align:left}' +
        '.pg-re-pop .pg-re-row:hover,.pg-re-pop .pg-re-row:focus{background:#2a313b;outline:0}' +
        '.pg-re-cap{margin:6px 8px 3px;font-size:10.5px;font-weight:650;letter-spacing:.06em;text-transform:uppercase;color:#8c96a4}' +
        '.pg-re-pop input{width:100%;height:30px;padding:0 8px;border-radius:7px;border:1px solid #30363d;background:#0d1117;color:#e6edf3;font:inherit}' +
        '.pg-re-tb{display:flex;gap:2px;min-width:0;padding:3px}' +
        '.pg-re-tb button{width:28px;height:26px;border-radius:5px;font-weight:600}.pg-re-tb button:hover{background:#2a313b}' +
        '.pg-re-cal{width:236px}.pg-re-cal-h{display:flex;align-items:center;justify-content:space-between;padding:2px 4px 6px;font-weight:600;color:#e6edf3}' +
        '.pg-re-cal-h button{width:26px;height:24px;border-radius:5px}.pg-re-cal-h button:hover{background:#2a313b}' +
        '.pg-re-cal-g{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center}' +
        '.pg-re-cal-g span{font-size:10.5px;color:#8c96a4;padding:2px 0}' +
        '.pg-re-cal-g button{height:28px;border-radius:6px}.pg-re-cal-g button:hover{background:#2a313b}' +
        '.pg-re-cal-g button.on{background:#2563eb;color:#fff}' +
        '#pg-re-foot{position:fixed;z-index:2147483645;left:0;right:0;bottom:0;padding:10px 14px;background:#161b22;border-top:1px solid #30363d;color:#c9d1d9;font:12.5px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;text-align:left;letter-spacing:normal}' +
        '#pg-re-foot *{box-sizing:border-box}' +
        '#pg-re-foot.ask,#pg-re-foot.conflict{background:#2b2111;border-top-color:#6b4f1d}' +
        '#pg-re-foot button{font:inherit;cursor:pointer;margin:0}' +
        '.pg-re-r{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.pg-re-r>span{flex:1;min-width:0}' +
        '.pg-re-d{display:inline-block;flex:none;width:8px;height:8px;border-radius:50%;background:#3ecf8e}' +
        '.pg-re-d.bad{background:#ff6b6b}.pg-re-d.warn{background:#f5b54a}' +
        '.pg-re-sub{margin-top:3px;color:#8b949e;font-size:12px}' +
        '.pg-re-b{height:28px;padding:0 12px;border-radius:6px;border:0;background:none;color:#c9d1d9}' +
        '.pg-re-b:hover{background:#2a313b}.pg-re-b[disabled]{opacity:.5;cursor:default}' +
        '.pg-re-b.p{background:#2563eb;color:#fff}.pg-re-b.p:hover{background:#1d4ed8}' +
        '.pg-re-cards{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}' +
        '.pg-re-card{padding:10px;border-radius:8px;background:#1f242c;box-shadow:inset 0 0 0 1px #343c48}' +
        '.pg-re-card small{display:block;font-size:10.5px;font-weight:650;letter-spacing:.06em;text-transform:uppercase;color:#8c96a4}' +
        '.pg-re-card p{margin:4px 0 8px;color:#e6edf3;max-height:5.8em;overflow:auto;white-space:pre-wrap}' +
        '.pg-re-card .pg-re-b{box-shadow:inset 0 0 0 1px #3d444d}' +
        '@media (max-width:560px){' +
            '#pg-re-foot .pg-re-r>span{flex-basis:100%}' +
            '.pg-re-cards{grid-template-columns:1fr}' +
            '.pg-re-pop{position:fixed!important;left:0!important;right:0;top:auto!important;bottom:0;border-radius:16px 16px 0 0;padding:10px 10px 14px}' +
            '.pg-re-cal{width:auto}' +
        '}';

    // ── Values ──
    function fields() { return (R.read && Array.isArray(R.read.fields)) ? R.read.fields : []; }

    function field(fid) {
        var list = fields();
        for (var i = 0; i < list.length; i++) if (parseInt(list[i].id, 10) === fid) return list[i];
        return null;
    }

    function title() { return String(((R.read || {}).record || {}).title || ''); }

    function valueOf(fid) {
        return Object.prototype.hasOwnProperty.call(R.edits, fid) ? R.edits[fid] : String(R.base[fid] == null ? '' : R.base[fid]);
    }

    // The changes the foot bar counts: a field refused in place is not one
    // until it is put right.
    function counted() {
        return Object.keys(R.edits).map(Number).filter(function (fid) { return !(R.bad[fid] && R.bad[fid].local); });
    }

    function plainOf(html) {
        var d = (R.doc || document).createElement('div');
        d.innerHTML = String(html || '');
        return String(d.textContent || '').trim();
    }

    function pictureUrl(name) {
        if (!name) return 'data:image/svg+xml,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" width="4" height="3"><rect width="4" height="3" fill="#e5e7eb"/></svg>');
        return (window.OUTPUT_PATH || '/') + encodeURIComponent(String(name)).replace(/%2F/g, '/');
    }

    function dateText(v) {
        var m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(String(v || '').trim());
        if (!m) return String(v || '');
        var lang = (R.doc && R.doc.documentElement.lang) || undefined;
        try {
            return new Date(+m[1], +m[2] - 1, +m[3]).toLocaleDateString(lang, { day: 'numeric', month: 'long', year: 'numeric' });
        } catch (e) { return String(v); }
    }

    // A field's value as one line, for the cards of a field changed meanwhile.
    function shownValue(fid, v) {
        var f = field(fid) || {};
        if (f.control === 'picture') return v ? (R.names[fid] || ('#' + v)) : _sdT('No picture');
        if (f.control === 'date') return dateText(v);
        return f.control === 'rich' ? plainOf(v) : String(v || '');
    }

    // Where the value refuses in place: '' when it may be kept.
    function refusal(f, v) {
        var t = String(v || '').trim();
        if (f.required && t === '' && f.control !== 'rich') return _sdT('This field cannot be empty.');
        if (f.required && f.control === 'rich' && plainOf(v) === '') return _sdT('This field cannot be empty.');
        if (f.control === 'number' && t !== '' && !/^-?\d+([.,]\d+)?$/.test(t)) return _sdT('A number is needed.');
        return '';
    }

    // ── Server ──
    function api(action, body) {
        var d = K.design || {};
        var url = d.apiUrl || ((window.OUTPUT_PATH || '/') + (typeof software_directory !== 'undefined' ? software_directory : 'pinegrap') + '/api.php');
        body.action = action;
        body.token = K._pgToken();
        return fetch(url, {
            method: 'POST', body: JSON.stringify(body), credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); });
    }

    function takeFields(list) {
        (list || []).forEach(function (f) {
            var fid = parseInt(f.id, 10);
            R.base[fid] = String(f.value == null ? '' : f.value);
            if (f.control === 'picture' && !Object.prototype.hasOwnProperty.call(R.edits, fid)) R.names[fid] = String(f.file_name || '');
        });
    }

    function read(id) {
        var ask = ++R.reading;
        api('record_edit_read', { item_id: id }).then(function (res) {
            if (ask !== R.reading || id !== R.id) return;
            R.read = (res && res.status === 'ok') ? res : null;
            R.base = {};
            if (R.read) takeFields(R.read.fields);
            K._sdRecEditBar();
            // Opened from the canvas row "Edit this record's content": the
            // switch turns on by itself unless the change would wait.
            if (K._sdRecEditWant(false)) {
                if (R.read && !R.read.waits) setOn(true);
                else K._sdRecEditWant(true);
            }
        }, function () {
            if (ask === R.reading) { R.read = null; K._sdRecEditBar(); }
        });
    }

    // ── The switch ──
    // The link "Open the record" to a record's own screen, in a new tab; the
    // arrow is a character, since the site's page may load no icon font.
    function link(screen, where) {
        var a = '<a class="pg-re-ln" href="' + esc(K._sdRecScreenUrl(screen)) + '" target="_blank" rel="noopener">' + esc(_sdT('Open the record')) +
            '<span aria-hidden="true">\u2197</span></a>';
        return where === 'row' ? '<div class="pg-pva-rule"></div>' + a : a;
    }

    function html(where) {
        if (!R.id) return (R.other && R.other.screen) ? link(R.other.screen, where) : '';
        if (!R.read) return '';
        if (R.read.waits) return link('edit_submitted_form.php?id=' + R.id, where);
        var sw = '<button type="button" class="pg-re-sw' + (R.on ? ' on' : '') + '" role="switch" aria-checked="' + (R.on ? 'true' : 'false') + '" data-re-act="switch">' +
            esc(_sdT('Edit content here')) + '<span class="pg-re-tg" aria-hidden="true"></span></button>';
        return where === 'row' ? '<div class="pg-pva-rule"></div>' + sw : sw;
    }

    function setOn(on) {
        if (R.on === on) return;
        if (!on && Object.keys(R.edits).length) { ask(function () { setOn(false); }); return; }
        R.on = on;
        if (!on) { closeEdit(false); unframe(); R.bad = {}; R.mode = ''; foot(); }
        K._sdRecEditMarks(on);
        K._sdRecEditBar();
    }

    // ── The frames ──
    function lookup(node) {
        var sid = parseInt((node.props || {}).sharedId, 10) || 0;
        var c = sid ? K._sharedCache[sid] : null;
        if (!c) return null;
        var cfg = null;
        try { cfg = c.system_region_config ? JSON.parse(c.system_region_config) : null; } catch (e) { cfg = null; }
        if (!cfg) return { kind: '', formId: 0, tree: c.tree };
        return { kind: String(cfg.regionType || 'widget'), formId: parseInt(cfg.custom_form_page_id || cfg.form_page_id, 10) || 0, tree: c.tree };
    }

    // Each framed element: {el, fid, prop, control, page} (page: a value of
    // the page's record outside a widget, which the Preview draws by name).
    function spots() {
        var P = window.PGRecordEditSpots, d = R.doc;
        if (!P || !d || !R.read) return [];
        var out = [];
        P.find(K.tree, fields(), { formId: parseInt(R.read.record.form_page_id, 10) || 0, lookup: lookup }).forEach(function (s) {
            var list = d.querySelectorAll('[data-pg-node="' + String(s.node).replace(/["\\]/g, '') + '"]');
            Array.prototype.forEach.call(list, function (el) {
                if (el.closest('#pg-pva,#pg-re-foot,#pg-re-layer')) return;
                out.push({ el: el, node: String(s.node), fid: s.field, prop: s.prop, control: s.control, page: String(s.token).indexOf('__record__') === 0 });
            });
        });
        return out;
    }

    function frame() {
        unframe();
        if (!R.on || !R.doc) return;
        R.spots = spots();
        R.spots.forEach(function (s) {
            if (s.prop !== 'alt') {
                s.el.classList.add('pg-re-f');
                s.el.setAttribute('data-re-f', String(s.fid));
                s.el.setAttribute('data-re-c', s.control);
                s.el.setAttribute('tabindex', '0');
            }
            if (s.page || Object.prototype.hasOwnProperty.call(R.edits, s.fid)) show(s, valueOf(s.fid));
        });
        marks();
    }

    function unframe() {
        var d = R.doc;
        if (!d) return;
        Array.prototype.forEach.call(d.querySelectorAll('.pg-re-f'), function (el) {
            el.classList.remove('pg-re-f', 'pg-re-on', 'pg-re-ch', 'pg-re-bad');
            el.removeAttribute('data-re-f');
            el.removeAttribute('data-re-c');
            el.removeAttribute('tabindex');
            el.removeAttribute('contenteditable');
        });
        var l = d.getElementById('pg-re-layer');
        if (l) l.parentNode.removeChild(l);
        R.pop = null;
        R.spots = [];
    }

    // A value into every element of its field but `skip` (the one typed in).
    function show(s, v, skip) {
        if (s.el === skip) return;
        if (s.prop === 'alt') { s.el.setAttribute('alt', v); return; }
        if (s.control === 'picture') { s.el.setAttribute('src', pictureUrl(v ? R.names[s.fid] : '')); s.el.removeAttribute('srcset'); return; }
        if (s.control === 'rich' && s.prop === 'html') { s.el.innerHTML = v; return; }
        if (s.control === 'rich') { s.el.textContent = plainOf(v); return; }
        s.el.textContent = s.control === 'date' ? dateText(v) : v;
    }

    function showField(fid, skip) {
        (R.spots || []).forEach(function (s) { if (s.fid === fid) show(s, valueOf(fid), skip); });
    }

    // The tags, dots and red reasons over the frames, in the layer.
    // The layer holds the marks (#pg-re-marks, drawn again as a whole) and
    // the open menu beside them, so a menu keeps its focus while the marks
    // change.
    function layer() {
        var d = R.doc, l = d.getElementById('pg-re-layer');
        if (!l) { l = d.createElement('div'); l.id = 'pg-re-layer'; l.innerHTML = '<div id="pg-re-marks"></div>'; d.body.appendChild(l); }
        return l;
    }

    function box(el) {
        var r = el.getBoundingClientRect(), w = R.win;
        return { left: r.left + w.pageXOffset, top: r.top + w.pageYOffset, right: r.right + w.pageXOffset, bottom: r.bottom + w.pageYOffset, width: r.width, height: r.height };
    }

    function marks() {
        if (!R.doc || !R.on) return;
        var h = '', hot = R.hot;
        (R.spots || []).forEach(function (s) {
            if (s.prop === 'alt') return;
            var b = box(s.el), fid = s.fid, f = field(fid) || {};
            var changed = Object.prototype.hasOwnProperty.call(R.edits, fid) && !R.bad[fid];
            s.el.classList.toggle('pg-re-ch', changed);
            s.el.classList.toggle('pg-re-bad', !!R.bad[fid]);
            if (changed) h += '<span class="pg-re-dot" style="left:' + (b.right - 3) + 'px;top:' + (b.top - 6) + 'px"></span>';
            if (R.bad[fid]) h += '<span class="pg-re-tag bad" style="left:' + b.left + 'px;top:' + (b.bottom + 5) + 'px">' + esc(R.bad[fid].msg) + '</span>';
            else if (hot === s.el) h += '<span class="pg-re-tag" style="left:' + (b.left - 2) + 'px;top:' + (b.top - 24) + 'px">' + esc(f.label || f.name || '') + '</span>';
            if (s.control === 'picture' && (hot === s.el || (R.pop && R.pop.el === s.el))) {
                h += '<button type="button" class="pg-re-pic" data-re-act="pic" data-re-at="' + R.spots.indexOf(s) + '" style="left:' + (b.left + b.width / 2 - 62) + 'px;top:' + (b.top + b.height / 2 - 14) + 'px">' + esc(_sdT('Change picture')) + '</button>';
            }
        });
        layer().querySelector('#pg-re-marks').innerHTML = h;
    }

    // ── Editing in place ──
    function spotOf(el) {
        for (var i = 0; i < (R.spots || []).length; i++) if (R.spots[i].el === el && R.spots[i].prop !== 'alt') return R.spots[i];
        return null;
    }

    function startEdit(s) {
        if (R.open && R.open.el === s.el) return;
        closeEdit(true);
        if (s.control === 'picture') { pictureMenu(s); return; }
        R.open = { el: s.el, fid: s.fid, s: s, before: valueOf(s.fid) };
        s.el.classList.add('pg-re-on');
        if (s.control === 'date') { calendar(s); return; }
        var rich = s.control === 'rich' && s.prop === 'html';
        try { s.el.contentEditable = rich ? 'true' : 'plaintext-only'; } catch (e) { s.el.contentEditable = 'true'; }
        if (!rich && s.el.contentEditable !== 'plaintext-only') s.el.contentEditable = 'true';
        if (rich) { s.el.innerHTML = valueOf(s.fid); textBar(s); }
        else s.el.textContent = valueOf(s.fid);
        s.el.focus();
        var sel = R.win.getSelection(), range = R.doc.createRange();
        range.selectNodeContents(s.el);
        if (rich) range.collapse(false);
        sel.removeAllRanges();
        sel.addRange(range);
    }

    // Ends the edit: `keep` keeps what was typed, else gives back what the
    // field showed before it.
    function closeEdit(keep) {
        var o = R.open;
        closePop();
        if (!o) return;
        R.open = null;
        o.el.classList.remove('pg-re-on');
        o.el.removeAttribute('contenteditable');
        // Escape on a value refused in place gives back what the record holds.
        if (!keep) change(o.fid, refusal(field(o.fid) || {}, o.before) ? String(R.base[o.fid] == null ? '' : R.base[o.fid]) : o.before, null);
        showField(o.fid);
        marks();
        foot();
    }

    // A value typed or chosen for field `fid`, shown in its other places.
    function change(fid, v, from) {
        var f = field(fid) || {};
        if (v === String(R.base[fid] == null ? '' : R.base[fid]) && !R.theirs[fid]) delete R.edits[fid];
        else R.edits[fid] = v;
        var why = refusal(f, v);
        if (why) R.bad[fid] = { msg: why, local: true };
        else delete R.bad[fid];
        if (R.mode === 'refused' || R.mode === 'error') R.mode = '';
        showField(fid, from);
        marks();
        foot();
    }

    function typed(el) {
        var o = R.open;
        if (!o || o.el !== el) return;
        var rich = o.s.control === 'rich' && o.s.prop === 'html';
        // A plain text area keeps the line breaks it holds; Enter still
        // ends the edit, so a line of text stays one line.
        var multi = !!(field(o.fid) || {}).multiline;
        var v = rich ? el.innerHTML : String(el.textContent || '');
        if (!rich && !multi) v = v.replace(/[\r\n]+/g, ' ');
        if (o.s.control === 'rich' && !rich) v = esc(v);
        change(o.fid, v, el);
    }

    // ── Small menus: the text bar, the calendar, the picture menu ──
    function closePop() {
        if (R.pop && R.pop.node && R.pop.node.parentNode) R.pop.node.parentNode.removeChild(R.pop.node);
        R.pop = null;
    }

    function openPop(s, cls, inner, above) {
        closePop();
        var b = box(s.el), node = R.doc.createElement('div');
        node.className = 'pg-re-pop ' + cls;
        node.setAttribute('role', 'dialog');
        node.innerHTML = inner;
        layer().appendChild(node);
        node.style.left = Math.max(8, Math.min(b.left, R.win.pageXOffset + R.doc.documentElement.clientWidth - node.offsetWidth - 8)) + 'px';
        node.style.top = (above ? b.top - node.offsetHeight - 8 : b.bottom + 8) + 'px';
        R.pop = { node: node, el: s.el, s: s };
        return node;
    }

    function textBar(s) {
        var b = function (cmd, label, txt) {
            return '<button type="button" data-re-cmd="' + cmd + '" title="' + esc(label) + '" aria-label="' + esc(label) + '">' + txt + '</button>';
        };
        openPop(s, 'pg-re-tb', b('bold', _sdT('Bold'), 'B') + b('italic', _sdT('Italic'), '<i>I</i>') + b('link', _sdT('Link'), '&#128279;') +
            b('insertUnorderedList', _sdT('Bulleted list'), '&bull;') + b('insertOrderedList', _sdT('Numbered list'), '1.'), true);
    }

    function command(cmd) {
        var o = R.open;
        if (!o) return;
        o.el.focus();
        if (cmd === 'link') {
            var url = R.win.prompt(_sdT('Link address'), 'https://');
            if (url) R.doc.execCommand('createLink', false, url);
        } else {
            R.doc.execCommand(cmd, false, null);
        }
        typed(o.el);
    }

    function calendar(s, month) {
        var v = valueOf(s.fid), m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(v), now = new Date();
        var at = month || (m ? new Date(+m[1], +m[2] - 1, 1) : new Date(now.getFullYear(), now.getMonth(), 1));
        var lang = R.doc.documentElement.lang || undefined, h = '';
        var name;
        try { name = at.toLocaleDateString(lang, { month: 'long', year: 'numeric' }); } catch (e) { name = (at.getMonth() + 1) + '/' + at.getFullYear(); }
        h += '<div class="pg-re-cal-h"><button type="button" data-re-cal="-1" aria-label="' + esc(_sdT('Previous month')) + '">&lsaquo;</button><span>' + esc(name) +
            '</span><button type="button" data-re-cal="1" aria-label="' + esc(_sdT('Next month')) + '">&rsaquo;</button></div><div class="pg-re-cal-g">';
        for (var w = 0; w < 7; w++) {
            var wd = new Date(2024, 0, 1 + w); // a Monday
            try { h += '<span>' + esc(wd.toLocaleDateString(lang, { weekday: 'narrow' })) + '</span>'; } catch (e) { h += '<span></span>'; }
        }
        var lead = (at.getDay() + 6) % 7, days = new Date(at.getFullYear(), at.getMonth() + 1, 0).getDate();
        for (var i = 0; i < lead; i++) h += '<span></span>';
        for (var d = 1; d <= days; d++) {
            var iso = at.getFullYear() + '-' + ('0' + (at.getMonth() + 1)).slice(-2) + '-' + ('0' + d).slice(-2);
            var on = m && +m[1] === at.getFullYear() && +m[2] === at.getMonth() + 1 && +m[3] === d;
            h += '<button type="button" data-re-day="' + iso + '"' + (on ? ' class="on"' : '') + '>' + d + '</button>';
        }
        var node = openPop(s, 'pg-re-cal', h + '</div>', false);
        R.pop.month = at;
        var first = node.querySelector('button.on') || node.querySelector('[data-re-day]');
        if (first) first.focus();
    }

    function pictureMenu(s) {
        closeEdit(true);
        var f = field(s.fid) || {}, alt = null;
        (R.spots || []).forEach(function (o) { if (o.el === s.el && o.prop === 'alt') alt = o; });
        var h = '<button type="button" class="pg-re-row" data-re-act="choose">' + esc(_sdT('Choose a picture…')) + '</button>';
        if (alt) {
            h += '<div class="pg-re-cap">' + esc(_sdT('Alternative text')) + '</div><input type="text" data-re-alt="' + alt.fid + '" value="' + esc(valueOf(alt.fid)) + '" aria-label="' + esc(_sdT('Alternative text')) + '">';
        }
        if (!f.required) h += '<button type="button" class="pg-re-row" data-re-act="remove">' + esc(_sdT('Remove the picture')) + '</button>';
        var node = openPop(s, 'pg-re-picm', h, false);
        R.pop.alt = alt;
        var first = node.querySelector('button,input');
        if (first) first.focus();
        marks();
    }

    function choosePicture(s) {
        closePop();
        if (!K._sdPickerReady()) return;
        try { window.focus(); } catch (e) {}
        window.pgEditorPicker.pictures().then(function (items) {
            var item = (items || [])[0];
            try { R.win.focus(); } catch (e) {}
            if (!item || !(parseInt(item.id, 10) > 0)) return;
            R.names[s.fid] = String(item.name || '');
            change(s.fid, String(parseInt(item.id, 10)), null);
        });
    }

    // ── The foot bar ──
    function changesText(n) {
        return n === 1 ? _sdT('1 change to {var:1}', [title()]) : _sdT('{var:1} changes to {var:2}', [n, title()]);
    }

    function footHtml() {
        var list = counted(), n = list.length, busy = R.mode === 'saving';
        var save = '<button type="button" class="pg-re-b p" data-re-act="save"' + (busy || !n ? ' disabled' : '') + '>' + esc(busy ? _sdT('Saving…') : _sdT('Save to the record')) + '</button>';
        var discard = '<button type="button" class="pg-re-b" data-re-act="discard"' + (busy ? ' disabled' : '') + '>' + esc(_sdT('Discard')) + '</button>';
        if (R.mode === 'ask') {
            return '<div class="pg-re-r"><i class="pg-re-d warn"></i><span>' + esc(n === 1 ? _sdT('Save your change to {var:1} first?', [title()]) : _sdT('Save your {var:1} changes to {var:2} first?', [n, title()])) + '</span>' +
                '<button type="button" class="pg-re-b" data-re-act="keep">' + esc(_sdT('Keep editing')) + '</button>' + discard + save + '</div>';
        }
        if (R.mode === 'conflict') {
            var c = R.conflicts[R.ci] || {}, fid = parseInt(c.field_id, 10);
            var more = R.conflicts.length > 1 ? ' ' + _sdT('{var:1} of {var:2}', [R.ci + 1, R.conflicts.length]) : '';
            return '<div class="pg-re-r"><i class="pg-re-d warn"></i><span>' + esc(_sdT('{var:1} was changed on the record while you were editing. Nothing was saved yet.', [c.label || ''])) + esc(more) + '</span>' + discard + '</div>' +
                '<div class="pg-re-cards"><div class="pg-re-card"><small>' + esc(_sdT('Theirs, as the record holds it now')) + '</small><p>' + esc(shownValue(fid, c.theirs)) + '</p>' +
                '<button type="button" class="pg-re-b" data-re-act="theirs">' + esc(_sdT('Keep theirs')) + '</button></div>' +
                '<div class="pg-re-card"><small>' + esc(_sdT('Yours')) + '</small><p>' + esc(shownValue(fid, valueOf(fid))) + '</p>' +
                '<button type="button" class="pg-re-b" data-re-act="mine">' + esc(_sdT('Keep mine')) + '</button></div></div>';
        }
        var dot = '', text;
        if (R.mode === 'refused') { dot = ' bad'; text = _sdT('{var:1} of {var:2} changes was refused by the record. Nothing was saved.', [R.refused, n]); }
        else if (R.mode === 'error') { dot = ' bad'; text = _sdT('The record could not be reached. Nothing was saved.'); }
        else if (busy) text = n === 1 ? _sdT('Saving 1 change to {var:1}', [title()]) : _sdT('Saving {var:1} changes to {var:2}', [n, title()]);
        else text = changesText(n);
        return '<div class="pg-re-r"><i class="pg-re-d' + dot + '"></i><span role="status">' + esc(text) + '</span>' + discard + save + '</div>' +
            '<div class="pg-re-sub">' + esc(_sdT('This changes the record wherever it is shown.')) + '</div>';
    }

    function foot() {
        var d = R.doc;
        if (!d || !d.body) return;
        var el = d.getElementById('pg-re-foot');
        var show = R.on && (Object.keys(R.edits).length > 0 || R.mode !== '');
        if (!show) {
            if (el) el.parentNode.removeChild(el);
            d.documentElement.style.removeProperty('--pg-re-h');
            return;
        }
        if (!el) { el = d.createElement('div'); el.id = 'pg-re-foot'; el.setAttribute('role', 'region'); el.setAttribute('aria-label', _sdT('Changes to the record')); d.body.appendChild(el); }
        el.className = R.mode;
        el.innerHTML = footHtml();
        d.documentElement.style.setProperty('--pg-re-h', el.offsetHeight + 'px');
    }

    // Asks before leaving changes not saved; `then` runs once they are
    // saved or discarded.
    function ask(then) {
        closeEdit(true);
        R.pending = then;
        R.mode = 'ask';
        foot();
    }

    function goOn() {
        var then = R.pending;
        R.pending = null;
        if (then) then();
    }

    function discard() {
        closeEdit(true);
        var was = Object.keys(R.edits).map(Number);
        R.edits = {};
        R.bad = {};
        R.theirs = {};
        R.mode = '';
        R.conflicts = [];
        takeFields(fields());
        was.forEach(function (fid) { showField(fid); });
        marks();
        foot();
        goOn();
    }

    function save() {
        closeEdit(true);
        var list = counted();
        if (!list.length) { goOn(); return; }
        R.mode = 'saving';
        foot();
        var changes = list.map(function (fid) {
            return { field_id: fid, value: R.edits[fid], base: Object.prototype.hasOwnProperty.call(R.theirs, fid) ? R.theirs[fid] : String(R.base[fid] == null ? '' : R.base[fid]) };
        });
        // What this save carries: a change typed while it is on its way is
        // not part of it and stays after the answer.
        R.sent = {};
        changes.forEach(function (c) { R.sent[c.field_id] = c.value; });
        api('record_edit_save', { item_id: R.id, changes: changes }).then(saved, function () { R.mode = 'error'; R.pending = null; foot(); });
    }

    function saved(res) {
        var st = res && res.status;
        if (st === 'saved' || st === 'unchanged') {
            if (res.record) R.read.record = res.record;
            R.read.fields = res.fields || R.read.fields;
            Object.keys(R.sent).forEach(function (k) {
                var fid = parseInt(k, 10);
                if (R.edits[fid] === R.sent[fid]) delete R.edits[fid];
                delete R.theirs[fid];
                if (!Object.prototype.hasOwnProperty.call(R.edits, fid)) delete R.bad[fid];
            });
            R.sent = {}; R.conflicts = []; R.mode = '';
            takeFields(R.read.fields);
            // A change typed meanwhile that the record now holds is none.
            Object.keys(R.edits).forEach(function (k) {
                var fid = parseInt(k, 10);
                if (R.edits[fid] === String(R.base[fid] == null ? '' : R.base[fid])) { delete R.edits[fid]; delete R.bad[fid]; }
            });
            foot();
            K._sdRecEditBar({ tone: 'ok', text: _sdT('Saved to {var:1}. The item\'s history keeps what it was before.', [title()]), act: 'close' });
            K._sdRecEditRedraw();
            goOn();
            return;
        }
        R.pending = null;
        if (st === 'conflict') {
            // A field only the other person changed is theirs.
            (res.fields || []).forEach(function (f) {
                var fid = parseInt(f.id, 10);
                if (!Object.prototype.hasOwnProperty.call(R.edits, fid)) { R.base[fid] = String(f.value == null ? '' : f.value); if (f.control === 'picture') R.names[fid] = String(f.file_name || ''); }
                else if (f.control === 'picture') R.theirsName[fid] = String(f.file_name || '');
            });
            R.conflicts = res.conflicts || [];
            R.ci = 0;
            R.mode = R.conflicts.length ? 'conflict' : '';
            foot();
            return;
        }
        if (st === 'invalid' || st === 'refused') {
            var errs = res.errors || (res.field_id ? [{ field_id: res.field_id, message: res.message }] : []);
            errs.forEach(function (e) { R.bad[parseInt(e.field_id, 10)] = { msg: String(e.message || ''), local: false }; });
            R.refused = errs.length || 1;
            R.mode = 'refused';
            marks();
            foot();
            return;
        }
        if (st === 'approval' && R.read) {
            R.read.waits = true;
        } else {
            R.read = null;
        }
        R.edits = {}; R.bad = {}; R.mode = '';
        R.on = false;
        unframe();
        foot();
        K._sdRecEditMarks(false);
        K._sdRecEditBar({ tone: 'warn', text: String((res && res.message) || _sdT('This item is gone or may no longer be edited here.')), act: 'close' });
    }

    // A field changed meanwhile answered: mine is sent again over theirs,
    // theirs drops the change; after the last the save goes on by itself.
    function answer(mine) {
        var c = R.conflicts[R.ci] || {}, fid = parseInt(c.field_id, 10);
        if (mine) R.theirs[fid] = String(c.theirs == null ? '' : c.theirs);
        else {
            delete R.edits[fid];
            delete R.theirs[fid];
            R.base[fid] = String(c.theirs == null ? '' : c.theirs);
            if (R.theirsName[fid] !== undefined) R.names[fid] = R.theirsName[fid];
            showField(fid);
        }
        R.ci++;
        if (R.ci < R.conflicts.length) { foot(); return; }
        R.conflicts = [];
        R.mode = '';
        marks();
        if (counted().length) save(); else foot();
    }

    // ── Events in the Preview's document ──
    function onClick(e) {
        var t = e.target && e.target.closest ? e.target : null;
        if (!t) return;
        var act = t.closest('[data-re-act]');
        if (act) {
            e.preventDefault();
            e.stopPropagation();
            var a = act.getAttribute('data-re-act');
            if (a === 'switch') setOn(!R.on);
            else if (a === 'save') save();
            else if (a === 'discard') discard();
            else if (a === 'keep') { R.mode = ''; R.pending = null; foot(); }
            else if (a === 'mine') answer(true);
            else if (a === 'theirs') answer(false);
            else if (a === 'pic') pictureMenu(R.spots[parseInt(act.getAttribute('data-re-at'), 10)]);
            else if (a === 'choose' && R.pop) choosePicture(R.pop.s);
            else if (a === 'remove' && R.pop) { var ps = R.pop.s; closePop(); change(ps.fid, '', null); }
            return;
        }
        if (!R.on) return;
        var cmd = t.closest('[data-re-cmd]');
        if (cmd) { e.preventDefault(); command(cmd.getAttribute('data-re-cmd')); return; }
        var cal = t.closest('[data-re-cal]');
        if (cal && R.pop) { e.preventDefault(); var m = R.pop.month; calendar(R.pop.s, new Date(m.getFullYear(), m.getMonth() + parseInt(cal.getAttribute('data-re-cal'), 10), 1)); return; }
        var day = t.closest('[data-re-day]');
        if (day && R.open) { e.preventDefault(); var fid = R.open.fid; change(fid, day.getAttribute('data-re-day'), null); closeEdit(true); return; }
        if (t.closest('.pg-re-pop')) return;
        var f = t.closest('.pg-re-f');
        if (f) {
            // A framed text inside a link is edited, not followed.
            e.preventDefault();
            e.stopPropagation();
            var s = spotOf(f);
            if (s) startEdit(s);
            return;
        }
        if (R.open) closeEdit(true);
        else if (R.pop) { closePop(); marks(); }
    }

    function onKey(e) {
        var t = e.target;
        if (t && t.getAttribute && t.getAttribute('data-re-act') === 'switch' && e.key === 'Tab' && !e.shiftKey && R.on) {
            var first = R.doc.querySelector('.pg-re-f');
            if (first) { e.preventDefault(); first.focus(); }
            return;
        }
        if (!R.on) return;
        if (e.key === 'Escape') {
            if (R.open) { e.preventDefault(); var el = R.open.el; closeEdit(false); el.focus(); }
            else if (R.pop) { e.preventDefault(); var pel = R.pop.el; closePop(); marks(); pel.focus(); }
            return;
        }
        if (R.open && t === R.open.el && e.key === 'Enter' && !(R.open.s.control === 'rich' && R.open.s.prop === 'html')) {
            e.preventDefault();
            closeEdit(true);
            t.focus();
            return;
        }
        if (!R.open && t && t.classList && t.classList.contains('pg-re-f') && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            var s = spotOf(t);
            if (s) startEdit(s);
        }
    }

    function onInput(e) {
        var t = e.target;
        if (R.open && t === R.open.el) { typed(t); return; }
        if (t && t.hasAttribute && t.hasAttribute('data-re-alt')) change(parseInt(t.getAttribute('data-re-alt'), 10), String(t.value || ''), null);
    }

    // A paste into a line of text comes in as plain text.
    function onPaste(e) {
        if (!R.open || e.target !== R.open.el && !R.open.el.contains(e.target)) return;
        if (R.open.s.control === 'rich' && R.open.s.prop === 'html') return;
        e.preventDefault();
        var text = String((e.clipboardData && e.clipboardData.getData('text/plain')) || '').replace(/[\r\n]+/g, ' ');
        R.doc.execCommand('insertText', false, text);
    }

    function onFocusOut(e) {
        var o = R.open;
        if (!o || e.target !== o.el) return;
        var to = e.relatedTarget;
        if (to && R.pop && R.pop.node.contains(to)) return;
        if (o.s.control === 'date') return;
        closeEdit(true);
    }

    function onOver(e) {
        var f = e.target && e.target.closest ? e.target.closest('.pg-re-f,.pg-re-pic') : null;
        var el = f && f.classList.contains('pg-re-pic') ? R.hot : f;
        if (el === R.hot) return;
        R.hot = el || null;
        marks();
    }

    // The text bar's buttons keep the focus, and the selection, in the text.
    function onDown(e) {
        if (e.target && e.target.closest && e.target.closest('[data-re-cmd]')) e.preventDefault();
    }

    function onFocusIn(e) {
        var t = e.target;
        if (t && t.classList && t.classList.contains('pg-re-f') && R.hot !== t) { R.hot = t; marks(); }
    }

    function bind(d, w) {
        d.addEventListener('click', onClick, true);
        d.addEventListener('keydown', onKey, true);
        d.addEventListener('input', onInput, true);
        d.addEventListener('paste', onPaste, true);
        d.addEventListener('focusout', onFocusOut, true);
        d.addEventListener('focusin', onFocusIn, true);
        d.addEventListener('mouseover', onOver, true);
        d.addEventListener('mousedown', onDown, true);
        var frameId = 0;
        w.addEventListener('resize', function () {
            if (frameId) return;
            frameId = w.requestAnimationFrame(function () { frameId = 0; marks(); foot(); });
        });
        w.addEventListener('beforeunload', function (e) {
            if (!Object.keys(R.edits).length || w !== R.win) return;
            e.preventDefault();
            e.returnValue = '';
        });
        if (!d.getElementById('pg-re-css')) {
            var st = d.createElement('style');
            st.id = 'pg-re-css';
            st.textContent = CSS;
            (d.head || d.body).appendChild(st);
        }
    }

    // ── From the core ──
    // The Preview's document and the bar's answer (_sdRecEditSync()): the
    // item drawn, read once per item; a new document framed again with the
    // changes and the field being edited kept.
    function sync(doc, win, setups) {
        if (win !== R.win) {
            R.win = win;
            R.doc = null;
            R.on = false;
            R.edits = {}; R.bad = {}; R.theirs = {}; R.mode = ''; R.open = null; R.pop = null;
            K._sdRecEditMarks(false);
        }
        var sid = 0, id = 0, other = null;
        Object.keys(setups || {}).forEach(function (k) {
            var s = setups[k] || {}, rec = parseInt(s.record, 10) || 0;
            if (rec <= 0) return;
            if (s.source === 'form' && !id) { sid = parseInt(k, 10); id = rec; }
            else if ((s.source === 'product' || s.source === 'event') && !other) other = { sid: parseInt(k, 10), id: rec };
        });
        otherRecord(id ? null : other);
        if (id !== R.id) {
            R.id = id;
            R.sid = sid;
            R.read = null;
            R.edits = {}; R.bad = {}; R.theirs = {}; R.mode = ''; R.names = {};
            if (R.on) { R.on = false; unframe(); foot(); K._sdRecEditMarks(false); }
            if (id) read(id);
        }
        if (doc && doc !== R.doc) {
            var open = R.open ? R.open.fid : 0;
            R.open = null;
            R.pop = null;
            R.doc = doc;
            bind(doc, win);
            frame();
            foot();
            focusWanted();
            if (open) {
                var s = (R.spots || []).filter(function (o) { return o.fid === open && o.prop !== 'alt'; })[0];
                if (s && s.control !== 'picture') startEdit(s);
            }
        }
    }

    // The frame of the element the canvas row was used on, focused once the
    // page is drawn with its frames; nothing when that element is not framed.
    function focusWanted() {
        var want = R.on ? K._sdRecEditWant(false) : null;
        if (!want || !R.spots.length || R.open) return;
        K._sdRecEditWant(true);
        var framed = R.spots.filter(function (o) { return o.prop !== 'alt'; });
        var s = framed.filter(function (o) { return o.node === want.node; })[0];
        if (!s) return;
        try { s.el.scrollIntoView({ block: 'center' }); } catch (e) {}
        s.el.focus();
    }

    // A product or an event the Preview draws (none when a collection item
    // is drawn): the link "Open the record" once the server says this
    // person may open it on its own screen; asked once per record.
    function otherRecord(o) {
        var key = o ? o.sid + ':' + o.id : '';
        if (key === (R.other ? R.other.key : '')) return;
        R.other = o ? { key: key, screen: '' } : null;
        K._sdRecEditBar();
        if (!o) return;
        K._sdRecOpenAsk(o.sid, o.id).then(function (a) {
            if (!R.other || R.other.key !== key || !a || a.status !== 'ok') return;
            R.other.screen = String(a.screen);
            K._sdRecEditBar();
        });
    }

    // A choice of the "Preview as" bar while changes are not saved: the foot
    // bar asks first (the core's _sdPvAsChoose()).
    function holds(c) {
        if (!R.on || !Object.keys(R.edits).length) return false;
        ask(function () { K._sdPvAsChoose(c); });
        return true;
    }

    // The Preview opened again: the switch is off, nothing is kept.
    function reset() {
        R.win = null;
        R.id = 0;
        R.other = null;
    }

    return { holds: holds, html: html, reset: reset, sync: sync };
});
