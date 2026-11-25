/**
 * Pinegrap - Enterprise Website Platform
 *
 * The pure rules of Compare's Look tab (window.PgCompareLook): the two
 * pictures of a page under a slider handle or side by side, at a desktop's
 * or a phone's width. No DOM, no request; the window itself is the
 * designer part `compare` (style_designer_compare.js).
 *
 * - liveTree(baseline): the stored tree out of a page's baseline (its
 *   tree's JSON, then "|" and the settings), null when there is none.
 * - prefs(text) / prefsText(prefs): the form and the width chosen last, as
 *   the browser keeps them; the handle's place is not kept.
 * - layout(state, prefs, narrow): what the window shows for a state of the
 *   change reader (PgCompareChanges.read()): one picture or two, the handle,
 *   which choosers are offered and the note over the picture.
 * - key(), clamp(), width(), scale(): the handle and the picture's size.
 * - side(), sideValue(), sideValues(), sameSide(): a side of the compare
 *   ("now", "live" or "rev:<no>") and the values of its chooser.
 * - foot(state, count, live): the code of the foot's sentence.
 * - withBase(html, base): a server-drawn page's addresses resolved against
 *   the site.
 * - icon(), sideOf(), handleFor(), align(), nameOf(), wordsOf(): a row of
 *   "What changed": its icon, the picture its place is on, the handle's
 *   place, the two pictures lined up on it, its names and its words.
 *
 * tests/designer_compare_look_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var WIDTHS = { desktop: 1280, phone: 390 };
    var FORMS = { slider: true, side: true };

    // A JSON value is never a prefix of a longer one, so the first "|" whose
    // prefix parses is where the tree ends: a "|" inside one of its texts
    // leaves the prefix open.
    function liveTree(baseline) {
        if (typeof baseline !== 'string' || baseline === '') return null;
        var at = baseline.indexOf('|');
        while (at !== -1) {
            var value;
            var ok = true;
            try { value = JSON.parse(baseline.slice(0, at)); } catch (e) { ok = false; }
            if (ok) return (value && typeof value === 'object') ? value : null;
            at = baseline.indexOf('|', at + 1);
        }
        return null;
    }

    function prefs(text) {
        var out = { form: 'slider', width: 'desktop' };
        var v = null;
        if (typeof text === 'string' && text !== '') {
            try { v = JSON.parse(text); } catch (e) { v = null; }
        }
        if (v && typeof v === 'object') {
            if (FORMS[v.form] === true) out.form = v.form;
            if (typeof v.width === 'string' && WIDTHS.hasOwnProperty(v.width)) out.width = v.width;
        }
        return out;
    }

    function prefsText(p) {
        var v = prefs(JSON.stringify({ form: p && p.form, width: p && p.width }));
        return JSON.stringify(v);
    }

    // `new`: the draft alone (the page was never on the site); `same`: one
    // picture; `unreadable` costs the list only. A narrow window has the
    // slider alone at a phone's width.
    function layout(state, p, narrow) {
        p = prefs(JSON.stringify(p || {}));
        var out = { form: p.form, width: p.width, only: '', handle: false, forms: true, widths: true, note: '' };
        if (narrow) {
            out.form = 'slider';
            out.width = 'phone';
            out.forms = false;
            out.widths = false;
        }
        if (state === 'new' || state === 'same') {
            out.only = (state === 'new') ? 'after' : 'before';
            out.form = 'slider';
            out.forms = false;
            out.note = state;
        }
        out.handle = (out.only === '' && out.form === 'slider');
        return out;
    }

    function clamp(pos) {
        var n = Number(pos);
        if (!isFinite(n)) return 50;
        return Math.max(0, Math.min(100, n));
    }

    // The handle's place after a key, null for a key that is not its own.
    function key(pos, name, shift) {
        var step = shift ? 10 : 2;
        if (name === 'ArrowLeft' || name === 'ArrowDown') return clamp(pos - step);
        if (name === 'ArrowRight' || name === 'ArrowUp') return clamp(pos + step);
        if (name === 'Home') return 0;
        if (name === 'End') return 100;
        return null;
    }

    function width(name) {
        return WIDTHS.hasOwnProperty(name) ? WIDTHS[name] : WIDTHS.desktop;
    }

    // Scaled down to the room, never up.
    function scale(room, w) {
        room = Number(room) || 0;
        w = Number(w) || 0;
        if (room <= 0 || w <= 0 || room >= w) return 1;
        return room / w;
    }

    function side(v) {
        if (v && typeof v === 'object') v = sideValue(v);
        if (v === 'now' || v === 'live') return { kind: v, no: 0 };
        var m = /^rev:(\d+)$/.exec(String(v || ''));
        if (m && parseInt(m[1], 10) > 0) return { kind: 'rev', no: parseInt(m[1], 10) };
        return null;
    }

    function sideValue(s) {
        if (!s || typeof s !== 'object') return '';
        if (s.kind === 'now' || s.kind === 'live') return s.kind;
        if (s.kind === 'rev' && (parseInt(s.no, 10) || 0) > 0) return 'rev:' + parseInt(s.no, 10);
        return '';
    }

    // The page now while it is open in a tab, the page on the site while it
    // is there, then every revision the list holds, newest first.
    function sideValues(revisions, hasNow, hasLive) {
        var out = [];
        if (hasNow) out.push('now');
        if (hasLive) out.push('live');
        (Array.isArray(revisions) ? revisions : []).forEach(function (r) {
            var v = sideValue({ kind: 'rev', no: r && r.revision_no });
            if (v && out.indexOf(v) === -1) out.push(v);
        });
        return out;
    }

    function sameSide(a, b) {
        return sideValue(a) !== '' && sideValue(a) === sideValue(b);
    }

    // `live` says the two sides are the page on the site and the draft.
    function foot(state, count, live) {
        if (state === 'same' || state === 'new' || state === 'unreadable') return state;
        return (live ? 'live_' : '') + ((count === 1) ? 'one' : 'many');
    }

    function escAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function withBase(html, base) {
        html = String(html || '');
        if (/<base\s/i.test(html)) return html;
        var tag = '<base href="' + escAttr(base) + '">';
        var m = /<head(\s[^>]*)?>/i.exec(html);
        if (!m) return tag + html;
        var at = m.index + m[0].length;
        return html.slice(0, at) + tag + html.slice(at);
    }

    // ── "What changed" ──
    var ICONS = {
        added: 'bi-plus-circle', removed: 'bi-dash-circle', picture: 'bi-image', words: 'bi-fonts',
        moved_up: 'bi-arrow-up', moved_down: 'bi-arrow-down', moved_into: 'bi-arrow-return-right',
        link: 'bi-link-45deg', look: 'bi-palette', other: 'bi-three-dots'
    };

    function icon(row) {
        row = row || {};
        return ICONS[row.code] || ICONS[row.kind] || ICONS.other;
    }

    // The picture a row's place is on: the draft for an added element, the
    // page on the site for a removed one, else both.
    function sideOf(row) {
        var list = (row && Array.isArray(row.changes)) ? row.changes : [];
        var one = list.length ? list[0].side : 'both';
        for (var i = 1; i < list.length; i++) if (list[i].side !== one) return 'both';
        return (one === 'after' || one === 'before') ? one : 'both';
    }

    // A place on one side only puts the handle at the end that shows it.
    function handleFor(side, pos) {
        if (side === 'after') return 0;
        if (side === 'before') return 100;
        return clamp(pos);
    }

    // The two pictures' shifts that put the element at the same height in
    // both, and that height; null when neither picture has it.
    function align(yBefore, yAfter) {
        var hasB = typeof yBefore === 'number' && isFinite(yBefore);
        var hasA = typeof yAfter === 'number' && isFinite(yAfter);
        if (!hasB && !hasA) return null;
        if (!hasB || !hasA) return { before: 0, after: 0, top: hasB ? yBefore : yAfter };
        var top = Math.max(yBefore, yAfter);
        return { before: top - yBefore, after: top - yAfter, top: top };
    }

    // An element named in an opened row: its kind and its own words.
    function nameOf(change) {
        change = change || {};
        var what = String(change.what || '');
        return change.words ? what + ' \u201C' + change.words + '\u201D' : what;
    }

    // The old and new words of a row of one text, null for any other row.
    function wordsOf(row) {
        var list = (row && Array.isArray(row.changes)) ? row.changes : [];
        if (list.length !== 1 || list[0].kind !== 'words') return null;
        return { before: String(list[0].before || ''), after: String(list[0].after || '') };
    }

    window.PgCompareLook = {
        liveTree: liveTree,
        prefs: prefs,
        prefsText: prefsText,
        layout: layout,
        clamp: clamp,
        key: key,
        width: width,
        scale: scale,
        side: side,
        sideValue: sideValue,
        sideValues: sideValues,
        sameSide: sameSide,
        foot: foot,
        withBase: withBase,
        icon: icon,
        sideOf: sideOf,
        handleFor: handleFor,
        align: align,
        nameOf: nameOf,
        wordsOf: wordsOf
    };
})();
