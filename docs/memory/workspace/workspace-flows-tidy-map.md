---
name: workspace-flows-tidy-map
description: Tidy and the map on the Flows canvas strip (FE9, #11325): ws_flow_tidy over ws_flow_auto_layout() in columns, frames redrawn, one undo step; the map card, when it shows, its drag
---

## What does Tidy do?
- The strip's "Tidy" (`fl_tidy`, word and `bi-magic`, ghost small `pg-button`, only while `addable()`) sends the draft as the editor holds it and the chosen ids to the panel action `ws_flow_tidy` (owner's edit right, `ws_flow_for(..., 'edit')`); nothing is saved there, the autosave keeps the new places.
- `ws_flow_tidy($graph, $chosen)` (pure, `flow_draft.php`) answers id => [x, y] of the boxes whose place changed. Fewer than two chosen: every box laid out anew; two or more: only those, the others kept.
- With none kept, `ws_flow_auto_layout($graph, $placed, true)` puts each box in the column of its longest way in (`WS_FLOW_LAYOUT_ORIGIN + layer * WS_FLOW_LAYOUT_STEP_X`), its row from the box before, a row lower while taken. Without `$columns` (Pinegrap AI drafts, patches, a flow from a table) a box still stands after the first box it follows.
- In the editor `tidyPlaces()` moves the boxes, then draws each frame again round the boxes it held before (`framedIn()`, `frameRound()`), name and fold kept; a frame holding none stays; notes stay. One undo step (`changed(true)`); the whole flow is fitted after, not when several were tidied. Read out: `fl_tidied`, or `fl_tidy_none` when nothing moved. The button is disabled only while the request runs.

## What is the map?
- A 172 by 104 px card 16 px in from the canvas's bottom left (`.wsf-map` in `.pgc-stage`, `MAP` in `workspace_flow.js`): each box not folded away as a small block, the part on screen as a framed rectangle (`mapDrawing()`, 6 px clear).
- Shown from 12 boxes (`MAP.boxes`) by itself, or as the strip's Map (`fl_map`, `bi-map`, pressed while shown) chooses; the choice is kept per browser in `localStorage` `pg_wsf_map` ('on' / 'off'). Hidden in the List view, an embedded drawing and the gallery.
- Redrawn one frame after the view changes (the adapter's `view()` calls `mapLater()`), after `drawCanvas()` and after every change.
- A press or drag on it puts the point under the pointer in the canvas's middle (`mapPress()`, `mapMove()`, pointer capture). It is drawn by the flows editor, not by the canvas core.

## Where is it tested and shown?
- `tests/workspace_flow_draft_test.php`: `test_ws_flow_tidy`. `tests/workspace_flows_canvas_test.php`: `test_workspace_flows_canvas_tidy_and_map` (Node: places, frames, the map inside its card).
- Design examples: the pattern "Canvas tools" shows Tidy, Note, Map and the map card.

Related: docs/memory/workspace/workspace-flows-canvas-tools.md, docs/memory/workspace/workspace-flows-notes-frames.md, docs/memory/workspace/workspace-flows-overview.md
