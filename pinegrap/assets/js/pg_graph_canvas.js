/**
 * Pinegrap - Enterprise Website Platform
 *
 * The graph canvas core (window.PGGraphCanvas): what a box-and-arrow canvas
 * needs whatever its boxes mean. Boxes sit on a layer moved and scaled with
 * one CSS transform, at canvas units snapped to a 16 px grid; arrows are
 * cubic curves from a box's right edge to the next box's left one, or with
 * adapter.route 'elbow' right-angle ways through the gaps.
 *
 * Its first user is the Workspace flow canvas (assets/js/workspace_flow.js),
 * which keeps what a flow's boxes are, their forms, saving and runs, and
 * hands the core what it needs through small adapters:
 *
 *  - the geometry helpers (snap, clamp, svgEl, curve, freeId, typing, bytes)
 *    and right-angle routes (route() finds a way through the gaps between
 *    boxes, path() rounds its turns, lanes() spreads ways sharing a gap);
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
    // Right-angle arrows: past this many drawn, the faint ones keep the
    // curve; the radius of a rounded turn.
    var ROUTE_MOST = 150;
    var ROUTE_RADIUS = 8;

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

    // ── Right-angle routes ─────────────────────────────────────────────

    // The sides a route leaves or enters by, as unit steps.
    var SIDES = { right: [1, 0], bottom: [0, 1], left: [-1, 0], top: [0, -1] };
    var SIDE_NAMES = ['right', 'bottom', 'left', 'top'];

    // The boxes ({ x, y, w, h }) grown by margin and kept in buckets of
    // 256 units, so that a short segment is checked against the few boxes
    // near it.
    function obstacles(boxes, margin) {
        var cell = 256;
        var buckets = new Map();
        var list = [];

        function bucket(cx, cy) {
            return cx * 65536 + cy;
        }

        (boxes || []).forEach(function (box) {
            if (!box || !(box.w > 0) || !(box.h > 0)) {
                return;
            }

            var r = { x1: box.x - margin, y1: box.y - margin, x2: box.x + box.w + margin, y2: box.y + box.h + margin };

            list.push(r);

            for (var cx = Math.floor(r.x1 / cell); cx <= Math.floor(r.x2 / cell); cx++) {
                for (var cy = Math.floor(r.y1 / cell); cy <= Math.floor(r.y2 / cell); cy++) {
                    var key = bucket(cx, cy);
                    var held = buckets.get(key);

                    if (held) {
                        held.push(r);
                    } else {
                        buckets.set(key, [r]);
                    }
                }
            }
        });

        // Whether the segment (or point) from (x1, y1) to (x2, y2), one
        // axis apart, touches the inside of a grown box; its edge is free.
        function blocked(x1, y1, x2, y2) {
            var lx = Math.min(x1, x2);
            var hx = Math.max(x1, x2);
            var ly = Math.min(y1, y2);
            var hy = Math.max(y1, y2);

            for (var cx = Math.floor(lx / cell); cx <= Math.floor(hx / cell); cx++) {
                for (var cy = Math.floor(ly / cell); cy <= Math.floor(hy / cell); cy++) {
                    var near = buckets.get(bucket(cx, cy));

                    if (!near) {
                        continue;
                    }

                    for (var i = 0; i < near.length; i++) {
                        var r = near[i];
                        var acrossX = (lx < hx) ? (hx > r.x1 && lx < r.x2) : (lx > r.x1 && lx < r.x2);
                        var acrossY = (ly < hy) ? (hy > r.y1 && ly < r.y2) : (ly > r.y1 && ly < r.y2);

                        if (acrossX && acrossY) {
                            return true;
                        }
                    }
                }
            }

            return false;
        }

        var xs = [];
        var ys = [];

        list.forEach(function (r) {
            xs.push(r.x1, r.x2);
            ys.push(r.y1, r.y2);
        });

        var bx = lines(xs);
        var by = lines(ys);
        var count = bx.length * by.length;

        // Whether a crossing of the box lines is free, and the leg from it
        // to the next line right and down, worked out once each (0 not yet,
        // 1 free, 2 blocked).
        return { list: list, blocked: blocked, xs: bx, ys: by, node: new Int8Array(count), right: new Int8Array(count), down: new Int8Array(count) };
    }

    // The obstacles of a list of boxes, kept for the same list and margin
    // (a canvas routes every arrow of one drawing over one list).
    var obstacleCache = (typeof WeakMap === 'function') ? new WeakMap() : null;

    function obstaclesOf(boxes, margin) {
        var held = (obstacleCache && boxes) ? obstacleCache.get(boxes) : null;

        if (held && held.margin === margin && held.count === boxes.length) {
            return held.room;
        }

        var room = obstacles(boxes, margin);

        if (obstacleCache && boxes) {
            obstacleCache.set(boxes, { margin: margin, count: boxes.length, room: room });
        }

        return room;
    }

    // The sorted values with the extra ones added, no repeats.
    function withLines(sorted, extra) {
        var out = sorted.slice();

        extra.forEach(function (value) {
            var lo = 0;
            var hi = out.length;

            while (lo < hi) {
                var mid = (lo + hi) >> 1;

                if (out[mid] < value) {
                    lo = mid + 1;
                } else {
                    hi = mid;
                }
            }

            if (out[lo] !== value) {
                out.splice(lo, 0, value);
            }
        });

        return out;
    }

    // The sorted values with no repeats.
    function lines(values) {
        var sorted = values.slice().sort(function (a, b) { return a - b; });

        return sorted.filter(function (value, i) { return !i || value !== sorted[i - 1]; });
    }

    function search(sorted, value) {
        var lo = 0;
        var hi = sorted.length - 1;

        while (lo <= hi) {
            var mid = (lo + hi) >> 1;

            if (sorted[mid] === value) {
                return mid;
            }

            if (sorted[mid] < value) {
                lo = mid + 1;
            } else {
                hi = mid - 1;
            }
        }

        return -1;
    }

    // Drops the points between two others on one line.
    function straighten(points) {
        var out = [];

        points.forEach(function (p) {
            var last = out[out.length - 1];

            if (last && last.x === p.x && last.y === p.y) {
                return;
            }

            if (out.length >= 2) {
                var a = out[out.length - 2];

                if ((a.x === last.x && last.x === p.x) || (a.y === last.y && last.y === p.y)) {
                    out[out.length - 1] = p;
                    return;
                }
            }

            out.push(p);
        });

        return out;
    }

    // The search's arrays for the routes over one list of boxes, kept with
    // its obstacles and reused: a value counts only when its stamp is the
    // route's own.
    function workFor(room, size) {
        if (!room.work || room.work.size < size) {
            var length = Math.max(size, room.work ? room.work.size * 2 : 0);

            room.work = { size: length, stamp: 0, cost: new Float64Array(length), back: new Int32Array(length), seen: new Int32Array(length), shut: new Int32Array(length) };
        }

        return room.work;
    }

    // A right-angle way from a port to a box, through the gaps between the
    // boxes: from { x, y, side } (side 'right' unless given: the way first
    // runs margin units out of it), to the box { x, y, w, h } it ends on,
    // at the middle of the edge it reaches first (a left or right edge at
    // opts.y when given), boxes every box to keep clear of, its two ends
    // among them. opts (all optional): margin (16, the room kept round a
    // box), bend (24, what a turn costs in units), budget (20000 steps
    // tried). Answers the points [{ x, y }, ...], from the port to the
    // edge, each turn once; null when no way is found within the budget.
    function route(from, to, boxes, opts) {
        opts = opts || {};

        var margin = (opts.margin === undefined) ? 16 : opts.margin;
        var bend = (opts.bend === undefined) ? 24 : opts.bend;
        var budget = opts.budget || 20000;
        var room = obstaclesOf(boxes, margin);
        var side = SIDES[from.side] ? from.side : 'right';
        var start = { x: from.x + SIDES[side][0] * margin, y: from.y + SIDES[side][1] * margin };
        var yAt = (typeof opts.y === 'number') ? opts.y : to.y + to.h / 2;
        var goals = [
            { side: 'left', edge: { x: to.x, y: yAt } },
            { side: 'right', edge: { x: to.x + to.w, y: yAt } },
            { side: 'top', edge: { x: to.x + to.w / 2, y: to.y } },
            { side: 'bottom', edge: { x: to.x + to.w / 2, y: to.y + to.h } }
        ].map(function (goal) {
            var step = SIDES[goal.side];

            goal.at = { x: goal.edge.x + step[0] * margin, y: goal.edge.y + step[1] * margin };
            // The way into the edge runs against the side it is on.
            goal.dir = (SIDE_NAMES.indexOf(goal.side) + 2) % 4;

            return goal;
        }).filter(function (goal) {
            return !room.blocked(goal.at.x, goal.at.y, goal.at.x, goal.at.y);
        });

        if (!goals.length || room.blocked(start.x, start.y, start.x, start.y)) {
            return null;
        }

        var xs = withLines(room.xs, [start.x].concat(goals.map(function (goal) { return goal.at.x; })));
        var ys = withLines(room.ys, [start.y].concat(goals.map(function (goal) { return goal.at.y; })));
        var ny = ys.length;
        var goalAt = {};

        goals.forEach(function (goal) {
            goalAt[search(xs, goal.at.x) * ny + search(ys, goal.at.y)] = goal;
        });

        var nx = xs.length;
        var nodes = nx * ny;
        // Each line's place among the box lines, -1 for a line of this
        // route's own (its port, its goals).
        var bny = room.ys.length;
        var xmap = xs.map(function (v) { return search(room.xs, v); });
        var ymap = ys.map(function (v) { return search(room.ys, v); });
        // Per node off the box lines: 0 not yet known, 1 free, 2 inside a box.
        var free = new Int8Array(nodes);

        function open(node) {
            var xi = (node / ny) | 0;
            var yi = node % ny;
            var held = (xmap[xi] >= 0 && ymap[yi] >= 0) ? room.node : free;
            var at = (held === free) ? node : xmap[xi] * bny + ymap[yi];

            if (!held[at]) {
                held[at] = room.blocked(xs[xi], ys[yi], xs[xi], ys[yi]) ? 2 : 1;
            }

            return held[at] === 1;
        }

        // Whether the leg between two neighbouring nodes is clear.
        function clear(xi, yi, nxi, nyi) {
            var ax = xmap[Math.min(xi, nxi)];
            var ay = ymap[Math.min(yi, nyi)];

            if (ax >= 0 && ay >= 0) {
                var across = yi === nyi;
                var bx2 = across ? xmap[Math.max(xi, nxi)] : ax;
                var by2 = across ? ay : ymap[Math.max(yi, nyi)];

                if ((across && bx2 === ax + 1) || (!across && by2 === ay + 1)) {
                    var held = across ? room.right : room.down;
                    var at = ax * bny + ay;

                    if (!held[at]) {
                        held[at] = room.blocked(xs[xi], ys[yi], xs[nxi], ys[nyi]) ? 2 : 1;
                    }

                    return held[at] === 1;
                }
            }

            return !room.blocked(xs[xi], ys[yi], xs[nxi], ys[nyi]);
        }

        var gx = goals.map(function (goal) { return goal.at.x; });
        var gy = goals.map(function (goal) { return goal.at.y; });
        var gd = goals.map(function (goal) { return goal.dir; });

        // The least a way from (x, y), arriving in dir, still costs to
        // enter a goal: the distance and the turns it cannot do without
        // (none on the goal's own line heading for it; two heading the
        // other way or along it off its line; else one).
        function guess(x, y, dir) {
            var best = Infinity;

            for (var i = 0; i < gx.length; i++) {
                var dx = gx[i] - x;
                var dy = gy[i] - y;
                var turns = 1;

                if (dir === gd[i]) {
                    var onLine = (dir === 0) ? (dy === 0 && dx >= 0) : (dir === 2) ? (dy === 0 && dx <= 0) : (dir === 1) ? (dx === 0 && dy >= 0) : (dx === 0 && dy <= 0);

                    turns = onLine ? 0 : 2;
                } else if ((dir + 2) % 4 === gd[i]) {
                    turns = 2;
                }

                var est = Math.abs(dx) + Math.abs(dy) + turns * bend;

                if (est < best) {
                    best = est;
                }
            }

            return best;
        }

        // A state is a node (a crossing of two lines) and the direction the
        // way arrived in; a state past the last one marks a way that has
        // entered the edge of goal (state - states).
        var states = nodes * 4;
        var work = workFor(room, states + goals.length);
        var stamp = ++work.stamp;
        var costs = work.cost;
        var backs = work.back;
        var seen = work.seen;
        var shut = work.shut;

        function cost(state) {
            return (seen[state] === stamp) ? costs[state] : Infinity;
        }

        function setCost(state, value, from) {
            seen[state] = stamp;
            costs[state] = value;
            backs[state] = from;
        }

        function back(state) {
            return (seen[state] === stamp) ? backs[state] : -1;
        }
        // A binary heap of states by cost guess; between equal guesses the
        // one that came further first (fewer ways tried), then the first
        // pushed.
        var heapF = [];
        var heapG = [];
        var heapO = [];
        var heapS = [];
        var order = 0;

        function before(i, j) {
            return heapF[i] < heapF[j] || (heapF[i] === heapF[j] && (heapG[i] > heapG[j] || (heapG[i] === heapG[j] && heapO[i] < heapO[j])));
        }

        function swap(i, j) {
            var f = heapF[i];
            var gv = heapG[i];
            var o = heapO[i];
            var st = heapS[i];

            heapF[i] = heapF[j];
            heapG[i] = heapG[j];
            heapO[i] = heapO[j];
            heapS[i] = heapS[j];
            heapF[j] = f;
            heapG[j] = gv;
            heapO[j] = o;
            heapS[j] = st;
        }

        function push(f, state, g) {
            var i = heapF.length;

            heapF.push(f);
            heapG.push(g);
            heapO.push(order++);
            heapS.push(state);

            while (i > 0) {
                var up = (i - 1) >> 1;

                if (!before(i, up)) {
                    break;
                }

                swap(i, up);
                i = up;
            }
        }

        function pop() {
            var top = heapS[0];
            var last = heapF.length - 1;

            swap(0, last);
            heapF.pop();
            heapG.pop();
            heapO.pop();
            heapS.pop();

            var i = 0;

            for (;;) {
                var l = 2 * i + 1;
                var r = l + 1;
                var m = i;

                if (l < heapF.length && before(l, m)) {
                    m = l;
                }

                if (r < heapF.length && before(r, m)) {
                    m = r;
                }

                if (m === i) {
                    break;
                }

                swap(i, m);
                i = m;
            }

            return top;
        }

        var goalAt = new Int8Array(nodes).fill(-1);

        goals.forEach(function (goal, n) {
            goalAt[search(xs, goal.at.x) * ny + search(ys, goal.at.y)] = n;
        });

        var startState = (search(xs, start.x) * ny + search(ys, start.y)) * 4 + SIDE_NAMES.indexOf(side);

        setCost(startState, margin, -1);
        push(margin + guess(start.x, start.y, SIDE_NAMES.indexOf(side)), startState, margin);

        var tried = 0;
        var done = -1;

        while (heapF.length && tried < budget) {
            var state = pop();

            if (shut[state] === stamp) {
                continue;
            }

            shut[state] = stamp;

            if (state >= states) {
                done = state;
                break;
            }

            tried++;

            var g = cost(state);
            var node = state >> 2;
            var dir = state & 3;
            var xi = (node / ny) | 0;
            var yi = node % ny;
            var x = xs[xi];
            var y = ys[yi];
            var reach = goalAt[node];

            if (reach >= 0) {
                var goal = goals[reach];
                var turns = (goal.dir === dir) ? 0 : ((goal.dir + 2) % 4 === dir ? 2 : 1);
                var fin = states + reach;
                var fg = g + margin + turns * bend;

                if (fg < cost(fin)) {
                    setCost(fin, fg, state);
                    push(fg, fin, fg);
                }
            }

            for (var d = 0; d < 4; d++) {
                if ((d + 2) % 4 === dir) {
                    continue;
                }

                var nxi = xi + ((d === 0) ? 1 : (d === 2) ? -1 : 0);
                var nyi = yi + ((d === 1) ? 1 : (d === 3) ? -1 : 0);

                if (nxi < 0 || nyi < 0 || nxi >= nx || nyi >= ny) {
                    continue;
                }

                var next = nxi * ny + nyi;
                var nstate = next * 4 + d;

                if (shut[nstate] === stamp) {
                    continue;
                }

                var tx = xs[nxi];
                var ty = ys[nyi];
                // Up costs a hair more than down, so that a tie goes under.
                var ng = g + Math.abs(tx - x) + Math.abs(ty - y) * ((d === 3) ? 1.001 : 1) + ((d === dir) ? 0 : bend);

                if (ng >= cost(nstate) || !open(next) || !clear(xi, yi, nxi, nyi)) {
                    continue;
                }

                setCost(nstate, ng, state);
                push(ng + guess(tx, ty, d), nstate, ng);
            }
        }

        if (done < 0) {
            return null;
        }

        var points = [goals[done - states].edge];

        for (var s = back(done); s >= 0; s = back(s)) {
            var at = s >> 2;

            points.push({ x: xs[(at / ny) | 0], y: ys[at % ny] });
        }

        points.push({ x: from.x, y: from.y });

        return straighten(points.reverse());
    }

    // The SVG path of a right-angle way, each turn rounded (at most radius,
    // at most half of the shorter of its two legs), and its middle: the
    // point half-way along it ({ d, mx, my }, as curve() gives).
    function path(points, radius) {
        var r = (radius === undefined) ? 8 : radius;
        var d = 'M' + points[0].x + ' ' + points[0].y;
        var total = 0;

        for (var i = 1; i < points.length; i++) {
            total += Math.abs(points[i].x - points[i - 1].x) + Math.abs(points[i].y - points[i - 1].y);
        }

        for (var j = 1; j < points.length - 1; j++) {
            var a = points[j - 1];
            var p = points[j];
            var b = points[j + 1];
            var inLen = Math.abs(p.x - a.x) + Math.abs(p.y - a.y);
            var outLen = Math.abs(b.x - p.x) + Math.abs(b.y - p.y);
            var k = Math.min(r, inLen / 2, outLen / 2);
            var ix = Math.sign(p.x - a.x);
            var iy = Math.sign(p.y - a.y);
            var ox = Math.sign(b.x - p.x);
            var oy = Math.sign(b.y - p.y);

            d += ' L' + (p.x - ix * k) + ' ' + (p.y - iy * k) + ' Q' + p.x + ' ' + p.y + ' ' + (p.x + ox * k) + ' ' + (p.y + oy * k);
        }

        var end = points[points.length - 1];
        var half = total / 2;
        var mx = points[0].x;
        var my = points[0].y;

        d += ' L' + end.x + ' ' + end.y;

        for (var n = 1; n < points.length; n++) {
            var len = Math.abs(points[n].x - points[n - 1].x) + Math.abs(points[n].y - points[n - 1].y);

            if (half <= len) {
                mx = points[n - 1].x + Math.sign(points[n].x - points[n - 1].x) * half;
                my = points[n - 1].y + Math.sign(points[n].y - points[n - 1].y) * half;
                break;
            }

            half -= len;
        }

        return { d: d, mx: mx, my: my };
    }

    // Ways (point lists from route(), null kept as null) whose inner legs
    // run on one line and overlap moved apart, gap units between
    // neighbours (6), spread round that line in the order given; a way's
    // first and last legs, at its ports, stay where they are. room (none
    // when not given) is the most a leg may move off its line: more ways
    // than fit gap apart in it are drawn closer. Answers new point lists.
    function lanes(routes, gap, room) {
        var spacing = (gap === undefined) ? 6 : gap;
        var out = (routes || []).map(function (points) {
            return points ? points.map(function (p) { return { x: p.x, y: p.y }; }) : null;
        });
        var legs = {};

        out.forEach(function (points, r) {
            if (!points) {
                return;
            }

            for (var i = 1; i < points.length - 2; i++) {
                var a = points[i];
                var b = points[i + 1];
                var upright = a.x === b.x;
                var key = (upright ? 'v' : 'h') + (upright ? a.x : a.y);

                (legs[key] = legs[key] || []).push({ r: r, i: i, upright: upright, lo: upright ? Math.min(a.y, b.y) : Math.min(a.x, b.x), hi: upright ? Math.max(a.y, b.y) : Math.max(a.x, b.x) });
            }
        });

        Object.keys(legs).forEach(function (key) {
            var list = legs[key];
            var seen = [];

            // Legs that overlap, directly or through another, share a gap.
            list.forEach(function (leg, n) {
                if (seen[n]) {
                    return;
                }

                var group = [n];

                seen[n] = true;

                for (var g = 0; g < group.length; g++) {
                    var one = list[group[g]];

                    list.forEach(function (other, m) {
                        if (!seen[m] && other.lo < one.hi && one.lo < other.hi) {
                            seen[m] = true;
                            group.push(m);
                        }
                    });
                }

                if (group.length < 2) {
                    return;
                }

                var step = spacing;

                if (typeof room === 'number' && room >= 0) {
                    step = Math.min(step, 2 * room / (group.length - 1));
                }

                group.sort(function (a, b) { return (list[a].r - list[b].r) || (list[a].i - list[b].i); });
                group.forEach(function (m, place) {
                    var leg = list[m];
                    var shift = (place - (group.length - 1) / 2) * step;
                    var points = out[leg.r];

                    if (leg.upright) {
                        points[leg.i].x += shift;
                        points[leg.i + 1].x += shift;
                    } else {
                        points[leg.i].y += shift;
                        points[leg.i + 1].y += shift;
                    }
                });
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
    // its tallest box), before and after ({ id, label, w, h, gap, maxH,
    // items or groups }: items stacked one under another, each group of
    // them with its own heading; maxH, a number or 'grid' for the grid's
    // height, is the column's greatest height: past it the boxes go on in
    // further columns beside it, gapX apart, at least four boxes each, a
    // sub-heading moving on with its first box, the column's heading over
    // all of them). Answers { places: { id: { x, y } }, headings:
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
        // The fewest boxes a wrapped side column holds.
        var sideLeast = 4;

        function sized(id) {
            return opts.size ? opts.size(id) : null;
        }

        function place(id, x, y, bw, bh) {
            places[id] = { x: Math.round(x), y: Math.round(y) };
            boxes.push({ x: x, y: y, w: bw, h: bh });
        }

        function title(id, label, x, y, width, kind) {
            var heading = { id: id, label: label, x: Math.round(x), y: Math.round(y), w: Math.round(width), kind: kind };
            var box = { x: x, y: y, w: width, h: head };

            headings.push(heading);
            boxes.push(box);

            return { heading: heading, box: box };
        }

        function filled(column) {
            return !!column && !!((column.items && column.items.length) || (column.groups || []).some(function (group) { return group && group.items && group.items.length; }));
        }

        // The grid's bands, placed from gridX once it is known; the grid's
        // height is known first (a side column may wrap at it).
        var cells = [];
        var bandHeads = [];
        var gridCols = Math.min(cols, bands.reduce(function (most, group) { return Math.max(most, group.items.length); }, 0));
        var gridW = gridCols ? gridCols * w + (gridCols - 1) * gapX : 0;
        var y = y0;

        bands.forEach(function (group, band) {
            if (band) {
                y += bandGap;
            }

            if (group.label) {
                bandHeads.push({ id: group.id, label: group.label, y: y });
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

                    cells.push({ id: id, dx: col * (w + gapX), y: y, w: (size && size.w) || w, h: bh });
                    rowH = Math.max(rowH, bh);
                });
                y += rowH;
            }
        });

        var gridH = y - y0;

        // A side column from x; answers its width. Past its greatest height
        // it takes as many columns as that height needs (at most one per
        // four boxes), each as low as that many allow.
        function side(column, x) {
            var cw = column.w || w;
            var ch = column.h || h;
            var gap = (column.gap === undefined) ? gapY : column.gap;
            var most = (column.maxH === 'grid') ? gridH : (Number(column.maxH) || 0);
            var parts = (column.groups || [{ items: column.items }]).filter(function (group) { return group && group.items && group.items.length; });
            var total = parts.reduce(function (sum, group) { return sum + group.items.length; }, 0);
            var top = y0 + (column.label ? head : 0);

            function heightOf(id) {
                var size = sized(id);

                return (size && size.h) || ch;
            }

            // Stacks the column with columns no taller than limit (0: one
            // column), a column broken only after least boxes; places them
            // when put, and answers how many columns it took.
            function stack(limit, least, put) {
                var y = top;
                var col = 0;
                var count = 0;

                function wrap(need) {
                    if (limit > 0 && count >= least && (y + need > y0 + limit)) {
                        col++;
                        y = top;
                        count = 0;
                    }
                }

                parts.forEach(function (group) {
                    if (group.label) {
                        wrap(head + heightOf(group.items[0]));

                        if (put) {
                            title(group.id, group.label, x + col * (cw + gapX), y, cw, 'sub');
                        }
                        y += head;
                    }

                    group.items.forEach(function (id) {
                        var size = sized(id);
                        var bh = (size && size.h) || ch;

                        wrap(bh);

                        if (put) {
                            place(id, x + col * (cw + gapX), y, (size && size.w) || cw, bh);
                        }
                        y += bh + gap;
                        count++;
                    });
                });

                return col + 1;
            }

            var limit = 0;

            if (most > 0) {
                var columns = Math.max(1, Math.min(stack(most, 1, false), Math.floor(total / sideLeast)));

                if (columns > 1) {
                    // The lowest height that keeps to that many columns.
                    var low = 1;
                    var high = most;

                    while (stack(high, sideLeast, false) > columns) {
                        high *= 2;
                    }
                    while (low < high) {
                        var mid = Math.floor((low + high) / 2);

                        if (stack(mid, sideLeast, false) > columns) {
                            low = mid + 1;
                        } else {
                            high = mid;
                        }
                    }
                    limit = low;
                }
            }

            var label = column.label ? title(column.id, column.label, x, y0, cw, 'side') : null;
            var cols = stack(limit, sideLeast, true);
            var width = cols * cw + (cols - 1) * gapX;

            if (label) {
                label.heading.w = Math.round(width);
                label.box.w = width;
            }

            return width;
        }

        var before = filled(opts.before) ? opts.before : null;
        var after = filled(opts.after) ? opts.after : null;
        var gridX = before ? x0 + side(before, x0) + sideGap : x0;

        bandHeads.forEach(function (band) {
            title(band.id, band.label, gridX, band.y, gridW, 'group');
        });
        cells.forEach(function (cell) {
            place(cell.id, gridX + cell.dx, cell.y, cell.w, cell.h);
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
    // Optional: host.size(item) a box's { w, h } (else 208 x 80),
    // host.zoomMin and host.zoomMax (else 0.25 and 2; never below 0.05),
    // and host.start() a point ({ x, y }, or null) fit() shows 48 px in from
    // the top left when everything does not fit at the smallest zoom (else
    // the middle of everything).
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
            var whole = Math.min((rect.width - 96) / Math.max(1, maxX - minX), (rect.height - 96) / Math.max(1, maxY - minY));
            var k = clamp(whole, zoomMin, Math.min(1.25, zoomMax));
            var start = (whole < zoomMin && host.start) ? host.start() : null;

            if (start) {
                host.view({ x: 48 - start.x * k, y: 48 - start.y * k, k: k });
            } else {
                host.view({ x: (rect.width - (maxX - minX) * k) / 2 - minX * k, y: (rect.height - (maxY - minY) * k) / 2 - minY * k, k: k });
            }
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
    //    optional size(item), zoomMin, zoomMax and start() as viewport()
    //    takes them
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
    //    { y } from the box's top edge, or { x, y, side } from its top left
    //    with the side the arrow leaves by (right, left, top or bottom; else
    //    read from the row holding the [data-port] dot inside the box);
    //  - route (optional, 'elbow'): the arrows drawn run in right angles
    //    with rounded turns through the gaps between the boxes of items()
    //    (G.route(), G.path()), arrows sharing a gap side by side
    //    (G.lanes()), to the nearest edge of their target; an arrow with no
    //    way, a hidden one and, past 150 drawn, a faint one keep the curve.
    //    A box moved routes its own arrows again; routeMargin (optional,
    //    16) is the room an arrow keeps round a box: arrows sharing a gap
    //    spread 6 apart round a line that far from the box, so a canvas
    //    with wide gaps and many arrows gives more;
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
    //  - focusChanged(id) (optional): told the id in focus (null for none)
    //    each time it changes, by focus() or by a press;
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
        var view = viewport({ ui: ui, view: adapter.view, items: adapter.items, size: adapter.size, zoomMin: adapter.zoomMin, zoomMax: adapter.zoomMax, start: adapter.start });
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
        // Right-angle arrows (adapter.route 'elbow'): the boxes they keep
        // clear of, and whether a settle() is on its way.
        var elbow = adapter.route === 'elbow';
        var routeBoxes = null;
        var settling = false;

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

            var x = box.w;
            var side = 'right';

            if (at && (typeof at.y === 'number')) {
                y = at.y;
            }

            if (at && (typeof at.x === 'number')) {
                x = at.x;
                side = SIDES[at.side] ? at.side : side;
            }

            return { x: item.x + x, y: item.y + y, side: side };
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

            routeBoxes = null;

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

        // An arrow's curve, as every arrow is drawn without adapter.route.
        function pathOf(one) {
            var start = from(one.from, one.options.port);
            var end = into(one.to, one.options.slot || 0, one.options.slots || 1);

            return curve(start.x, start.y, end.x, end.y);
        }

        function draw(one, line) {
            one.group.children[0].setAttribute('d', line.d);
            one.group.children[1].setAttribute('d', line.d);
        }

        // ── Right-angle arrows (adapter.route 'elbow') ──

        // The boxes an arrow keeps clear of, as they are now; made again
        // after a box is drawn or moved.
        function boxList() {
            if (!routeBoxes) {
                routeBoxes = adapter.items().map(function (item) {
                    var box = size(item);

                    return { id: item.id, x: item.x, y: item.y, w: box.w, h: box.h };
                });
            }

            return routeBoxes;
        }

        // Every arrow drawn routed through the gaps (one still routed keeps
        // its way), arrows sharing a gap spread side by side; a hidden arrow,
        // a faint one while more than ROUTE_MOST are drawn, and one with no
        // way keep the curve.
        function settle() {
            settling = false;

            if (!elbow) {
                return;
            }

            var arrows = live();
            var shown = arrows.filter(function (one) { return one.state !== 'hidden'; }).length;
            var routed = [];

            arrows.forEach(function (one) {
                var wanted = (one.state !== 'hidden') && !((one.state === 'faint') && (shown > ROUTE_MOST));

                if (!wanted) {
                    if (one.points !== null) {
                        one.points = null;
                        draw(one, pathOf(one));
                    }
                    return;
                }

                if (one.points === null) {
                    var start = from(one.from, one.options.port);
                    var end = into(one.to, one.options.slot || 0, one.options.slots || 1);
                    var box = size(one.to);

                    one.points = route(start, { x: one.to.x, y: one.to.y, w: box.w, h: box.h }, boxList(), { y: end.y, margin: adapter.routeMargin }) || false;

                    if (!one.points) {
                        draw(one, pathOf(one));
                    }
                }

                if (one.points) {
                    routed.push(one);
                }
            });

            var margin = (adapter.routeMargin === undefined) ? 16 : adapter.routeMargin;

            // The faint arrows spread among themselves, under the others,
            // so they never crowd the arrows of the box in focus; lanes stay
            // 4 clear of a box: at most margin - 4 off their line.
            [true, false].forEach(function (faintOnes) {
                var set = routed.filter(function (one) { return (one.state === 'faint') === faintOnes; });

                lanes(set.map(function (one) { return one.points; }), 6, Math.max(0, margin - 4)).forEach(function (points, i) {
                    draw(set[i], path(points, ROUTE_RADIUS));
                });
            });
        }

        // settle() once the arrows being drawn now are all there.
        function settleSoon() {
            if (elbow && !settling) {
                settling = true;
                Promise.resolve().then(settle);
            }
        }

        // An arrow from a box's right edge (or its named port) to a target's
        // left one, its id (an index, or a stable name) in data-edge, a wide
        // invisible path over it to press; its group is added to ui.svg.
        // options: { port, slot, slots } (spread()). Its path and middle are
        // returned.
        function edge(id, source, target, options) {
            var one = { from: source, to: target, options: options || {}, points: null };
            var line = pathOf(one);
            var group = svgEl('g', classes.edge);
            var stroke = svgEl('path', classes.edge + '-line');
            var hit = svgEl('path', classes.edge + '-hit');

            group.setAttribute('data-edge', String(id));
            stroke.setAttribute('d', line.d);
            hit.setAttribute('d', line.d);
            group.appendChild(stroke);
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

            settleSoon();

            return { group: group, path: line };
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
                settle();
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

            if (moved && adapter.focusChanged) {
                adapter.focusChanged(next);
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
                    one.points = null;
                    draw(one, pathOf(one));
                }
            });

            routeBoxes = null;
            settle();
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
        route: route,
        path: path,
        lanes: lanes,
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
