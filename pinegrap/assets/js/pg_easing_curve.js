/**
 * Pinegrap - Enterprise Website Platform
 *
 * The easing curve as numbers (window.PGCurve), for the curve editor
 * (part 'curve'): the four numbers of a cubic Bézier from an easing as
 * written or as typed, its written forms, where the curve stands at a time,
 * whether it overshoots, and a handle moved within the graph's room. Pure:
 * no DOM, run by the tests under Node.
 *
 *   PGCurve.points(easing)      // [x1, y1, x2, y2] of a named easing, a
 *                               // cubic-bezier() or a stored curve; null
 *   PGCurve.parse(text)         // the typed "x1, y1, x2, y2", or null
 *   PGCurve.text(p) / css(p)    // "0.22, 1, 0.36, 1" / "cubic-bezier(...)"
 *   PGCurve.at(p, t)            // the change at time t (0 to 1)
 *   PGCurve.overshoots(p)       // whether it goes past an end
 *   PGCurve.move(p, i, x, y)    // handle i (0 or 1) moved, held in the room
 *
 * The numbers follow pg_fx_curve_numbers(): x1 and x2 from 0 to 1, y1 and
 * y2 held from -0.5 to 1.5, at most three decimals.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */
(function (root) {
    'use strict';

    // The named easings as CSS defines them.
    var NAMED = {
        linear: [0, 0, 1, 1],
        ease: [0.25, 0.1, 0.25, 1],
        'ease-in': [0.42, 0, 1, 1],
        'ease-out': [0, 0, 0.58, 1],
        'ease-in-out': [0.42, 0, 0.58, 1]
    };
    var LOW = -0.5, HIGH = 1.5;

    // A number rounded to three decimals, halves away from zero (PHP's
    // round()), -0 as 0.
    function round3(n) {
        n = n < 0 ? -Math.round(-n * 1000) / 1000 : Math.round(n * 1000) / 1000;
        return n === 0 ? 0 : n;
    }

    function parse(text) {
        var parts = String(text == null ? '' : text).split(',');
        if (parts.length !== 4) return null;
        var out = [];
        for (var i = 0; i < 4; i++) {
            var t = parts[i].trim();
            if (!/^-?(\d{1,3}(\.\d*)?|\.\d+)$/.test(t)) return null;
            var n = Number(t);
            if (i % 2 === 0 && (n < 0 || n > 1)) return null;
            if (i % 2 === 1) n = Math.max(LOW, Math.min(HIGH, n));
            out.push(round3(n));
        }
        return out;
    }

    function points(easing) {
        var s = String(easing == null ? '' : easing).trim().toLowerCase();
        if (NAMED[s]) return NAMED[s].slice();
        var m = /^var\(\s*--pg-var-c-[a-z0-9-]+\s*,\s*([\s\S]+)\)$/.exec(s);
        if (m) s = m[1].trim();
        m = /^cubic-bezier\(([^()]*)\)$/.exec(s);
        return m ? parse(m[1]) : null;
    }

    function text(p) {
        return p.map(function (n) { return String(round3(n)); }).join(', ');
    }

    function css(p) {
        return 'cubic-bezier(' + text(p) + ')';
    }

    // One coordinate of the Bézier from (0,0) to (1,1) at parameter s.
    function bez(a, b, s) {
        var r = 1 - s;
        return 3 * r * r * s * a + 3 * r * s * s * b + s * s * s;
    }

    // The change at time t: the parameter whose x is t (halving, as x
    // only grows while x1 and x2 stay from 0 to 1), then its y.
    function at(p, t) {
        t = Math.max(0, Math.min(1, Number(t) || 0));
        var lo = 0, hi = 1, s = t;
        for (var i = 0; i < 40; i++) {
            s = (lo + hi) / 2;
            if (bez(p[0], p[2], s) < t) lo = s; else hi = s;
        }
        return bez(p[1], p[3], s);
    }

    function overshoots(p) {
        for (var i = 1; i < 50; i++) {
            var y = bez(p[1], p[3], i / 50);
            if (y > 1.001 || y < -0.001) return true;
        }
        return false;
    }

    // The curve with handle i (0 the first, 1 the second) at (x, y), held
    // in the graph's room and kept to two decimals, as a drag or a key
    // sets it.
    function move(p, i, x, y) {
        var out = p.slice();
        var r2 = function (n) { n = Math.round(n * 100) / 100; return n === 0 ? 0 : n; };
        out[i * 2] = r2(Math.max(0, Math.min(1, Number(x) || 0)));
        out[i * 2 + 1] = r2(Math.max(LOW, Math.min(HIGH, Number(y) || 0)));
        return out;
    }

    var api = { NAMED: NAMED, LOW: LOW, HIGH: HIGH, points: points, parse: parse, text: text, css: css, at: at, overshoots: overshoots, move: move };
    root.PGCurve = api;
    if (typeof module !== 'undefined' && module.exports) module.exports = api;
})(typeof window !== 'undefined' ? window : this);
