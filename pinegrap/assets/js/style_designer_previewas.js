/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the "Preview as" part: the bar at the top of the
 * Preview window with the "Record", "Visitor" and "Case" choosers, "Reset"
 * and the one line under it. The bar lives in the Preview's document (a
 * blob page of this origin) but is drawn and run from here: the editor's
 * window keeps its state, so an open menu, the search typed and the
 * spinner survive the live preview's reloads. The choice is the core's
 * (_sdPvAsChoice(), kept per page in this browser, sent with
 * designer/preview_widgets); the bar changes it and the core draws the
 * page again with it.
 *
 * Not needed to open a page: the core runs this file the first time a
 * preview_widgets answer says the page offers a choice (_sdPvAsAnswered()).
 * A page that offers none shows no bar: its Preview is as before.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('previewas', function (K) {
    'use strict';

    var _sdT = K._sdT;
    var esc = K.esc;

    var CASES = ['typical', 'empty', 'long', 'sold_out', 'error'];
    var SEEN_KEY = 'pgPreviewAsSeen';
    var PAGE_ROWS = 20; // what designer/preview_records answers at most

    // win/doc: the Preview window and the document the bar is bound to;
    // setups: the last answer's sid => {case, record, source, cases};
    // defaults: sid => the record drawn with no choice; labels: id => name
    // of every record listed so far, per sid; menu: the open menu ('' none,
    // 'record', 'visitor', 'case', 'sheet'); sid: the record widget whose
    // records the menu lists; find: per sid {q, rows, busy, failed};
    // drawing: a draw the bar asked for is on its way; line: the line under
    // the bar ({tone, text, act}) or null; narrow: the one-button form.
    var S = {
        win: null, doc: null, root: null, setups: {}, audience: false, defaults: {}, labels: {},
        menu: '', sid: 0, find: {}, drawing: false, line: null, narrow: false, timer: 0, poll: 0
    };

    function caseNames() {
        return {
            typical:  [_sdT('Typical'), _sdT('The page as most visitors see it')],
            empty:    [_sdT('Empty'), _sdT('A list with no record, an empty cart, a search with no result')],
            long:     [_sdT('Long'), _sdT('The longest title and text among the records')],
            sold_out: [_sdT('Sold out'), _sdT('A product that cannot be bought')],
            error:    [_sdT('Error'), _sdT('A form sent with a required field missing')]
        };
    }

    // ── What the page offers ──
    function recordSids() {
        return Object.keys(S.setups).filter(function (sid) { return !!(S.setups[sid] || {}).source; });
    }

    function pageCases() {
        var have = { typical: true };
        Object.keys(S.setups).forEach(function (sid) {
            ((S.setups[sid] || {}).cases || []).forEach(function (c) { have[c] = true; });
        });
        return CASES.filter(function (c) { return have[c]; });
    }

    function offer() {
        return { record: recordSids().length > 0, visitor: S.audience, case: pageCases().length > 1 };
    }

    function shown() {
        var o = offer();
        return o.record || o.visitor || o.case;
    }

    function choice() { return K._sdPvAsChoice(); }

    function isDefault(c) {
        return c.case === 'typical' && c.who === 'signed_out' && c.visit === 'first_visit' && !Object.keys(c.records).length;
    }

    // "Long" and "Empty" choose the record themselves.
    function recordSet(c) { return c.case === 'long' || c.case === 'empty'; }

    // ── Icons (the Preview's page may have no icon font) ──
    function svg(d, size) {
        return '<svg width="' + (size || 14) + '" height="' + (size || 14) + '" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false">' + d + '</svg>';
    }
    var IC = {
        eye: '<path d="M8 3C4.4 3 1.7 5.6.6 8c1.1 2.4 3.8 5 7.4 5s6.3-2.6 7.4-5C14.3 5.6 11.6 3 8 3zm0 8.5A3.5 3.5 0 1 1 8 4.5a3.5 3.5 0 0 1 0 7zM8 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/>',
        caret: '<path d="M3.2 5.7a.7.7 0 0 1 1-.1L8 9l3.8-3.4a.7.7 0 1 1 .9 1.1l-4.2 3.8a.7.7 0 0 1-1 0L3.3 6.7a.7.7 0 0 1-.1-1z"/>',
        tick: '<path d="M13.5 4.2a.8.8 0 0 1 0 1.1l-6.3 6.3a.8.8 0 0 1-1.1 0L2.5 8a.8.8 0 1 1 1.1-1.1l3 3 5.8-5.7a.8.8 0 0 1 1.1 0z"/>',
        reset: '<path d="M8 2.5a5.5 5.5 0 1 1-5.2 7.3.75.75 0 1 1 1.4-.5A4 4 0 1 0 5.2 5H7a.75.75 0 0 1 0 1.5H3.5a.75.75 0 0 1-.75-.75V2.25a.75.75 0 0 1 1.5 0v1.6A5.5 5.5 0 0 1 8 2.5z"/>',
        x: '<path d="M4.3 3.2 8 6.9l3.7-3.7a.75.75 0 1 1 1.1 1.1L9.1 8l3.7 3.7a.75.75 0 1 1-1.1 1.1L8 9.1l-3.7 3.7a.75.75 0 1 1-1.1-1.1L6.9 8 3.2 4.3a.75.75 0 0 1 1.1-1.1z"/>',
        lock: '<path d="M8 1.5A3 3 0 0 0 5 4.5V6H4a1 1 0 0 0-1 1v6.5a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-1V4.5a3 3 0 0 0-3-3zm1.5 4.5h-3V4.5a1.5 1.5 0 0 1 3 0V6z"/>',
        search: '<path d="M7 1.5a5.5 5.5 0 0 1 4.4 8.8l3.1 3.1a.75.75 0 1 1-1.1 1.1l-3.1-3.1A5.5 5.5 0 1 1 7 1.5zm0 1.5a4 4 0 1 0 0 8 4 4 0 0 0 0-8z"/>',
        back: '<path d="M10.3 2.7a.75.75 0 0 1 0 1.1L6.1 8l4.2 4.2a.75.75 0 1 1-1.1 1.1L4.5 8.5a.75.75 0 0 1 0-1L9.2 2.7a.75.75 0 0 1 1.1 0z"/>',
        dot: '<circle cx="8" cy="8" r="4"/>'
    };

    // ── The bar's look: the designer's dark chrome, the same in both themes ──
    var CSS =
        'html{padding-top:var(--pg-pva-h,40px)!important}' +
        '.fixed-top,.sticky-top{top:var(--pg-pva-h,40px)!important}' +
        '#pg-pva{position:fixed;z-index:2147483646;left:0;right:0;top:0;font:12.5px/1.45 system-ui,-apple-system,"Segoe UI",sans-serif;color:#c9d1d9;text-align:left;letter-spacing:normal}' +
        '#pg-pva *{box-sizing:border-box}' +
        '#pg-pva button{font:inherit;color:inherit;background:none;border:0;margin:0;cursor:pointer}' +
        '#pg-pva button:focus-visible,#pg-pva input:focus-visible{outline:2px solid #58a6ff;outline-offset:1px}' +
        '.pg-pva-bar{height:40px;display:flex;align-items:center;gap:4px;padding:0 10px;background:#161b22;border-bottom:1px solid #30363d;white-space:nowrap;overflow:hidden}' +
        '.pg-pva-ttl{flex:none;display:inline-flex;align-items:center;gap:7px;margin-right:8px;font-size:12px;font-weight:600;color:#8b949e}' +
        '.pg-pva-ch{flex:none;display:inline-flex;align-items:center;gap:6px;height:28px;padding:0 9px;border-radius:6px}' +
        '.pg-pva-ch small{font-size:12px;color:#8b949e}.pg-pva-ch b{font-weight:550;color:#e6edf3;max-width:200px;overflow:hidden;text-overflow:ellipsis}' +
        '.pg-pva-ch svg{color:#8b949e}.pg-pva-ch:hover,.pg-pva-ch.open{background:#2a313b}' +
        '.pg-pva-ch.on{background:rgba(91,155,255,.12);box-shadow:inset 0 0 0 1px rgba(91,155,255,.45)}.pg-pva-ch.on b{color:#cfe2ff}' +
        '.pg-pva-ch.set{opacity:.5;cursor:default}' +
        '.pg-pva-end{margin-left:auto;display:inline-flex;align-items:center;gap:8px}' +
        '.pg-pva-run{display:inline-flex;align-items:center;gap:6px;color:#c9d1d9}' +
        '.pg-pva-spin{width:12px;height:12px;border-radius:50%;border:2px solid #3d444d;border-top-color:#58a6ff;animation:pg-pva-r .8s linear infinite}' +
        '@keyframes pg-pva-r{to{transform:rotate(360deg)}}' +
        '.pg-pva-b{display:inline-flex;align-items:center;gap:6px;height:26px;padding:0 10px;border-radius:6px;font-size:12px}' +
        '.pg-pva-b.q{box-shadow:inset 0 0 0 1px #3d444d}.pg-pva-b:hover{background:#2a313b}' +
        '.pg-pva-n{display:inline-grid;place-items:center;min-width:16px;height:16px;padding:0 4px;border-radius:8px;font-size:10.5px;font-weight:600;color:#fff;background:#2563eb}' +
        '.pg-pva-ln{display:flex;align-items:center;gap:10px;padding:8px 12px;background:#1f242c;border-bottom:1px solid #30363d}' +
        '.pg-pva-ln>span{flex:1}.pg-pva-ln>svg{flex:none;color:#58a6ff}' +
        '.pg-pva-ln.ok>svg{color:#3ecf8e}.pg-pva-ln.bad>svg{color:#ff6b6b}.pg-pva-ln.warn>svg{color:#f5b54a}' +
        '.pg-pva-x{display:inline-grid;place-items:center;width:24px;height:24px;border-radius:6px;color:#8b949e}.pg-pva-x:hover{background:#2a313b}' +
        '.pg-pva-pop{position:absolute;top:42px;width:252px;max-height:calc(100vh - 60px);overflow:auto;padding:8px;border-radius:12px;background:#1f242c;box-shadow:inset 0 0 0 1px #343c48,0 14px 34px rgba(0,0,0,.55)}' +
        '.pg-pva-pop.rec{width:320px}' +
        '.pg-pva-cap{margin:8px 8px 3px;font-size:10.5px;font-weight:650;letter-spacing:.06em;text-transform:uppercase;color:#8c96a4}' +
        '.pg-pva-row{display:flex;align-items:center;gap:9px;width:100%;min-height:30px;padding:4px 8px;border-radius:8px;text-align:left}' +
        '.pg-pva-row>span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}.pg-pva-row em{font-style:normal;color:#8c96a4}' +
        '.pg-pva-row small{display:block;color:#8c96a4;white-space:normal}' +
        '.pg-pva-row:hover,.pg-pva-row:focus{background:#2a313b;outline:0}.pg-pva-row.on{background:rgba(91,155,255,.14)}' +
        '.pg-pva-tk{flex:none;width:18px;color:#5b9bff}' +
        '.pg-pva-rule{height:1px;margin:6px 0;background:#2a313b}' +
        '.pg-pva-note{margin:6px 8px;color:#8c96a4;white-space:normal}' +
        '.pg-pva-note svg{vertical-align:-2px;margin-right:6px}' +
        '.pg-pva-find{display:flex;align-items:center;gap:8px;height:32px;margin:0 0 6px;padding:0 10px;border-radius:8px;background:#0d1117;box-shadow:inset 0 0 0 1px #30363d;color:#8b949e}' +
        '.pg-pva-find input{flex:1;min-width:0;height:30px;border:0;background:none;color:#e6edf3;font:inherit;outline:0}' +
        '.pg-pva-seg{display:flex;padding:3px;margin:0 0 8px;border-radius:9px;background:#11151a;box-shadow:inset 0 0 0 1px #30363d}' +
        '.pg-pva-seg button{flex:1;min-width:0;height:24px;padding:0 8px;border-radius:6px;color:#8b949e;overflow:hidden;text-overflow:ellipsis}' +
        '.pg-pva-seg button.on{background:#2a313b;color:#e6edf3}' +
        '.pg-pva-sel{width:100%;height:30px;margin:0 0 8px;border-radius:8px;background:#0d1117;color:#e6edf3;border:1px solid #30363d;font:inherit}' +
        '.pg-pva-dim{position:fixed;inset:0;background:rgba(0,0,0,.45)}' +
        '.pg-pva-sheet{position:fixed;left:0;right:0;bottom:0;max-height:70vh;overflow:auto;padding:8px 10px 12px;border-radius:16px 16px 0 0;background:#1f242c;box-shadow:inset 0 1px 0 #343c48,0 -16px 36px rgba(0,0,0,.45)}' +
        '.pg-pva-grab{width:36px;height:4px;margin:0 auto 8px;border-radius:2px;background:#3a4049}' +
        '.pg-pva-head{display:flex;align-items:center;gap:8px;margin:0 0 6px;font-weight:600;color:#e6edf3}' +
        '@media (max-width:560px){.pg-pva-ln{flex-wrap:wrap}}';

    // ── Values ──
    function rowLabel(sid, id) {
        var l = S.labels[sid] || {};
        if (l[id]) return l[id];
        var c = choice();
        return (c.labels[sid] && String(c.records[sid]) === String(id)) ? String(c.labels[sid]) : '';
    }

    function recordValue(c) {
        var sids = recordSids();
        if (!sids.length) return '';
        var sid = sids[0], s = S.setups[sid] || {};
        var id = parseInt(s.record, 10) || 0;
        var text = id ? (rowLabel(sid, id) || _sdT('Shown by default')) : _sdT('No record');
        if (recordSet(c) && id) text = rowLabel(sid, id) || caseNames()[c.case][0];
        return sids.length > 1 ? _sdT('{var:1} +{var:2}', [text, sids.length - 1]) : text;
    }

    function whoName(who) {
        return who === 'signed_in' ? _sdT('Signed in') : who === 'member' ? _sdT('Member') : _sdT('Signed out');
    }

    function visitorValue(c) {
        return c.visit === 'returning' ? _sdT('{var:1}, returning', [whoName(c.who)]) : whoName(c.who);
    }

    function changedCount(c) {
        return (Object.keys(c.records).length ? 1 : 0) + ((c.who !== 'signed_out' || c.visit !== 'first_visit') ? 1 : 0) + (c.case !== 'typical' ? 1 : 0);
    }

    // ── Drawing ──
    function chooserHtml(name, label, value, on, set) {
        return '<button type="button" class="pg-pva-ch' + (on ? ' on' : '') + (set ? ' set' : '') + (S.menu === name ? ' open' : '') + '"' +
            ' data-pva-open="' + name + '" aria-haspopup="menu" aria-expanded="' + (S.menu === name ? 'true' : 'false') + '"' +
            (set ? ' aria-disabled="true" title="' + esc(_sdT('The case chooses the record')) + '"' : '') + '>' +
            '<small>' + esc(label) + '</small><b>' + esc(value) + '</b>' + svg(IC.caret, 10) + '</button>';
    }

    function barHtml() {
        var c = choice(), o = offer();
        var h = '<div class="pg-pva-bar" role="toolbar" aria-label="' + esc(_sdT('Preview as')) + '">';
        if (S.narrow) {
            var n = changedCount(c);
            h += '<button type="button" class="pg-pva-ch' + (n ? ' on' : '') + (S.menu ? ' open' : '') + '" data-pva-open="sheet" aria-haspopup="dialog">' +
                svg(IC.eye) + '<b>' + esc(_sdT('Preview as')) + '</b>' + (n ? '<span class="pg-pva-n">' + n + '</span>' : '') + svg(IC.caret, 10) + '</button>';
        } else {
            h += '<span class="pg-pva-ttl">' + svg(IC.eye) + esc(_sdT('Preview as')) + '</span>';
            if (o.record) h += chooserHtml('record', _sdT('Record'), recordValue(c), Object.keys(c.records).length > 0, recordSet(c));
            if (o.visitor) h += chooserHtml('visitor', _sdT('Visitor'), visitorValue(c), c.who !== 'signed_out' || c.visit !== 'first_visit', false);
            if (o.case) h += chooserHtml('case', _sdT('Case'), caseNames()[c.case][0], c.case !== 'typical', false);
        }
        h += '<span class="pg-pva-end">';
        if (S.drawing) h += '<span class="pg-pva-run" role="status"><span class="pg-pva-spin"></span>' + esc(_sdT('Drawing…')) + '</span>';
        if (!isDefault(c) && !S.narrow) {
            h += '<button type="button" class="pg-pva-b" data-pva-act="reset">' + svg(IC.reset, 13) + esc(_sdT('Reset')) + '</button>';
        }
        return h + '</span></div>';
    }

    function lineHtml() {
        var L = S.line;
        if (!L) {
            var seen = false;
            try { seen = S.win.opener ? localStorage.getItem(SEEN_KEY) === '1' : true; } catch (e) { seen = true; }
            if (seen) return '';
            L = { tone: '', text: _sdT('See this page as a visitor would: choose a record, who is looking, and a case. Nothing is written to the site.'), act: 'seen' };
        }
        var btn = '';
        if (L.act === 'seen') btn = '<button type="button" class="pg-pva-b q" data-pva-act="seen">' + esc(_sdT('Got it')) + '</button>';
        else if (L.act === 'retry') btn = '<button type="button" class="pg-pva-b q" data-pva-act="retry">' + esc(_sdT('Try again')) + '</button>';
        else btn = '<button type="button" class="pg-pva-x" data-pva-act="close" aria-label="' + esc(_sdT('Close')) + '">' + svg(IC.x, 12) + '</button>';
        return '<div class="pg-pva-ln' + (L.tone ? ' ' + L.tone : '') + '" role="status">' + svg(L.tone === 'warn' || L.tone === 'bad' ? IC.dot : IC.eye) +
            '<span>' + esc(L.text) + '</span>' + btn + '</div>';
    }

    function rowHtml(attrs, on, main, detail, second) {
        return '<button type="button" class="pg-pva-row' + (on ? ' on' : '') + '" role="menuitemradio" aria-checked="' + (on ? 'true' : 'false') + '" ' + attrs + '>' +
            '<span class="pg-pva-tk">' + (on ? svg(IC.tick) : '') + '</span>' +
            '<span>' + esc(main) + (second ? '<small>' + esc(second) + '</small>' : '') + '</span>' + (detail ? '<em>' + esc(detail) + '</em>' : '') + '</button>';
    }

    function sourceCaption(source) {
        return source === 'product' ? _sdT('Products') : source === 'event' ? _sdT('Events') : _sdT('Form submissions');
    }

    function noRecordLine(sid, source) {
        var name = String(((K._sharedCache || {})[sid] || {}).name || '') || sourceCaption(source);
        return source === 'product' ? _sdT('“{var:1}” has no product yet.', [name])
            : source === 'event' ? _sdT('“{var:1}” has no event yet.', [name])
            : _sdT('“{var:1}” has no submission yet.', [name]);
    }

    function widgetName(sid) {
        var s = S.setups[sid] || {};
        return String(((K._sharedCache || {})[sid] || {}).name || '') || sourceCaption(s.source);
    }

    function recordRowsHtml() {
        var sid = S.sid, s = S.setups[sid] || {}, f = S.find[sid] || {};
        var drawn = parseInt(s.record, 10) || 0, def = S.defaults[sid];
        if (f.busy && !f.rows) return '<div class="pg-pva-note">' + esc(_sdT('Searching…')) + '</div>';
        if (f.failed) return '<div class="pg-pva-note">' + esc(_sdT('The records could not be read. Try again in a moment.')) + '</div>';
        var rows = f.rows || [];
        if (!rows.length) {
            return '<div class="pg-pva-note">' + esc(f.q ? _sdT('No record matches “{var:1}”.', [f.q]) : noRecordLine(sid, s.source)) + '</div>';
        }
        var h = rows.map(function (r) {
            return rowHtml('data-pva-rec="' + esc(String(r.id)) + '"', String(r.id) === String(drawn), String(r.label || ('#' + r.id)),
                String(r.id) === String(def) ? _sdT('shown by default') : '', '');
        }).join('');
        if (f.busy) h += '<div class="pg-pva-note">' + esc(_sdT('Searching…')) + '</div>';
        else if (!f.q && rows.length >= PAGE_ROWS) h += '<div class="pg-pva-note">' + esc(_sdT('Type to find the others.')) + '</div>';
        return h;
    }

    function recordMenuHtml() {
        var sids = recordSids(), s = S.setups[S.sid] || {}, f = S.find[S.sid] || {};
        var h = '';
        if (sids.length > 3) {
            h += '<select class="pg-pva-sel" data-pva-widget aria-label="' + esc(_sdT('Widget')) + '">' + sids.map(function (sid) {
                return '<option value="' + esc(sid) + '"' + (String(sid) === String(S.sid) ? ' selected' : '') + '>' + esc(widgetName(sid)) + '</option>';
            }).join('') + '</select>';
        } else if (sids.length > 1) {
            h += '<div class="pg-pva-seg" role="tablist">' + sids.map(function (sid) {
                return '<button type="button" role="tab" data-pva-widget="' + esc(sid) + '" aria-selected="' + (String(sid) === String(S.sid) ? 'true' : 'false') + '"' +
                    (String(sid) === String(S.sid) ? ' class="on"' : '') + '>' + esc(widgetName(sid)) + '</button>';
            }).join('') + '</div>';
        }
        h += '<label class="pg-pva-find">' + svg(IC.search, 13) +
            '<input type="search" data-pva-q value="' + esc(f.q || '') + '" placeholder="' + esc(_sdT('Search by name')) + '" aria-label="' + esc(_sdT('Search by name')) + '" autocomplete="off"></label>';
        h += '<div class="pg-pva-cap">' + esc(sourceCaption(s.source)) + '</div>';
        return h + '<div data-pva-list>' + recordRowsHtml() + '</div>';
    }

    function visitorMenuHtml() {
        var c = choice();
        var h = rowHtml('data-pva-who="signed_out"', c.who === 'signed_out', _sdT('Signed out'), '', '') +
            rowHtml('data-pva-who="signed_in"', c.who === 'signed_in', _sdT('Signed in'), '', _sdT('{var:1}, the sample account', [_sdT('Alex Morgan')]));
        if (S.audience) {
            h += '<div class="pg-pva-cap">' + esc(_sdT('Visit')) + '</div>' +
                rowHtml('data-pva-visit="first_visit"', c.visit === 'first_visit', _sdT('First visit'), '', '') +
                rowHtml('data-pva-visit="returning"', c.visit === 'returning', _sdT('Returning'), '', '');
        }
        if (c.who !== 'signed_out') {
            h += '<div class="pg-pva-note">' + svg(IC.lock, 12) + esc(_sdT('The address, e-mail and phone of a real customer are masked.')) + '</div>';
        }
        return h;
    }

    function caseMenuHtml() {
        var c = choice(), names = caseNames();
        return pageCases().map(function (k) {
            return rowHtml('data-pva-case="' + k + '"', c.case === k, names[k][0], '', names[k][1]);
        }).join('');
    }

    // The narrow window's sheet: a row per chooser, or one chooser's list
    // with a back arrow (S.sheet).
    function sheetHtml() {
        var c = choice(), o = offer(), h = '<div class="pg-pva-grab"></div>';
        if (S.sheet) {
            var title = S.sheet === 'record' ? _sdT('Record') : S.sheet === 'visitor' ? _sdT('Visitor') : _sdT('Case');
            h += '<div class="pg-pva-head"><button type="button" class="pg-pva-x" data-pva-act="back" aria-label="' + esc(_sdT('Back')) + '">' + svg(IC.back, 12) + '</button>' + esc(title) + '</div>';
            return h + (S.sheet === 'record' ? recordMenuHtml() : S.sheet === 'visitor' ? visitorMenuHtml() : caseMenuHtml());
        }
        var row = function (name, label, value, set) {
            return '<button type="button" class="pg-pva-row" data-pva-sheet="' + name + '"' + (set ? ' aria-disabled="true"' : '') + '>' +
                '<span class="pg-pva-tk"></span><span>' + esc(label) + '<small>' + esc(value) + '</small></span>' + svg(IC.caret, 10) + '</button>';
        };
        if (o.record) h += row('record', _sdT('Record'), recordValue(c), recordSet(c));
        if (o.visitor) h += row('visitor', _sdT('Visitor'), visitorValue(c), false);
        if (o.case) h += row('case', _sdT('Case'), caseNames()[c.case][0], false);
        if (!isDefault(c)) {
            h += '<div class="pg-pva-rule"></div><button type="button" class="pg-pva-row" data-pva-act="reset"><span class="pg-pva-tk">' + svg(IC.reset, 13) + '</span><span>' + esc(_sdT('Reset')) + '</span></button>';
        }
        return h;
    }

    function menuHtml() {
        if (!S.menu) return '';
        if (S.menu === 'sheet') {
            return '<div class="pg-pva-dim" data-pva-act="shut"></div><div class="pg-pva-sheet" role="dialog" aria-label="' + esc(_sdT('Preview as')) + '">' + sheetHtml() + '</div>';
        }
        var body = S.menu === 'record' ? recordMenuHtml() : S.menu === 'visitor' ? visitorMenuHtml() : caseMenuHtml();
        return '<div class="pg-pva-pop' + (S.menu === 'record' ? ' rec' : '') + '" role="menu" data-pva-pop>' + body + '</div>';
    }

    function markup() {
        return barHtml() + lineHtml() + menuHtml();
    }

    // The bar into a Preview document's markup (the core's _sdPvAsInject()),
    // so a reload shows it at once and the page never jumps.
    function inject(html) {
        if (!shown()) return html;
        var add = '<style id="pg-pva-css">' + CSS + '</style><div id="pg-pva">' + markup() + '</div>';
        var m = /<\/body\s*>(?![\s\S]*<\/body\s*>)/i.exec(html);
        return m ? html.slice(0, m.index) + add + html.slice(m.index) : html + add;
    }

    // Draws the bar again in the bound document, keeping the focus on the
    // control that had it, and the page pushed down by the bar's height.
    function render() {
        var d = S.doc;
        if (!d || !d.body) return;
        var root = d.getElementById('pg-pva');
        if (!shown()) {
            if (root) root.parentNode.removeChild(root);
            var css = d.getElementById('pg-pva-css');
            if (css) css.parentNode.removeChild(css);
            d.documentElement.style.removeProperty('--pg-pva-h');
            S.root = null;
            return;
        }
        if (!d.getElementById('pg-pva-css')) {
            var st = d.createElement('style');
            st.id = 'pg-pva-css';
            st.textContent = CSS;
            (d.head || d.body).appendChild(st);
        }
        if (!root) {
            root = d.createElement('div');
            root.id = 'pg-pva';
            d.body.appendChild(root);
        }
        if (root !== S.root) bind(root);
        var a = d.activeElement, key = '';
        if (a && root.contains(a)) key = focusKey(a);
        var caret = (a && a.hasAttribute && a.hasAttribute('data-pva-q')) ? a.selectionStart : null;
        root.innerHTML = markup();
        // Too narrow for the choosers: the one button.
        var bar = root.querySelector('.pg-pva-bar');
        if (!S.narrow && bar && bar.scrollWidth > bar.clientWidth + 1) {
            S.narrow = true;
            if (S.menu && S.menu !== 'sheet') { S.sheet = S.menu; S.menu = 'sheet'; }
            root.innerHTML = markup();
        }
        place(root);
        var top = root.querySelector('.pg-pva-bar');
        var line = root.querySelector('.pg-pva-ln');
        d.documentElement.style.setProperty('--pg-pva-h', ((top ? top.offsetHeight : 40) + (line ? line.offsetHeight : 0)) + 'px');
        if (key) {
            var el = root.querySelector(key);
            if (el) {
                el.focus();
                if (caret !== null && el.setSelectionRange) { try { el.setSelectionRange(caret, caret); } catch (e) {} }
            }
        }
    }

    function focusKey(el) {
        var names = ['data-pva-q', 'data-pva-open', 'data-pva-rec', 'data-pva-who', 'data-pva-visit', 'data-pva-case', 'data-pva-widget', 'data-pva-sheet', 'data-pva-act'];
        for (var i = 0; i < names.length; i++) {
            if (el.hasAttribute(names[i])) {
                var v = el.getAttribute(names[i]);
                return '[' + names[i] + (v ? '="' + String(v).replace(/["\\]/g, '') + '"' : '') + ']';
            }
        }
        return '';
    }

    // The open menu under its chooser, kept inside the window.
    function place(root) {
        var pop = root.querySelector('[data-pva-pop]');
        var btn = root.querySelector('[data-pva-open="' + S.menu + '"]');
        if (!pop || !btn) return;
        var w = S.doc.documentElement.clientWidth;
        var left = Math.max(8, Math.min(btn.getBoundingClientRect().left, w - pop.offsetWidth - 8));
        pop.style.left = left + 'px';
        pop.style.top = (root.querySelector('.pg-pva-bar').offsetHeight + 2) + 'px';
    }

    // ── Choosing ──
    function choose(change) {
        var c = choice();
        change(c);
        S.drawing = true;
        S.line = (S.line && S.line.act === 'retry') ? null : S.line;
        K._sdPvAsChoose(c);
        render();
    }

    function openMenu(name, focusFirst) {
        if (name === 'record') {
            var sids = recordSids();
            if (!S.sid || sids.indexOf(String(S.sid)) === -1) S.sid = sids[0];
            loadRecords(S.sid, (S.find[S.sid] || {}).q || '');
        }
        S.menu = name;
        render();
        var root = S.root;
        if (!root) return;
        var first = root.querySelector('[data-pva-q]') || root.querySelector('.pg-pva-row.on') || root.querySelector('.pg-pva-row');
        if (first && focusFirst !== false) first.focus();
    }

    function closeMenu() {
        var was = S.menu;
        S.menu = '';
        S.sheet = '';
        render();
        if (S.root && was) {
            var b = S.root.querySelector('[data-pva-open="' + was + '"]') || S.root.querySelector('[data-pva-open]');
            if (b) b.focus();
        }
    }

    // The records of widget `sid` whose name holds q, after a short pause
    // while typing; a newer search replaces the one on its way.
    function loadRecords(sid, q, typed) {
        var f = S.find[sid] = S.find[sid] || {};
        if (!typed && f.rows && f.q === q) return;
        f.q = q;
        f.busy = true;
        f.failed = false;
        clearTimeout(S.timer);
        var ask = function () {
            var mine = f.ask = {};
            K._sdPvAsRecords(sid, q).then(function (rows) {
                if (f.ask !== mine) return;
                f.busy = false;
                f.failed = !rows;
                f.rows = rows || [];
                var l = S.labels[sid] = S.labels[sid] || {};
                f.rows.forEach(function (r) { l[r.id] = String(r.label || ''); });
                drawList();
            });
        };
        if (typed) S.timer = setTimeout(ask, 300); else ask();
    }

    // Only the list of the "Record" menu: the search field keeps its caret.
    function drawList() {
        var list = S.root && S.root.querySelector('[data-pva-list]');
        if (list) list.innerHTML = recordRowsHtml();
        else if (S.root) render();
        var bar = S.root && S.root.querySelector('.pg-pva-bar');
        if (bar && !S.menu) render();
    }

    function act(name) {
        if (name === 'reset') {
            S.menu = '';
            S.sheet = '';
            choose(function (c) { c.case = 'typical'; c.who = 'signed_out'; c.visit = 'first_visit'; c.records = {}; c.labels = {}; });
        } else if (name === 'seen') {
            try { localStorage.setItem(SEEN_KEY, '1'); } catch (e) {}
            render();
        } else if (name === 'close') {
            S.line = null;
            render();
        } else if (name === 'retry') {
            S.line = null;
            choose(function () {});
        } else if (name === 'back') {
            S.sheet = '';
            render();
        } else if (name === 'shut') {
            closeMenu();
        }
    }

    function onClick(e) {
        var t = e.target && e.target.closest ? e.target.closest('[data-pva-open],[data-pva-act],[data-pva-rec],[data-pva-who],[data-pva-visit],[data-pva-case],[data-pva-widget],[data-pva-sheet]') : null;
        if (!t || t.tagName === 'SELECT') return;
        e.preventDefault();
        if (t.getAttribute('aria-disabled') === 'true') return;
        var c;
        if (t.hasAttribute('data-pva-open')) {
            var name = t.getAttribute('data-pva-open');
            if (S.menu === name) closeMenu(); else openMenu(name);
        } else if (t.hasAttribute('data-pva-act')) {
            act(t.getAttribute('data-pva-act'));
        } else if (t.hasAttribute('data-pva-sheet')) {
            S.sheet = t.getAttribute('data-pva-sheet');
            if (S.sheet === 'record') { var sids = recordSids(); if (!S.sid || sids.indexOf(String(S.sid)) === -1) S.sid = sids[0]; loadRecords(S.sid, ''); }
            render();
        } else if (t.hasAttribute('data-pva-widget')) {
            S.sid = t.getAttribute('data-pva-widget');
            loadRecords(S.sid, (S.find[S.sid] || {}).q || '');
            render();
        } else if (t.hasAttribute('data-pva-rec')) {
            var id = parseInt(t.getAttribute('data-pva-rec'), 10) || 0, sid = S.sid;
            closeMenu();
            choose(function (ch) {
                if (id === parseInt(S.defaults[sid], 10)) { delete ch.records[sid]; delete ch.labels[sid]; return; }
                ch.records[sid] = id;
                ch.labels[sid] = rowLabel(sid, id);
            });
        } else if (t.hasAttribute('data-pva-who')) {
            c = t.getAttribute('data-pva-who');
            closeMenu();
            if (choice().who !== c) choose(function (ch) { ch.who = c; });
        } else if (t.hasAttribute('data-pva-visit')) {
            // A second choice beside the rows above: the menu stays open.
            c = t.getAttribute('data-pva-visit');
            if (choice().visit !== c) choose(function (ch) { ch.visit = c; });
        } else if (t.hasAttribute('data-pva-case')) {
            c = t.getAttribute('data-pva-case');
            closeMenu();
            if (choice().case !== c) choose(function (ch) { ch.case = c; });
        }
    }

    function onKey(e) {
        var t = e.target, root = S.root;
        if (!root) return;
        if (e.key === 'Escape' && S.menu) { e.preventDefault(); closeMenu(); return; }
        if (t.hasAttribute && t.hasAttribute('data-pva-open') && !S.menu && (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            if (t.getAttribute('aria-disabled') !== 'true') openMenu(t.getAttribute('data-pva-open'));
            return;
        }
        if (!S.menu || (e.key !== 'ArrowDown' && e.key !== 'ArrowUp' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight')) return;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            // Left and Right change the widget in the segmented row.
            if (S.menu !== 'record' && S.sheet !== 'record') return;
            var sids = recordSids();
            if (sids.length < 2 || sids.length > 3) return;
            e.preventDefault();
            var at = sids.indexOf(String(S.sid)) + (e.key === 'ArrowRight' ? 1 : -1);
            S.sid = sids[(at + sids.length) % sids.length];
            loadRecords(S.sid, (S.find[S.sid] || {}).q || '');
            render();
            return;
        }
        var rows = Array.prototype.slice.call(root.querySelectorAll('.pg-pva-row'));
        if (!rows.length) return;
        e.preventDefault();
        var i = rows.indexOf(t);
        i = (i === -1) ? (e.key === 'ArrowDown' ? 0 : rows.length - 1) : (i + (e.key === 'ArrowDown' ? 1 : -1) + rows.length) % rows.length;
        rows[i].focus();
    }

    function onInput(e) {
        var t = e.target;
        if (t.hasAttribute && t.hasAttribute('data-pva-q')) {
            loadRecords(S.sid, String(t.value || '').trim(), true);
            drawList();
        } else if (t.hasAttribute && t.hasAttribute('data-pva-widget')) {
            S.sid = t.value;
            loadRecords(S.sid, (S.find[S.sid] || {}).q || '');
            render();
        }
    }

    function bind(root) {
        S.root = root;
        root.addEventListener('click', onClick);
        root.addEventListener('keydown', onKey);
        root.addEventListener('input', onInput);
        root.addEventListener('change', onInput);
    }

    // A press on the page closes the open menu; a resize measures the bar
    // again.
    function bindDoc(d, w) {
        d.addEventListener('mousedown', function (e) {
            if (S.menu && S.root && !S.root.contains(e.target)) closeMenu();
        });
        var frame = 0;
        w.addEventListener('resize', function () {
            if (frame) return;
            frame = w.requestAnimationFrame(function () {
                frame = 0;
                if (S.narrow && S.menu === 'sheet' && !S.sheet) S.menu = '';
                S.narrow = false;
                render();
            });
        });
    }

    // The core loaded a new Preview document (_sdShowPreview()): binds to
    // it once it has loaded. A navigation leaves no callback that survives
    // into the next document, so it is polled for.
    function attach(w) {
        S.win = w;
        clearTimeout(S.poll);
        var tries = 0;
        (function poll() {
            if (!w || w.closed || ++tries > 200) return;
            var d = null;
            try { d = w.document; } catch (e) { return; }
            if (!d || d === S.doc || d.readyState === 'loading' || String(d.URL || '').indexOf('blob:') !== 0) {
                S.poll = setTimeout(poll, 30);
                return;
            }
            S.doc = d;
            S.root = null;
            bindDoc(d, w);
            render();
        })();
    }

    // A preview_widgets answer (the core's _sdPvAsAnswered()): what was
    // drawn and what the page offers. A kept record the person may no
    // longer open, or that is gone, came back as the default: the line says
    // so and the choice forgets it.
    function answer(w, setups, audience) {
        S.setups = setups || S.setups || {};
        S.audience = !!audience;
        S.drawing = false;
        var c = choice(), gone = '', changed = false;
        Object.keys(S.setups).forEach(function (sid) {
            var s = S.setups[sid] || {};
            if (!s.source) return;
            var want = parseInt(c.records[sid], 10) || 0;
            if (!want && !recordSet(c) && c.case !== 'sold_out') S.defaults[sid] = parseInt(s.record, 10) || 0;
            if (want && !recordSet(c) && c.case !== 'sold_out' && (parseInt(s.record, 10) || 0) !== want) {
                gone = String(c.labels[sid] || ('#' + want));
                delete c.records[sid];
                delete c.labels[sid];
                changed = true;
            }
        });
        Object.keys(c.records).forEach(function (sid) {
            if (!S.setups[sid] || !S.setups[sid].source) { delete c.records[sid]; delete c.labels[sid]; changed = true; }
        });
        if (changed) K._sdPvAsChoose(c, true);
        if (gone) S.line = { tone: 'warn', text: _sdT('“{var:1}” is no longer there. The default record is shown.', [gone]), act: 'close' };
        else if (S.line && S.line.act === 'retry') S.line = null;
        // The label of the default record, for the chooser's value.
        var sids = recordSids();
        if (sids.length && !S.find[sids[0]]) loadRecords(sids[0], '');
        if (w && w !== S.win) { S.doc = null; attach(w); return; }
        if (!S.doc && w) { attach(w); return; }
        try { if (S.win && S.win.document !== S.doc) { attach(S.win); return; } } catch (e) {}
        render();
    }

    // The draw the bar asked for failed: the last page stays.
    function failed() {
        S.drawing = false;
        S.line = { tone: 'bad', text: _sdT('This case could not be drawn. The page shows the last one that was.'), act: 'retry' };
        render();
    }

    return { answer: answer, attach: attach, failed: failed, inject: inject };
});
