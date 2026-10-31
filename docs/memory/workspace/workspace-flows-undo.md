---
name: workspace-flows-undo
description: Undo and redo on the flow canvas (workspace_flow.js): the toolbar buttons, Ctrl+Z / Ctrl+Y / Ctrl+Shift+Z, the history of graph snapshots, which changes fold into one step, when it resets
---

## Where are undo and redo?
- Toolbar Undo and Redo icon buttons beside Problems, drawn only while the draft is edited (`drawBar()`); `drawState()` disables them with no step to go to. The result ("Change undone" / "Change redone") is announced for screen readers; a button that goes grey hands the focus to the other one.
- Keys on the document (`bindWindow()`): Ctrl+Z undoes, Ctrl+Y or Ctrl+Shift+Z redoes (Cmd on a Mac). Skipped while `typing()` (an input, textarea, select or contenteditable keeps the browser's own undo), outside the screen's root (a dialog), with the template gallery or the scheduled section shown. The keyboard help lists them (`fl_key_undo`).

## What is a step?
- `changed(structure, step)` records `JSON.stringify(state.graph)` into `state.history` (`historyOf()`, `historyRecord()`, `historyStep()`; test `history_steps` in `tests/workspace_flows_canvas_test.php` runs them under Node). At most `HISTORY_MAX` (50) steps.
- A change that is neither structural nor its own step, made within `HISTORY_FOLD` (1 s) of another such change, folds into it: typing in a box's settings, arrow-key moves. A structural change (box or arrow added or removed), a box dropped after a drag (`changed(false, true)`) and a template applied (one `changed(true)`) are one step each.
- The flow's name and home channel live outside the graph: no step. A new change drops what was undone.
- A folding change is recorded once the changes pause `SETTLE_DELAY` (200 ms, `settle()`), with the data threads and outline reads put off by `refreshNode()`; a step of its own, `undo()`, `show()` and `showRun()` settle first; `undoable()` counts a waiting change. Each key used to write the graph as JSON and redraw every thread (150-300 ms a key at 97 boxes, #3497).

## How is an undo saved and when does history reset?
- Undo puts the snapshot back with `graphOf()`, keeps a selected box that is still there (an arrow, selected by its index, is unselected) and calls `changed(true)`: redrawn and saved by the normal autosave; it records nothing, the graph equals `history.now`.
- Off in the run view: `showRun()` sets `state.edit` false and the buttons are not drawn.
- `show(flow)` starts a new history for another flow and keeps it for the same flow (Publish and Pause call `show()` again, which only refreshes `history.now`). "Load it again" after a 409 clears it, so undo never brings back what the other screen wrote over.
