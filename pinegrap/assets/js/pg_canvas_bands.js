/**
 * Pinegrap - Enterprise Website Platform
 *
 * The spacing bands of the visual designer's canvas guides
 * (window.PGCanvasBands): where the selection's outer spacing (margin) and
 * inner spacing (padding) are drawn as bands around and inside its frame,
 * where each band's handle sits, how far a dragged or stepped handle
 * changes a side, which sides Shift and Alt add, where the value label goes
 * and which screen size the canvas width stands for. Pure: plain boxes and
 * numbers in, plain objects out; no element, no listener.
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

    // The handle's thickness in screen pixels: a faint inner handle sits
    // this far inside the frame so it does not cover the outer one.
    var HANDLE = 6;
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
        var outer = [], inner = [];
        ['t', 'e', 'b', 's'].forEach(function (side) {
            var e = edge(side, rtl);
            var mo = m[e] * k, pi = p[e] * k, band, hb, at;
            if (e === 'top') {
                band = { left: b.left, top: b.top - mo, right: b.right, bottom: b.top };
                at = { x: midX, y: band.top, axis: 'x' };
                hb = { left: b.left, top: b.top, right: b.right, bottom: inTop };
                inner.push(item('p', side, e, hb, { x: midX, y: pi > 0 ? inTop : b.top + HANDLE, axis: 'x' }, pi));
            } else if (e === 'bottom') {
                band = { left: b.left, top: b.bottom, right: b.right, bottom: b.bottom + mo };
                at = { x: midX, y: band.bottom, axis: 'x' };
                hb = { left: b.left, top: inBottom, right: b.right, bottom: b.bottom };
                inner.push(item('p', side, e, hb, { x: midX, y: pi > 0 ? inBottom : b.bottom - HANDLE, axis: 'x' }, pi));
            } else if (e === 'left') {
                band = { left: b.left - mo, top: b.top, right: b.left, bottom: b.bottom };
                at = { x: band.left, y: midY, axis: 'y' };
                hb = { left: b.left, top: inTop, right: b.left + pi, bottom: inBottom };
                inner.push(item('p', side, e, hb, { x: pi > 0 ? hb.right : b.left + HANDLE, y: midY, axis: 'y' }, pi));
            } else {
                band = { left: b.right, top: b.top, right: b.right + mo, bottom: b.bottom };
                at = { x: band.right, y: midY, axis: 'y' };
                hb = { left: b.right - pi, top: inTop, right: b.right, bottom: inBottom };
                inner.push(item('p', side, e, hb, { x: pi > 0 ? hb.left : b.right - HANDLE, y: midY, axis: 'y' }, pi));
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

    // The screen size a canvas `width` pixels wide stands for: 'xs' to 'xxl'.
    function sizeFor(width) {
        var w = num(width);
        for (var i = 0; i < SIZES.length; i++) {
            if (w >= SIZES[i][1]) return SIZES[i][0];
        }
        return 'xs';
    }

    window.PGCanvasBands = {
        HANDLE: HANDLE,
        edge: edge,
        layout: layout,
        growth: growth,
        keyStep: keyStep,
        reach: reach,
        labelAt: labelAt,
        sizeFor: sizeFor
    };
})();
