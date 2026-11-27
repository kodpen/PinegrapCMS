/**
 * Pinegrap - Enterprise Website Platform
 *
 * A grid box's named areas in the designer's Advanced › Layout, as the
 * value of `grid-template-areas`: each area one rectangle of cells with a
 * name, a cell in no area a dot. The part `grid`
 * (assets/js/style_designer_grid.js) draws them as a small grid and writes
 * the value per screen size through window.PGSizeStyle; a child is put
 * into an area with `grid-area: <name>`.
 *
 *   PGGridAreas.CSS, ITEM, MAX
 *   PGGridAreas.parse(value)               // { ok, cols, rows, areas: [{ name, c, r, w, h }] }
 *   PGGridAreas.build(areas, cols, rows)   // the CSS value, '' for no area
 *   PGGridAreas.count(track)               // tracks of a parsed Columns or Rows, 0 none set
 *   PGGridAreas.nameError(name, areas, skip) // '' kept, 'bad' or 'taken'
 *   PGGridAreas.nextName(areas)            // 'area1', 'area2', … not taken
 *   PGGridAreas.at(areas, c, r)            // the index of the area on a cell, -1 free
 *   PGGridAreas.free(areas, rect, cols, rows, skip) // the rectangle is inside and free
 *   PGGridAreas.rect(a, b)                 // the rectangle between two cells
 *   PGGridAreas.fit(areas, cols, rows)     // { areas, cut: [names], gone: [names] }
 *   PGGridAreas.item(value)                // the area name a child's grid-area holds, '' none
 *
 * Cells count from 1; an area is { name, c: column, r: row, w: width,
 * h: height }. `skip` is the index of an area left out of a check (the
 * one being moved, resized or renamed).
 *
 * window.PGGridAreas in a browser; module.exports under Node. Plain ES5.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function (root) {
    'use strict';

    var CSS = 'grid-template-areas';
    var ITEM = 'grid-area';
    var MAX = 12;
    var NAME = /^[A-Za-z][A-Za-z0-9_-]*$/;
    // Words `grid-area` reads as keywords, in any case: a child given one
    // would not stand in an area of that name.
    var KEYWORD = /^(?:auto|span|none|initial|inherit|unset|revert|revert-layer|default)$/i;

    function parse(value) {
        var v = String(value == null ? '' : value).trim();
        var out = { ok: true, cols: 0, rows: 0, areas: [] };
        if (!v || v === 'none') return out;
        var bad = { ok: false, cols: 0, rows: 0, areas: [] };
        var lines = [];
        var re = /"([^"]*)"|'([^']*)'|(\S+)/g, m;
        while ((m = re.exec(v))) {
            if (m[3] !== undefined) return bad;
            lines.push((m[1] !== undefined ? m[1] : m[2]).trim().split(/\s+/).filter(Boolean));
        }
        if (!lines.length || lines.length > MAX) return bad;
        var cols = lines[0].length;
        if (!cols || cols > MAX || lines.some(function (l) { return l.length !== cols; })) return bad;
        var seen = {}, order = [];
        for (var r = 0; r < lines.length; r++) {
            for (var c = 0; c < cols; c++) {
                var t = lines[r][c];
                if (/^\.+$/.test(t)) continue;
                if (!NAME.test(t)) return bad;
                if (!seen[t]) { seen[t] = { c1: c, c2: c, r1: r, r2: r, n: 0 }; order.push(t); }
                var s = seen[t];
                s.c1 = Math.min(s.c1, c); s.c2 = Math.max(s.c2, c);
                s.r1 = Math.min(s.r1, r); s.r2 = Math.max(s.r2, r);
                s.n++;
            }
        }
        var areas = order.map(function (name) {
            var s = seen[name];
            return { name: name, c: s.c1 + 1, r: s.r1 + 1, w: s.c2 - s.c1 + 1, h: s.r2 - s.r1 + 1, n: s.n };
        });
        // Each name must fill its whole rectangle.
        if (areas.some(function (a) { return a.n !== a.w * a.h; })) return bad;
        out.cols = cols;
        out.rows = lines.length;
        out.areas = areas.map(function (a) { return { name: a.name, c: a.c, r: a.r, w: a.w, h: a.h }; });
        return out;
    }

    function at(areas, c, r) {
        for (var i = 0; i < areas.length; i++) {
            var a = areas[i];
            if (c >= a.c && c < a.c + a.w && r >= a.r && r < a.r + a.h) return i;
        }
        return -1;
    }

    function build(areas, cols, rows) {
        if (!areas || !areas.length || cols < 1 || rows < 1) return '';
        var out = [];
        for (var r = 1; r <= rows; r++) {
            var line = [];
            for (var c = 1; c <= cols; c++) {
                var i = at(areas, c, r);
                line.push(i === -1 ? '.' : areas[i].name);
            }
            out.push('"' + line.join(' ') + '"');
        }
        return out.join(' ');
    }

    // Areas need a set number of tracks: Equal or Fixed sizes.
    function count(track) {
        if (!track) return 0;
        return (track.mode === 'equal' || track.mode === 'fixed') && track.count >= 1 && track.count <= MAX ? track.count : 0;
    }

    function nameError(name, areas, skip) {
        var n = String(name == null ? '' : name).trim();
        if (!NAME.test(n) || KEYWORD.test(n)) return 'bad';
        for (var i = 0; i < (areas || []).length; i++) {
            if (i !== skip && areas[i].name === n) return 'taken';
        }
        return '';
    }

    function nextName(areas) {
        for (var k = (areas || []).length + 1; ; k++) {
            if (nameError('area' + k, areas, -1) === '') return 'area' + k;
        }
    }

    function free(areas, rect, cols, rows, skip) {
        if (!rect || rect.w < 1 || rect.h < 1 || rect.c < 1 || rect.r < 1) return false;
        if (rect.c + rect.w - 1 > cols || rect.r + rect.h - 1 > rows) return false;
        for (var i = 0; i < (areas || []).length; i++) {
            if (i === skip) continue;
            var a = areas[i];
            if (rect.c < a.c + a.w && a.c < rect.c + rect.w && rect.r < a.r + a.h && a.r < rect.r + rect.h) return false;
        }
        return true;
    }

    function rect(a, b) {
        var c = Math.min(a.c, b.c), r = Math.min(a.r, b.r);
        return { c: c, r: r, w: Math.max(a.c, b.c) - c + 1, h: Math.max(a.r, b.r) - r + 1 };
    }

    function fit(areas, cols, rows) {
        var out = { areas: [], cut: [], gone: [] };
        (areas || []).forEach(function (a) {
            var w = Math.min(a.w, cols - a.c + 1), h = Math.min(a.h, rows - a.r + 1);
            if (w < 1 || h < 1) { out.gone.push(a.name); return; }
            if (w !== a.w || h !== a.h) out.cut.push(a.name);
            out.areas.push({ name: a.name, c: a.c, r: a.r, w: w, h: h });
        });
        return out;
    }

    function item(value) {
        var v = String(value == null ? '' : value).trim();
        return NAME.test(v) && !KEYWORD.test(v) ? v : '';
    }

    var api = { CSS: CSS, ITEM: ITEM, MAX: MAX, parse: parse, build: build, count: count, nameError: nameError,
        nextName: nextName, at: at, free: free, rect: rect, fit: fit, item: item };

    root.PGGridAreas = api;

    if (typeof module === 'object' && module && module.exports) {
        module.exports = api;
    }
})(typeof window !== 'undefined' ? window : this);
