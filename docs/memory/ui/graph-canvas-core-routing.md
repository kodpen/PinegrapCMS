---
name: graph-canvas-core-routing
description: Graph canvas core routing (PV5): G.route() right-angle ways through the gaps, G.path() rounded turns, G.lanes() side by side, adapter route 'elbow', when an arrow keeps the curve, speed
module: graph_canvas
---

## What does G.route(from, to, boxes, opts) give?
- A right-angle way from a port `{ x, y, side }` (side right unless given; the way first runs `margin` out of it) to the box `to` `{ x, y, w, h }`: the points from the port to the middle of the edge it reaches first (a left or right edge at `opts.y` when given), each turn once. Null when no way is found within `opts.budget` (20000 steps).
- Every box in `boxes`, its two ends included, is kept `opts.margin` (16) clear; a turn costs `opts.bend` (24) units. A tie goes under rather than over (up costs 0.1 % more).
- How: A* over the crossings of the lines `margin` outside every box edge, plus the port's and the goals' own lines; the guess is the distance plus the turns it cannot do without. The grown boxes sit in 256-unit buckets; the box lines, which nodes are free and which legs are clear are kept per `boxes` array (a WeakMap): pass a new array once a box moves.

## What do G.path() and G.lanes() give?
- `G.path(points, radius)` → `{ d, mx, my }` as `curve()` gives: turns rounded with a quadratic corner of at most `radius` (8) and half the shorter leg; the middle is half-way along.
- `G.lanes(routes, gap)`: inner legs of different ways on one line that overlap (directly or through another) move apart, `gap` (6) units between neighbours, centred on the line, in the order given; the first and last legs (at the ports) stay. A null way stays null; the ways given are not changed.

## What does adapter route 'elbow' do?
- `create()` routes each drawn arrow over the boxes of `items()` (with `size()`) to the nearest edge of its target; `settle()` runs once the edges of a drawing are in (a microtask), on each focus or "All arrows" change, and from `reroute()` (a moved box routes its own arrows; others keep their way). Ways sharing a gap go through `lanes()`.
- An arrow keeps today's curve while hidden, while it is faint with more than `ROUTE_MOST` (150) drawn, and when `route()` finds no way. Every arrow is a curve at first and becomes a way when the drawing settles.
- `portAt(item, name)` may answer `{ x, y, side }` from the box's top left: the arrow leaves there, by that side. `{ y }` alone is the right edge as before.
- Flows passes no `route`: every arrow a curve as before (its harness 40 of 40, with and without `--min`).

## Who uses it, and how is it checked?
- The Prototype view passes `route: 'elbow'` (F14, PV5; designer/prototype-focus.md).
- Tests: `test_graph_canvas_route` (neighbours in a row one line, far apart under the row, another row, a side column and a wrapped one from its farthest column, a box in the way, no way, path, lanes, 300 routes of a redraw under 100 ms as the best of three batches after a first one), `test_graph_canvas_elbow_arrows` (the view's switch in `create()`), `test_proto_hotspots_view_routes_arrows`.
- Speed: about 50 ms for 300 routes over 40 pages and 50 shared parts on a cloud container; the first batch, while the script engine compiles the router, takes about 80 ms.
