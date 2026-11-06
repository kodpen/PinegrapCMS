---
name: workspace-flows-row-add-to-cell
description: Adding to a cell in the change-row flow box (flows-next B5, PR #7408): "+3" in a number or money cell adds to the row read at write time, on its revision
---

## What does it do?
- In a `row_set` box a number or money cell whose value, as written in the settings, starts with "+" adds: `{"8": "+3"}`, `{"4": "+1250"}` (money in minor units), `{"8": "+{{node.answer.answer_number}}"}`. A negative amount ("+-2") takes away. An empty cell counts as 0. A text cell keeps "+3" as text.
- Why: two runs that read one row and each wrote their own sum lost one addition (seen on dev with two shifts counting stock).

## How is it decided and checked?
- `ws_step_row_adds($columns, $cells)` reads the cells as written, before the run fills them: a value that only fills in to "+3" sets (and the store refuses it as no number, as before).
- `ws_step_row_check()` puts those column ids in the action's `adds`; `ws_step_fill_action()` does not touch it.
- `ws_step_row_cells_refusal($columns, $cells, $kind)`: a literal amount is checked by `ws_step_row_addend()` (the column's `ws_wtable_cell_check()` on what follows "+"); in `row_add` an adding cell is refused ("a new row has nothing to add to").

## How is it written?
- `ws_step_row_do()` reads the row on each try, `ws_step_row_cells_added()` lays the sums over that row's cells, and `ws_wtable_row_update()` writes on that row's revision. A `changed_meanwhile` reads again, up to `WS_STEP_ROW_TRIES` (5, was 2 for every change-row box): no addition is lost; a row changed on every try fails the box with its sentence.
- The store rounds a number to the column's decimals and keeps the range.

## Canvas and drafts?
- workspace_flow.js `cellKept()`: a number or money field typed "+12,50" keeps "+1250" (money) or "+12.5" (number); `cellShown()` shows it back with the site's mark and the plus. "+{{…}}" stays as typed.
- `ws_flow_ai_catalogue_hints()` gives `row_set.cells` a hint naming "+3".
- Tests `tests/workspace_flow_row_add_to_cell_test.php`.
