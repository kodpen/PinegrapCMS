---
name: workspace-flows-add-here
description: A flow box added where it goes (FE6, #11273): the "+" on an arrow and after a free outcome, the add list and "Often next", the arrow cut in two, the boxes moved, a dropped arrow end
---

## What opens the add list?
- Pointing at an arrow shows a 20 px "+" at its middle (`edgePlusAt()`); an arrow chosen keeps it. A dashed "+" stands 12 px right of each box with a free outcome (`drawPluses()`, redrawn with the arrows).
- A free outcome (`freeOutcome()`): the first of the box's outcomes with no arrow, never one of `SIDE_OUTCOMES` (failed, cancelled, timeout, expired, withdrawn); a task joined on done shows no "+".
- An arrow drawn from a port and let go on the empty canvas: the core's `dropped()` hook (`arrowDropped()`), joined on the first free outcome, else the first; the box lands at the drop point.
- A on the canvas: at the arrow chosen, or after the box chosen (`addAtSelected()`). "/" focuses the list's search, or opens the rail's card and its search (`searchBoxes()`). Both in the Keyboard list (`fl_key_add`).
- Only while the draft is edited: no run shown, no embedded drawing (`addable()`). The "+" buttons take no Tab stop; the core leaves their clicks alone (`ownClicks` `.wsf-plus`).

## What does the list hold? (openAdd(), drawAdd())
- A 280 px card in `.wsf-main` under the place (above it when there is no room), the search focused: typed words list every box whose name holds them; else "Often next" (`fl_add_often`), then "All" with the groups as chips and their counts, the chip pressed showing its boxes.
- What it offers (`addKinds()`): the palette's kinds an arrow may lead into; on an arrow only those that also lead on (no End, start or value box).
- "Often next" (`oftenNext()`, `OFTEN_NEXT`) is a fixed table by the box before: a message → Wait, Condition, Task; a Wait → Condition, Post a message, Task; else Wait, Condition, Send an e-mail. A kind not offered gives its place to the row's fourth. No use is counted.
- Keys: Esc closes it (the focus back on the box or the canvas), Enter in the search adds the first row, the arrows go from row to row. A click outside closes it.

## Where does the box go? (addHere(), one undo step)
- On an arrow (`insertOnEdge()`): the arrow's first half goes into the new box on its outcome, a second arrow leaves the new box on its first outcome to the old end. When the ends stand closer than `INSERT_GAP` (260 px between the box before's right edge and the end's left), the end and every box after it (`downstream()`, never through the box before) move one column right (`LAYOUT_STEP_X`, 288, as `WS_FLOW_LAYOUT_STEP_X`). The new box sits in the middle of the gap, on the grid.
- After a box: joined on the outcome its "+" stood for, one column right of it, a row (128) lower for each box there (`placeAfter()`), or at an arrow's dropped end.

## Where is it tested and shown?
- `test_workspace_flows_canvas_add_at_an_arrow` (Node): the table, free outcomes, the kinds offered, the cut, the move, the places.
- Harness scenario "a box added where it goes" (`workspace-flows-canvas-harness.md`).
- Design examples: the pattern "Add list" (`pg_look_examples_add_list_pattern()`).

Related: docs/memory/workspace/workspace-flows-canvas-tools.md, docs/memory/workspace/workspace-flows-palette.md, docs/memory/ui/graph-canvas-core-module.md
