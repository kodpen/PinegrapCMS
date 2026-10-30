---
name: workspace-flows-table-box-form
description: The canvas form of the add-row and change-row flow boxes (F6b-2, #3359): tables offered, a field per column, cells kept as JSON one key at a time, the JSON fallback, threads
---

## What does the canvas offer?
- `KINDS` row_add, row_set (group system, icons in `FLOW_ICONS`); offered while `ws_flow_row_tables()` lists a table with `edit` (the viewer changes its rows; columns carry `options`).
- `rowWriteFields()`: table (only `edit` tables; another table clears the cells), row for row_set (a value field, in `VALUE_FIELDS`), a field per column but formulas: a choice for checkbox and status, kind + number for a link, else a value field. Money typed as an amount, kept in minor units; a typed number or person id kept as a number, a value as text. A typed amount or number is read by the site's separators (`siteNumber()`, #3541: Turkish `1.250` is 1250, `1.250,5` 1250.5; before, the first comma was made a dot and `1.250` was 1.25), shown back with the site's mark (`cellShown()`). Empty leaves the column out.

## How are the cells kept?
- `setCell()` changes one key of the parsed JSON, so keys the form does not show stay. Cells that are no JSON object, or a table not in the list, show one "Cells as JSON" field: a box built over the API saves unchanged until edited.

## Values and threads?
- The cells' tokens draw threads (`fieldTexts()`) but no thread drops into them (`openFields()`). Tests `test_workspace_flows_canvas_table_write_boxes`.

## Do saved templates ask the table?
- Yes (PR #3597): `workspace-flows-template-table.md`.
