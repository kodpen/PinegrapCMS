---
name: workspace-flows-name-window
description: The Flows editor with nothing chosen (FE3, #11165): no panel; the flow's facts, home channel and keys under the name's chevron; the problems as a list under their button
---

## What shows with nothing chosen? (drawSettings() in workspace_flow.js)
- No panel: the chosen box's card closes (`wsf-side-open` off, `workspace-flows-side-panel.md`). Built in FE3 (`plans/flows-editor-next-steps.md`, board A1); before it a right column held the flow's summary (FW4, #9228).

## What is in the name's window? (sideFlow(box))
- The chevron beside the flow's name (`.wsf-name-more`, "About this flow") opens a 320 px window under it (`#wsf-pop-flow`): Start, Boxes and arrows, Last flow run, Publishing (`wsf-side-facts`); the live/draft hint (`flowHelp()`, a reader "only its owner changes it"); the home channel field (`homeField()`); the Keyboard list folded.
- A reader has the chevron too. The window is drawn afresh each time it opens.

## How are the problems shown?
- "No problems" / "Problems: N" / "Warnings: N" (`.wsf-problems-button`) opens a 320 px list under it (`#wsf-pop-problems`), right-aligned; it can be pressed with none, the list then says "No problems". `showProblems()` focuses the first "Go to the box", else the heading; the chosen box stays chosen.
- `drawProblems()` fills it with `problemList()`: a heading, each row in its tone (rose problem, amber warning), "Go to the box" or "Go to the arrow", which closes the list and selects and reveals what it names. Refilled as the server's check returns while it is open.

## How do the two windows open and close? (barWindow(), openPop())
- One open at a time (`state.pop`, kept per flow through a bar redraw). Its button again, Esc in it or on its button (the focus back on the button), or a press outside it (`popOutside()` on the root) closes it. `placePop()` keeps it 16 px inside the screen.
- `aria-expanded` / `aria-controls` on the button, `role="dialog"` with a name on the window. Listed in Keyboard (`fl_key_windows`).

## And on a phone?
- The same windows under the bar. Both views show one "Add a box" button (`ui.addBox`, `md:d-none`) that opens the list as a full-height page (`foldPalette(true)`).

## And with no flow open at all?
- `.wsf-app.wsf-nothing` (clearScreen()) hides the panel; the main column takes the whole width (#9219).

## Tests
- `test_workspace_flows_canvas_side_card_and_windows`; `test_workspace_flows_canvas_live_hint_and_cancel_dialog` pins the hint in `sideFlow()`.
- Harness (`workspace-flows-canvas-harness.md`): the problems scenario (no card with nothing chosen, the name's window and Esc, the list under Problems, "Go to the box").

Related: docs/memory/workspace/workspace-flows-side-panel.md, docs/memory/workspace/workspace-flows-window-bar.md
