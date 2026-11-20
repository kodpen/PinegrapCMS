/**
 * Pinegrap - Enterprise Website Platform
 *
 * The spacing box of the visual designer's Style › Spacing section
 * (window.PGSpacingBox): which Bootstrap spacing class a side, a screen size
 * and a value are, both ways. Pure: it reads and returns class lists and
 * touches no element.
 *
 * - kind 'm' (margin) or 'p' (padding); side 't', 'e', 'b', 's' (top, end,
 *   bottom, start) or 'all'; size 'xs' (no infix), 'sm', 'md', 'lg', 'xl',
 *   'xxl'; value '0' to '5', 'auto' on margins only (the scale Bootstrap
 *   5.3 ships, as today's rows offer it).
 * - It writes only the classes today's rows write: `mt-*`, `mb-*`, `ms-*`,
 *   `me-*`, `pt-*`, `pb-*`, `ps-*`, `pe-*` and `m-*` / `p-*` for all four
 *   sides, each with its size forms (`mt-md-3`). Axis classes (`mx-*`,
 *   `my-*`) and negative margins (`mt-n2`) a page already holds are read
 *   and kept; they are written only away, when a side they set is cleared.
 * - read(): a side's value as the browser applies the classes: a side
 *   class over an axis class over the all-sides class, a larger size over a
 *   smaller one; `here` says it is set at the size asked for.
 * - write(): a class list with sides set or cleared at one size; every other
 *   class stays in its place and the new ones go to the end, as today's
 *   rows add them.
 *
 * Nothing runs at load but the definition of window.PGSpacingBox. It is the
 * first source of the designer part 'spacing'
 * (assets/js/style_designer_spacing.min.js, pg_designer_parts()); after
 * editing run `php tools/build_js.php`. tests/designer_spacing_box_test.php
 * runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var SIZES = ['xs', 'sm', 'md', 'lg', 'xl', 'xxl'];
    // Clockwise from the top, as the box draws them.
    var SIDES = ['t', 'e', 'b', 's'];
    var SCALE = ['0', '1', '2', '3', '4', '5'];
    var OPPOSITE = { t: 'b', b: 't', s: 'e', e: 's' };
    // The axis class that also sets a side.
    var AXIS = { t: 'y', b: 'y', s: 'x', e: 'x' };
    var RE = /^([mp])([tbsexy]?)-(?:(sm|md|lg|xl|xxl)-)?(n[1-5]|[0-5]|auto)$/;

    function tokens(list) {
        return String(list == null ? '' : list).split(/\s+/).filter(Boolean);
    }

    // The class of `kind` on `side` (a side, 'x', 'y' or 'all') at `size`.
    function cls(kind, side, size, value) {
        return kind + (side === 'all' ? '' : side) + '-' + (size && size !== 'xs' ? size + '-' : '') + value;
    }

    // A spacing class read back: { kind, side, size, value }, or null for
    // any other class (padding has neither auto nor negative values).
    function parse(c) {
        var m = RE.exec(String(c || ''));
        if (!m || (m[1] === 'p' && (m[4] === 'auto' || m[4].charAt(0) === 'n'))) return null;
        return { kind: m[1], side: m[2] || 'all', size: m[3] || 'xs', value: m[4] };
    }

    // A typed value: '0' to '5', 'auto' on margins ("oto" too, as the
    // drawing's Turkish reads), '' for none; null when it is no value.
    function value(text, kind) {
        var v = String(text == null ? '' : text).trim().toLowerCase();
        if (v === '') return '';
        if (SCALE.indexOf(v) !== -1) return v;
        if (kind === 'm' && (v === 'auto' || v === 'oto' || v === 'otomatik')) return 'auto';
        return null;
    }

    // The value `kind` sets on `side` at exactly `size` in the parsed
    // classes, or null: the side class, else the axis class, else all.
    function at(parsed, kind, side, size) {
        var found = { own: null, axis: null, all: null };
        parsed.forEach(function (p) {
            if (!p || p.kind !== kind || p.size !== size) return;
            if (p.side === side) found.own = p.value;
            else if (p.side === AXIS[side]) found.axis = p.value;
            else if (p.side === 'all') found.all = p.value;
        });
        if (found.own !== null) return found.own;
        return found.axis !== null ? found.axis : found.all;
    }

    // Each side's value at `size`: { t: { value, here, size }, … }; `size`
    // is the screen size the value comes from ('' for none).
    function read(list, kind, size) {
        var parsed = tokens(list).map(parse);
        var upto = SIZES.indexOf(size);
        if (upto === -1) upto = 0;
        var out = {};
        SIDES.forEach(function (side) {
            var r = { value: '', here: false, size: '' };
            for (var i = 0; i <= upto; i++) {
                var v = at(parsed, kind, side, SIZES[i]);
                if (v !== null) r = { value: v, here: i === upto, size: SIZES[i] };
            }
            out[side] = r;
        });
        return out;
    }

    // The sizes at which the list holds a margin or padding class, smallest first.
    function held(list) {
        var has = {};
        tokens(list).forEach(function (c) { var p = parse(c); if (p) has[p.size] = true; });
        return SIZES.filter(function (s) { return has[s]; });
    }

    // The sides a change on `side` goes to: Shift adds the opposite side,
    // Alt makes it all four (written as the one all-sides class).
    function sides(side, shift, alt) {
        if (alt) return { sides: SIDES.slice(), all: true };
        return { sides: shift ? [side, OPPOSITE[side]] : [side], all: false };
    }

    // The list with `val` ('' clears) on `sideList` of `kind` at `size`;
    // `all`: the all-sides class in place of every class of that size.
    function write(list, kind, size, sideList, val, all) {
        if (val !== '' && value(val, kind) === null) return String(list == null ? '' : list);
        var arr = tokens(list);
        var changed = false;
        var drop = function (test) {
            var before = arr.length;
            arr = arr.filter(function (c) { var p = parse(c); return !(p && p.kind === kind && p.size === size && test(p)); });
            if (arr.length !== before) changed = true;
        };
        var add = function (c) { arr.push(c); changed = true; };
        if (all) {
            drop(function () { return true; });
            if (val !== '') add(cls(kind, 'all', size, val));
            return changed ? arr.join(' ') : String(list == null ? '' : list);
        }
        sideList.forEach(function (side) {
            drop(function (p) { return p.side === side; });
            if (val !== '') { add(cls(kind, side, size, val)); return; }
            // Cleared while the axis or all-sides class of this size sets
            // it: those go, and the other sides they set keep their value
            // as side classes.
            var parsed = arr.map(parse);
            var covers = function (p) { return p && p.kind === kind && p.size === size && (p.side === AXIS[side] || p.side === 'all'); };
            if (!parsed.some(covers)) return;
            var keep = {};
            SIDES.forEach(function (o) { if (o !== side) keep[o] = at(parsed, kind, o, size); });
            drop(function (p) { return p.side === AXIS[side] || p.side === 'all'; });
            var left = arr.map(parse);
            SIDES.forEach(function (o) {
                if (o === side || keep[o] === null) return;
                if (at(left, kind, o, size) === null) add(cls(kind, o, size, keep[o]));
            });
        });
        return changed ? arr.join(' ') : String(list == null ? '' : list);
    }

    // The value `delta` steps from `from` on the scale (a drag, an arrow
    // key): 0 to 5, then auto on margins; none and negative values start
    // below 0.
    function step(from, delta, kind) {
        var scale = kind === 'm' ? SCALE.concat(['auto']) : SCALE;
        var i = scale.indexOf(String(from));
        if (i === -1) i = delta > 0 ? -1 : 0;
        return scale[Math.max(0, Math.min(scale.length - 1, i + delta))];
    }

    window.PGSpacingBox = {
        SIZES: SIZES,
        SIDES: SIDES,
        cls: cls,
        parse: parse,
        value: value,
        read: read,
        held: held,
        sides: sides,
        write: write,
        step: step
    };
})();
