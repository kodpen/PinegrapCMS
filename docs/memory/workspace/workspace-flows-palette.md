---
name: workspace-flows-palette
description: The list of boxes to add (FW2 #9178, FE2): a card beside the add rail, numbered sections, the start row with Change, folding groups of 32 px rows
---

## Why sections?
- The palette listed its groups as start, values, logic, people, message, system: nothing said which box goes first, and the owner could not tell (2026-11-03). The flow window drawing (`workflow/decisions-made-workspace-20.md`) gave adding boxes a place of its own, in rows that fold; since FE2 that place is a card beside the add rail, and "+" on an arrow opens the shorter add list (`workspace-flows-add-here.md`).

## How is it laid out? (drawPalette() in workspace_flow.js)
- The card's head "Add a box" with Close (`fl_palette_close`); "Start from a template" while the flow is empty; the search (`pg-input pg-input-sm`); the sections in a scroll of their own; the foot line "Click or drag onto the canvas".
- `PALETTE_STAGES`: 1 Start (`start`), 2 Steps (`message`, `people`, `logic`, `records`, `system`), 3 End (`end`), Values (`values`, no number). A section's hint (`fl_stage_*_hint`) is its heading's tooltip.
- Records and tables (`fl_group_records`, `PALETTE_RECORDS`): `change`, `record_get`, `row_add`, `row_get`, `row_set`, `rows_get`, out of System; `paletteGroupOf()` decides, the canvas keeps their system colour (`KINDS`).
- A box is a 32 px row: its tile (`pg-icon-tile` in the group's `--wsf-kind`) and its name on one line, the full name as its tooltip, the foot line as its description. Click or Enter adds it, drag drops it.

## Which groups fold?
- The steps' groups and Values are buttons with `aria-expanded`; a folded one shows its count. At first Message and People are open, the rest folded (`PALETTE_FOLDED`); the person's choice stays while the page is open (`state.paletteFolded`). A search unfolds every group it found a box in (`paletteGroupOpen()`).

## How is the start shown?
- Without a start box: the start kinds as rows. With one: a single row naming it (`aria-label` "This flow: …") with "Change"; no start kind is listed, refused or disabled (`startRows()`). The outline's add buttons still disable a second start (`startTaken()`, `fl_one_start`).
- Change (`aria-expanded`) unfolds the other start kinds; choosing one makes the start box that kind in place (`swapStart()`): id, place, title and arrows kept, settings those of the new kind (`newConfig()`), one undo step. A start whose settings were filled in (`startFilled()`) asks first (`fl_start_change_ask`).

## Where is it, and how does it open?
- A 264 px card beside the add rail over the canvas's top left, opened by "+" or a group's icon, closed by Esc, Close, a box added or a click on the canvas; nothing kept in the browser (FE2, `workspace-flows-canvas-tools.md`).
- Under 768 px one "Add a box" button opens the same card as a full-height page (`workspace-flows-name-window.md`).

## Tests
- `test_workspace_flows_canvas_palette_groups` (Node): group and section order, Records and tables, folding, search, Turkish fold, hints, start rows, `startFilled()`, `startTaken()`.
- Harness (`workspace-flows-canvas-harness.md`): the palette scenario (one start row, folds, 32 px rows), Change swapping the start with one undo, the add rail.
