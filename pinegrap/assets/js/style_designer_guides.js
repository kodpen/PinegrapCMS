/**
 * Pinegrap - Enterprise Website Platform
 *
 * Visual Page Editor - the guides part: Measure and the layout lines on the
 * canvas. With one element selected, holding Alt and pointing at another
 * element draws the distances between their edges in page pixels; pointing
 * at an element that holds the selection draws the four inner distances.
 * Shift+G draws the page's container, its twelve columns and the gutters
 * over the whole canvas until it is pressed again. The lines sit in a
 * layer of the editor's own over the canvas frame, never in the canvas'
 * page, so nothing enters the tree, the saved markup or the Preview.
 * Measure changes no value.
 *
 * The numbers come from window.PGCanvasGuides (assets/js/pg_canvas_guides.js,
 * the first file of this part). Not needed to open a page: the editor runs
 * this part the first time Alt is pressed with one element selected, or
 * Shift+G (_sdPartLoad(), pg_designer_parts()). It reaches the editor only through
 * the kit (_sdPartKit()) and the hosts handed to measure() and lines().
 * While nothing is measured or lined it holds no listener.
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
    // page's CSS pixels for the numbers; `rect` is a rectangle's pixels per
    // CSS pixel.
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
        // Chromium answers rectangles inside the zoomed canvas already
        // zoomed, other browsers in the page's pixels: the <html> box's
        // width on screen against its CSS width tells which (G.zoomScales()).
        var sc = G.zoomScales(parseFloat(docEl.style.zoom) || 1, frame.offsetWidth > 0 ? fr.width / frame.offsetWidth : 1,
            docEl.getBoundingClientRect().width, docEl.offsetWidth);
        return {
            host: host,
            left: left - hr.left,
            top: top - hr.top,
            width: w,
            height: h,
            ox: fr.left - left,
            oy: fr.top - top,
            k: sc.k,
            zoom: sc.zoom,
            rect: sc.rect
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

    // ── Layout lines ────────────────────────────────────────────────────
    // The page's container, its columns and gutters over the whole canvas,
    // in a layer of their own beside Measure's. `L` is set only while they
    // show; the listeners (the canvas' scroll and resize, the editor's
    // resize, Escape) live only as long.
    var L = null;
    var CONTAINER = '.container, .container-sm, .container-md, .container-lg, .container-xl, .container-xxl';

    // A custom property's length in page px ('1.5rem', '24px', '2em'); a
    // custom property is read as written, not resolved. Undefined when it
    // is none of those.
    function lengthPx(text, doc, cs) {
        var m = /^\s*(-?[\d.]+)(px|rem|em)?\s*$/.exec(String(text || ''));
        if (!m) return undefined;
        var n = parseFloat(m[1]);
        if (!(n >= 0)) return undefined;
        if (m[2] === 'rem') return n * (parseFloat(doc.defaultView.getComputedStyle(doc.documentElement).fontSize) || 16);
        if (m[2] === 'em') return n * (parseFloat(cs.fontSize) || 16);
        return n;
    }

    // The first container on the page that has a width, in the frame's
    // space with its paddings, and the gutter in page px; the page's own
    // values, so a design that changed them is drawn as it is.
    function pageGrid(doc) {
        var win = doc.defaultView;
        var list = doc.body ? doc.body.querySelectorAll(CONTAINER) : [];
        for (var i = 0; i < list.length; i++) {
            var c = list[i];
            if (!c.offsetWidth) continue;
            var r = c.getBoundingClientRect();
            var s = r.width / c.offsetWidth;
            var cs = win.getComputedStyle(c);
            return {
                container: {
                    left: r.left,
                    right: r.right,
                    padLeft: (parseFloat(cs.paddingLeft) || 0) * s,
                    padRight: (parseFloat(cs.paddingRight) || 0) * s
                },
                gutter: lengthPx(cs.getPropertyValue('--bs-gutter-x'), doc, cs)
            };
        }
        return null;
    }

    function linesDraw() {
        if (!L) return;
        L.frame = 0;
        var doc = L.host.doc;
        var v = view(doc);
        var docEl = doc.documentElement;
        if (!v || !docEl || !docEl.clientWidth) {
            if (L.layer) L.layer.hidden = true;
            return;
        }
        var page = docEl.getBoundingClientRect();
        // A rectangle's pixels per CSS pixel; the page is as wide as its
        // <html> box, which a zoom lays out narrower in CSS pixels.
        var s = v.rect;
        var found = pageGrid(doc);
        var root = parseFloat(doc.defaultView.getComputedStyle(docEl).fontSize) || 16;
        var g = G.layout({ left: page.left, top: 0, right: page.left + (docEl.offsetWidth || docEl.clientWidth) * s, bottom: 1 }, {
            zoom: s,
            root: root,
            gutter: found ? found.gutter : undefined,
            container: found ? found.container : null,
            rtl: !!L.host.rtl
        });
        if (!g) return;
        var out = [];
        var x = function (n) { return v.ox + n * v.k; };
        g.columns.forEach(function (c) {
            out.push(el('sd-gl-col', 'left:' + px(x(c.left)) + ';width:' + px((c.right - c.left) * v.k) + ';'));
        });
        out.push(el('sd-gl-edge', 'left:' + px(x(g.left)) + ';'));
        out.push(el('sd-gl-edge', 'left:' + px(x(g.right) - 1) + ';'));
        if (!L.layer) {
            L.layer = document.createElement('div');
            L.layer.className = 'sd-gl-layer';
            L.layer.setAttribute('aria-hidden', 'true');
        }
        var layer = L.layer;
        if (layer.parentNode !== v.host) v.host.appendChild(layer);
        layer.style.left = px(v.left);
        layer.style.top = px(v.top);
        layer.style.width = px(v.width);
        layer.style.height = px(v.height);
        layer.textContent = '';
        out.forEach(function (n) { layer.appendChild(n); });
        layer.hidden = false;
    }

    function linesSoon() {
        if (L && !L.frame) L.frame = requestAnimationFrame(linesDraw);
    }

    // Escape ends the lines and only that, outside a text being typed or a
    // window over the editor.
    function linesEsc(e) {
        if (e.key !== 'Escape' || !L) return;
        var t = e.target;
        if (t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName || '') || (t.closest && t.closest('dialog')))) return;
        e.preventDefault();
        e.stopPropagation();
        var ended = L.host.ended;
        linesOff();
        if (ended) ended();
    }

    function linesListen(on) {
        var win = L.win;
        var fn = on ? 'addEventListener' : 'removeEventListener';
        [window, win].forEach(function (w) {
            if (!w) return;
            w[fn]('keydown', linesEsc, true);
            w[fn]('resize', linesSoon);
        });
        if (win) win[fn]('scroll', linesSoon, { passive: true, capture: true });
    }

    // Shows the lines, or draws them again for the canvas `host.doc` (a
    // canvas drawn again, a zoom, a size of the bar). `host` is { doc, rtl,
    // ended() }.
    function lines(host) {
        if (!G || !host || !host.doc || !host.doc.defaultView) return;
        if (L && L.win !== host.doc.defaultView) linesOff();
        if (!L) {
            L = { host: host, win: host.doc.defaultView, layer: null, frame: 0 };
            linesListen(true);
        }
        L.host = host;
        linesSoon();
    }

    function linesOff() {
        if (!L) return;
        linesListen(false);
        if (L.frame) cancelAnimationFrame(L.frame);
        if (L.layer && L.layer.parentNode) L.layer.parentNode.removeChild(L.layer);
        L = null;
    }

    return { measure: measure, stop: stop, lines: lines, linesOff: linesOff };
});
