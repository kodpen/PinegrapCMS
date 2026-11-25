/**
 * Pinegrap - Enterprise Website Platform
 *
 * The geometry of the visual designer's canvas guides
 * (window.PGCanvasGuides): distances between two boxes, a parent's inner
 * distances, the spacing scale's nearest step, the grid column under a
 * pointer and equal gaps among siblings. Pure: it reads plain boxes and
 * numbers and touches no element.
 *
 * - A box is { left, top, right, bottom } (a DOMRect-like object with
 *   x / y / width / height is read too), all in one coordinate space: the
 *   canvas frame's, already zoomed. `zoom` (default 1) turns those pixels
 *   back into the page's CSS pixels for the numbers shown; positions stay in
 *   the boxes' own space so the drawing can use them as they are.
 * - A measurement line is { axis, from, to, at, value }: axis 'x' runs
 *   left to right between `from` and `to` at height `at`, axis 'y' runs top
 *   to bottom at `at` across. `value` is the length in page pixels, rounded.
 * - Sides follow the spacing box: 't', 'e', 'b', 's' (top, end, bottom,
 *   start); `rtl` puts the start on the right.
 * - The spacing scale is Bootstrap 5.3's: steps '0' to '5' are 0, .25, .5,
 *   1, 1.5 and 3 times the root font size (16 px unless given).
 * - The grid is Bootstrap's twelve columns: a row is split into equal
 *   column boxes; each column's content sits half a gutter (1.5rem by
 *   default) inside its box.
 *
 * Nothing runs at load but the definition of window.PGCanvasGuides.
 * tests/designer_canvas_guides_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // Bootstrap 5.3's $spacers as multiples of the root font size.
    var SPACER_REM = [0, 0.25, 0.5, 1, 1.5, 3];
    var COLUMNS = 12;
    // Two boxes count as touching or two gaps as equal within this many
    // page pixels (sub-pixel layout and rounding).
    var TOLERANCE = 0.5;

    function num(v, d) {
        v = Number(v);
        return isFinite(v) ? v : d;
    }

    // A box with left, top, right, bottom, or null when it has none.
    function box(r) {
        if (!r) return null;
        var l = num(r.left, num(r.x, NaN));
        var t = num(r.top, num(r.y, NaN));
        var rr = num(r.right, l + num(r.width, NaN));
        var b = num(r.bottom, t + num(r.height, NaN));
        if (!isFinite(l) || !isFinite(t) || !isFinite(rr) || !isFinite(b)) return null;
        return { left: Math.min(l, rr), top: Math.min(t, b), right: Math.max(l, rr), bottom: Math.max(t, b) };
    }

    function zoomOf(z) {
        z = num(z, 1);
        return z > 0 ? z : 1;
    }

    function px(len, zoom) {
        var v = Math.round(len / zoomOf(zoom));
        return v === 0 ? 0 : v;
    }

    function line(axis, from, to, at, zoom) {
        return { axis: axis, from: from, to: to, at: at, value: px(to - from, zoom) };
    }

    // The middle of where [a1, a2] and [b1, b2] overlap, or null.
    function overlapMid(a1, a2, b1, b2) {
        var lo = Math.max(a1, b1), hi = Math.min(a2, b2);
        return hi >= lo ? (lo + hi) / 2 : null;
    }

    // Whether a and b are apart (or just touch) along `axis`.
    function apart(axis, a, b) {
        var s = axis === 'x' ? 'left' : 'top', e = axis === 'x' ? 'right' : 'bottom';
        return b[s] >= a[e] || a[s] >= b[e];
    }

    // The lines between the selection `a` and the pointed box `b` (Measure,
    // Alt held), horizontal ones first, then vertical.
    // - Apart along an axis: one line across the gap, drawn at the middle of
    //   where the boxes face each other; when they do not face each other
    //   (b lies off diagonally) at the middle of `a`, and the line carries
    //   `reach`, the cross position of b's nearest edge, where the drawing
    //   extends a dashed guide to (null otherwise).
    // - Overlapping on both axes (one inside the other, or crossing): along
    //   each axis a line between the two starts and one between the two
    //   ends, each only when it has a length: a nested box's inner
    //   distances.
    // - Apart along one axis only: no line along the other.
    function distances(a, b, zoom) {
        a = box(a);
        b = box(b);
        if (!a || !b) return [];
        var out = [];
        ['x', 'y'].forEach(function (axis) {
            var cross = axis === 'x' ? 'y' : 'x';
            var s = axis === 'x' ? 'left' : 'top', e = axis === 'x' ? 'right' : 'bottom';
            var cs = axis === 'x' ? 'top' : 'left', ce = axis === 'x' ? 'bottom' : 'right';
            var off = apart(cross, a, b);
            var at = overlapMid(a[cs], a[ce], b[cs], b[ce]);
            var reach = null;
            if (at === null) {
                at = (a[cs] + a[ce]) / 2;
                reach = b[ce] <= a[cs] ? b[ce] : b[cs];
            }
            function push(from, to) {
                var l = line(axis, from, to, at, zoom);
                l.reach = reach;
                out.push(l);
            }
            if (b[s] >= a[e]) {
                push(a[e], b[s]);
            } else if (a[s] >= b[e]) {
                push(b[e], a[s]);
            } else if (!off) {
                if (a[s] !== b[s]) push(Math.min(a[s], b[s]), Math.max(a[s], b[s]));
                if (a[e] !== b[e]) push(Math.min(a[e], b[e]), Math.max(a[e], b[e]));
            }
        });
        return out;
    }

    // The four distances from `child` to the inside of `parent` in page
    // pixels by side ({ t, e, b, s }), each with its line. A side where the
    // child sticks out reads negative.
    function inner(child, parent, zoom, rtl) {
        var c = box(child), p = box(parent);
        if (!c || !p) return null;
        var midX = (Math.max(c.left, p.left) + Math.min(c.right, p.right)) / 2;
        var midY = (Math.max(c.top, p.top) + Math.min(c.bottom, p.bottom)) / 2;
        var left = { value: px(c.left - p.left, zoom), line: line('x', p.left, c.left, midY, zoom) };
        var right = { value: px(p.right - c.right, zoom), line: line('x', c.right, p.right, midY, zoom) };
        return {
            t: { value: px(c.top - p.top, zoom), line: line('y', p.top, c.top, midX, zoom) },
            b: { value: px(p.bottom - c.bottom, zoom), line: line('y', c.bottom, p.bottom, midX, zoom) },
            s: rtl ? right : left,
            e: rtl ? left : right
        };
    }

    // The spacing scale in page pixels, steps '0' to '5'.
    function scale(rootPx) {
        var r = num(rootPx, 16);
        if (!(r > 0)) r = 16;
        return SPACER_REM.map(function (m) { return m * r; });
    }

    // The step of the scale nearest to `value` page pixels:
    // { step: '0'..'5', px }. Halfway between two steps takes the smaller.
    // `steps` is a scale in pixels (scale()) or the root font size.
    function nearestStep(value, steps) {
        var list = Array.isArray(steps) ? steps : scale(steps);
        var v = Math.max(0, num(value, 0));
        var best = 0;
        for (var i = 1; i < list.length; i++) {
            if (Math.abs(list[i] - v) < Math.abs(list[best] - v)) best = i;
        }
        return { step: String(best), px: list[best] };
    }

    // The step a band's handle lands on after a drag: the side started at
    // step `from` ('0'..'5', anything else reads as 0) and the pointer moved
    // `delta` page pixels outwards (negative inwards).
    function dragStep(from, delta, steps) {
        var list = Array.isArray(steps) ? steps : scale(steps);
        var i = parseInt(from, 10);
        var start = i >= 0 && i < list.length ? list[i] : 0;
        return nearestStep(start + num(delta, 0), list);
    }

    // A row's columns: `count` equal boxes across the row's box, each with
    // `content`, its box less half a gutter on both sides (the grid drawn
    // over the row). Numbered from the start: from the right when `rtl`.
    function columns(row, opts) {
        var r = box(row);
        opts = opts || {};
        var count = Math.max(1, Math.round(num(opts.count, COLUMNS)));
        var half = num(opts.gutter, 1.5 * num(opts.root, 16)) * zoomOf(opts.zoom) / 2;
        if (!r) return [];
        var w = (r.right - r.left) / count, out = [];
        for (var i = 0; i < count; i++) {
            var left = opts.rtl ? r.right - (i + 1) * w : r.left + i * w;
            var right = left + w;
            var h = Math.min(half, w / 2);
            out.push({
                index: i + 1,
                left: left,
                right: right,
                top: r.top,
                bottom: r.bottom,
                content: { left: left + h, right: right - h, top: r.top, bottom: r.bottom }
            });
        }
        return out;
    }

    // The column (1..count) under the pointer at `x` in a row, counted from
    // the start; a pointer outside the row reads as the nearest end column.
    function columnAt(x, row, opts) {
        var r = box(row);
        opts = opts || {};
        var count = Math.max(1, Math.round(num(opts.count, COLUMNS)));
        if (!r || r.right <= r.left) return null;
        var f = (num(x, r.left) - r.left) / (r.right - r.left);
        if (opts.rtl) f = 1 - f;
        return Math.max(1, Math.min(count, Math.floor(f * count) + 1));
    }

    // A column's span while its end edge is dragged: the column starts after
    // `startCol` columns of the row (0 for the first) and the pointer is at
    // `x`; the nearest column line wins, at least 1 and at most what is left
    // of the row. { span, of } for "6 of 12".
    function spanAt(startCol, x, row, opts) {
        var r = box(row);
        opts = opts || {};
        var count = Math.max(1, Math.round(num(opts.count, COLUMNS)));
        var start = Math.max(0, Math.min(count - 1, Math.round(num(startCol, 0))));
        if (!r || r.right <= r.left) return null;
        var f = (num(x, r.left) - r.left) / (r.right - r.left);
        if (opts.rtl) f = 1 - f;
        var span = Math.round(f * count) - start;
        return { span: Math.max(1, Math.min(count - start, span)), of: count };
    }

    // The gaps between neighbouring boxes along `axis` ('x' side by side,
    // 'y' stacked): the boxes sorted by their start, each gap from one box's
    // end to the next one's start when the next one starts after it. Boxes
    // that overlap on the axis give no gap. Each gap is a line (see
    // distances()) with `between`: the two boxes' indexes in `boxes`.
    function gaps(boxes, axis, zoom) {
        axis = axis === 'y' ? 'y' : 'x';
        var s = axis === 'x' ? 'left' : 'top', e = axis === 'x' ? 'right' : 'bottom';
        var cs = axis === 'x' ? 'top' : 'left', ce = axis === 'x' ? 'bottom' : 'right';
        var list = (boxes || []).map(function (b, i) { return { b: box(b), i: i }; })
            .filter(function (o) { return o.b; })
            .sort(function (p, q) { return p.b[s] - q.b[s] || p.i - q.i; });
        var out = [];
        for (var k = 1; k < list.length; k++) {
            var a = list[k - 1].b, b = list[k].b;
            if (b[s] < a[e]) continue;
            var at = overlapMid(a[cs], a[ce], b[cs], b[ce]);
            if (at === null) at = (Math.max(a[cs], b[cs]) + Math.min(a[ce], b[ce])) / 2;
            var l = line(axis, a[e], b[s], at, zoom);
            l.between = [list[k - 1].i, list[k].i];
            out.push(l);
        }
        return out;
    }

    // The sibling gaps the same size as `value` page pixels (the gap a
    // handle is setting), within `tolerance` page pixels (0.5 by default).
    function equalGaps(boxes, value, axis, zoom, tolerance) {
        var z = zoomOf(zoom);
        var tol = num(tolerance, TOLERANCE);
        var v = num(value, NaN);
        if (!isFinite(v)) return [];
        return gaps(boxes, axis, z).filter(function (g) {
            return Math.abs((g.to - g.from) / z - v) <= tol;
        });
    }

    // Where a moving value holds: the target nearest to `value` when it is
    // within `threshold`, else null (a handle holds at an equal gap).
    function snap(value, targets, threshold) {
        var v = num(value, NaN), t = num(threshold, 4), best = null;
        if (!isFinite(v)) return null;
        (targets || []).forEach(function (x) {
            x = num(x, NaN);
            if (isFinite(x) && Math.abs(x - v) <= t && (best === null || Math.abs(x - v) < Math.abs(best - v))) best = x;
        });
        return best;
    }

    window.PGCanvasGuides = {
        COLUMNS: COLUMNS,
        box: box,
        distances: distances,
        inner: inner,
        scale: scale,
        nearestStep: nearestStep,
        dragStep: dragStep,
        columns: columns,
        columnAt: columnAt,
        spanAt: spanAt,
        gaps: gaps,
        equalGaps: equalGaps,
        snap: snap
    };
})();
