/**
 * Pinegrap - Enterprise Website Platform
 *
 * The graph canvas core (window.PGGraphCanvas): what a box-and-arrow canvas
 * needs whatever its boxes mean. Boxes sit on a layer moved and scaled with
 * one CSS transform, at canvas units snapped to a 16 px grid; arrows are
 * cubic curves from a box's right edge to the next box's left one.
 *
 * Its first user is the Workspace flow canvas (assets/js/workspace_flow.js),
 * which keeps what a flow's boxes are, their forms, saving and runs, and
 * hands the core what it needs through small adapters:
 *
 *  - the geometry helpers (snap, clamp, svgEl, curve, freeId, typing, bytes);
 *  - the undo history of a graph kept as text (historyOf, historyRecord,
 *    historyStep);
 *  - viewport(host): pan, zoom about a point, fit, pinch and the wheel on a
 *    viewport element, its layer and its zoom label, reading and writing the
 *    view { x, y, k } through the host.
 *
 * Served minified as assets/js/pg_graph_canvas.min.js (pg_js_bundles(),
 * includes/fn/output.php), printed before the script that uses it; after
 * editing run `php tools/build_js.php`.
 *
 * @author      Erdal Güral (Kodpen)
 * @link        https://kodpen.com
 * @copyright   2017–2026 Kodpen
 * @license     https://opensource.org/licenses/mit-license.html MIT License
 */

