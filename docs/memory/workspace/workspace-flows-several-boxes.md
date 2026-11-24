---
name: workspace-flows-several-boxes
description: Several flow boxes at once (FE7, #11289): chosen with the core's gestures, the floating tools, moved together, Duplicate, Copy and Paste (localStorage, other flows) with arrows and threads, Delete
---

## How are several boxes chosen on the Flows canvas?
- By the core's gestures (`ui/graph-canvas-core-several.md`): Shift+click, Shift+drag on the empty canvas, Ctrl+A. `severalChosen()` closes the panel (`state.selected` null) while two or more are chosen; a box selected otherwise (Tab, the panel) lets the set go (`select()`).
- Not in an embedded drawing (`embed()` sets the adapter's `several` to null) or in the outline.

## What do the floating tools hold? (drawSeveral())
- `.pg-floatbar.wsf-several` in the canvas layer, 12 px under the lowest box, at its zoom: "{n} boxes" (`fl_boxes_count`), Duplicate, Copy, Frame (`workspace-flows-notes-frames.md`), Delete (`fl_delete_boxes`) as ghost small `pg-button`s. Copy alone while the draft cannot change (`addable()`). Redrawn with `markSelected()` and after a move.

## Which keys act on them? (canvasKey())
- Arrow keys move the set a grid step (Shift: four); Delete or Backspace removes it; Ctrl+C, Ctrl+V, Ctrl+D copy, paste, duplicate the set or the one box selected (Ctrl+D on one box is ⋯ › Duplicate, no arrows). Keyboard list: `fl_key_several`, `fl_key_copy`.

## What do Duplicate and Paste keep? (clipOf(), pasteClip(), addClip())
- `clipOf()`: the boxes in the graph's order, deep copies, the arrows among them only. `pasteClip()`: new ids by `freeId()`, the arrows and the `{{node.<id>.…}}` threads among them on the new ids (any string of the config, a condition's `rule.ref` too).
- A thread read from a box not copied stays in the same flow (`clip.flow`) and goes with its space elsewhere (`withoutToken()`). A start box is left out of a flow that has one, with its arrows (toast `fl_paste_no_start`). A kind not offered (`memberKind()`) is left out.
- Duplicate stands the copies right of the set (its width plus 32 px); Paste at their place; both a row (104) lower while one would cover a box. The flow's box limit is checked first. The copies are chosen; one undo step.
- The copy is kept in `localStorage` (`CLIP_KEY` `pg_flow_clip`), so it pastes into another flow; a browser refusing it gets `fl_copy_failed`.

## What does Delete do with several?
- `removeSeveral()`: the boxes, their arrows and the values other boxes read from them (`dropValuesOf()`), the arrow problems cleared; one undo step, `fl_removed_boxes` read out.

## Where is it tested?
- `test_workspace_flows_canvas_several_boxes_copied` (Node, the pure copy) and the harness scenario "several boxes" (`workspace-flows-canvas-harness.md`).

Related: docs/memory/workspace/workspace-flows-add-here.md, docs/memory/ui/graph-canvas-core-several.md, docs/memory/plans/flows-editor-next-screens-4.md
