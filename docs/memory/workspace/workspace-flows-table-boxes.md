---
name: workspace-flows-table-boxes
description: The table boxes of flows (F6b-1, #3189): row_add adds a workflow table row, row_set changes cells of one; cells as JSON text, owner's edit reach, store writers, pause, what non-editors read
---

## What are they?
- Step kinds `row_add` ("Add a row to a table") and `row_set` ("Change a row of a table") in `ws_step_kinds()` (steps.php), types of their own. Outcomes done, failed; output `row_id`; retries as every step box. Tests `tests/workspace_flow_row_write_test.php`.
- Settings: `table_id`; `row_set` also `row`, a row id or one value alone (`{{trigger.row_id}}`, `{{node.row_add_1.row_id}}`, `ws_step_row_names_row()`); `cells`, a JSON object as text, column id => value (`WS_STEP_ROW_CELLS_MAX`), an object given written as its JSON as `mcp.arguments`. Values as the table API takes them: money in minor units, a status its key, a link `{"kind": "contact", "id": 12}`.

## Why JSON text?
- `row` and `cells` are the kind's `texts`, read as strings by `ws_flow_node_texts()`: the reference check and box try cover them. Values fill with target `json` (inside JSON strings, no word joiner: a cell is plain data the screens escape); `ws_step_row_cells()` also reads the text with each value emptied, so one outside quotes leaves no object. No `ws_scheduled_fill()`: `{{date}}` stays as typed.

## Who may, and when is it checked?
- `ws_step_row_check()` at publish, each run and box try, with the owner's rights: `ws_wtable_flow_check(…, 'edit')` (no role reaches a table, so a role-3 owner writes only in reach), each key a column that keeps cells (no formula), a value without a run value one its column takes (`ws_wtable_cell_check()`), a literal row id in the table. Filled values are checked by the store.
- The owner's rights alone: the starter rule stays change-only (`workflow/decisions-made-workspace.md`).

## How does it run?
- `ws_step_run()` writes `began:<kind>` first (a stopped box fails, never writes twice), checks, fills, then `ws_step_row_do()` in the run's event depth: `ws_wtable_row_create($owner, …)` or `ws_wtable_row_update()` on the row's revision now, read again once on `changed_meanwhile`. The store keeps the row limit, the cell checks, the row event (a row start box's flow starts one level down) and the record tags.
- Refusals in words: `ws_step_row_cells_refusal()`, `ws_step_row_write_refusal()` (each refused column by name, `ws_step_row_cell_reason()`).

## What if the table's owner has left the team?
- Gate `paused` (`workflow-table-shares.md`): publish refused; at run `ws_step_row_wait()`: waiting, effect cleared, `wait_key` `wtable:<table id>`, next look in `WS_FLOW_RECHECK`. `ws_flow_step_recheck()` hands it to `ws_step_row_recheck()`: still away, it waits on; else ready again, the try not counted. No `workspace.flow.run.waiting` event (its `for` lists no table).

## Who reads what?
- Run readers without edit: no `row_id` (`ws_step_outputs_held()`); "A row was added to a table." or "A row of a table was changed." for the sentence naming the table (`ws_step_result_shown()`). The draft card marks both outbound.

## What is left (F6b-2)?
- Canvas: `KINDS`, form (table, row, a field per column, value chips), `fl_*` texts. `ws_flow_graph_present()` masks `row` and the cell values for non-editors as the change box (flows.php was held by #3172). Saved templates ask no table yet.
