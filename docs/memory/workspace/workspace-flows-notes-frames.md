---
name: workspace-flows-notes-frames
description: Notes and frames on the Flows canvas (FE8): the graph's notes (by, day) and frames (30, folded), the API's frames part, the strip's Note, Frame in the tools of several boxes, drag, resize and fold
---

## What do the graph's notes and frames hold?
- `notes`: `{id, text, x, y, w, h, by, day}`: text up to 2,000 characters, `by` the writer's first name (80 characters, one line), `day` a `Y-m-d` date or ''. 200 notes (`WS_FLOW_MAX_NOTES`).
- `frames`: `{id, name, x, y, w, h, folded}`: name up to 160 characters on one line, sizes not below 0, `folded` true only from `true`, `1`, `'1'` or `'true'`. 30 frames (`WS_FLOW_MAX_FRAMES`); one more is a `too_many` problem a draft may hold.
- `ws_flow_graph_normalize()` keeps both; `ws_flow_graph_check()` reads neither but for their counts, and a run never reads them. `ws_flow_graph_empty()` has `frames`; `ws_flow_graph_present()` passes them (a hand-built graph with none answers []).
- No schema: both are keys of the graph JSON (`ws_flows.draft`, `ws_flow_versions.graph`).

## How does the API take them?
- `frames` is a body part of the draft writes beside `nodes`, `edges`, `notes` (`ws_api_flow_input()`): sent, it replaces the draft's frames; left out, they are kept. A template create refuses it with boxes. The `WorkspaceFlowGraph` schema names `frames` and the notes' `by` and `day`.

## How are notes drawn and changed? (workspace_flow.js, drawDecor())
- In `.wsf-notes` over the boxes: the writer and day (`toLocaleDateString`, day and short month), the text as a textarea edited in place (typing folds into one undo step), × and the corner grip while the draft may change (`addable()`).
- The strip's Note (`fl_note_add`, `bi-sticky`, only while `addable()`): a 208 by 128 note in the middle of what the canvas shows, signed with the first word of `BOOT.me.name` and today, its text focused. Its head drags it, the grip resizes it (at least 144 by 80); each is one undo step.

## How are frames drawn and changed?
- In `.wsf-frames` behind the arrows and boxes; the body lets the pointer through (the canvas pans and the Shift+drag rectangle works over it), the 36 px head takes it: the fold chevron, the name edited in place (placeholder "Frame", Enter or Esc leaves it), "{n} boxes" and ×.
- A frame holds the boxes whose middle lies in its rectangle (`frameInside()`); a drag of its head moves them with it.
- "Frame" in the tools of several boxes (`drawSeveral()`, `frameBoxes()`): a frame round them with 24 px at the sides and bottom and 48 at the top for the head, the boxes let go, its name focused.
- Folded: one 40 px row with its name and count; its boxes and every arrow touching them are hidden (`foldedAway()`, `state.foldedAway`). Fold and × are one undo step each; × keeps the boxes.
- Keys typed in a note or a frame's name never reach the canvas (the core's `typing()` and `ownClicks`).

## Where is it tested?
- `tests/workspace_flows_test.php`: `test_workspace_flows_note_writer_and_day`, `_frames_normalized`, `_frames_checked_for_count_only`, `_api_frames_part`.
- Harness scenario "notes and frames" (`workspace-flows-canvas-harness.md`).

Related: docs/memory/workspace/workspace-flows-several-boxes.md, docs/memory/workspace/workspace-flows-store.md, docs/memory/plans/flows-editor-next-screens-4.md
