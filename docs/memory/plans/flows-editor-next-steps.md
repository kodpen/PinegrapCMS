---
name: flows-editor-next-steps
description: Plan flows-editor-next, steps FE1 to FE5 (the canvas line): boxes as cards, the rail and bottom strip, the floating panel, data threads, the panel's fields; how a step is checked
---

## Which steps are there? (each enters `assets/js/workspace_flow.js` and the `.wsf-*` / `.pgc-*` rules of `backend.src.css`)
- FE1 (M) done (#11083): a box as a card (board A1): tile, three lines, ports, the ring, problem and run lines, value boxes dashed; arrows 1.75 px with the outcome's pill. The run view, the List view's headings and the read-only card draw the same box; the mini window (`.ws-fd-*`) stays. The pattern "Flow box" joins Design examples. Tests: `tests/workspace_flows_canvas_test.php` (group colours and icons kept), `look_examples`.
- FE2 (M) done (#11140): the add rail with its group icons and the list as a card beside it (`drawPalette()`, `foldPalette()`); the bottom strip with Undo, Redo, zoom, Fit and Data threads moved from the bar (`drawBar()`, `threadsButton()`); the bar's new order. `pg_wsf_palette` no longer read. Pattern "Canvas tools". Memory: `workspace/workspace-flows-canvas-tools.md`.
- FE3 (M) done (#11165): the chosen box's panel as a card over the canvas, closed with nothing chosen; the flow's facts, home channel and Keyboard list in the name's window; problems as a list under their button; a chosen box brought out from under the card (`clearOfCard()`). Memory: `workspace/workspace-flows-side-panel.md`, `workspace/workspace-flows-name-window.md`.
- FE4 (M) after FE3: data threads only for the box in hand (boards 1a, 1b): `drawData()`, `dataRowSlots()`, `markThreads()`, the "N values" mark, upright ends in `threadPath()` (pure, red test first), the strip's button drawing all. The run view and the read-only card follow.
- FE5 (M) after FE4: the panel's fields (boards 2a to 2c): one line of help and the "?" window for every `fl_*` help longer than a line; "Add value" inside the field with the list's head line and the "Always" group (`valueField()`, `valuesHint()`, `valueGroups()`); the e-mail's "Recipient" words as chips (the step reads which words the send path fills and lists them in `ws_flow_js_config()`); folded parts showing their state.

## Which steps run side by side?
- None of these: each waits for the one before it to land. FE6 to FE9 follow in `flows-editor-next-steps-2.md`; FE10a and FE11a there touch no canvas file and run beside this line.

## How is a step checked?
- Parts are copied from the Design examples screen; a part with no model there is drawn on the plan's boards and joins the screen in its step.
- Before ready: the editor at 1440 and 390 px, both themes, with a flow of at least six boxes and one with none, against the boards the step names; "Screen check" in the PR. The browser harness (`workspace/workspace-flows-canvas-harness.md`) is run by hand where the step changes pointer work, and the PR says so.
- Every new control is reached and worked from the keyboard, and the Keyboard list names its keys.
- Turkish for every new text with `php tools/tr_add.php`.

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-screens.md, docs/memory/plans/flows-editor-next-screens-2.md, docs/memory/plans/flows-editor-next-steps-2.md
