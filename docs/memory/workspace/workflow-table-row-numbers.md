---
name: workflow-table-row-numbers
description: The stable # number of workflow table rows (11.0.183, PR #8461): counter in ws_wtables.next_number, the row's number, kept through the bin, shown in #, found by by: number
---

## What is the number?
- Owner (2026-11-10, #8460): every table numbers its rows 1, 2, 3 for good; a delete shifts none and a number is never given again. Stored in columns (`workflow/decisions-made-schema-14.md`).
- `ws_wtables.next_number` is the last number the table gave; `ws_wtable_rows.number` the row's, with `KEY idx_number (table_id, number)`; `ws_wtable_row_bin.number` keeps it while the row is binned. All `INT UNSIGNED NOT NULL DEFAULT 0`.
- `position` stays: it is the row's place in the table's order and still shifts on delete and move.

## How is it given?
- `ws_wtable_number_take()`: `UPDATE ws_wtables SET next_number = LAST_INSERT_ID(next_number + 1)`, then `SELECT LAST_INSERT_ID()`. One statement, so two rows added at once never share a number; no GET_LOCK needed. A row that then fails to be written leaves a gap.
- `ws_wtable_row_create()` takes it for every new row (the import and starters go through it). A restore keeps the binned row's number; a row binned before 11.0.183 (number 0) takes the next one.
- Every INSERT adds the column through `ws_wtable_number_field()` / `_value()`, '' before `ws_wtable_numbers_ready()`, since files land before the upgrade runs.

## What did the migration do?
- Sub-step 11.0.183 `upgrade_2026_11_0_ws_wtable_numbers()`: the three columns and the index with the install helpers, then rows still at 0 numbered per table in (position, id) order after the highest given, and every counter set to GREATEST(itself, MAX(number)). Re-runnable.

## Where does it show?
- `ws_wtable_row_label()`: the number, else the place (before the upgrade). The screen's `#` column, the row form's "Row N", the board card's `#N` and the `ws_refs` title of a row without text.
- `ws_wtable_row_out()` and `ws_wtable_row_present()` carry `number`; the API's `WorkspaceTableRow` too.
- Row boxes: `by: number` (`ws_wtable_row_numbered()`), worded "row #N" (`workspace-flows-row-choice.md`). The canvas list: By its place in the table, By its # number, By its id, The last row.

## Tests
- `tests/workspace_wtable_numbers_test.php`; the ways in `tests/workspace_flow_row_choice_test.php`.
