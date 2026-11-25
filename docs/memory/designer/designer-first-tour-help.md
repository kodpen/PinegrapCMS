---
name: designer-first-tour-help
description: The designer's Help window ("How do I…", the keyboard shortcuts, "Tips"), a task's three pointers, the first-use tip line any feature asks for, and an empty page's starts (FT3)
module: output
---

## Where is it?
- The part `tour` (`assets/js/style_designer_tour.js`: `help()`, `start(taskId)`, `tip()`, `empty()`), its model `assets/js/pg_designer_tour.js` (`tasks()`, `starts()`), the look `.sd-help-*`, `.sd-tip-*`, `.sd-empty-*` in `assets/css/style_designer.css`. Drawing: `designer-first-tour-help-drawing.md` (T4 to T7). Built in #11689.
- The core keeps `openShortcutsModal(part)`, `_sdHelpShow()`, `_sdTipDue()`, `_sdTipAsk()`, `_sdEmptySync()`, `_sdEmptyRun()` (section `// ── The first tour` in `style_designer.src.js`).

## How does the Help window open?
- One dialog, title "Help", 436 px: a switch "How do I…" / "Keyboard shortcuts" under its head, "Tips" at its foot. The status bar's "?" button opens it on "How do I…"; the ? key, F1 and the command palette's "Keyboard Shortcuts" on the shortcuts. Left and right change the part, up and down move over the rows.
- Every opening calls the part's `help()`, which draws the rows for the access and the switch. Without the part handed the switch is hidden and the window is the shortcuts alone.

## What does a task do?
- A row closes the window and runs `start('<task>')`: the tour's ring and bubble on the task's pointers, "How do I · 1 of 3", "Close" in place of "Skip". The count is the task's three pointers as drawn, not those on screen, since a later control often comes only with the press before it.
- A click or a drag start on the ringed control (in the canvas frame too) moves on after 150 ms; the next control is looked for up to eight times, then skipped. A task never marks the tour as seen.
- The tasks, their selectors and order are in the model (`TASKS`); a content editor gets "text" and "publish". The test fails a selector whose id or class the editor no longer draws.

## How does a feature ask for a first-use tip?
- `_sdTipAsk(name, text, head, box)`: the core reads `pg.designer.<user id>.tour` itself and loads nothing when the tip was seen or "Tips" is off. The part puts one line under `head`; "Got it", or a click inside `box` outside the line and the head, writes it seen. Asked again after the feature redrew its head, the line moves under the new head.
- Never two at once, never while the tour or a pointer runs (a starting tour takes the line away). "Tips" off hides the line shown; tips seen stay seen.
- Asked today by History (`history`) and by Overview when it is unfolded (`overview`). The older lines (select similar, bands, states, side by side, overrides, variables, Sketch, Preview as) keep their own drawing and their own browser key, and "Tips" off hides them too (#11723, `designer-tips-off.md`).

## When does an empty page offer its starts?
- `_sdEmptySync()` at the end of `render()`: a root with no children, full access, not a shared component alone, Prototype view closed. Drawn in `#sd-canvas .sd-iframe-area`, never in the page; it goes with the first element.
- "Add a section" opens the palette on "Blocks" (shown when the Blocks list exists); "Sketch it" runs `_sdSketchStart()` (when the Prototype view can load); "From a document" calls `_sdDocumentReady()` / `_sdDocumentOpen()` of the part `document` when those exist (plan designer-document-to-page, FD3).
- Only the cards take the pointer; a drag fades them and lets the drop reach the canvas. Under 620 px of canvas they stand in a column, 250 px wide.

## What was not checked?
- No browser run: the Help window, the pointers, the tip line and the starts were not clicked through; the model's tasks and starts are tested in Node (`tests/designer_tour_model_test.php`).
