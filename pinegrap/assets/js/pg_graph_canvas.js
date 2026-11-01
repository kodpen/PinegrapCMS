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
 *    view { x, y, k } through the host;
 *  - create(adapter): a canvas's pointer and keys on top of its viewport:
 *    pan, pinch, Space, the zoom keys, and the drags the adapter's own
 *    kinds make (moving a box, drawing an arrow), its listeners removed
 *    again by destroy().
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

    // ── The canvas: pointer and keys ───────────────────────────────────

    // One canvas's pan, zoom, pinch and keys, and the pointer plumbing its
    // user builds on. The adapter gives:
    //  - ui (viewport, layer, zoomLabel), view(next) and items() as
    //    viewport() takes them;
    //  - ownClicks: a selector of what keeps its own clicks and keys (a link
    //    on a box): the canvas takes no pointer and no key from it;
    //  - classes: { panning, space }, put on the viewport while it pans and
    //    while Space is held;
    //  - press(event, drag, space): a pointer went down on the canvas; it
    //    sets drag.kind ('pan' unless it says otherwise) and what its own
    //    kinds need, or answers false when it took the press itself (the
    //    pointer is then forgotten);
    //  - move(event, drag, dx, dy): a drag of the adapter's kind moved past
    //    4 px from where it started;
    //  - release(event, drag): the drag's pointer went up (or was cancelled
    //    by the browser: event.type says which);
    //  - cancel(drag): a drag was cut short (Esc, a second finger); drag is
    //    null when there was none;
    //  - key(event): a key on the canvas, first; true when it handled it.
    //    Arrows the adapter does not take pan, + and − zoom, 0 fits.
    function create(adapter) {
        var ui = adapter.ui;
        var view = viewport({ ui: ui, view: adapter.view, items: adapter.items });
        var pointers = {};
        var pinch = null;
        var drag = null;
        var space = false;
        var bound = [];

        function listen(target, type, handler, options) {
            target.addEventListener(type, handler, options);
            bound.push([target, type, handler, options]);
        }

        function own(target) {
            return !!(adapter.ownClicks && target.closest && target.closest(adapter.ownClicks));
        }

        function points() {
            return Object.keys(pointers).map(function (id) { return pointers[id]; });
        }

        function pointerDown(event) {
            pointers[event.pointerId] = { x: event.clientX, y: event.clientY };

            // A second finger: the drag so far becomes a pinch.
            if (Object.keys(pointers).length === 2) {
                var both = points();

                cancel();
                pinch = view.pinchStart(both);
                return;
            }

            if ((event.button !== undefined) && (event.button > 1)) {
                return;
            }

            if (own(event.target)) {
                delete pointers[event.pointerId];
                return;
            }

            var v = adapter.view();
            var next = { id: event.pointerId, sx: event.clientX, sy: event.clientY, moved: false, kind: 'pan', vx: v.x, vy: v.y };

            if (adapter.press(event, next, space) === false) {
                delete pointers[event.pointerId];
                return;
            }

            drag = next;

            try {
                ui.viewport.setPointerCapture(event.pointerId);
            } catch (error) {
                // A pointer that cannot be captured still drags while it stays on the canvas.
            }

            if (drag.kind === 'pan') {
                ui.viewport.classList.add(adapter.classes.panning);
            }
        }

        function pointerMove(event) {
            if (pointers[event.pointerId]) {
                pointers[event.pointerId] = { x: event.clientX, y: event.clientY };
            }

            if (pinch) {
                var both = points();

                if (both.length < 2) {
                    return;
                }

                view.pinchMove(pinch, both);
                return;
            }

            if (!drag || (drag.id !== event.pointerId)) {
                return;
            }

            var dx = event.clientX - drag.sx;
            var dy = event.clientY - drag.sy;

            if (!drag.moved && (Math.abs(dx) + Math.abs(dy) < 4)) {
                return;
            }

            drag.moved = true;

            if (drag.kind === 'pan') {
                var v = adapter.view();

                v.x = drag.vx + dx;
                v.y = drag.vy + dy;
                view.apply();
            } else {
                adapter.move(event, drag, dx, dy);
            }
        }

        function pointerUp(event) {
            delete pointers[event.pointerId];

            if (pinch) {
                if (Object.keys(pointers).length < 2) {
                    pinch = null;
                }

                return;
            }

            var done = drag;

            if (!done || (done.id !== event.pointerId)) {
                return;
            }

            drag = null;
            ui.viewport.classList.remove(adapter.classes.panning);
            adapter.release(event, done);
        }

        function cancel() {
            var done = drag;

            drag = null;
            ui.viewport.classList.remove(adapter.classes.panning);
            adapter.cancel(done);
        }

        function key(event) {
            if (typing(event.target) || event.altKey || own(event.target)) {
                return;
            }

            var name = event.key;
            var step = (event.shiftKey ? GRID * 4 : GRID) * 2;
            var handled = !!adapter.key(event);
            var v = adapter.view();

            if (handled) {
                // The adapter's own key.
            } else if ((name === 'ArrowLeft') || (name === 'ArrowRight') || (name === 'ArrowUp') || (name === 'ArrowDown')) {
                v.x -= (name === 'ArrowLeft') ? -step : (name === 'ArrowRight') ? step : 0;
                v.y -= (name === 'ArrowUp') ? -step : (name === 'ArrowDown') ? step : 0;
                view.apply();
                handled = true;
            } else if ((name === '+') || (name === '=')) {
                view.zoomBy(1.2);
                handled = true;
            } else if ((name === '-') || (name === '_')) {
                view.zoomBy(1 / 1.2);
                handled = true;
            } else if (name === '0') {
                view.fit();
                handled = true;
            }

            if (handled) {
                event.preventDefault();
            }
        }

        // Space held down on the canvas: dragging anywhere on it moves it.
        // Elsewhere Space keeps its own work (a summary, a button).
        function spaceDown(event) {
            if ((event.key === ' ') && !typing(event.target) && ui.viewport.contains(event.target)) {
                space = true;
                ui.viewport.classList.add(adapter.classes.space);
                event.preventDefault();
            }
        }

        function spaceUp(event) {
            if (event.key === ' ') {
                space = false;
                ui.viewport.classList.remove(adapter.classes.space);
            }
        }

        // The listeners, once the adapter's ui holds the viewport.
        function bind() {
            listen(ui.viewport, 'pointerdown', pointerDown);
            listen(ui.viewport, 'pointermove', pointerMove);
            listen(ui.viewport, 'pointerup', pointerUp);
            listen(ui.viewport, 'pointercancel', pointerUp);
            listen(ui.viewport, 'wheel', view.wheel, { passive: false });
            listen(ui.viewport, 'keydown', key);
            listen(document, 'keydown', spaceDown);
            listen(document, 'keyup', spaceUp);
        }

        function destroy() {
            bound.forEach(function (item) {
                item[0].removeEventListener(item[1], item[2], item[3]);
            });
            bound = [];
            pointers = {};
            pinch = null;
            drag = null;
        }

        return {
            view: view,
            bind: bind,
            destroy: destroy,
            cancel: cancel,
            // The drag on its way, null when none; hold() puts one in its
            // place (a box pressed before it takes the focus, null to let a
            // drag go without cancel()).
            dragging: function () { return drag; },
            hold: function (next) { drag = next; }
        };
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
        viewport: viewport,
        create: create
    };
})();
