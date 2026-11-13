---
name: workspace-flows-palette
description: The flow window's left panel "Add a box" (flow-window FW2, #9178): numbered sections, the start as one row with Change, folding groups of 32 px rows, the rail when folded or narrow
---

## Why sections?
- The palette listed its groups as start, values, logic, people, message, system: nothing said which box goes first, and the owner could not tell (2026-11-03). The flow window drawing (`workflow/decisions-made-workspace-20.md`) then gave the left panel one job, adding boxes, in rows that fold.

## How is it laid out? (drawPalette() in workspace_flow.js)
- Head "Add a box" with the fold button; "Start from a template" while the flow is empty; the search (`pg-input pg-input-sm`); the sections in a scroll of their own; the foot line "Click or drag onto the canvas".
- `PALETTE_STAGES`: 1 Start (`start`), 2 Steps (`message`, `people`, `logic`, `records`, `system`), 3 End (`end`), Values (`values`, no number). A section's hint (`fl_stage_*_hint`) is its heading's tooltip.
- Records and tables (`fl_group_records`, `PALETTE_RECORDS`): `change`, `record_get`, `row_add`, `row_get`, `row_set`, `rows_get`, out of System; `paletteGroupOf()` decides, the canvas keeps their system colour (`KINDS`).
- A box is a 32 px row: its tile (`pg-icon-tile` in the group's `--wsf-kind`) and its name on one line, the full name as its tooltip, the foot line as its description. Click or Enter adds it, drag drops it.

## Which groups fold?
- The steps' groups and Values are buttons with `aria-expanded`; a folded one shows its count. At first Message and People are open, the rest folded (`PALETTE_FOLDED`); the person's choice stays while the page is open (`state.paletteFolded`). A search unfolds every group it found a box in (`paletteGroupOpen()`).

## How is the start shown?
- Without a start box: the start kinds as rows. With one: a single row naming it (`aria-label` "This flow: …") with "Change"; no start kind is listed, refused or disabled (`startRows()`). The outline's add buttons still disable a second start (`startTaken()`, `fl_one_start`).
- Change (`aria-expanded`) unfolds the other start kinds; choosing one makes the start box that kind in place (`swapStart()`): id, place, title and arrows kept, settings those of the new kind (`newConfig()`), one undo step. A start whose settings were filled in (`startFilled()`) asks first (`fl_start_change_ask`).

## How does it fold to a rail?
- 1280 px and up: the fold button folds the panel to a 48 px rail ("+" and the numbers 1, 2, 3); the choice is kept in this browser (`localStorage` `pg_wsf_palette`).
- 768-1279 px: the rail at rest; "+" or a number opens the 264 px panel over the canvas's left edge (`wsf-palette-over`); Esc (focus back on "+"), a box added or a click on the canvas folds it (`foldPalette()`).
- Under 768 px the panel is hidden; one "Add a box" button opens it as a full-height page (`workspace-flows-empty-panel.md`).

## Tests
- `test_workspace_flows_canvas_palette_groups` (Node): group and section order, Records and tables, folding, search, Turkish fold, hints, start rows, `startFilled()`, `startTaken()`.
- Harness (`workspace-flows-canvas-harness.md`): the palette scenario (one start row, folds, 32 px rows), Change swapping the start with one undo, the narrow rail.
