---
name: graph-canvas-core-several
description: Graph canvas core, several boxes (FE7, #11289): the several() hook, Shift+click, the Shift+drag rectangle (pgc-lasso), Ctrl+A, a set dragged together, chosen() and choose(), inRect() and toggle()
module: graph_canvas
---

## Who gets several boxes?
- Only an adapter that gives `several(ids)` (read at each use, so a user may take it away: Flows sets it to null while it draws an embedded flow). The Prototype view gives none and keeps its single selection; a Shift+click there is a plain click.

## What chooses them?
- Shift+click on a box adds it or takes it out (`G.toggle()`); the set starts from the box `mark()` was last given. Shift+drag on the empty canvas draws `pgc-lasso` (`classes.lasso`) and adds the boxes it touches (`G.inRect(items, rect, size)`, drawn either way). Ctrl+A chooses every box.
- A set holds two boxes or more: one left over is told to the adapter as `select()` of that box. The adapter hears `several(ids)` each change, `[]` when the set goes.

## What lets them go?
- Esc (when the adapter's `key()` left it), a click on the empty canvas, a plain press on a box or arrow outside the set, `choose([])`. `mark()` drops a set whose boxes are gone (an undo).

## How do they move?
- A plain press on a chosen box drags the set: each box on the grid, `moved(item, items)` once at the end, `cancel()` puts all back. The viewport takes the focus, so the adapter's keys (Delete, the arrows) reach it.

## How are they marked and read?
- `mark(selected)` marks the set with `classes.selected` beside what is selected; `chosen()` gives the ids, `choose(ids)` sets them (ids that exist, two or more, else none). Choosing reads out `pgc_chosen` ("{n} boxes chosen").
- Tests: `test_graph_canvas_several_boxes` (Node).

Related: docs/memory/ui/graph-canvas-core-module.md, docs/memory/workspace/workspace-flows-several-boxes.md
