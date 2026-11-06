---
name: workspace-flows-row-get-box
description: The Read a row box of flows (row_get): view reach with the owner's rights, pause, outputs row_id/found/cell_<id>, a missing row done with found 0, non-editors, canvas
---

## What is it?
- Step kind `row_get` ("Read a row of a table") in `ws_step_kinds()` (steps.php), type of its own, outcomes done and failed, retries as every step box. Not in `ws_step_row_kinds()` (those write). Functions in `includes/workspace/flow_row_get.php`. Tests `tests/workspace_flow_row_read_test.php`, canvas `test_workspace_flows_canvas_row_get_box`.
- Settings (`ws_step_config_normalize('row_get')`): `table_id` and `row`, a literal row id or one value alone (`ws_step_row_names_row()`, as `row_set`). `row` is its only text, filled with target `json` like the table boxes.
- Built so a counter line (read a row, raise it, write it back) is one flow (`workflow/decisions-made-product-30.md`).

## Who may, and when is it checked?
- `ws_step_row_get_check()` at publish, each run and box try, with the owner's rights: `ws_wtable_flow_check(…, 'view')`; a literal row id is not looked up (a missing row is no refusal). Role 3 builds it (`ws_flow_member_kinds()`) on tables they read.
- Gate `paused` (table owner left the team): publish refused; at run the generic `ws_step_run()` path calls `ws_step_row_wait()` and `ws_step_row_recheck()` wakes it, as the write boxes (`workspace-flows-table-boxes.md`).

## What does it give?
- `ws_step_outputs('row_get', $config)`: `row_id`, `found` (1 or 0), then `cell_<column id>` per column from `ws_flow_row_outputs()` (columns from the store unless `$config['columns']` gives them; number/money typed number).
- `ws_step_row_get_do()` reads `ws_wtable_row()` → `ws_wtable_row_out()` (formulas worked out) → `ws_wtable_row_present()`; `ws_step_row_get_data()` (pure) turns the cells into text by `ws_flow_row_cell_text()`.
- A row that is not there, or a filled row text that is no id: done, `found` 0, `row_id` null, every cell ''. Only a gone table fails the box.
- `ws_step_result_data()` keeps a `row_get` cell as text (a number cell may have decimals; the number branch would cut it to an int).

## Who reads what?
- `ws_step_outputs_held()` holds all its values from run readers without edit; `ws_step_result_shown()` gives them "A row of a table was read." / "No row of the table was read."
- `ws_flow_graph_present()` masks it with `ws_flow_row_box_masked()`: `row` ''; a box without `cells` gets none added.

## Canvas, API, templates
- workspace_flow.js: `KINDS.row_get` (group system), palette entry after `row_set`, offered while the person reads a table (`FL.steps.tables`); `rowGetFields()` (table, row); `rowWriteMeta()` names table and row; `boxValues()` adds the cells through `rowCellValues()`. Icon in workspace.js `FLOW_ICONS`. Texts `fl_kind_row_get`, `fl_rows_read_help`, `fl_rows_get_row_help`.
- API: named in the `nodes` description of POST /workspace/flows; the catalogue (GET /workspace/flow-kinds) and Pinegrap AI's draft catalogue read it from the kinds, with a kind hint in `ws_flow_ai_catalogue_hints()`.
- Templates: a table setting on it asks view (`ws_flow_template_table_need()`); a literal row is cleared with the table (`ws_flow_template_table_cleared()`); saved templates map its `table_id` (`flow_templates_saved.php`).