(function () {
    'use strict';

    var GRID = 16;
    var BOX_W = 208;
    var BOX_H = 80;
    var ZOOM_MIN = 0.25;
    var ZOOM_MAX = 2;
    var SVG_NS = 'http://www.w3.org/2000/svg';
    // Undo goes back at most HISTORY_MAX steps; a change that is not
    // structural made within HISTORY_FOLD ms of the one before it (typing,
    // an arrow-key move) folds into that step.
    var HISTORY_MAX = 50;
    var HISTORY_FOLD = 1000;

    // ── Small helpers ──────────────────────────────────────────────────

    function snap(value) {
        return Math.round(value / GRID) * GRID;
    }

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function svgEl(tag, className) {
        var node = document.createElementNS(SVG_NS, tag);

        if (className) {
            node.setAttribute('class', className);
        }

        return node;
    }

    // An arrow from (x1, y1) to (x2, y2): its path and its middle, where
    // its label goes.
    function curve(x1, y1, x2, y2) {
        var dx = Math.max(48, Math.abs(x2 - x1) / 2);

        return { d: 'M' + x1 + ' ' + y1 + ' C' + (x1 + dx) + ' ' + y1 + ' ' + (x2 - dx) + ' ' + y2 + ' ' + x2 + ' ' + y2, mx: (x1 + x2) / 2, my: (y1 + y2) / 2 };
    }

    function freeId(prefix, list) {
        var n = 1;

        while (list.some(function (item) { return item.id === prefix + '_' + n; })) {
            n++;
        }

        return prefix + '_' + n;
    }

    function typing(target) {
        return !!(target && target.closest && target.closest('input, textarea, select, [contenteditable="true"]'));
    }

    function bytes(text) {
        return new Blob([text]).size;
    }

    // ── Undo and redo ──────────────────────────────────────────────────

    // The steps of one graph as JSON text: before each change (past), those
    // undone (future), and the graph as it is (now).
    function historyOf(flow, graph) {
        return { flow: flow, past: [], future: [], now: graph, at: 0, fold: false };
    }

    // The graph after a change; false when it is the same (the flow's name,
    // its home). A change undone cannot be redone once another one is made.
    function historyRecord(history, graph, fold, at) {
        if (graph === history.now) {
            return false;
        }

        if (!(fold && history.fold && ((at - history.at) < HISTORY_FOLD))) {
            history.past.push(history.now);

            if (history.past.length > HISTORY_MAX) {
                history.past.shift();
            }
        }

        history.future = [];
        history.now = graph;
        history.at = at;
        history.fold = fold;

        return true;
    }

    // One step back (or forward again): the graph to show, null when there
    // is none. The change after it starts a step of its own.
    function historyStep(history, back) {
        var from = back ? history.past : history.future;

        if (!from.length) {
            return null;
        }

        (back ? history.future : history.past).push(history.now);
        history.now = from.pop();
        history.fold = false;

        return history.now;
    }

    // ── The viewport ───────────────────────────────────────────────────

    // Pan and zoom of one canvas. The host gives the elements as they are
    // when called (they may be drawn after the viewport is made):
    // host.ui.viewport, host.ui.layer and host.ui.zoomLabel; host.view()
    // reads the view { x, y, k }, host.view(view) puts a new one in its
    // place; host.items() lists the boxes ({ x, y }) fit() brings into sight.
    function viewport(host) {
        function apply() {
            var ui = host.ui;
            var v = host.view();
            var dots = GRID * v.k;

            // Zoomed far out, every fourth dot of the grid is drawn.
            while (dots < 8) {
                dots *= 4;
            }

            ui.layer.style.transform = 'translate(' + v.x + 'px, ' + v.y + 'px) scale(' + v.k + ')';
            ui.viewport.style.backgroundSize = dots + 'px ' + dots + 'px';
            ui.viewport.style.backgroundPosition = v.x + 'px ' + v.y + 'px';
            ui.zoomLabel.textContent = Math.round(v.k * 100) + '%';
        }

        function zoomAt(clientX, clientY, factor) {
            var rect = host.ui.viewport.getBoundingClientRect();
            var v = host.view();
            var k = clamp(v.k * factor, ZOOM_MIN, ZOOM_MAX);
            var px = clientX - rect.left;
            var py = clientY - rect.top;

            v.x = px - (px - v.x) * (k / v.k);
            v.y = py - (py - v.y) * (k / v.k);
            v.k = k;
            apply();
        }

        function zoomBy(factor) {
            var rect = host.ui.viewport.getBoundingClientRect();

            zoomAt(rect.left + rect.width / 2, rect.top + rect.height / 2, factor);
        }

        // Everything on the canvas in sight, at most a little larger than life.
        function fit() {
            var items = host.items();
            var rect = host.ui.viewport.getBoundingClientRect();

            if (!items.length || !rect.width) {
                host.view({ x: 40, y: 40, k: 1 });
                apply();
                return;
            }

            var minX = Math.min.apply(null, items.map(function (item) { return item.x; }));
            var minY = Math.min.apply(null, items.map(function (item) { return item.y; }));
            var maxX = Math.max.apply(null, items.map(function (item) { return item.x + BOX_W; }));
            var maxY = Math.max.apply(null, items.map(function (item) { return item.y + BOX_H; }));
            var k = clamp(Math.min((rect.width - 96) / Math.max(1, maxX - minX), (rect.height - 96) / Math.max(1, maxY - minY)), ZOOM_MIN, 1.25);

            host.view({ x: (rect.width - (maxX - minX) * k) / 2 - minX * k, y: (rect.height - (maxY - minY) * k) / 2 - minY * k, k: k });
            apply();
        }

        function point(clientX, clientY) {
            var rect = host.ui.viewport.getBoundingClientRect();
            var v = host.view();

            return { x: (clientX - rect.left - v.x) / v.k, y: (clientY - rect.top - v.y) / v.k };
        }

        // A box ({ x, y }) brought into the middle when it is outside the
        // viewport or within 32 px of its edge.
        function reveal(item) {
            var rect = host.ui.viewport.getBoundingClientRect();
            var v = host.view();
            var left = item.x * v.k + v.x;
            var top = item.y * v.k + v.y;
            var margin = 32;

            if ((left < margin) || (top < margin) || (left + BOX_W * v.k > rect.width - margin) || (top + BOX_H * v.k > rect.height - margin)) {
                v.x = rect.width / 2 - (item.x + BOX_W / 2) * v.k;
                v.y = rect.height / 2 - (item.y + BOX_H / 2) * v.k;
                apply();
            }
        }

        // Ctrl+wheel zooms about the pointer, the wheel alone pans.
        function wheel(event) {
            event.preventDefault();

            if (event.ctrlKey || event.metaKey) {
                zoomAt(event.clientX, event.clientY, Math.exp(-event.deltaY * 0.002));
            } else {
                var v = host.view();

                v.x -= event.deltaX;
                v.y -= event.deltaY;
                apply();
            }
        }

        // A pinch starting with two pointers ({ x, y } in client units).
        function pinchStart(points) {
            var v = host.view();

            return {
                d: Math.max(1, Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y)),
                cx: (points[0].x + points[1].x) / 2,
                cy: (points[0].y + points[1].y) / 2,
                view: { x: v.x, y: v.y, k: v.k }
            };
        }

        // The pinch's two pointers moved: the point under their middle at
        // the start stays under it, scaled by how far apart they are now.
        function pinchMove(pinch, points) {
            var k = clamp(pinch.view.k * Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y) / pinch.d, ZOOM_MIN, ZOOM_MAX);
            var rect = host.ui.viewport.getBoundingClientRect();
            var cx = (points[0].x + points[1].x) / 2 - rect.left;
            var cy = (points[0].y + points[1].y) / 2 - rect.top;
            var px = (pinch.cx - rect.left - pinch.view.x) / pinch.view.k;
            var py = (pinch.cy - rect.top - pinch.view.y) / pinch.view.k;

            host.view({ x: cx - px * k, y: cy - py * k, k: k });
            apply();
        }

        return { apply: apply, zoomAt: zoomAt, zoomBy: zoomBy, fit: fit, point: point, reveal: reveal, wheel: wheel, pinchStart: pinchStart, pinchMove: pinchMove };
    }

    window.PGGraphCanvas = {
        GRID: GRID,
        BOX_W: BOX_W,
        BOX_H: BOX_H,
        ZOOM_MIN: ZOOM_MIN,
        ZOOM_MAX: ZOOM_MAX,
        snap: snap,
        clamp: clamp,
        svgEl: svgEl,
        curve: curve,
        freeId: freeId,
        typing: typing,
        bytes: bytes,
        historyOf: historyOf,
        historyRecord: historyRecord,
        historyStep: historyStep,
        viewport: viewport
    };
})();
