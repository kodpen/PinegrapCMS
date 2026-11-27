---
name: workflow-tables-grid
description: A Data table's cells changed in the grid (workspace_wtables.php?id=N, DM3): double click, Enter or F2, the row_cell action, refusals under the cell, changed_meanwhile, workspace_wtable_grid.js
---

## Which cells change in the grid?
- For a person who may write the table, a cell of a text, number, money, date (with or without time), checkbox or status column: `ws_wtable_grid_cell_editor()` marks its `td` with `data-ws-wt-edit` (the field) and `data-ws-wt-value` (the drawer's form value, `ws_wtable_screen_cell_to_form()`).
- A text holding a line break, and formula, lookup, rollup, person, record link, row link and list cells open the row drawer instead: one line of the grid would lose a break, and those fields need a picker or are worked out.
- Every cell of a writer's row carries `data-ws-wt-col` and `tabindex="-1"`; the row carries `data-ws-wt-row` and `data-ws-wt-revision`. A reader's grid has none of them (`ws_wtable_grid_row_cells()`).

## How is a cell opened and saved?
- A double click (the second click of a pair), Enter or F2 on the cell turns it into its field: a one-line text box, a native date or datetime-local box, a check box, or a select of the status choices with "(none)".
- Enter or leaving the field saves; Escape gives it up; Tab and Shift+Tab save and open the next or previous changeable cell of the grid. An unchanged value (spaces around it not counted) sends nothing.
- One cell a post: `wt_action=row_cell` with `wt_column`, `wt_row`, `revision`, `value`. `ws_wtable_grid_cell_change()` reads it as the row drawer reads its field (`ws_wtable_screen_cell_from_form()`) and the store writes it (`ws_wtable_row_update()`): rights, limits and key checks are the store's.
- The JSON answer: `ok`, `error`, `message`; when saved or changed meanwhile also `revision` and `cells`, the row's cells as drawn now (a formula or lookup may follow the cell), which replace the row's `td`s.

## What does a refusal show?
- The typed value stays in its field, marked invalid, the reason under it in the store's words (`ws_wtable_grid_cell_refusal_text()`: the cell's own code, else the refusal as a whole). The same value left again is not sent again; Escape puts the cell back.
- `changed_meanwhile`: the row is drawn as it is now and the store's sentence stands under the cell.
- Saves and refusals are read aloud from a polite live region under the grid.

## How do the pointer and the keyboard reach a row?
- A single click on a changeable cell waits 400 ms for a second click before it opens the row drawer; any other cell opens it at once, as does the row's number. A row with a refused field open does not navigate away.
- The arrow keys move between cells; ArrowDown or ArrowRight from the grid's box or a row's number enters the cells.

## Where is it?
- `assets/js/workspace_wtable_grid.js`, its own bundle `workspace_wtable_grid.min.js`, printed with its settings (`ws_wtable_grid_script_config()`, `#ws-wt-grid-config`) on a writer's table view only; the row click left the screen's inline script for it.
- The totals rows are not worked out again after a cell is saved: they follow on the next page load.
- Tests: `tests/workspace_wtable_grid_test.php` (the PHP parts) and `tests/workspace_wtable_grid_js_test.php` (sameValue, step in Node).
