---
name: graph-canvas-core-logic
description: Graph canvas core pure logic: G.order() ranks from a start, G.arrange() a wrapped grid of groups with side columns, G.linksOf(), G.check() dangling, unreached, stuck
module: graph_canvas
---

## What is it?
- Pure helpers of `assets/js/pg_graph_canvas.js` (`window.PGGraphCanvas`, here `G`) for any box-and-arrow user: nothing in them means page, link or flow, nothing touches the DOM. Added for the Prototype view (`plans/prototype-canvas.md`); Flows calls none of them and draws as before.
- A graph is `ids` (box ids, an array, its order meaningful) and `edges` (objects with `from` and `to`; other fields are kept and ignored). An edge whose end is not among `ids` is skipped by `order()` and reported by `check()`.
- Tests: `tests/graph_canvas_test.php` (`test_graph_canvas_order_and_links`, `test_graph_canvas_arrange`, `test_graph_canvas_check`), run in Node.

## What does G.order(ids, edges, start) give?
- `{ ids, rank }`: `rank[id]` is the breadth-first distance from `start` (0 for it), `null` for a box nothing reaches from it. `ids` is sorted by rank, the given order between equal ranks, the unreached last in the given order.
- A loop or a self-arrow changes nothing; a `start` that is not a box leaves every rank null and the order as given.

## What does G.arrange(groups, opts) give?
- `groups`: `[{ id, label, items }]` in their order; a group with no items is left out, one with no `label` gets no heading place.
- Each group is a band: its heading (`opts.head` high, 40) then its items in rows of `opts.cols` (8), `opts.w` x `opts.h` boxes (208 x 80) with `opts.gapX` / `opts.gapY` (48) between; the next band starts `opts.bandGap` (gapY) below. A row is as high as its tallest box when `opts.size(id)` gives sizes.
- `opts.before` and `opts.after`: a side column before the grid and one after it, `{ id, label, w, h, groups | items }`, `opts.sideGap` (2 x gapX) from the grid; its items stacked one under another (`gap`, default gapY), each sub-group with its own heading.
- Answers `{ places, headings, bounds }`, whole units from `opts.x`, `opts.y` (0): `places[id] = { x, y }`; `headings` `[{ id, label, x, y, w, kind }]` with kind `group`, `side` or `sub`; `bounds` `{ x, y, w, h }` over all of them (zeros when empty). The same input gives the same answer.

## What do G.linksOf() and G.check() give?
- `G.linksOf(edges, id)` → `{ out, in }`: the edges leaving and reaching `id`, in the given order, as given.
- `G.check(ids, edges, start)` → `{ dangling, unreached, stuck }`, reported only, nothing fixed:
  - `dangling`: edges whose `from` or `to` is no box (as given, in order).
  - `unreached`: boxes no edge from another box reaches; never `start`. A self-arrow does not count. The caller leaves out what is never one (the Prototype view: shared parts, chips).
  - `stuck`: boxes with no edge out to anything but themselves (an edge to a non-box counts as a way out).
- The Prototype view shows `unreached` from PV11a (F27); no screen shows `stuck`.
