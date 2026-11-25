/**
 * Pinegrap - Enterprise Website Platform
 *
 * A grid's Columns and Rows in the designer's Advanced › Layout, as the
 * values of `grid-template-columns` and `grid-template-rows`: a count of
 * equal tracks, fixed sizes, "As many as fit" with a smallest width
 * (columns only), or a value typed another way, kept as it is. The part
 * `grid` (assets/js/style_designer_grid.js) writes the value per screen
 * size through window.PGSizeStyle.
 *
 *   PGGridTracks.CSS, MAX
 *   PGGridTracks.parse(value, axis)        // { mode, count, sizes, min, value }
 *   PGGridTracks.build(spec, axis)         // the CSS value, '' for Default, null refused
 *   PGGridTracks.pick(current, mode, axis) // the value a chosen mode starts with
 *   PGGridTracks.edit(current, field, text, axis) // the value after a field's change
 *   PGGridTracks.track(token)              // one track size as CSS, '' refused
 *
 * `axis` is 'columns' or 'rows'; `mode` '', 'equal', 'fixed', 'fit' or
 * 'typed'. Equal tracks are `minmax(0, 1fr)` so a long word does not widen
 * one column; "As many as fit" is `min(<width>, 100%)` so one column never
 * overflows a narrow screen.
 *
 * window.PGGridTracks in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var CSS = { columns: 'grid-template-columns', rows: 'grid-template-rows' };
    // The most tracks a count or a list of sizes takes.
    var MAX = 12;
    // What a mode starts with on an element that has no value.
    var START = { columns: { count: 3, size: '1fr' }, rows: { count: 2, size: 'auto' } };
    var START_COUNT_FIXED = 2;
    var START_MIN = '200px';

    var LEN = /^(?:\d*\.?\d+)(?:px|%|rem|em|vw|vh|ch)$/i;
    var FR = /^(?:\d*\.?\d+)fr$/i;
    var NUM = /^\+?(?:\d*\.?\d+)$/;
    var EQUAL = /^repeat\(\s*(\d+)\s*,\s*(?:minmax\(\s*0(?:px)?\s*,\s*1fr\s*\)|1fr)\s*\)$/i;
    var FIT = /^repeat\(\s*auto-fi(?:t|ll)\s*,\s*minmax\(\s*(?:min\(\s*([^,()]+?)\s*,\s*100%\s*\)|([^,()]+?))\s*,\s*1fr\s*\)\s*\)$/i;

    // A length (not fr): a bare number is pixels, 0 stays 0; '' refused.
    function length(s) {
        s = String(s == null ? '' : s).trim();
        if (NUM.test(s)) return +s === 0 ? '0' : s.replace(/^\+/, '') + 'px';
        return LEN.test(s) ? s.toLowerCase() : '';
    }

    // A size inside minmax(): a length, fr, auto or a content keyword.
    function inner(s) {
        s = String(s || '').trim();
        if (/^(auto|min-content|max-content)$/i.test(s)) return s.toLowerCase();
        if (FR.test(s)) return s.toLowerCase();
        return length(s);
    }

    function track(token) {
        var s = String(token == null ? '' : token).trim();
        if (!s) return '';
        var m = /^minmax\(\s*([^,()]+?)\s*,\s*([^,()]+?)\s*\)$/i.exec(s);
        if (m) {
            var a = inner(m[1]), b = inner(m[2]);
            return a && b && !FR.test(a) ? 'minmax(' + a + ', ' + b + ')' : '';
        }
        m = /^fit-content\(\s*([^,()]+?)\s*\)$/i.exec(s);
        if (m) {
            var f = length(m[1]);
            return f ? 'fit-content(' + f + ')' : '';
        }
        return inner(s);
    }

    // The value's tracks split on spaces outside parentheses.
    function tokens(text) {
        var out = [], cur = '', depth = 0;
        String(text || '').split('').forEach(function (c) {
            if (c === '(') depth++;
            if (c === ')') depth--;
            if (/\s/.test(c) && depth === 0) {
                if (cur) out.push(cur);
                cur = '';
                return;
            }
            cur += c;
        });
        if (cur) out.push(cur);
        return out;
    }

    // A value that would end the declaration or the rule early.
    function bad(value) {
        return /[{};<>]/.test(String(value || '').replace(/"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'/g, ''));
    }

    function parse(value, axis) {
        var v = String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
        var out = { mode: '', count: 0, sizes: [], min: '', value: v };
        if (!v) return out;
        var m = EQUAL.exec(v);
        if (m && +m[1] >= 1 && +m[1] <= MAX) {
            out.mode = 'equal';
            out.count = +m[1];
            return out;
        }
        m = axis === 'columns' ? FIT.exec(v) : null;
        if (m && length(m[1] || m[2])) {
            out.mode = 'fit';
            out.min = length(m[1] || m[2]);
            return out;
        }
        var list = tokens(v);
        var sizes = list.map(track);
        if (list.length && list.length <= MAX && sizes.every(Boolean)) {
            out.mode = 'fixed';
            out.sizes = sizes;
            out.count = sizes.length;
            return out;
        }
        out.mode = 'typed';
        return out;
    }

    function build(spec, axis) {
        spec = spec || {};
        switch (spec.mode) {
            case '':
            case undefined:
                return '';
            case 'equal':
                var n = String(spec.count == null ? '' : spec.count).trim();
                if (!/^\d+$/.test(n) || +n < 1 || +n > MAX) return null;
                return 'repeat(' + (+n) + ', minmax(0, 1fr))';
            case 'fixed':
                var sizes = (spec.sizes || []).map(track);
                if (!sizes.length || sizes.length > MAX || !sizes.every(Boolean)) return null;
                return sizes.join(' ');
            case 'fit':
                var min = length(spec.min);
                if (axis !== 'columns' || !min || min === '0') return null;
                return 'repeat(auto-fit, minmax(min(' + min + ', 100%), 1fr))';
            case 'typed':
                var t = String(spec.value == null ? '' : spec.value).replace(/\s+/g, ' ').trim();
                return t && !bad(t) ? t : null;
        }
        return null;
    }

    function pick(current, mode, axis) {
        var cur = current || { mode: '', count: 0, sizes: [], min: '', value: '' };
        var st = START[axis] || START.columns;
        switch (mode) {
            case 'equal':
                return build({ mode: 'equal', count: cur.count || st.count }, axis);
            case 'fixed':
                if (cur.mode === 'fixed') return build(cur, axis);
                var sizes = [];
                for (var i = 0; i < (cur.count || START_COUNT_FIXED); i++) sizes.push(st.size);
                return build({ mode: 'fixed', sizes: sizes }, axis);
            case 'fit':
                return build({ mode: 'fit', min: cur.min || START_MIN }, axis);
            case 'typed':
                return build({ mode: 'typed', value: cur.value }, axis);
        }
        return build({ mode: '' }, axis);
    }

    function edit(current, field, text, axis) {
        switch (field) {
            case 'count': return build({ mode: 'equal', count: text }, axis);
            case 'sizes': return build({ mode: 'fixed', sizes: String(text || '').replace(/,(?![^(]*\))/g, ' ').trim() ? tokens(String(text).replace(/,(?![^(]*\))/g, ' ')) : [] }, axis);
            case 'min': return build({ mode: 'fit', min: text }, axis);
            case 'typed': return build({ mode: 'typed', value: text }, axis);
        }
        return null;
    }

    var api = { CSS: CSS, MAX: MAX, parse: parse, build: build, pick: pick, edit: edit, track: track };

    root.PGGridTracks = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
