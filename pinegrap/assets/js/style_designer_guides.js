/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the guides part: Measure on the canvas. With one
 * element selected, holding Alt and pointing at another element draws the
 * distances between their edges in page pixels; pointing at an element that
 * holds the selection draws the four inner distances. The lines sit in a
 * layer of the editor's own over the canvas frame, never in the canvas'
 * page, so nothing enters the tree, the saved markup or the Preview.
 * Measure changes no value.
 *
 * The numbers come from window.PGCanvasGuides (assets/js/pg_canvas_guides.js,
 * the first file of this part). Not needed to open a page: the editor runs
 * this part the first time Alt is pressed with one element selected
 * (_sdPartLoad(), pg_designer_parts()). It reaches the editor only through
 * the kit (_sdPartKit()) and the host handed to measure(). While nothing is
 * measured it holds no listener.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2026 Erdal Güral. All rights reserved.
 * @license     Proprietary, see license-editors.txt
 */
StyleDesigner.definePart('guides', function (K) {
    'use strict';

    var G = window.PGCanvasGuides;

    // A number narrower than its line by less than this sits beside it.
    var NUMBER_ROOM = 30;

    // The measure under way: the host handed by the editor, the element
    // pointed at, the layer and the windows listened to.
    var M = null;

    // The part of the canvas frame in view, in the coordinates of the
    // canvas wrapper the layer is put in, and how an element's rectangle
    // inside the frame is turned into the layer's pixels: `k` scales it
    // (the zoom on the canvas' <html> where the rectangle is not zoomed yet,
    // and the Fit scale) and `zoom` turns the layer's pixels back into the
    // page's CSS pixels for the numbers.
    function view(doc) {
        var win = doc && doc.defaultView;
        var frame = win ? win.frameElement : null;
        var area = frame && frame.closest ? frame.closest('.sd-iframe-area') : null;
        var host = area ? area.closest('.sd-canvas-wrapper') : null;
        if (!host || !doc.documentElement) return null;
        var fr = frame.getBoundingClientRect();
        var ar = area.getBoundingClientRect();
        var hr = host.getBoundingClientRect();
        var left = Math.max(fr.left, ar.left + area.clientLeft);
        var top = Math.max(fr.top, ar.top + area.clientTop);
        var w = Math.min(fr.right, ar.left + area.clientLeft + area.clientWidth) - left;
        var h = Math.min(fr.bottom, ar.top + area.clientTop + area.clientHeight) - top;
        if (w <= 0 || h <= 0) return null;
        var docEl = doc.documentElement;
        var cssZoom = parseFloat(docEl.style.zoom) || 1;
        // Browsers that follow the standard CSS zoom answer rectangles
        // already zoomed: the <html> box is then its client width times the
        // zoom. Older ones answer the page's pixels and draw the zoom on top.
        var inPage = Math.abs(docEl.getBoundingClientRect().width - docEl.clientWidth * cssZoom) >= 2;
        var k = inPage ? cssZoom : 1;
        if (frame.offsetWidth > 0) k *= fr.width / frame.offsetWidth;
        return {
            host: host,
            left: left - hr.left,
            top: top - hr.top,
            width: w,
            height: h,
            ox: fr.left - left,
            oy: fr.top - top,
            k: k,
            zoom: k * (inPage ? 1 : cssZoom)
        };
    }

    // An element's rectangle inside the frame in the layer's pixels.
    function place(r, v) {
        if (!r) return null;
        return {
            left: v.ox + r.left * v.k,
            top: v.oy + r.top * v.k,
            right: v.ox + r.right * v.k,
            bottom: v.oy + r.bottom * v.k
        };
    }

    function el(cls, css) {
        var d = document.createElement('div');
        d.className = cls;
        d.style.cssText = css;
        return d;
    }

    function px(n) {
        return Math.round(n * 10) / 10 + 'px';
    }

    // A measure line with its caps and its number. A line of no length
    // draws the shared edge over `span` (its cross range) and no number.
    function drawLine(out, l, span) {
        var len = l.to - l.from;
        if (l.value === 0 || len < 0.5) {
            if (!span || span[1] <= span[0]) return;
            out.push(l.axis === 'x'
                ? el('sd-gm-edge', 'left:' + px(l.from) + ';top:' + px(span[0]) + ';width:1px;height:' + px(span[1] - span[0]) + ';')
                : el('sd-gm-edge', 'left:' + px(span[0]) + ';top:' + px(l.from) + ';width:' + px(span[1] - span[0]) + ';height:1px;'));
            return;
        }
        out.push(l.axis === 'x'
            ? el('sd-gm-line sd-gm-h', 'left:' + px(l.from) + ';top:' + px(l.at) + ';width:' + px(len) + ';')
            : el('sd-gm-line sd-gm-v', 'left:' + px(l.at) + ';top:' + px(l.from) + ';height:' + px(len) + ';'));
        var mid = (l.from + l.to) / 2;
        var beside = len < NUMBER_ROOM;
        var num = l.axis === 'x'
            ? el('sd-gm-num' + (beside ? ' sd-gm-below' : ''), 'left:' + px(mid) + ';top:' + px(l.at) + ';')
            : el('sd-gm-num' + (beside ? ' sd-gm-aside' : ''), 'left:' + px(l.at) + ';top:' + px(mid) + ';');
        num.textContent = String(l.value);
        out.push(num);
    }

    // The dashed guide of a line to the other element's edge.
    function drawGuide(out, axis, from, to, at) {
        var lo = Math.min(from, to), len = Math.abs(to - from);
        if (len < 0.5) return;
        out.push(axis === 'x'
            ? el('sd-gm-ext sd-gm-h', 'left:' + px(lo) + ';top:' + px(at) + ';width:' + px(len) + ';')
            : el('sd-gm-ext sd-gm-v', 'left:' + px(at) + ';top:' + px(lo) + ';height:' + px(len) + ';'));
    }

    function overlap(a1, a2, b1, b2) {
        return [Math.max(a1, b1), Math.min(a2, b2)];
    }

    // The four inner distances of `child` in `parent`; a side of 0 draws
    // the shared edge along the child.
    function drawInner(out, child, parent, zoom) {
        var d = G.inner(child, parent, zoom, false);
        if (!d) return;
        ['t', 'b', 's', 'e'].forEach(function (side) {
            var s = d[side];
            if (s.value < 0) return;
            drawLine(out, s.line, s.line.axis === 'x' ? [child.top, child.bottom] : [child.left, child.right]);
        });
    }

    // Lines between the selection `a` and the element pointed at `b`.
    function drawApart(out, a, b, zoom) {
        G.distances(a, b, zoom).forEach(function (l) {
            var span = l.axis === 'x' ? overlap(a.top, a.bottom, b.top, b.bottom) : overlap(a.left, a.right, b.left, b.right);
            drawLine(out, l, span);
            if (l.reach !== null && l.reach !== undefined && l.value !== 0) {
                // Off diagonally: from the line's end on b's side to b's edge.
                var end = (l.axis === 'x' ? (b.left >= a.right) : (b.top >= a.bottom)) ? l.to : l.from;
                drawGuide(out, l.axis === 'x' ? 'y' : 'x', l.at, l.reach, end);
            }
        });
        var o = G.offset(a, b, zoom);
        if (o) {
            drawLine(out, o, null);
            drawGuide(out, o.guide.axis, o.guide.from, o.guide.to, o.guide.at);
        }
    }

    function clear() {
        if (M && M.layer) M.layer.hidden = true;
    }

    function draw() {
        if (!M) return;
        M.frame = 0;
        var sel = M.host.selected();
        var hov = M.target;
        if (!sel || !hov || hov === sel || !hov.isConnected || !sel.isConnected) return clear();
        var v = view(M.host.doc);
        if (!v) return clear();
        var a = place(G.box(M.host.rectOf(sel)), v);
        var b = place(G.box(M.host.rectOf(hov)), v);
        if (!a || !b) return clear();
        var out = [];
        out.push(el('sd-gm-hov', 'left:' + px(b.left) + ';top:' + px(b.top) + ';width:' + px(b.right - b.left) + ';height:' + px(b.bottom - b.top) + ';'));
        var name = M.host.nameOf(hov);
        if (name) {
            var tag = el('sd-gm-tag', 'left:' + px(b.left) + ';top:' + px(b.top) + ';');
            tag.textContent = name;
            out.push(tag);
        }
        if (hov.contains(sel)) drawInner(out, a, b, v.zoom);
        else if (sel.contains(hov)) drawInner(out, b, a, v.zoom);
        else drawApart(out, a, b, v.zoom);
        if (!M.layer) {
            M.layer = document.createElement('div');
            M.layer.className = 'sd-gm-layer';
            M.layer.setAttribute('aria-hidden', 'true');
        }
        var layer = M.layer;
        if (layer.parentNode !== v.host) v.host.appendChild(layer);
        layer.style.left = px(v.left);
        layer.style.top = px(v.top);
        layer.style.width = px(v.width);
        layer.style.height = px(v.height);
        layer.textContent = '';
        out.forEach(function (n) { layer.appendChild(n); });
        layer.hidden = false;
    }

    // One draw per frame however fast the pointer moves.
    function soon() {
        if (M && !M.frame) M.frame = requestAnimationFrame(draw);
    }

    // The canvas element a pointer is over: its canvas wrapper.
    function wrapOf(node) {
        while (node && node.nodeType !== 1) node = node.parentNode;
        return node && node.closest ? node.closest('[data-sd-id]') : null;
    }

    function onMove(e) {
        var w = wrapOf(e.target);
        if (w !== M.target) {
            M.target = w;
            soon();
        }
    }

    function onOut(e) {
        // The pointer left the canvas.
        if (!e.relatedTarget) {
            M.target = null;
            soon();
        }
    }

    function onKeyUp(e) {
        if (e.key === 'Alt' || !e.altKey) stop();
    }

    function listen(on) {
        var doc = M.host.doc;
        var win = doc.defaultView;
        var fn = on ? 'addEventListener' : 'removeEventListener';
        doc[fn]('mousemove', onMove, true);
        doc[fn]('mouseout', onOut, true);
        // A press ends Measure: Alt and a drag copies the element.
        doc[fn]('mousedown', stop, true);
        [window, win].forEach(function (w) {
            if (!w) return;
            w[fn]('keyup', onKeyUp, true);
            w[fn]('blur', stop);
        });
        if (win) win[fn]('scroll', soon, { passive: true, capture: true });
        window[fn]('resize', soon);
    }

    // Starts measuring from the element under the pointer. `host` is
    // { doc, selected(), rectOf(wrap), nameOf(wrap) }: the canvas document,
    // the selected element's wrapper (null once the selection is not one
    // element of that document), its rectangle and its name.
    function measure(host) {
        if (!G || !host || !host.doc || !host.doc.defaultView) return;
        if (M && M.host.doc === host.doc) {
            M.host = host;
            return;
        }
        stop();
        var over = host.doc.querySelectorAll(':hover');
        M = { host: host, target: over.length ? wrapOf(over[over.length - 1]) : null, layer: null, frame: 0 };
        listen(true);
        draw();
    }

    function stop() {
        if (!M) return;
        listen(false);
        if (M.frame) cancelAnimationFrame(M.frame);
        if (M.layer && M.layer.parentNode) M.layer.parentNode.removeChild(M.layer);
        M = null;
    }

    return { measure: measure, stop: stop };
});
