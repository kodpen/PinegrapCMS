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
 *  - the pure graph logic: order() ranks boxes breadth-first from a start,
 *    arrange() places groups of boxes in a wrapped grid with side columns,
 *    linksOf() gives a box's arrows, check() finds what may be wrong;
 *  - the undo history of a graph kept as text (historyOf, historyRecord,
 *    historyStep);
 *  - viewport(host): pan, zoom about a point, fit, pinch and the wheel on a
 *    viewport element, its layer and its zoom label, reading and writing the
 *    view { x, y, k } through the host;
 *  - autosave(host): a graph saved a moment after its last change, at
 *    once on demand, on a keepalive request as the page is left, and held
 *    on a conflict (409) until the host reads it again;
 *  - create(adapter): a canvas on top of its viewport: its boxes with
 *    their ports, its arrows, a box moved and an arrow drawn by dragging
 *    from a box's port, the selection's mark and the live region; pan,
 *    pinch, Space and the zoom keys; the drags the adapter's own kinds
 *    make (a data thread), its listeners removed again by destroy().
 *
 * A box is 208 x 80 unless the adapter's size() says otherwise; an arrow
 * leaves a box's middle or one of its named ports (a row inside it), ends
 * on a box or another target, its id kept as given (an index or a stable
 * name), and arrows into one box may spread over its left edge (spread()).
 * Nothing runs at load but the definition of window.PGGraphCanvas, so the
 * script may be printed with or without defer, before its user.
 *
 * Its texts (keys pgc_*) come from pg_graph_canvas_js_strings()
 * (includes/fn/graph_canvas.php) through the adapter's text().
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
    // The lowest zoom a host may ask for (a map of whole pages).
    var ZOOM_FLOOR = 0.05;
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

    // The size { w, h } of a box: the host's size(item) when it gives one,
    // else 208 x 80.
    function sizeOf(host, item) {
        var size = (host && host.size) ? host.size(item) : null;

        return { w: (size && size.w) || BOX_W, h: (size && size.h) || BOX_H };
    }

    // Where the arrows into each box end when they spread over its left
    // edge: list holds one { to, y } per arrow (the box it goes to, the
    // height it leaves at); each gets { slot, slots }, its place among the
    // arrows into that box ordered by height (ties keep their order) and
    // how many there are.
    function spread(list) {
        var groups = {};

        list.forEach(function (item, index) {
            (groups[item.to] = groups[item.to] || []).push(index);
        });

        var out = list.map(function () { return { slot: 0, slots: 1 }; });

        Object.keys(groups).forEach(function (to) {
            var group = groups[to].slice().sort(function (a, b) { return (list[a].y - list[b].y) || (a - b); });

            group.forEach(function (index, slot) {
                out[index] = { slot: slot, slots: group.length };
            });
        });

        return out;
    }

    // The pure graph logic below reads a graph as ids (the boxes, in an
    // order that means something to the caller) and edges ({ from, to },
    // other fields kept as given). Nothing in it touches the DOM.

    // The box ids as a lookup of their first place in ids.
    function indexOf(ids) {
        var at = {};

        ids.forEach(function (id, index) {
            if (!Object.prototype.hasOwnProperty.call(at, id)) {
                at[id] = index;
            }
        });

        return at;
    }

    // Breadth-first ranks from start: { ids, rank }, rank[id] the number of
    // arrows on the shortest way from start (0 for it), null for a box
    // nothing reaches from it; ids sorted by rank, the given order between
    // equal ranks, the unreached last in the given order. An arrow whose
    // end is no box is skipped.
    function order(ids, edges, start) {
        var at = indexOf(ids);
        var next = {};
        var rank = {};

        ids.forEach(function (id) {
            rank[id] = null;
            next[id] = [];
        });

        (edges || []).forEach(function (edge) {
            if (Object.prototype.hasOwnProperty.call(at, edge.from) && Object.prototype.hasOwnProperty.call(at, edge.to) && edge.from !== edge.to) {
                next[edge.from].push(edge.to);
            }
        });

        if (Object.prototype.hasOwnProperty.call(at, start)) {
            var queue = [start];

            rank[start] = 0;

            for (var i = 0; i < queue.length; i++) {
                var from = queue[i];

                next[from].forEach(function (to) {
                    if (rank[to] === null) {
                        rank[to] = rank[from] + 1;
                        queue.push(to);
                    }
                });
            }
        }

        var sorted = ids.filter(function (id, index) { return at[id] === index; }).sort(function (a, b) {
            var ra = (rank[a] === null) ? Infinity : rank[a];
            var rb = (rank[b] === null) ? Infinity : rank[b];

            return (ra === rb) ? (at[a] - at[b]) : (ra - rb);
        });

        return { ids: sorted, rank: rank };
    }

    // The arrows leaving and reaching one box, in the given order.
    function linksOf(edges, id) {
        var out = [];
        var into = [];

        (edges || []).forEach(function (edge) {
            if (edge.from === id) {
                out.push(edge);
            }

            if (edge.to === id) {
                into.push(edge);
            }
        });

        return { out: out, in: into };
    }

    // What may be wrong with a graph, reported only: dangling, the arrows
    // whose start or end is no box; unreached, the boxes no arrow from
    // another box reaches (never start); stuck, the boxes with no arrow out
    // but to themselves (an arrow to something that is no box is a way out).
    function check(ids, edges, start) {
        var at = indexOf(ids);
        var reached = {};
        var leaves = {};
        var dangling = [];

        (edges || []).forEach(function (edge) {
            var from = Object.prototype.hasOwnProperty.call(at, edge.from);
            var to = Object.prototype.hasOwnProperty.call(at, edge.to);

            if (!from || !to) {
                dangling.push(edge);
            }

            if (edge.from !== edge.to) {
                if (from) {
                    leaves[edge.from] = true;
                }

                if (from && to) {
                    reached[edge.to] = true;
                }
            }
        });

        var boxes = ids.filter(function (id, index) { return at[id] === index; });

        return {
            dangling: dangling,
            unreached: boxes.filter(function (id) { return (id !== start) && !reached[id]; }),
            stuck: boxes.filter(function (id) { return !leaves[id]; })
        };
    }

    // Places boxes in groups as bands of a wrapped grid, with a side column
    // before the grid and one after it. groups: [{ id, label, items }], a
    // group with no items left out, one with no label given no heading.
    // opts (all optional): cols (8), w and h (the box, 208 x 80), gapX and
    // gapY (48), head (a heading's height, 40), bandGap (gapY, between
    // bands), sideGap (2 x gapX, between a side column and the grid), x and
    // y (the top left, 0), size(id) → { w, h } or null (a row is as high as
    // its tallest box), before and after ({ id, label, w, h, gap, items or
    // groups }: items stacked one under another, each group of them with
    // its own heading). Answers { places: { id: { x, y } }, headings:
    // [{ id, label, x, y, w, kind: 'side' | 'group' | 'sub' }], bounds:
    // { x, y, w, h } }, in whole units.
    function arrange(groups, opts) {
        opts = opts || {};

        var cols = Math.max(1, opts.cols || 8);
        var w = opts.w || BOX_W;
        var h = opts.h || BOX_H;
        var gapX = (opts.gapX === undefined) ? 48 : opts.gapX;
        var gapY = (opts.gapY === undefined) ? 48 : opts.gapY;
        var head = (opts.head === undefined) ? 40 : opts.head;
        var bandGap = (opts.bandGap === undefined) ? gapY : opts.bandGap;
        var sideGap = (opts.sideGap === undefined) ? 2 * gapX : opts.sideGap;
        var x0 = opts.x || 0;
        var y0 = opts.y || 0;
        var places = {};
        var headings = [];
        var boxes = [];
        var bands = (groups || []).filter(function (group) { return group && group.items && group.items.length; });

        function sized(id) {
            return opts.size ? opts.size(id) : null;
        }

        function place(id, x, y, bw, bh) {
            places[id] = { x: Math.round(x), y: Math.round(y) };
            boxes.push({ x: x, y: y, w: bw, h: bh });
        }

        function title(id, label, x, y, width, kind) {
            headings.push({ id: id, label: label, x: Math.round(x), y: Math.round(y), w: Math.round(width), kind: kind });
            boxes.push({ x: x, y: y, w: width, h: head });
        }

        function filled(column) {
            return !!column && !!((column.items && column.items.length) || (column.groups || []).some(function (group) { return group && group.items && group.items.length; }));
        }

        function side(column, x) {
            var cw = column.w || w;
            var ch = column.h || h;
            var gap = (column.gap === undefined) ? gapY : column.gap;
            var y = y0;

            if (column.label) {
                title(column.id, column.label, x, y, cw, 'side');
                y += head;
            }

            (column.groups || [{ items: column.items }]).forEach(function (group) {
                if (!group || !group.items || !group.items.length) {
                    return;
                }

                if (group.label) {
                    title(group.id, group.label, x, y, cw, 'sub');
                    y += head;
                }

                group.items.forEach(function (id) {
                    var size = sized(id);
                    var bh = (size && size.h) || ch;

                    place(id, x, y, (size && size.w) || cw, bh);
                    y += bh + gap;
                });
            });
        }

        var before = filled(opts.before) ? opts.before : null;
        var after = filled(opts.after) ? opts.after : null;
        var gridX = before ? x0 + (before.w || w) + sideGap : x0;
        var gridCols = Math.min(cols, bands.reduce(function (most, group) { return Math.max(most, group.items.length); }, 0));
        var gridW = gridCols ? gridCols * w + (gridCols - 1) * gapX : 0;
        var y = y0;

        if (before) {
            side(before, x0);
        }

        bands.forEach(function (group, band) {
            if (band) {
                y += bandGap;
            }

            if (group.label) {
                title(group.id, group.label, gridX, y, gridW, 'group');
                y += head;
            }

            for (var row = 0; row * cols < group.items.length; row++) {
                var rowH = 0;

                if (row) {
                    y += gapY;
                }

                group.items.slice(row * cols, (row + 1) * cols).forEach(function (id, col) {
                    var size = sized(id);
                    var bh = (size && size.h) || h;

                    place(id, gridX + col * (w + gapX), y, (size && size.w) || w, bh);
                    rowH = Math.max(rowH, bh);
                });
                y += rowH;
            }
        });

        if (after) {
            side(after, gridW ? gridX + gridW + sideGap : gridX);
        }

        var bounds = { x: 0, y: 0, w: 0, h: 0 };

        if (boxes.length) {
            var left = Math.round(Math.min.apply(null, boxes.map(function (box) { return box.x; })));
            var top = Math.round(Math.min.apply(null, boxes.map(function (box) { return box.y; })));
            var right = Math.round(Math.max.apply(null, boxes.map(function (box) { return box.x + box.w; })));
            var bottom = Math.round(Math.max.apply(null, boxes.map(function (box) { return box.y + box.h; })));

            bounds = { x: left, y: top, w: right - left, h: bottom - top };
        }

        return { places: places, headings: headings, bounds: bounds };
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
    // Optional: host.size(item) a box's { w, h } (else 208 x 80), and
    // host.zoomMin and host.zoomMax (else 0.25 and 2; never below 0.05).
    function viewport(host) {
        var zoomMin = Math.max(ZOOM_FLOOR, host.zoomMin || ZOOM_MIN);
        var zoomMax = host.zoomMax || ZOOM_MAX;

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
            var k = clamp(v.k * factor, zoomMin, zoomMax);
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
            var maxX = Math.max.apply(null, items.map(function (item) { return item.x + sizeOf(host, item).w; }));
            var maxY = Math.max.apply(null, items.map(function (item) { return item.y + sizeOf(host, item).h; }));
            var k = clamp(Math.min((rect.width - 96) / Math.max(1, maxX - minX), (rect.height - 96) / Math.max(1, maxY - minY)), zoomMin, Math.min(1.25, zoomMax));

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
            var size = sizeOf(host, item);

            if ((left < margin) || (top < margin) || (left + size.w * v.k > rect.width - margin) || (top + size.h * v.k > rect.height - margin)) {
                v.x = rect.width / 2 - (item.x + size.w / 2) * v.k;
                v.y = rect.height / 2 - (item.y + size.h / 2) * v.k;
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
            var k = clamp(pinch.view.k * Math.hypot(points[0].x - points[1].x, points[0].y - points[1].y) / pinch.d, zoomMin, zoomMax);
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

    // ── Autosave ───────────────────────────────────────────────────────

    // The saving of one graph. The host gives:
    //  - record: the object the save state is kept on, which the host reads
    //    too: dirty (changes not sent), changes (a count of them), saving
    //    (the save on its way, a promise, else null), saveError (why the
    //    last save failed), conflict (why the graph cannot be saved over:
    //    a newer one is on the server), leaveSent (the changes count a
    //    keepalive save went out for), saveTimer;
    //  - ready(): whether there is a graph the person may save now;
    //  - send(options): a promise of the save of the graph as it is now
    //    (options.keepalive on a page being left); it applies the answer
    //    itself and rejects with an error { field, message } (no field: no
    //    answer came);
    //  - size(): the bytes of what send() would send;
    //  - isConflict(error) and conflict(message): a refusal because the
    //    graph changed meanwhile, and its message shown;
    //  - failed(): the text of a failure that has none;
    //  - drawn(): the save state drawn again;
    //  - delay (ms after the last change) and keepaliveBytes (the most a
    //    keepalive request carries).
    function autosave(host) {
        var record = host.record;

        function later() {
            window.clearTimeout(record.saveTimer);
            record.saveTimer = window.setTimeout(save, host.delay);
        }

        // A change made: sent once the changes pause (later()).
        function changed() {
            record.dirty = true;
            record.changes++;
        }

        // options: { keepalive } for the save sent as the page is left.
        function save(options) {
            window.clearTimeout(record.saveTimer);

            if (!host.ready() || record.conflict) {
                return Promise.resolve();
            }

            if (record.saving) {
                return record.saving.then(function () { return record.dirty ? save() : null; });
            }

            if (!record.dirty) {
                return Promise.resolve();
            }

            var keepalive = !!(options && options.keepalive);
            var sent = record.changes;

            record.dirty = false;
            record.saveError = '';
            record.leaveSent = -1;
            record.saving = host.send(options).catch(function (error) {
                record.dirty = true;

                // A keepalive request is rejected when the page closes under
                // it, yet the browser still delivers it: no answer (no field)
                // means the save is on its way. The same changes are not sent
                // again as the page goes (the server would refuse the stale
                // revision); a page that stays saves them again after the
                // delay.
                if (keepalive && (error.field === undefined)) {
                    record.leaveSent = sent;
                    return;
                }

                if (host.isConflict(error)) {
                    host.conflict(error.message);
                } else {
                    record.saveError = error.message || host.failed();
                }
            }).then(function () {
                record.saving = null;
                host.drawn();

                if (record.dirty && !record.conflict && !record.saveError) {
                    later();
                }
            });

            host.drawn();

            return record.saving;
        }

        // What is still unsaved, saved before something else is opened.
        function flush() {
            return record.dirty ? save() : (record.saving || Promise.resolve());
        }

        // The page is hidden or being left: the last changes go out on a
        // request the browser finishes even after the page is gone
        // (keepalive). It is an ordinary save otherwise: its answer is
        // read, so a page that lives on (a tab shown again) holds the
        // revision the server moved to, and a refusal leaves the changes
        // unsaved. Browsers refuse a keepalive body over 64 KiB: a larger
        // graph is saved the usual way while the page is only hidden, and
        // when it is left (leaving) it stays unsaved, which beforeunload
        // asks about. beforeunload, visibilitychange and pagehide all come
        // as a page is left: changes whose keepalive save is already on its
        // way (leaveSent) are sent once.
        function leave(leaving) {
            if (!host.ready() || !record.dirty || record.saving || record.conflict || (record.leaveSent === record.changes)) {
                return;
            }

            var keepalive = host.size() <= host.keepaliveBytes;

            if (keepalive || !leaving) {
                save({ keepalive: keepalive });
            }
        }

        return { changed: changed, later: later, save: save, flush: flush, leave: leave };
    }

    // ── The canvas: boxes, arrows, pointer and keys ────────────────────

    // One canvas: its boxes, ports and arrows, a box moved and an arrow
    // drawn by the pointer, its pan, zoom, pinch and keys, and the pointer
    // plumbing its user builds on. The adapter gives:
    //  - ui (viewport, layer, zoomLabel), view(next), items(), and the
    //    optional size(item), zoomMin and zoomMax as viewport() takes them
    //    (a box with a size() is drawn at it); ui.boxes (the boxes' parent), ui.svg (the
    //    arrows'), ui.temp (the path of an arrow being drawn) and ui.live
    //    (a polite live region);
    //  - text(key, ...): a text of pg_graph_canvas_js_strings(), its holes
    //    filled; boxRole(): a box's role description (else pgc_box);
    //  - item(id): the box { id, x, y } of that id, null when none;
    //  - editable(): whether the graph may be changed now (boxes move, the
    //    out port draws arrows);
    //  - coord(value): a canvas unit as a box's place keeps it (else the
    //    grid's snap);
    //  - select(selected): a box ({ type: 'node', id }) or an arrow
    //    ({ type: 'edge', id: index }) was pressed;
    //  - placed(element): a box (or what the adapter places with place())
    //    was put at its place;
    //  - edges() (optional): the boxes moved, the arrows are drawn again;
    //    without it only the arrows of the box moved are (reroute());
    //  - moved(item): a box was dragged to a new place;
    //  - canConnect(from, to, port) and connected(from, to, port): whether
    //    an arrow may go from one box (its named port, else null) to a
    //    target, and an arrow drawn from an out port released on a target
    //    (to is null on none of the graph);
    //  - targets and target(element) (optional): a selector of what an
    //    arrow may end on beside a box (an end chip), and the item of such
    //    an element (else item() of its data-node);
    //  - portAt(item, name) (optional): where a named port of a box sits,
    //    { y } from the box's top edge (else read from the row holding the
    //    [data-port] dot inside the box);
    //  - thumb(item, holder) (optional): a box drawn with options.thumb
    //    gets its picture put into holder once it first comes into sight;
    //  - ownClicks: a selector of what keeps its own clicks and keys (a link
    //    on a box): the canvas takes no pointer and no key from it;
    //  - focus (optional, true): only the arrows of the box in focus are
    //    drawn, <edge>-out and <edge>-in, the boxes and targets at neither
    //    end of one dimmed; none with no box in focus; allEdges(true) draws
    //    the others too, <edge>-faint, under them. A press on a box puts it
    //    in focus, a click on the empty canvas takes the focus away. Without
    //    the field every arrow is drawn as before;
    //  - label(item) (optional): a box's name in what the focus announces
    //    (else its aria-label, else its id);
    //  - groups() (optional): the headings groups() draws behind the boxes,
    //    [{ id, label, x, y, w, kind }] as G.arrange() gives them;
    //  - classes (optional, each name else its CLASSES default, styled by
    //    the pgc- block of backend.src.css): { panning, space } put on the
    //    viewport while it pans and while Space is held; box, port, edge (a
    //    box, its ports <port>-in and <port>-out, an arrow's group with its
    //    <edge>-line and <edge>-hit paths), selected (what is selected),
    //    target (the box an arrow being drawn may go to), hidden (ui.temp
    //    while no arrow is drawn);
    //  - press(event, drag, space): a pointer went down on the canvas; it
    //    sets drag.kind and what its own kinds need, or answers false when
    //    it took the press itself (the pointer is then forgotten). A kind
    //    it leaves unset is the core's: 'connect' on a box's out port,
    //    'move' on a box ('none' when the graph cannot change), 'none' on
    //    an arrow, else 'pan' with drag.empty;
    //  - move(event, drag, dx, dy): a drag of the adapter's kind moved past
    //    4 px from where it started;
    //  - release(event, drag): the drag's pointer went up (or was cancelled
    //    by the browser: event.type says which); every drag but an arrow
    //    drawn and a box moved;
    //  - cancel(drag): a drag was cut short (Esc, a second finger); drag is
    //    null when there was none; a box moved is back at its place first;
    //  - key(event): a key on the canvas, first; true when it handled it.
    //    Arrows the adapter does not take pan, + and − zoom, 0 fits.
    // The class names create() puts on what it draws and marks.
    var CLASSES = {
        panning: 'pgc-panning',
        space: 'pgc-space',
        box: 'pgc-node',
        port: 'pgc-port',
        edge: 'pgc-edge',
        selected: 'pgc-selected',
        target: 'pgc-target',
        hidden: 'pgc-hidden',
        thumb: 'pgc-thumb',
        edgeOut: 'pgc-edge-out',
        edgeIn: 'pgc-edge-in',
        faint: 'pgc-edge-faint',
        dim: 'pgc-dim',
        group: 'pgc-group'
    };

    function merge(base, over) {
        var out = {};

        Object.keys(base).forEach(function (key) {
            out[key] = (over && over[key]) ? over[key] : base[key];
        });

        return out;
    }

    function create(adapter) {
        var ui = adapter.ui;
        var view = viewport({ ui: ui, view: adapter.view, items: adapter.items, size: adapter.size, zoomMin: adapter.zoomMin, zoomMax: adapter.zoomMax });
        var pointers = {};
        var pinch = null;
        var drag = null;
        var space = false;
        var bound = [];
        var classes = merge(CLASSES, adapter.classes);
        // The arrows drawn by id, to draw the ones of a box moved again.
        var drawn = {};
        var watcher = null;
        // The focus (adapter.focus): the box in focus, null for none, and
        // whether the other arrows are drawn faintly.
        var focusing = !!adapter.focus;
        var focusId = null;
        var faint = false;

        function text() {
            return adapter.text.apply(null, arguments);
        }

        function coord(value) {
            return adapter.coord ? adapter.coord(value) : snap(value);
        }

        function closest(target, selector) {
            return (target && target.closest) ? target.closest(selector) : null;
        }

        // ── Boxes and arrows ──

        function size(item) {
            return sizeOf(adapter, item);
        }

        // A port dot: side 'in' or 'out'; a named one (a row's) carries its
        // name in data-port and sits in the middle of the row it is put in.
        function port(side, name) {
            var dot = document.createElement('span');

            dot.className = classes.port + ' ' + classes.port + '-' + side;
            dot.setAttribute('aria-hidden', 'true');

            if (name) {
                dot.setAttribute('data-port', name);
            }

            return dot;
        }

        function boxOf(item) {
            return ui.boxes.querySelector('[data-node="' + String(item.id).replace(/"/g, '') + '"]');
        }

        // Where an arrow leaves a box: the middle of its right edge, or the
        // middle of the row holding its named port; a port not drawn (a row
        // folded away) leaves from the middle.
        function from(item, name) {
            var box = size(item);
            var y = box.h / 2;
            var at = null;

            if (name && adapter.portAt) {
                at = adapter.portAt(item, name);
            } else if (name) {
                var node = boxOf(item);
                var dot = node ? node.querySelector('[data-port="' + String(name).replace(/"/g, '') + '"]') : null;
                var row = dot ? dot.parentNode : null;

                if (row && row.offsetParent) {
                    var top = 0;

                    for (var el = row; el && (el !== node); el = el.offsetParent) {
                        top += el.offsetTop;
                    }

                    at = { y: top + row.offsetHeight / 2 };
                }
            }

            if (at && (typeof at.y === 'number')) {
                y = at.y;
            }

            return { x: item.x + box.w, y: item.y + y };
        }

        // Where an arrow ends on a target: the middle of its left edge, or
        // its place among the arrows into it (slot of slots).
        function into(item, slot, slots) {
            var box = size(item);

            return { x: item.x, y: item.y + ((slots > 1) ? box.h * (slot + 1) / (slots + 1) : box.h / 2) };
        }

        // A box of the graph at its place: focusable, named for a screen
        // reader, its in port first, fill(box) its content, its out port
        // last. options: { className, into, out, fill, thumb }; thumb puts a
        // holder for the box's picture before the content (adapter.thumb()
        // fills it once the box is in sight). A box the adapter sizes is
        // drawn at its size.
        function box(item, options) {
            var node = document.createElement('div');

            node.className = classes.box + (options.className ? ' ' + options.className : '');
            node.tabIndex = 0;
            node.setAttribute('role', 'group');
            node.setAttribute('aria-roledescription', adapter.boxRole ? adapter.boxRole() : text('pgc_box'));
            node.setAttribute('data-node', item.id);
            node.setAttribute('data-refocus', 'box-' + item.id);

            if (adapter.size) {
                var dims = size(item);

                node.style.width = dims.w + 'px';
                node.style.height = dims.h + 'px';
            }

            if (options.into) {
                node.appendChild(port('in'));
            }

            if (options.thumb && adapter.thumb) {
                thumb(item, node.appendChild(document.createElement('div')));
            }

            if (options.fill) {
                options.fill(node);
            }

            if (options.out) {
                var out = port('out');

                if (adapter.editable()) {
                    out.title = text('pgc_port_out');
                }

                node.appendChild(out);
            }

            place(node, item);

            if (focusing && (focusId !== null) && !ends()[String(item.id)]) {
                node.classList.add(classes.dim);
            }

            return node;
        }

        function place(node, item) {
            node.style.left = item.x + 'px';
            node.style.top = item.y + 'px';

            if (adapter.placed) {
                adapter.placed(node);
            }
        }

        // A box's picture holder, filled by the adapter once it is in sight
        // (at once where the browser cannot tell).
        function thumb(item, holder) {
            holder.className = classes.thumb;
            holder.setAttribute('aria-hidden', 'true');

            if (!window.IntersectionObserver) {
                adapter.thumb(item, holder);
                return;
            }

            if (!watcher) {
                watcher = new window.IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        var target = entry.target.pgcThumb;

                        if (entry.isIntersecting && target) {
                            delete entry.target.pgcThumb;
                            watcher.unobserve(entry.target);
                            adapter.thumb(target, entry.target);
                        }
                    });
                }, { root: ui.viewport });
            }

            holder.pgcThumb = item;
            watcher.observe(holder);
        }

        function route(one) {
            var start = from(one.from, one.options.port);
            var end = into(one.to, one.options.slot || 0, one.options.slots || 1);

            return curve(start.x, start.y, end.x, end.y);
        }

        // An arrow from a box's right edge (or its named port) to a target's
        // left one, its id (an index, or a stable name) in data-edge, a wide
        // invisible path over it to press; its group is added to ui.svg.
        // options: { port, slot, slots } (spread()). Its path and middle are
        // returned.
        function edge(id, source, target, options) {
            var one = { from: source, to: target, options: options || {} };
            var path = route(one);
            var group = svgEl('g', classes.edge);
            var line = svgEl('path', classes.edge + '-line');
            var hit = svgEl('path', classes.edge + '-hit');

            group.setAttribute('data-edge', String(id));
            line.setAttribute('d', path.d);
            hit.setAttribute('d', path.d);
            group.appendChild(line);
            group.appendChild(hit);
            one.group = group;
            one.state = '';
            drawn[String(id)] = one;

            if (focusing) {
                show(one, stateOf(one));

                // A faint arrow goes under those of the box in focus.
                var over = (one.state === 'faint') ? ui.svg.querySelector('.' + classes.edgeOut + ', .' + classes.edgeIn) : null;

                if (over) {
                    ui.svg.insertBefore(group, over);
                } else {
                    ui.svg.appendChild(group);
                }

                if ((one.state === 'out') || (one.state === 'in')) {
                    undim(one);
                }
            } else {
                ui.svg.appendChild(group);
            }

            return { group: group, path: path };
        }

        // ── Focus ──

        // What an arrow is with the focus as it is: 'out' or 'in' of the box
        // in focus, 'faint' while every arrow is drawn, else 'hidden'.
        function stateOf(one) {
            if (focusId === null) {
                return 'hidden';
            }

            if (String(one.from.id) === focusId) {
                return 'out';
            }

            if (String(one.to.id) === focusId) {
                return 'in';
            }

            return faint ? 'faint' : 'hidden';
        }

        // An arrow's classes for its state; nothing is touched when the
        // state is the one it has.
        function show(one, state) {
            if (one.state === state) {
                return false;
            }

            var list = one.group.classList;

            list.remove(classes.edgeOut);
            list.remove(classes.edgeIn);
            list.remove(classes.faint);
            list.remove(classes.hidden);
            list.add({ out: classes.edgeOut, 'in': classes.edgeIn, faint: classes.faint, hidden: classes.hidden }[state]);
            one.state = state;

            return true;
        }

        // The arrows still on the canvas.
        function live() {
            return Object.keys(drawn).map(function (key) { return drawn[key]; }).filter(function (one) {
                return one.group.parentNode === ui.svg;
            });
        }

        // The ids at an end of an arrow of the box in focus, the box itself
        // among them.
        function ends() {
            var out = {};

            if (focusId === null) {
                return out;
            }

            out[focusId] = true;
            live().forEach(function (one) {
                if ((one.state === 'out') || (one.state === 'in')) {
                    out[String(one.from.id)] = true;
                    out[String(one.to.id)] = true;
                }
            });

            return out;
        }

        // The id of a box or of a target (adapter.targets) element.
        function idOf(element) {
            var raw = element.getAttribute('data-node');

            if (raw !== null) {
                return raw;
            }

            var item = adapter.target ? adapter.target(element) : null;

            return item ? String(item.id) : null;
        }

        function elementsOf() {
            return ui.boxes.querySelectorAll('[data-node]' + (adapter.targets ? ', ' + adapter.targets : ''));
        }

        // Every box and target at neither end of an arrow of the box in
        // focus dimmed; none while nothing is in focus.
        function dim() {
            var near = ends();

            Array.prototype.forEach.call(elementsOf(), function (element) {
                var id = idOf(element);

                if ((focusId !== null) && (id !== null) && !near[id]) {
                    element.classList.add(classes.dim);
                } else {
                    element.classList.remove(classes.dim);
                }
            });
        }

        // The two ends of an arrow of the box in focus drawn in full.
        function undim(one) {
            Array.prototype.forEach.call(elementsOf(), function (element) {
                var id = idOf(element);

                if ((id === String(one.from.id)) || (id === String(one.to.id))) {
                    element.classList.remove(classes.dim);
                }
            });
        }

        // The arrows of the box in focus put last, over the faint ones.
        function lift() {
            live().forEach(function (one) {
                if ((one.state === 'out') || (one.state === 'in')) {
                    ui.svg.appendChild(one.group);
                }
            });
        }

        // The arrows whose state changed drawn again, the boxes dimmed.
        function refocus() {
            var changed = false;

            live().forEach(function (one) {
                changed = show(one, stateOf(one)) || changed;
            });

            if (changed) {
                lift();
            }

            dim();
        }

        // A box (its id) in focus, null for none; again with the same id it
        // dims the boxes drawn since. A box put in focus is announced with
        // its name and its arrows' counts.
        function focus(id) {
            if (!focusing) {
                return;
            }

            var next = (id === null || id === undefined) ? null : String(id);
            var moved = next !== focusId;

            focusId = next;
            refocus();

            if (moved && (next !== null)) {
                announceFocus();
            }
        }

        function announceFocus() {
            var item = adapter.item(focusId);
            var node = ui.boxes.querySelector('[data-node="' + focusId.replace(/"/g, '') + '"]');
            var name = (item && adapter.label) ? adapter.label(item) : ((node && node.getAttribute('aria-label')) || focusId);
            var outs = 0;
            var ins = 0;

            live().forEach(function (one) {
                if (one.state === 'out') {
                    outs++;
                } else if (one.state === 'in') {
                    ins++;
                }
            });

            announce(text('pgc_focus', name, outs, ins));
        }

        // The arrows of the boxes out of focus drawn faintly (on) or not.
        function allEdges(on) {
            faint = !!on;

            if (focusing) {
                refocus();
            }
        }

        // The headings of adapter.groups() drawn behind the boxes: a label
        // and a rule each, at its place and width; drawn again on each call.
        function groups() {
            var old = ui.layer.querySelector('.' + classes.group + 's');
            var holder = document.createElement('div');

            if (old) {
                old.parentNode.removeChild(old);
            }

            holder.className = classes.group + 's';
            holder.setAttribute('aria-hidden', 'true');
            ui.layer.insertBefore(holder, ui.layer.firstChild);

            (adapter.groups ? adapter.groups() : []).forEach(function (one) {
                var head = document.createElement('div');
                var label = document.createElement('span');
                var rule = document.createElement('span');

                head.className = classes.group + (one.kind ? ' ' + classes.group + '-' + one.kind : '');
                head.setAttribute('data-group', String(one.id));
                head.style.left = one.x + 'px';
                head.style.top = one.y + 'px';
                head.style.width = one.w + 'px';
                label.className = classes.group + '-label';
                label.textContent = one.label || '';
                rule.className = classes.group + '-rule';
                head.appendChild(label);
                head.appendChild(rule);
                holder.appendChild(head);
            });

            return holder;
        }

        // The arrows from or into the boxes of these ids drawn again where
        // they now are (a box moved), the others left as they are. An arrow
        // whose group left ui.svg is forgotten.
        function reroute(ids) {
            var wanted = {};

            ids.forEach(function (id) { wanted[String(id)] = true; });

            Object.keys(drawn).forEach(function (key) {
                var one = drawn[key];

                if (one.group.parentNode !== ui.svg) {
                    delete drawn[key];
                    return;
                }

                if (wanted[String(one.from.id)] || wanted[String(one.to.id)]) {
                    var path = route(one);

                    one.group.children[0].setAttribute('d', path.d);
                    one.group.children[1].setAttribute('d', path.d);
                }
            });
        }

        // The boxes moved: the adapter draws the arrows again, else only
        // those of the box moved are.
        function rerouted(item) {
            if (adapter.edges) {
                adapter.edges();
            } else {
                reroute([item.id]);
            }
        }

        // What is selected ({ type, id }, null for nothing) marked on the
        // canvas: its data-<type> element.
        function mark(selected) {
            Array.prototype.forEach.call(ui.layer.querySelectorAll('.' + classes.selected), function (node) {
                node.classList.remove(classes.selected);
                node.removeAttribute('aria-current');
            });

            if (!selected) {
                return;
            }

            var target = ui.layer.querySelector('[data-' + selected.type + '="' + String(selected.id).replace(/"/g, '') + '"]');

            if (target) {
                target.classList.add(classes.selected);
                target.setAttribute('aria-current', 'true');
            }
        }

        // A text read out by a screen reader, again when it is the same.
        function announce(message) {
            ui.live.textContent = '';
            window.setTimeout(function () { ui.live.textContent = message; }, 30);
        }

        // The target under the pointer: its element and its item.
        function targetAt(clientX, clientY) {
            var under = document.elementFromPoint(clientX, clientY);
            var over = closest(under, adapter.targets ? '[data-node], ' + adapter.targets : '[data-node]');

            if (!over) {
                return { element: null, item: null };
            }

            return { element: over, item: (adapter.target && (over.getAttribute('data-node') === null)) ? adapter.target(over) : adapter.item(over.getAttribute('data-node')) };
        }

        function clearTargets() {
            Array.prototype.forEach.call(ui.boxes.querySelectorAll('.' + classes.target), function (node) { node.classList.remove(classes.target); });
        }

        // ── The core's drags ──

        // An arrow's id as edge() was given it: a number for an index.
        function edgeId(raw) {
            return /^\d+$/.test(raw) ? parseInt(raw, 10) : raw;
        }

        // A press the adapter left: an arrow from a box's out port, a box
        // moved, an arrow selected, else the canvas panned.
        function pressOwn(event, next) {
            var target = event.target;
            var node = closest(target, '.' + classes.box + '[data-node]');
            var arrow = closest(target, '.' + classes.edge);

            var out = node ? closest(target, '.' + classes.port + '-out') : null;

            if (adapter.editable() && out) {
                next.kind = 'connect';
                next.from = adapter.item(node.getAttribute('data-node'));
                next.port = out.getAttribute('data-port');
                event.preventDefault();
            } else if (node) {
                next.kind = adapter.editable() ? 'move' : 'none';
                next.item = adapter.item(node.getAttribute('data-node'));
                next.ix = next.item.x;
                next.iy = next.item.y;
                next.node = node;
                // Held before the box takes the focus: its focusin then
                // selects nothing again.
                drag = next;
                adapter.select({ type: 'node', id: next.item.id });
                node.focus({ preventScroll: true });
                focus(next.item.id);
            } else if (arrow) {
                next.kind = 'none';
                adapter.select({ type: 'edge', id: edgeId(arrow.getAttribute('data-edge')) });
                ui.viewport.focus({ preventScroll: true });
            } else {
                next.kind = 'pan';
                next.empty = true;
            }
        }

        function moveOwn(event, current, dx, dy) {
            var v = adapter.view();

            if (current.kind === 'move') {
                current.item.x = coord(current.ix + dx / v.k);
                current.item.y = coord(current.iy + dy / v.k);
                place(current.node, current.item);
                rerouted(current.item);
                return true;
            }

            if (current.kind === 'connect') {
                var at = view.point(event.clientX, event.clientY);
                var start = from(current.from, current.port);
                var aim = targetAt(event.clientX, event.clientY);

                ui.temp.setAttribute('d', curve(start.x, start.y, at.x, at.y).d);
                ui.temp.classList.remove(classes.hidden);
                clearTargets();

                if (aim.item && adapter.canConnect(current.from, aim.item, current.port || null)) {
                    aim.element.classList.add(classes.target);
                }

                return true;
            }

            // A box or an arrow pressed where nothing may change: nothing moves.
            return current.kind === 'none';
        }

        function releaseOwn(event, done) {
            if (done.kind === 'connect') {
                var aim = targetAt(event.clientX, event.clientY);

                ui.temp.classList.add(classes.hidden);
                clearTargets();

                if (aim.element && (event.type === 'pointerup')) {
                    adapter.connected(done.from, aim.item, done.port || null);
                }

                return true;
            }

            if ((done.kind === 'move') && done.moved) {
                adapter.moved(done.item);
                return true;
            }

            // A click on the empty canvas (not a pan) takes the focus away.
            if ((done.kind === 'pan') && done.empty && !done.moved && (event.type === 'pointerup')) {
                focus(null);
            }

            return false;
        }

        // ── Pointer and keys ──

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
            var next = { id: event.pointerId, sx: event.clientX, sy: event.clientY, moved: false, kind: null, vx: v.x, vy: v.y };

            if (adapter.press(event, next, space) === false) {
                delete pointers[event.pointerId];
                return;
            }

            if (!next.kind) {
                pressOwn(event, next);
            }

            drag = next;

            try {
                ui.viewport.setPointerCapture(event.pointerId);
            } catch (error) {
                // A pointer that cannot be captured still drags while it stays on the canvas.
            }

            if (drag.kind === 'pan') {
                ui.viewport.classList.add(classes.panning);
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
            } else if (!moveOwn(event, drag, dx, dy)) {
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
            ui.viewport.classList.remove(classes.panning);

            if (!releaseOwn(event, done)) {
                adapter.release(event, done);
            }
        }

        // A drag cut short: an arrow being drawn is dropped, a box being
        // moved goes back to where it was.
        function cancel() {
            var done = drag;

            drag = null;
            ui.viewport.classList.remove(classes.panning);
            ui.temp.classList.add(classes.hidden);
            clearTargets();

            if (done && (done.kind === 'move') && done.moved) {
                done.item.x = done.ix;
                done.item.y = done.iy;
                place(done.node, done.item);
                rerouted(done.item);
            }

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
                ui.viewport.classList.add(classes.space);
                event.preventDefault();
            }
        }

        function spaceUp(event) {
            if (event.key === ' ') {
                space = false;
                ui.viewport.classList.remove(classes.space);
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
            drawn = {};

            if (watcher) {
                watcher.disconnect();
                watcher = null;
            }
        }

        return {
            view: view,
            box: box,
            place: place,
            edge: edge,
            reroute: reroute,
            port: port,
            size: size,
            mark: mark,
            announce: announce,
            focus: focus,
            focused: function () { return focusId; },
            allEdges: allEdges,
            groups: groups,
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
        ZOOM_FLOOR: ZOOM_FLOOR,
        snap: snap,
        spread: spread,
        order: order,
        arrange: arrange,
        linksOf: linksOf,
        check: check,
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
        autosave: autosave,
        create: create
    };
})();
