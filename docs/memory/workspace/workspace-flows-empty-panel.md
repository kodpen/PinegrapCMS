---
name: workspace-flows-empty-panel
description: The flow window's right panel with no box chosen (FW4, #9228): the flow summary, home channel, the problems with "Go to the box"; the phone's "Add a box" page; the Problems button
---

## What does the panel show with nothing chosen? (sideFlow() in workspace_flow.js)
- The flow's head: its tile, "Flow", the name; no Close.
- Facts (`wsf-side-facts`): Start (the start box's kind, or "No start yet"), Boxes and arrows (the counts), Last flow run (its flag, or "Not run yet"), Publishing (the status pill, "Changes not published yet" while a live flow's draft differs).
- The live/draft hint (`flowHelp()`): a live flow with a version says changes stay in the draft; else "Nothing runs until the flow is published"; a reader sees "only its owner changes it". The "Select a box…" sentence of the old form is gone.
- The home channel field (`homeField()`), as before.
- The problems and warnings, then the Keyboard list folded.

## How are the problems shown?
- `drawProblems()` into `ui.sideProblems`, `problemList()`: a heading ("Problems: N" or "No problems", "Warnings: N"), then each a row in its tone (rose for a problem, amber for a warning) with its words and "Go to the box" (or "Go to the arrow"), which selects and reveals what it names.
- The list is refilled as the server's check returns, without drawing the whole panel again (it is skipped when a box is chosen or the panel is not the flow's).

## What does the bar's Problems button do now?
- `showProblems()`: unselects (or redraws), so the right panel shows the flow, and focuses the first "Go to the box", else the first heading. The old `#wsf-problems` strip over the canvas is gone (`.wsf-problems-button` keeps the bar's recipe, `workspace-flows-window-bar.md`).

## And the phone's "Add a box"?
- Both the canvas and the List view show one "Add a box" button (`ui.addBox`, `wsf-add-box`, `md:d-none`) in place of the outline's row of every kind; it opens the left panel as a full-height page (`foldPalette(true)`, `wsf-palette-over`). The List view's own add buttons stay but are hidden on a phone.

## Tests
- `test_workspace_flows_canvas_live_hint_and_cancel_dialog` pins the hint in `sideFlow()`.
- Harness (`workspace-flows-canvas-harness.md`): the problems scenario (Problems opens the flow panel, the facts, the rows and "Go to the box"); the phone scenario (the "Add a box" page, List's one button).
