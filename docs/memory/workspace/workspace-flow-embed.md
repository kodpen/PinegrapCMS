---
name: workspace-flow-embed
description: PGWsFlow.embed(host, boot) in workspace_flow.js: the Flows canvas drawn read-only elsewhere (the flow draft card), one at a time; boot fields, focus-gated wheel, keys, onSelect, destroy
---

## What is it?
- `window.PGWsFlow.embed(host, boot)` draws one flow read-only with the Flows canvas itself (workspace_flow.js on pg_graph_canvas.js), for the flow draft card in a channel (`workspace-flow-draft-card-window.md`, #6630) and Add › Scheduled flow's window (`workspace-flows-scheduled-ways-in.md`, #6665). No second drawing of boxes, arrows or threads.
- The stage and its zoom buttons only (`layoutStage()`, shared with `layout()`): no toolbar, palette, side panel, outline, run bar, marks strip or sketch box; `bindWindow()` (Ctrl+S, undo, blur, beforeunload) is not bound. `state.edit` false; threads always on; fit on load.
- Needs `window.PGWsKit` (workspace.js) and both bundles on the page; returns null without them.

## Why one at a time?
- The canvas keeps one module `state` and one core `canvas` (decided #4967, answer 1). A second `embed()` calls the first's `destroy()`: its host gets back the nodes it held before. `embed()` returns null while an editor is mounted (`state.screen`, set by `mount()`, freed by its `destroy()`); `mount()` ends an embed first (`workspace-flow-mount.md`).

## What does boot carry?
- `graph` `{ nodes, edges }` at its real places; a node may carry `said`/`failed` (dry run) and `mark`.
- `mark` `[{ id, mark }]` (`ws_flow_patch_marks()`), `tries` `{ id: { said, failed } }` (`ws_flow_card_tries()`); both optional beside the nodes' own fields.
- `config`: the `FL` the drawing reads when the page's `K.cfg.flows` is empty. Data threads need `outputs` and `trigger_outputs` in it (`boxValues()`), else a field reading a value shows as a red chip with no thread.
- `strings` (texts beside the page's), `people`, `onSelect(node)`, `onClose()`.

## How are marks and the dry run drawn?
- Added / Changed through the screen's `paintMark()` (`state.marks`, as `marksOf()` builds it). The dry run is the box's meta line (`paintTry()`): `said` in place of the meta text (kept as the line's title), `fg-danger` when `failed`.

## What do the pointer and keys do?
- The wheel is the drawing's only while focus is inside it (a click, Tab): a capture listener on the wrapper stops it before the core's viewport listener, so the channel scrolls past an unopened card. A press focuses the viewport first. Pinch and zoom buttons as on the canvas.
- Keys: the core's (arrows pan, + − zoom, 0 fits); `canvasKey()` handles only Esc (lets the box go). `drawSettings()`, `drawPalette()` and `focusField()` return at once in an embed.

## What does it give back?
- `{ destroy(), fit(), select(id) }`. `onSelect` hears each change of picked box once: `{ id, kind, label, config, name, kindName, mark, said, failed }` or null.

## How was it checked? (#6592)
- tests/workspace_flows_canvas_test.php unchanged; the harness (`workspace-flows-canvas-harness.md`) gives the same result as before. The embed was tried in headless Chromium with a stub kit (wheel unfocused scrolls the page, focused pans; Delete, C, Enter change nothing; a second embed restores the first host); not in the harness, whose page runs the screen.
