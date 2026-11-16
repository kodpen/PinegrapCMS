---
name: prototype-canvas-steps
description: Plan prototype-canvas, the engine steps PV1, PV3 and PV5: graph logic, focus and routing added to the graph canvas core as optional parts that Flows does not pass
---

## Which steps are there?
- PV1 (M) Graph logic in the core, pure. Done (#9865).
  - `G.order(ids, edges, start)`: breadth-first ranks from `start`, the given order between equals, unreached ids last with no rank.
  - `G.arrange(groups, opts)`: places in a wrapped grid: `cols`, the box size, the gaps; each group a band with a heading's place; one side column before the grid and one after it. Answers `{ places, headings, bounds }` in whole units.
  - `G.linksOf(edges, id)`: `{ out, in }` in the order given.
  - `G.check(ids, edges, start)`: arrows whose end is no box, boxes nothing reaches, boxes with no way out. Reported only: PV11a shows the boxes nothing reaches; no screen shows the last.
  - Tests: an empty graph, a loop, islands, forty boxes in three groups, a group longer than a row, the same answer for the same input.
  - Memory: a new `ui/graph-canvas-core-logic.md`.
- PV3 (M) Focus in the core. Done (#9886).
  - Adapter `focus: true`; `canvas.focus(id or null)`, `canvas.focused()`. With a focus only that box's arrows are in the drawing, classed `pgc-edge-out` and `pgc-edge-in`; boxes and targets at neither end of one get `pgc-dim`. With no focus and the field set, no arrow is drawn. Without the field every arrow is drawn, as today.
  - `canvas.allEdges(on)`: the others drawn too, classed `pgc-edge-faint`, under the focused ones.
  - `adapter.groups()`: headings (`pgc-group`: a label and a rule) drawn behind the boxes at the places `arrange()` gave.
  - A press on a box focuses it, on the empty canvas clears it; a focus change redraws only the arrows that change; `announce()` says the box's name and its counts.
  - Tests: the class sets for a fixture; the new CSS on tokens (`test_graph_canvas_css_on_pgc_tokens`).
  - Memory: a new `ui/graph-canvas-core-focus.md`.
- PV5 (M) Routing in the core. After PV4b.
  - `G.route(from, to, boxes, opts)`, pure: the points of a right-angle path from a port's point to the nearest edge of the target, through the gaps, across no box but its two ends; null when it finds no way within its budget, and the caller draws the curve.
  - `G.path(points, radius)`: the path with rounded corners. `G.lanes(routes)`: parts that share a gap moved apart, 6 units between neighbours.
  - Adapter `route: 'elbow'` turns it on. `portAt()` may answer `{ x, y, side }` (today `{ y }`): the arrow starts at that point.
  - Only drawn arrows are routed; a moved box routes its own again; with more than 150 drawn, the faint ones keep the curve.
  - Tests: neighbours in a row (one straight line), far apart in a row (under the row), another row, a side column, a box in the way, no way (null), 300 routes under 100 ms.
  - Memory: a new `ui/graph-canvas-core-routing.md`.
  - The view turns it on in the same PR (F14). Its pictures and PV4's go to the owner as one sheet.
  - The sheet holds, none left out (architect, 2026-11-16, agent channel message 6120): a shared frame selected (the Header: its own arrows, one of them broken and red); nothing selected; a page other pages lead to selected (the incoming arrows); "All arrows" on with a frame selected; one of these at 390. PV4's pictures showed no broken arrow and no selected shared frame.
