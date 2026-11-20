/**
 * Pinegrap - Enterprise Website Platform
 *
 * The corner box of the visual designer's Style › Border and corner section
 * (window.PGCornerBox): which Bootstrap radius class a side or all four
 * corners and a value are, both ways, and the radius each corner gets.
 * Pure: it reads and returns class lists and touches no element.
 *
 * - side 't', 'e', 'b', 's' (the `rounded-top-*`, `rounded-end-*`,
 *   `rounded-bottom-*`, `rounded-start-*` classes, each rounding the two
 *   corners of that side) or 'all' (`rounded-*`); value '0' to '5' on every
 *   side, 'circle' and 'pill' on all four only, as today's rows offer them.
 *   Bootstrap 5.3 has no class for one corner alone.
 * - Side classes of circle or pill and the bare `rounded` / `rounded-top`
 *   (the scale's 2) a page already holds are read and kept until that side
 *   is written.
 * - read(): each side's value (its own class, else the all-corners value,
 *   then not `here`) and each corner's radius in the stylesheet's order:
 *   rounded, -top, -end, -bottom, -start, the later winning a corner two
 *   classes set.
 * - write(): a class list with sides set or cleared; every other class
 *   stays in its place and new ones go to the end, as today's rows add them.
 *
 * Nothing runs at load but the definition of window.PGCornerBox. It is the
 * second source of the designer part 'spacing'
 * (assets/js/style_designer_spacing.min.js, pg_designer_parts()); after
 * editing run `php tools/build_js.php`. tests/designer_corner_box_test.php
 * runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Clockwise from the top, as the box draws them.
    var SIDES = ['t', 'e', 'b', 's'];
    // Clockwise from the top start corner.
    var CORNERS = ['ts', 'te', 'be', 'bs'];
    var SCALE = ['0', '1', '2', '3', '4', '5'];
    var OPPOSITE = { t: 'b', b: 't', s: 'e', e: 's' };
    var NAME = { t: 'top', e: 'end', b: 'bottom', s: 'start' };
    var LETTER = { top: 't', end: 'e', bottom: 'b', start: 's' };
    // The sides that set a corner, the winning one first (Bootstrap 5.3
    // prints -top, -end, -bottom, -start in that order).
    var SETS = { ts: ['s', 't'], te: ['e', 't'], be: ['b', 'e'], bs: ['s', 'b'] };
    var RE = /^rounded(?:-(top|end|bottom|start))?(?:-([0-5]|circle|pill))?$/;

    function tokens(list) {
        return String(list == null ? '' : list).split(/\s+/).filter(Boolean);
    }

    // The class of `side` ('t' … 's' or 'all') with `value`.
    function cls(side, value) {
        return 'rounded-' + (side === 'all' ? '' : NAME[side] + '-') + value;
    }

    // A radius class read back: { side, value }, or null for any other
    // class. The bare forms are the scale's 2 (var(--bs-border-radius)).
    function parse(c) {
        var m = RE.exec(String(c || ''));
        if (!m) return null;
        return { side: m[1] ? LETTER[m[1]] : 'all', value: m[2] || '2' };
    }

    // A typed value for `side`: '0' to '5', circle and pill on all four
    // (the Turkish words too), '' for none; null when it is no value.
    function value(text, side) {
        var v = String(text == null ? '' : text).trim().toLowerCase();
        if (v === '') return '';
        if (SCALE.indexOf(v) !== -1) return v;
        if (side !== 'all') return null;
        if (v === 'circle' || v === 'daire') return 'circle';
        if (v === 'pill' || v === 'hap') return 'pill';
        return null;
    }

    // Each side: { value, here }, the all-corners value as `all`, and each
    // corner's radius as `corners` ('' for the element's own default).
    function read(list) {
        var own = { all: null, t: null, e: null, b: null, s: null };
        tokens(list).forEach(function (c) {
            var p = parse(c);
            if (p) own[p.side] = p.value;
        });
        var all = own.all === null ? '' : own.all;
        var out = { all: { value: all, here: own.all !== null }, corners: {} };
        SIDES.forEach(function (side) {
            out[side] = own[side] !== null ? { value: own[side], here: true } : { value: all, here: false };
        });
        CORNERS.forEach(function (corner) {
            var by = SETS[corner];
            out.corners[corner] = own[by[0]] !== null ? own[by[0]] : (own[by[1]] !== null ? own[by[1]] : all);
        });
        return out;
    }

    // The sides a change on `side` goes to: Shift adds the opposite side,
    // Alt makes it all four (the one all-corners class, side classes gone).
    function sides(side, shift, alt) {
        if (alt) return { sides: SIDES.slice(), all: true };
        if (side === 'all') return { sides: ['all'], all: false };
        return { sides: shift ? [side, OPPOSITE[side]] : [side], all: false };
    }

    // The list with `val` ('' clears) on `sideList`; `all`: the all-corners
    // class in place of every radius class.
    function write(list, sideList, val, all) {
        var orig = String(list == null ? '' : list);
        if (val !== '' && value(val, all ? 'all' : sideList[0]) === null) return orig;
        var arr = tokens(list);
        var changed = false;
        var drop = function (test) {
            var before = arr.length;
            arr = arr.filter(function (c) { var p = parse(c); return !(p && test(p)); });
            if (arr.length !== before) changed = true;
        };
        var add = function (c) { arr.push(c); changed = true; };
        if (all) {
            drop(function () { return true; });
            if (val !== '') add(cls('all', val));
            return changed && arr.join(' ') !== tokens(orig).join(' ') ? arr.join(' ') : orig;
        }
        sideList.forEach(function (side) {
            drop(function (p) { return p.side === side; });
            if (val !== '') add(cls(side, val));
        });
        return changed && arr.join(' ') !== tokens(orig).join(' ') ? arr.join(' ') : orig;
    }

    // The value `delta` steps from `from` on 0 to 5 (a drag, an arrow key);
    // none starts below 0, circle and pill step down to 5.
    function step(from, delta) {
        var f = String(from);
        if (f === 'circle' || f === 'pill') return delta < 0 ? '5' : f;
        var i = SCALE.indexOf(f);
        if (i === -1) i = delta > 0 ? -1 : 0;
        return SCALE[Math.max(0, Math.min(SCALE.length - 1, i + delta))];
    }

    window.PGCornerBox = {
        SIDES: SIDES,
        CORNERS: CORNERS,
        cls: cls,
        parse: parse,
        value: value,
        read: read,
        sides: sides,
        write: write,
        step: step
    };
})();
