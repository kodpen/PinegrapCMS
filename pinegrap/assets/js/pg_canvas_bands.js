/**
 * Pinegrap - Enterprise Website Platform
 *
 * The spacing bands of the visual designer's canvas guides
 * (window.PGCanvasBands): where the selection's outer spacing (margin) and
 * inner spacing (padding) are drawn as bands around and inside its frame,
 * where each band's handle sits, how far a dragged or stepped handle
 * changes a side, which sides Shift and Alt add, where the value label goes
 * which screen size the canvas width stands for, and the spacing scale's
 * steps; the column handle's grid and span over a row, and the sibling gap
 * a dragged margin matches (equal gaps). Pure: plain boxes and numbers in,
 * plain objects out; no element, no listener.
 *
 * - A box is { left, top, right, bottom } in the layer's screen pixels; `k`
 *   is how many screen pixels one page pixel takes (zoom and Fit).
 * - Spacing is read as the browser lays it out: { top, right, bottom, left }
 *   in page pixels. Sides are named as in the Spacing box: 't', 'e', 'b',
 *   's'; `rtl` puts the start on the right.
 * - A handle is { kind: 'm'|'p', side, x, y, axis, faint }: its middle;
 *   axis 'x' lies along a top or bottom edge, 'y' along a side edge. A side
 *   with no spacing gets a faint handle on the frame's own edge.
 *
 * Nothing runs at load but the definition of window.PGCanvasBands.
 * tests/designer_canvas_bands_test.php runs it in Node.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    // The handle's thickness in screen pixels: an inner handle sits at
    // least this far inside the frame (a side with no padding, or less than
    // this on screen), so its pill never covers the outer one.
    var HANDLE = 6;
    // Bootstrap 5.3's $spacers as multiples of the root font size (as
    // PGCanvasGuides has them; kept here so this part needs no other).
    var SPACER_REM = [0, 0.25, 0.5, 1, 1.5, 3];
    // The canvas widths where Bootstrap's screen sizes start.
    var SIZES = [['xxl', 1400], ['xl', 1200], ['lg', 992], ['md', 768], ['sm', 576]];

    function num(v) {
        v = Number(v);
        return isFinite(v) ? v : 0;
    }

    // The physical edge of a logical side.
    function edge(side, rtl) {
        if (side === 't') return 'top';
        if (side === 'b') return 'bottom';
        if (side === 'e') return rtl ? 'left' : 'right';
        return rtl ? 'right' : 'left';
    }

    // Spacing in page pixels as { top, right, bottom, left }, never below 0
    // (a negative margin draws no band).
    function sides(v) {
        v = v || {};
        return {
            top: Math.max(0, num(v.top)),
            right: Math.max(0, num(v.right)),
            bottom: Math.max(0, num(v.bottom)),
            left: Math.max(0, num(v.left))
        };
    }

    // The bands and handles of a frame `b` with `margin` and `padding`:
    // { outer: [...], inner: [...] }, each item { side, edge, band, handle }
    // in the order t, e, b, s. The inner side bands run between the inner
    // top and bottom bands, as the content box does.
    function layout(b, margin, padding, k, rtl) {
        k = num(k) > 0 ? num(k) : 1;
        var m = sides(margin), p = sides(padding);
        var midX = (b.left + b.right) / 2;
        var inTop = b.top + p.top * k, inBottom = b.bottom - p.bottom * k;
        var midY = (inTop + inBottom) / 2;
        var hTop = Math.max(inTop, b.top + HANDLE), hBottom = Math.min(inBottom, b.bottom - HANDLE);
        var outer = [], inner = [];
        ['t', 'e', 'b', 's'].forEach(function (side) {
            var e = edge(side, rtl);
            var mo = m[e] * k, pi = p[e] * k, band, hb, at;
            if (e === 'top') {
                band = { left: b.left, top: b.top - mo, right: b.right, bottom: b.top };
                at = { x: midX, y: band.top, axis: 'x' };
                hb = { left: b.left, top: b.top, right: b.right, bottom: inTop };
                inner.push(item('p', side, e, hb, { x: midX, y: hTop, axis: 'x' }, pi));
            } else if (e === 'bottom') {
                band = { left: b.left, top: b.bottom, right: b.right, bottom: b.bottom + mo };
                at = { x: midX, y: band.bottom, axis: 'x' };
                hb = { left: b.left, top: inBottom, right: b.right, bottom: b.bottom };
                inner.push(item('p', side, e, hb, { x: midX, y: hBottom, axis: 'x' }, pi));
            } else if (e === 'left') {
                band = { left: b.left - mo, top: b.top, right: b.left, bottom: b.bottom };
                at = { x: band.left, y: midY, axis: 'y' };
                hb = { left: b.left, top: inTop, right: b.left + pi, bottom: inBottom };
                inner.push(item('p', side, e, hb, { x: Math.max(hb.right, b.left + HANDLE), y: midY, axis: 'y' }, pi));
            } else {
                band = { left: b.right, top: b.top, right: b.right + mo, bottom: b.bottom };
                at = { x: band.right, y: midY, axis: 'y' };
                hb = { left: b.right - pi, top: inTop, right: b.right, bottom: inBottom };
                inner.push(item('p', side, e, hb, { x: Math.min(hb.left, b.right - HANDLE), y: midY, axis: 'y' }, pi));
            }
            outer.push(item('m', side, e, band, at, mo));
        });
        return { outer: outer, inner: inner };
    }

    function item(kind, side, e, band, at, size) {
        return {
            side: side,
            edge: e,
            band: band,
            handle: { kind: kind, side: side, x: at.x, y: at.y, axis: at.axis, faint: !(size > 0) }
        };
    }

    // How many page pixels a side grows when its handle moves by dx, dy
    // screen pixels: an outer handle grows outwards, an inner one inwards.
    function growth(kind, e, dx, dy, k) {
        k = num(k) > 0 ? num(k) : 1;
        var out = e === 'top' ? -num(dy) : e === 'bottom' ? num(dy) : e === 'left' ? -num(dx) : num(dx);
        var g = (kind === 'p' ? -out : out) / k;
        return g === 0 ? 0 : g;
    }

    // The step an arrow key takes the side to: `dir` is the sign of the
    // growth the arrow gives (growth() of a one-pixel move).
    function keyStep(from, dir, count) {
        var n = count || 6;
        var i = parseInt(from, 10);
        if (!(i >= 0 && i < n)) i = 0;
        i += dir > 0 ? 1 : dir < 0 ? -1 : 0;
        return String(Math.max(0, Math.min(n - 1, i)));
    }

    // Which sides a change names: 'one', 'tb' (top and bottom), 'se' (start
    // and end) or 'all', as the Spacing box widens it with Shift and Alt.
    function reach(side, shift, alt) {
        if (alt) return 'all';
        if (!shift) return 'one';
        return side === 't' || side === 'b' ? 'tb' : 'se';
    }

    // Where a label `w` x `h` goes beside the pointer `x`, `y` in a layer
    // `bw` x `bh`: below right; a label that would leave the layer flips to
    // the pointer's other side, and stays inside it.
    function labelAt(x, y, w, h, bw, bh, gap) {
        var g = gap === undefined ? 12 : num(gap);
        var lx = x + g, ly = y + g;
        if (lx + w > bw) lx = x - g - w;
        if (ly + h > bh) ly = y - g - h;
        return {
            x: Math.max(0, Math.min(lx, bw - w)),
            y: Math.max(0, Math.min(ly, bh - h))
        };
    }

    // The spacing scale in page pixels, steps '0' to '5'.
    function scale(rootPx) {
        var r = num(rootPx) > 0 ? num(rootPx) : 16;
        return SPACER_REM.map(function (m) { return m * r; });
    }

    // The step of `steps` (a scale) nearest to `value` page pixels:
    // { step: '0'..'5', px }; halfway between two steps takes the smaller.
    // `from`, a start off the scale (startStep()), counts as one more step,
    // so a drag from it keeps it until a scale step is nearer.
    function nearestStep(value, steps, from) {
        var v = Math.max(0, num(value)), best = 0;
        for (var i = 1; i < steps.length; i++) {
            if (Math.abs(steps[i] - v) < Math.abs(steps[best] - v)) best = i;
        }
        if (offScale(from) && Math.abs(num(from.px) - v) < Math.abs(steps[best] - v)) {
            return { step: from.step, px: num(from.px) };
        }
        return { step: String(best), px: steps[best] };
    }

    function offScale(at) {
        return !!at && !/^[0-9]+$/.test(String(at.step));
    }

    // Where a change on a side `value` page pixels wide starts: its scale
    // step, or, for an automatic margin (`auto`), { step: 'auto', px } and
    // for a value off the scale { step: '', px }, as the band shows it.
    // Reading 186 px of `mx-auto` as step 5 made a drag write nothing
    // outwards and jump the element to the edge inwards.
    function startStep(value, steps, auto) {
        var v = Math.max(0, num(value));
        if (auto) return { step: 'auto', px: v };
        var n = nearestStep(v, steps);
        return Math.abs(n.px - v) <= 0.5 ? n : { step: '', px: v };
    }

    // The step an arrow key takes `at` ({ step, px }) to: a scale step as
    // keyStep(); a start off the scale goes to the next scale step that
    // way, and stays when there is none.
    function keyStepFrom(at, dir, steps) {
        if (!offScale(at)) {
            var s = keyStep(at.step, dir, steps.length);
            return { step: s, px: steps[parseInt(s, 10)] };
        }
        var px = num(at.px), pick = null;
        steps.forEach(function (p, i) {
            var ok = dir > 0 ? p > px : dir < 0 ? p < px : false;
            if (ok && (!pick || (dir > 0 ? p < pick.px : p > pick.px))) pick = { step: String(i), px: p };
        });
        return pick || at;
    }

    // The screen size a canvas `width` pixels wide stands for: 'xs' to 'xxl'.
    function sizeFor(width) {
        var w = num(width);
        for (var i = 0; i < SIZES.length; i++) {
            if (w >= SIZES[i][1]) return SIZES[i][0];
        }
        return 'xs';
    }

    // The `count` columns of a row box drawn over it: each column's content,
    // half a `gutter` (screen pixels) in from its column lines, as tall as
    // the row. None for a row with no width.
    function colGrid(row, count, gutter) {
        var n = count || 12, u = (row.right - row.left) / n, half = num(gutter) / 2, out = [];
        if (!(u > 0)) return out;
        for (var i = 0; i < n; i++) {
            out.push({ left: row.left + i * u + half, top: row.top, right: row.left + (i + 1) * u - half, bottom: row.bottom });
        }
        return out;
    }

    // The span a column's end edge dragged to `x` gives: the column's
    // content box `col` grows from its start column line to the nearest
    // line, at least 1 and at most `count` (a wider column falls to the next
    // line, as Bootstrap lays it out). `gutter` in screen pixels; `rtl`
    // counts from the right. 0 for a row with no width.
    function colSpan(col, x, row, count, gutter, rtl) {
        var n = count || 12, u = (row.right - row.left) / n;
        if (!(u > 0)) return 0;
        var raw = (rtl ? col.right - num(x) : num(x) - col.left) + num(gutter);
        return Math.max(1, Math.min(n, Math.round(raw / u)));
    }

    // The span a column `width` wide takes in a row `rowWidth` wide (both
    // the boxes between column lines): 1 to `count`.
    function colSpanOf(width, rowWidth, count) {
        var n = count || 12, u = num(rowWidth) / n;
        if (!(u > 0)) return 1;
        return Math.max(1, Math.min(n, Math.round(num(width) / u)));
    }

    // The gap a margin handle on `e` (top, bottom, left, right) changes
    // between the selection `sel` and its neighbour, and another gap among
    // the siblings `others` as wide (within `tol` page pixels, 0.5): only
    // boxes that share the selection's span across the dragged axis count.
    // { gap, match, where } with two lines { axis, from, to, at, value }
    // (value in page pixels), drawn beside the boxes; null when the dragged
    // side has no neighbour or no gap matches. `where` names the match:
    // above, below, left, right.
    function equalGap(sel, others, e, k, tol) {
        k = num(k) > 0 ? num(k) : 1;
        var t = tol === undefined ? 0.5 : num(tol);
        var y = e === 'top' || e === 'bottom';
        var lo = y ? 'top' : 'left', hi = y ? 'bottom' : 'right', a = y ? 'left' : 'top', z = y ? 'right' : 'bottom';
        var list = [sel].concat((others || []).filter(function (o) {
            return o && Math.min(o[z], sel[z]) - Math.max(o[a], sel[a]) > 0;
        }));
        list.sort(function (p, q) { return p[lo] - q[lo]; });
        var si = list.indexOf(sel), gaps = [], side = 0;
        list.forEach(function (box) { side = Math.max(side, box[z]); });
        for (var i = 0; i + 1 < list.length; i++) {
            var from = list[i][hi], to = list[i + 1][lo];
            gaps.push(to >= from ? { i: i, axis: y ? 'y' : 'x', from: from, to: to, at: side + 24, value: (to - from) / k } : null);
        }
        var d = gaps[(e === 'bottom' || e === 'right') ? si : si - 1];
        if (!d) return null;
        var best = null;
        gaps.forEach(function (g) {
            if (!g || g === d || Math.abs(g.value - d.value) > t) return;
            if (!best || Math.abs(g.i - d.i) < Math.abs(best.i - d.i)) best = g;
        });
        if (!best) return null;
        var line = function (g) { return { axis: g.axis, from: g.from, to: g.to, at: g.at, value: Math.round(g.value) }; };
        return {
            gap: line(d),
            match: line(best),
            where: best.i < d.i ? (y ? 'above' : 'left') : (y ? 'below' : 'right')
        };
    }

    // The step a drag shows when `held` (a step whose gap matched) is kept a
    // moment: it stays until `nearest` is closer to `raw` page pixels by
    // more than `extra`.
    function hold(raw, held, nearest, extra) {
        if (!held || held.step === nearest.step) return held || nearest;
        return Math.abs(num(raw) - held.px) < Math.abs(num(raw) - nearest.px) + num(extra) ? held : nearest;
    }

    // A CSS length's scale on the layer: `k` scales the canvas' rectangles
    // (_sdBandsGeo()), and where the browser answers them already zoomed
    // (Chromium: the <html> box `rectWidth` on screen for `cssWidth` CSS
    // pixels) a margin or padding read in CSS pixels takes that ratio too.
    function lengthScale(k, rectWidth, cssWidth) {
        k = num(k) > 0 ? num(k) : 1;
        var r = num(rectWidth) / num(cssWidth);
        return k * (isFinite(r) && r > 0 ? r : 1);
    }

    window.PGCanvasBands = {
        colGrid: colGrid,
        colSpan: colSpan,
        colSpanOf: colSpanOf,
        equalGap: equalGap,
        hold: hold,
        HANDLE: HANDLE,
        edge: edge,
        layout: layout,
        lengthScale: lengthScale,
        growth: growth,
        keyStep: keyStep,
        keyStepFrom: keyStepFrom,
        startStep: startStep,
        reach: reach,
        labelAt: labelAt,
        sizeFor: sizeFor,
        scale: scale,
        nearestStep: nearestStep
    };
})();
