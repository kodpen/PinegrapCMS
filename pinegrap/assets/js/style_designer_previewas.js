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
 * "Check all cases" (the "Case" menu's last row, the narrow sheet and the
 * command palette) draws each case of the page for each visitor it has a
 * rule for in the Preview's own window, measures each at the window's
 * width and lists the cases where a text leaves its box, a picture is
 * missing or a widget draws nothing. It writes nothing and keeps nothing:
 * the list goes with the window.
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
    // the bar ({tone, text, act}) or null; narrow: the one-button form;
    // run: "Check all cases" (null when its list is closed); marks: the
    // places of an opened row, outlined once its case is drawn.
    var S = {
        win: null, doc: null, root: null, setups: {}, audience: false, defaults: {}, labels: {},
        menu: '', sheet: '', sid: 0, find: {}, drawing: false, line: null, narrow: false, timer: 0, poll: 0,
        run: null, marks: null, opening: null
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

    // ── Check all cases: what is drawn and what counts (pure) ──
    // Each case of `cases` for each visitor the page has a rule for: signed
    // out always; signed in and returning where the page has an audience
    // condition (the server takes one visitor). Nothing for a page with one.
    function checkRuns(cases, audience) {
        var who = [{ who: 'signed_out', visit: 'first_visit' }];
        if (audience) who.push({ who: 'signed_in', visit: 'first_visit' }, { who: 'signed_out', visit: 'returning' });
        var runs = [];
        who.forEach(function (v) {
            CASES.forEach(function (k) {
                if (cases.indexOf(k) !== -1) runs.push({ case: k, who: v.who, visit: v.visit });
            });
        });
        return runs.length > 1 ? runs : [];
    }

    // Whether a block holding text lets it out: wider than its box, or past
    // the window's right edge when it starts inside it, or cut at the bottom
    // of a box whose height is held. A scroller, a cut with an ellipsis and
    // an inline box (its block is measured) are not findings; a pixel of
    // rounding is none either. m: {display, overflowX, overflowY,
    // textOverflow, sw, cw, sh, ch, left, right, viewW}.
    function textLeaves(m) {
        if (m.display === 'inline' || m.display === 'none' || m.display === 'contents') return false;
        if (m.cw <= 0 && m.ch <= 0) return false;
        var scrolls = function (o) { return o === 'auto' || o === 'scroll'; };
        if (scrolls(m.overflowX) || m.textOverflow === 'ellipsis') return (m.overflowY === 'hidden' || m.overflowY === 'clip') && m.sh > m.ch + 1;
        if (m.sw > m.cw + 1) return true;
        if (m.left < m.viewW && m.right > m.viewW + 1) return true;
        return (m.overflowY === 'hidden' || m.overflowY === 'clip') && m.sh > m.ch + 1;
    }

    // Whether a picture is missing: shown with no address, or loaded and
    // its file could not be read. One still waiting (lazy or loading) is
    // not judged. m: {src, complete, natural, lazy, shown}.
    function pictureMissing(m) {
        if (!m.shown) return false;
        if (!m.src) return true;
        if (!m.complete) return false;
        return m.natural === 0;
    }

    // The list's head, the line under it and its foot. r: {total, done,
    // found, busy, stopped, width}.
    function checkHead(r) {
        var head, sub;
        if (r.busy) {
            head = r.found === 1 ? _sdT('{var:1} case to look at so far', [r.found]) : _sdT('{var:1} cases to look at so far', [r.found]);
            sub = _sdT('Checking {var:1} of {var:2}…', [Math.min(r.done + 1, r.total), r.total]);
        } else {
            head = r.stopped ? _sdT('Stopped after {var:1} of {var:2}.', [r.done, r.total]) : _sdT('{var:1} of {var:2} cases to look at', [r.found, r.total]);
            sub = _sdT('A row opens its case.');
        }
        return { head: head, sub: sub, foot: r.busy ? _sdT('Checking at {var:1}px wide.', [r.width]) : _sdT('Checked at {var:1}px wide.', [r.width]) };
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
        dot: '<circle cx="8" cy="8" r="4"/>',
        list: '<path d="M2.5 3.5h1.5v1.5H2.5zm3 .1h8v1.3h-8zM2.5 7.3h1.5v1.5H2.5zm3 .1h8v1.3h-8zm-3 3.7h1.5v1.5H2.5zm3 .1h8v1.3h-8z"/>'
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
        '#pg-pva .pg-pva-row{display:flex;align-items:center;gap:9px;width:100%;min-height:30px;padding:4px 8px;border-radius:8px;text-align:left}' +
        '.pg-pva-row>span{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis}.pg-pva-row em{font-style:normal;color:#8c96a4}' +
        '.pg-pva-row small{display:block;color:#8c96a4;white-space:normal}' +
        '.pg-pva-row:hover,.pg-pva-row:focus{background:#2a313b;outline:0}.pg-pva-row.on{background:rgba(91,155,255,.14)}' +
        '#pg-pva .pg-pva-tk{flex:none;width:18px;color:#5b9bff}' +
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
        '.pg-pva-ck{position:fixed;top:calc(var(--pg-pva-h,40px) + 8px);right:8px;width:300px;max-height:calc(100vh - var(--pg-pva-h,40px) - 16px);display:flex;flex-direction:column;border-radius:12px;background:#1f242c;box-shadow:inset 0 0 0 1px #343c48,0 14px 34px rgba(0,0,0,.55)}' +
        '.pg-pva-ck.left{right:auto;left:8px}' +
        '.pg-pva-ck.nar{top:auto;left:0;right:0;bottom:0;width:auto;max-height:50vh;border-radius:16px 16px 0 0}' +
        '.pg-pva-ckh{display:flex;align-items:flex-start;gap:8px;padding:10px 10px 8px 14px;border-bottom:1px solid #2a313b}' +
        '.pg-pva-ckh>div{flex:1}.pg-pva-ckh b{display:block;font-weight:600;color:#e6edf3}.pg-pva-ckh small{display:block;color:#8c96a4}' +
        '.pg-pva-ckl{flex:1;overflow:auto;padding:6px}' +
        '.pg-pva-ckr{display:flex;align-items:flex-start;gap:6px;border-radius:8px;box-shadow:inset 3px 0 0 transparent}' +
        '.pg-pva-ckr.on{box-shadow:inset 3px 0 0 #f5b54a;background:#262c35}' +
        '#pg-pva .pg-pva-ckr>button:first-child{flex:1;min-width:0;display:block;padding:7px 8px 7px 10px;border-radius:8px;text-align:left}' +
        '.pg-pva-ckr>button:first-child:hover,.pg-pva-ckr>button:first-child:focus{background:#2a313b;outline:0}' +
        '.pg-pva-ckr>button[aria-disabled="true"]{cursor:default}' +
        '.pg-pva-ckr b{display:block;font-weight:550;color:#e6edf3}' +
        '.pg-pva-ckr span{display:flex;align-items:center;gap:6px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
        '.pg-pva-ckr span svg{flex:none;color:#f5b54a}.pg-pva-ckr span.bad svg{color:#ff6b6b}.pg-pva-ckr em{font-style:normal;color:#8c96a4;overflow:hidden;text-overflow:ellipsis}' +
        '.pg-pva-ckr .pg-pva-b{margin:6px 6px 0 0}' +
        '.pg-pva-ckf{display:flex;align-items:center;gap:8px;padding:8px 10px 10px 14px;border-top:1px solid #2a313b;color:#8c96a4}.pg-pva-ckf>span{flex:1}' +
        '#pg-pva-hl{position:absolute;left:0;top:0;width:0;height:0;z-index:2147483645;pointer-events:none}' +
        '#pg-pva-hl>div{position:absolute;border-radius:3px;box-shadow:0 0 0 2px #f5b54a,0 0 0 5px rgba(245,181,74,.28)}' +
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
            (set ? ' aria-disabled="true"' + (running() ? '' : ' title="' + esc(_sdT('The case chooses the record')) + '"') : '') + '>' +
            '<small>' + esc(label) + '</small><b>' + esc(value) + '</b>' + svg(IC.caret, 10) + '</button>';
    }

    function running() { return !!(S.run && S.run.busy); }

    function barHtml() {
        var busy = running(), c = busy ? S.run.cur : choice(), o = offer();
        var h = '<div class="pg-pva-bar" role="toolbar" aria-label="' + esc(_sdT('Preview as')) + '">';
        if (S.narrow) {
            var n = changedCount(c);
            h += '<button type="button" class="pg-pva-ch' + (n ? ' on' : '') + (S.menu ? ' open' : '') + '" data-pva-open="sheet" aria-haspopup="dialog">' +
                svg(IC.eye) + '<b>' + esc(_sdT('Preview as')) + '</b>' + (n ? '<span class="pg-pva-n">' + n + '</span>' : '') + svg(IC.caret, 10) + '</button>';
        } else {
            h += '<span class="pg-pva-ttl">' + svg(IC.eye) + esc(_sdT('Preview as')) + '</span>';
            // While a run draws, the choosers show the case on its way, dimmed.
            if (o.record) h += chooserHtml('record', _sdT('Record'), recordValue(c), Object.keys(c.records).length > 0, busy || recordSet(c));
            if (o.visitor) h += chooserHtml('visitor', _sdT('Visitor'), visitorValue(c), c.who !== 'signed_out' || c.visit !== 'first_visit', busy);
            if (o.case) h += chooserHtml('case', _sdT('Case'), caseNames()[c.case][0], c.case !== 'typical', busy);
        }
        h += '<span class="pg-pva-end">';
        if (busy) {
            h += '<span class="pg-pva-run" role="status"><span class="pg-pva-spin"></span>' + esc(_sdT('Checking {var:1} of {var:2}…', [Math.min(S.run.done + 1, S.run.runs.length), S.run.runs.length])) + '</span>' +
                '<button type="button" class="pg-pva-b q" data-pva-act="stop">' + esc(_sdT('Stop')) + '</button>';
        } else if (S.drawing) {
            h += '<span class="pg-pva-run" role="status"><span class="pg-pva-spin"></span>' + esc(_sdT('Drawing…')) + '</span>';
        }
        if (!busy && !isDefault(c) && !S.narrow) {
            h += '<button type="button" class="pg-pva-b" data-pva-act="reset">' + svg(IC.reset, 13) + esc(_sdT('Reset')) + '</button>';
        }
        // "Edit content here" (part 'recordedit'): the last thing of the bar.
        if (!S.narrow) h += K._sdRecEditHtml('bar');
        return h + '</span></div>';
    }

    function lineHtml() {
        var L = S.line;
        if (!L) {
            var seen = false;
            try { seen = localStorage.getItem(SEEN_KEY) === '1'; } catch (e) { seen = true; }
            if (K._sdTipsOff()) seen = true;
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
        }).join('') + checkRowHtml();
    }

    // "Check all cases" under a rule, last in the "Case" menu and the
    // narrow sheet; none on a page with one case.
    function checkRowHtml() {
        if (!checkRuns(pageCases(), S.audience).length) return '';
        return '<div class="pg-pva-rule"></div><button type="button" class="pg-pva-row" role="menuitem" data-pva-act="check"' + (running() ? ' aria-disabled="true"' : '') + '>' +
            '<span class="pg-pva-tk">' + svg(IC.list, 13) + '</span><span>' + esc(_sdT('Check all cases')) + '</span></button>';
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
        h += K._sdRecEditHtml('row');
        h += checkRowHtml();
        if (!isDefault(c)) {
            h += (checkRuns(pageCases(), S.audience).length ? '' : '<div class="pg-pva-rule"></div>') +
                '<button type="button" class="pg-pva-row" data-pva-act="reset"><span class="pg-pva-tk">' + svg(IC.reset, 13) + '</span><span>' + esc(_sdT('Reset')) + '</span></button>';
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

    // The list of "Check all cases": a panel over the page's right edge (a
    // sheet from the bottom in a narrow window), only the cases with a
    // finding; it opens with the first one.
    function listHtml() {
        var R = S.run;
        if (!R || (!R.results.length && !R.stopped)) return '';
        var found = R.results.filter(function (x) { return x.findings.length || x.failed; }).length;
        var t = checkHead({ total: R.runs.length, done: R.done, found: found, busy: R.busy, stopped: R.stopped, width: R.width });
        var names = caseNames(), kinds = {
            text: _sdT('A text leaves its box'), picture: _sdT('A picture is missing'), widget: _sdT('A widget draws nothing')
        };
        var h = '<div class="pg-pva-ck' + (S.narrow ? ' nar' : (R.left ? ' left' : '')) + '" role="region" aria-label="' + esc(_sdT('Check all cases')) + '" data-pva-ck>' +
            '<div class="pg-pva-ckh"><div><b>' + esc(t.head) + '</b><small>' + esc(t.sub) + '</small></div>' +
            '<button type="button" class="pg-pva-x" data-pva-act="unlist" aria-label="' + esc(_sdT('Close')) + '">' + svg(IC.x, 12) + '</button></div><div class="pg-pva-ckl">';
        R.results.forEach(function (x, i) {
            var run = R.runs[x.at];
            var title = names[run.case][0] + ' · ' + visitorValue(run);
            h += '<div class="pg-pva-ckr' + (R.open === i ? ' on' : '') + '"><button type="button" data-pva-res="' + i + '"' + (R.busy || x.failed ? ' aria-disabled="true"' : '') + '><b>' + esc(title) + '</b>';
            if (x.failed) {
                h += '<span class="bad">' + svg(IC.dot, 10) + esc(_sdT('Could not be drawn')) + '</span></button>' +
                    (R.busy ? '' : '<button type="button" class="pg-pva-b q" data-pva-retry="' + i + '">' + esc(_sdT('Try again')) + '</button>');
            } else {
                h += x.findings.slice(0, 3).map(function (f) {
                    return '<span>' + svg(IC.dot, 10) + esc(kinds[f.kind]) + (f.place ? ' <em>' + esc(f.place) + '</em>' : '') + '</span>';
                }).join('') + (x.findings.length > 3 ? '<span><em>' + esc(_sdT('+{var:1} more', [x.findings.length - 3])) + '</em></span>' : '') + '</button>';
            }
            h += '</div>';
        });
        h += '</div><div class="pg-pva-ckf"><span>' + esc(t.foot) + '</span>' +
            (R.busy ? '' : '<button type="button" class="pg-pva-b q" data-pva-act="check">' + esc(_sdT('Check again')) + '</button>') + '</div></div>';
        return h;
    }

    function markup() {
        return barHtml() + lineHtml() + menuHtml() + listHtml();
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
        // The document and the answer for "Edit content here".
        K._sdRecEditSync(d, S.win, S.setups);
        if (S.marks && S.marks.doc === d) outline();
    }

    function focusKey(el) {
        var names = ['data-pva-q', 'data-re-act', 'data-pva-open', 'data-pva-rec', 'data-pva-who', 'data-pva-visit', 'data-pva-case', 'data-pva-widget', 'data-pva-sheet', 'data-pva-res', 'data-pva-retry', 'data-pva-act'];
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
        } else if (name === 'check') {
            check();
        } else if (name === 'stop') {
            stop(true);
        } else if (name === 'unlist') {
            // Closed while it draws: the page as chosen again.
            var busy = running();
            if (busy) stop(false);
            S.run = null;
            clearMarks();
            if (busy) choose(function () {}); else render();
        }
    }

    function onClick(e) {
        var t = e.target && e.target.closest ? e.target.closest('[data-pva-open],[data-pva-act],[data-pva-rec],[data-pva-who],[data-pva-visit],[data-pva-case],[data-pva-widget],[data-pva-sheet],[data-pva-res],[data-pva-retry]') : null;
        if (!t || t.tagName === 'SELECT') return;
        e.preventDefault();
        if (t.getAttribute('aria-disabled') === 'true') return;
        var c;
        if (t.hasAttribute('data-pva-res')) {
            openResult(parseInt(t.getAttribute('data-pva-res'), 10) || 0);
        } else if (t.hasAttribute('data-pva-retry')) {
            retry(parseInt(t.getAttribute('data-pva-retry'), 10) || 0);
        } else if (t.hasAttribute('data-pva-open')) {
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
        // The list: Down and Up move through its rows, Escape closes it.
        var ck = t.closest ? t.closest('[data-pva-ck]') : null;
        if (ck && e.key === 'Escape') { e.preventDefault(); act('unlist'); return; }
        if (ck && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            var rs = Array.prototype.slice.call(ck.querySelectorAll('[data-pva-res]'));
            if (!rs.length) return;
            e.preventDefault();
            var k = rs.indexOf(t);
            k = (k === -1) ? 0 : (k + (e.key === 'ArrowDown' ? 1 : -1) + rs.length) % rs.length;
            rs[k].focus();
            return;
        }
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

    // Typing in the search; the widget select. A search field fires change
    // as it loses the focus too, which must not draw the list again under
    // the row taking the focus.
    function onInput(e) {
        var t = e.target;
        if (e.type === 'input' && t.hasAttribute && t.hasAttribute('data-pva-q')) {
            loadRecords(S.sid, String(t.value || '').trim(), true);
            drawList();
        } else if (e.type === 'change' && t.hasAttribute && t.hasAttribute('data-pva-widget')) {
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
            if (S.root && S.root.contains(e.target)) return;
            if (S.menu) closeMenu();
            // An outlined place stays until the next click.
            var hl = d.getElementById('pg-pva-hl');
            if (hl) hl.parentNode.removeChild(hl);
        });
        d.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !S.menu && S.run && !running() && !(S.root && S.root.contains(e.target))) act('unlist');
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
        if (w && w !== S.win) { S.doc = null; S.menu = ''; S.sheet = ''; S.run = null; S.marks = null; attach(w); return; }
        if (!S.doc && w) { attach(w); return; }
        try { if (S.win && S.win.document !== S.doc) { attach(S.win); return; } } catch (e) {}
        render();
    }

    // ── Check all cases: the run ──
    // R: {runs, queue (indices still to draw), done, busy, stopped, results
    // [{at, findings, failed}] in run order, width, cur (the choice drawn
    // now), token (a stop or a new run drops an answer on its way), open
    // (the opened row), left (the list at the left edge)}.
    function check() {
        if (running()) return;
        var runs = checkRuns(pageCases(), S.audience);
        S.menu = '';
        S.sheet = '';
        if (!runs.length || !S.win) { render(); return; }
        if (S.line && S.line.tone === 'ok') S.line = null;
        clearMarks();
        S.run = { runs: runs, queue: runs.map(function (r, i) { return i; }), done: 0, busy: true, stopped: false, results: [],
                  width: docWidth(), cur: runChoice(runs[0]), token: {}, open: -1, left: false };
        K._sdPvAsRunning(true);
        render();
        next(S.run.token);
    }

    // The document the Preview window holds now (the bar may not be bound
    // to it yet).
    function winDoc() {
        try { return S.win && !S.win.closed ? S.win.document : null; } catch (e) { return null; }
    }

    function docWidth() {
        try { return S.doc ? S.doc.documentElement.clientWidth : 0; } catch (e) { return 0; }
    }

    // The kept choice with a run's case and visitor: the chosen records stay.
    function runChoice(run) {
        var c = choice();
        c.case = run.case;
        c.who = run.who;
        c.visit = run.visit;
        return c;
    }

    function next(token) {
        var R = S.run;
        if (!R || R.token !== token) return;
        if (!R.queue.length) { finish(); return; }
        var at = R.queue[0];
        R.cur = runChoice(R.runs[at]);
        render();
        var done = function (findings, bad) {
            if (S.run !== R || R.token !== token) return;
            R.queue.shift();
            R.done = Math.min(R.done + 1, R.runs.length);
            R.results = R.results.filter(function (x) { return x.at !== at; });
            if (bad || findings.length) R.results.push({ at: at, findings: findings || [], failed: !!bad });
            R.results.sort(function (a, b) { return a.at - b.at; });
            next(token);
        };
        K._sdPvAsAsk(R.cur).then(function (ans) {
            if (S.run !== R || R.token !== token) return;
            if (!ans) { done([], true); return; }
            // Each widget between two marks, so one that draws nothing is found.
            var wrapped = {};
            Object.keys(ans.widgets).forEach(function (id) {
                wrapped[id] = '<!--pg-pva-w:' + id + '-->' + String(ans.widgets[id] == null ? '' : ans.widgets[id]) + '<!--/pg-pva-w-->';
            });
            var was = winDoc();
            if (!K._sdPvAsShow(K._sdPreviewDocHtml(wrapped, ans.raw))) { stop(false); return; }
            loaded(was).then(function (d) {
                if (S.run !== R || R.token !== token) return;
                if (!d) { done([], true); return; }
                R.width = d.documentElement.clientWidth;
                var found = [];
                try { found = measure(d); } catch (e) { done([], true); return; }
                done(found, false);
            });
        });
    }

    // The new Preview document once it and its pictures and fonts have
    // loaded (at most 8 s); null when the window went away.
    function loaded(was) {
        return new Promise(function (resolve) {
            var tries = 0;
            (function poll() {
                var d = null;
                try { d = S.win && !S.win.closed ? S.win.document : null; } catch (e) { d = null; }
                if (!d) { resolve(null); return; }
                var ready = d !== was && String(d.URL || '').indexOf('blob:') === 0 && d.readyState === 'complete';
                if (!ready && ++tries < 160) { setTimeout(poll, 50); return; }
                if (d === was || String(d.URL || '').indexOf('blob:') !== 0) { resolve(null); return; }
                var fonts = (d.fonts && d.fonts.ready) ? d.fonts.ready : Promise.resolve();
                var t = setTimeout(function () { resolve(d); }, 1500);
                fonts.then(function () {
                    S.win.requestAnimationFrame(function () { clearTimeout(t); resolve(d); });
                }, function () { clearTimeout(t); resolve(d); });
            })();
        });
    }

    // The findings of one drawn case: [{kind, place, path}], at most five
    // of a kind. The bar, the list and the outline are not the page's.
    function measure(d) {
        var w = d.defaultView, body = d.body, viewW = d.documentElement.clientWidth, out = [];
        var mine = function (el) { return !!(el.closest && el.closest('#pg-pva,#pg-pva-hl')); };
        var ranges = widgetRanges(d);
        var count = { text: 0, picture: 0, widget: 0 };
        var add = function (kind, el, place) {
            if (count[kind] >= 5) return;
            count[kind]++;
            out.push({ kind: kind, place: place, path: pathOf(el, body) });
        };
        // Widgets that draw nothing.
        ranges.forEach(function (r) {
            var box = r.range.getBoundingClientRect();
            if (box.width < 2 || box.height < 2) add('widget', r.start.parentElement || body, widgetName(r.id));
        });
        // Pictures.
        Array.prototype.forEach.call(body.querySelectorAll('img'), function (img) {
            if (mine(img)) return;
            var cs = w.getComputedStyle(img);
            var m = { src: img.currentSrc || img.getAttribute('src') || '', complete: img.complete, natural: img.naturalWidth,
                      lazy: img.loading === 'lazy', shown: img.getClientRects().length > 0 && cs.visibility !== 'hidden' };
            if (!pictureMissing(m)) return;
            var src = String(img.getAttribute('src') || '');
            var file = /^data:/i.test(src) ? '' : src.split(/[?#]/)[0].split('/').pop();
            add('picture', img, placeName(ranges, img, String(img.getAttribute('alt') || '').trim() || file));
        });
        // Texts: each block holding text of its own, the innermost kept.
        var blocks = [], seen = [];
        var walker = d.createTreeWalker(body, w.NodeFilter.SHOW_TEXT);
        for (var n = walker.nextNode(); n; n = walker.nextNode()) {
            if (!/\S/.test(n.nodeValue)) continue;
            var el = n.parentElement;
            if (!el || /^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE|TEXTAREA|OPTION)$/.test(el.tagName) || mine(el)) continue;
            while (el && el !== body && w.getComputedStyle(el).display === 'inline') el = el.parentElement;
            if (!el || el === body || seen.indexOf(el) !== -1) continue;
            seen.push(el);
            var cs2 = w.getComputedStyle(el);
            if (cs2.visibility === 'hidden') continue;
            var rect = el.getBoundingClientRect();
            if (textLeaves({ display: cs2.display, overflowX: cs2.overflowX, overflowY: cs2.overflowY, textOverflow: cs2.textOverflow,
                             sw: el.scrollWidth, cw: el.clientWidth, sh: el.scrollHeight, ch: el.clientHeight,
                             left: rect.left, right: rect.right, viewW: viewW })) blocks.push(el);
        }
        blocks.filter(function (el) {
            return !blocks.some(function (o) { return o !== el && el.contains(o); });
        }).forEach(function (el) {
            add('text', el, placeName(ranges, el, excerpt(el.textContent)));
        });
        return out;
    }

    // Each widget's markup between its two marks: [{id, start, range}].
    function widgetRanges(d) {
        var w = d.defaultView, out = [], open = null;
        var walker = d.createTreeWalker(d.body, w.NodeFilter.SHOW_COMMENT);
        for (var n = walker.nextNode(); n; n = walker.nextNode()) {
            var m = /^pg-pva-w:(\d+)$/.exec(n.nodeValue);
            if (m) { open = { id: m[1], start: n }; continue; }
            if (n.nodeValue === '/pg-pva-w' && open) {
                var r = d.createRange();
                r.setStartAfter(open.start);
                r.setEndBefore(n);
                out.push({ id: open.id, start: open.start, range: r });
                open = null;
            }
        }
        return out;
    }

    function excerpt(text) {
        var t = String(text || '').replace(/\s+/g, ' ').trim();
        return t.length > 40 ? t.slice(0, 39) + '…' : t;
    }

    // The name of a place: its words or file, and the widget it is in.
    function placeName(ranges, el, words) {
        var inW = '';
        ranges.some(function (r) {
            try { if (r.range.intersectsNode(el)) { inW = widgetName(r.id); return true; } } catch (e) {}
            return false;
        });
        if (!words) return inW;
        return inW ? _sdT('“{var:1}” in {var:2}', [words, inW]) : '“' + words + '”';
    }

    // An element's place from the body by element positions: the same in
    // the case drawn again without the widget marks (comments are not
    // counted).
    function pathOf(el, body) {
        var parts = [];
        while (el && el !== body && el.parentElement) {
            var i = 1, s = el;
            while ((s = s.previousElementSibling)) i++;
            parts.unshift(el.tagName.toLowerCase() + ':nth-child(' + i + ')');
            el = el.parentElement;
        }
        return parts.length ? 'body>' + parts.join('>') : 'body';
    }

    function finish() {
        var R = S.run;
        R.busy = false;
        K._sdPvAsRunning(false);
        if (!R.results.length && !R.stopped) {
            S.run = null;
            S.line = { tone: 'ok', text: _sdT('Every case looks right. {var:1} cases drawn.', [R.runs.length]), act: 'close' };
        }
        // The page as chosen again.
        choose(function () {});
    }

    // "Stop" keeps what was found so far; `kept` false when the window went
    // away or the list was closed.
    function stop(kept) {
        var R = S.run;
        if (!R || !R.busy) return;
        R.token = {};
        R.queue = [];
        R.stopped = true;
        if (!kept) { R.busy = false; K._sdPvAsRunning(false); return; }
        finish();
    }

    // "Try again" on a case that could not be drawn.
    function retry(i) {
        var R = S.run, x = R && R.results[i];
        if (!x || R.busy) return;
        R.queue = [x.at];
        R.done = Math.max(0, R.done - 1);
        R.busy = true;
        R.token = {};
        K._sdPvAsRunning(true);
        render();
        next(R.token);
    }

    // A row opens its case: the choosers change to it (kept as the page's
    // choice), the case is drawn as the run drew it, and its places are
    // outlined once that document is there.
    function openResult(i) {
        var R = S.run, x = R && R.results[i];
        if (!x || R.busy || x.failed) return;
        R.open = i;
        var c = runChoice(R.runs[x.at]), paths = x.findings.map(function (f) { return f.path; });
        var mine = S.opening = {};
        clearMarks();
        K._sdPvAsChoose(c, true);
        S.drawing = true;
        render();
        K._sdPvAsAsk(c).then(function (ans) {
            if (S.opening !== mine || S.run !== R) return;
            var was = winDoc();
            if (!ans || !K._sdPvAsShow(K._sdPreviewDocHtml(ans.widgets, ans.raw))) { failed(); return; }
            S.setups = ans.setups || S.setups;
            loaded(was).then(function (d) {
                if (S.opening !== mine) return;
                S.drawing = false;
                if (d) S.marks = { paths: paths, doc: d };
                render();
            });
        });
    }

    function clearMarks() {
        S.marks = null;
        try {
            var hl = S.doc && S.doc.getElementById('pg-pva-hl');
            if (hl) hl.parentNode.removeChild(hl);
        } catch (e) {}
    }

    // The opened row's places outlined in amber, as far as a text runs, and
    // the first scrolled into sight; the page itself is never changed. The
    // list moves to the left edge when the place lies under it.
    function outline() {
        var d = S.doc, w = S.win, paths = S.marks.paths;
        S.marks = null;
        if (!d || !w) return;
        var old = d.getElementById('pg-pva-hl');
        if (old) old.parentNode.removeChild(old);
        var hl = d.createElement('div');
        hl.id = 'pg-pva-hl';
        var first = null, sx = w.scrollX || 0, sy = w.scrollY || 0;
        paths.forEach(function (p) {
            var el = null;
            try { el = d.querySelector(p); } catch (e) { el = null; }
            if (!el) return;
            var r = el.getBoundingClientRect();
            var box = d.createElement('div');
            box.style.cssText = 'left:' + (r.left + sx) + 'px;top:' + (r.top + sy) + 'px;width:' + Math.max(r.width, el.scrollWidth) + 'px;height:' + Math.max(r.height, 4) + 'px';
            hl.appendChild(box);
            if (!first) first = r;
        });
        d.documentElement.appendChild(hl);
        if (!first) return;
        var barH = parseInt(w.getComputedStyle(d.documentElement).getPropertyValue('--pg-pva-h'), 10) || 40;
        w.scrollTo(sx, Math.max(0, first.top + sy - barH - 24));
        var left = !S.narrow && first.right > d.documentElement.clientWidth - 316;
        if (S.run && S.run.left !== left) { S.run.left = left; render(); }
    }

    // The draw the bar asked for failed: the last page stays.
    function failed() {
        S.drawing = false;
        S.line = { tone: 'bad', text: _sdT('This case could not be drawn. The page shows the last one that was.'), act: 'retry' };
        render();
    }

    // The line under the bar, from "Edit content here" (null clears it).
    function note(line) {
        S.line = line || null;
        render();
    }

    return { answer: answer, attach: attach, check: check, failed: failed, inject: inject, note: note, redraw: render };
});
