---
name: workspace-flows-rows-get-box
description: The Read rows box of flows (rows_get, PR #8400) and Read a row by match: conditions as JSON, view reach, outputs count/rows/sum_ avg_ min_ max_<id>, the list For each walks, checks, canvas
---

## What is it?
- Step kind `rows_get` (`ws_step_kinds()`), functions in `includes/workspace/flow_rows_get.php`, engine in `wtable_where.php` (`workflow-tables-conditions.md`): SELECT … WHERE … ORDER BY … LIMIT for a flow (plan `plans/tables-relational.md`, step 2). Tests `tests/workspace_flow_rows_get_test.php`, `test_workspace_flows_canvas_rows_get_box`.
- Settings (`ws_step_rows_get_normalize()`): `table_id`, `match` (all, any), `where`, `sort` (column id, 0 the table order), `dir` (asc, desc), `limit` (1-50, the For each cap; none is 50).
- `where` is JSON text like a table box's `cells`: a list sent over the API is written as its JSON, a text is kept trimmed. It is the box's only text, filled with target `json`, so a run value lands as given.

## What does a condition's value take?
- Typed in the column's machine form (money in minor units, YYYY-MM-DD, a status key, 1/0), or one value alone such as `{{trigger.cell_3}}` (fallback allowed); a value beside other text is a problem.
- Editor (`ws_step_where_problems()`, pure): not a list, more than 10, a condition without column, comparison or value. Publish, run, try (`ws_step_where_refusal()`, store columns): a column gone, a comparison its type does not take, a typed value that does not fit, a sort column gone. A run value that does not fit holds for no row.

## Who may, and what does it give?
- `ws_step_rows_get_check()`: view reach with the owner's rights, paused while the table's owner is gone, as `row_get` (`workspace-flows-row-get-box.md`). Role 3 builds it; agent tier read.
- `ws_step_rows_get_do()`: every row (`ws_wtable_rows()`), those meeting the conditions, their totals, sorted, the first `limit` listed.
- Outputs: `count` (every row that met them, not only the list), `rows`, then per number, money and formula column `sum_<id>`, `avg_<id>`, `min_<id>`, `max_<id>` ("Sum of Price" …).
- Totals stay text (`ws_step_result_data()`) so decimals survive: up to six places, money in minor units, '' when no row had a value, a sum of nothing '0'.
- `rows`: JSON `[{row_id, position, cell_<id>}]`, cells as a read row gives them, whole rows while it fits `WS_FLOW_LOOP_ITEMS_BYTES` (32 KB); the sentence says when it holds fewer. A For each box takes it (`listValue()`); the canvas names its fields after the columns (`rowsItemLabels()`).

## Read a row by match
- `row_get` gains `by: match` (`ws_step_row_get_bys()`; `row_set` keeps three ways): no row text, `match` and `where` as above, the first row in table order that meets them (`ws_step_row_match()`). No condition is a problem; none found is done with found 0.
- `row_get` always keeps `match` and `where` (where '' unless by match), so the catalogue lists them.

## Who reads what?
- Non-editors: all values held, sentences "Rows of a table were read." / "No rows of the table were read.", conditions masked to column and comparison (`ws_step_where_masked()` in `ws_flow_row_box_masked()`).
- Templates clear `where` and `sort` with the table; saved templates map `table_id`.

## Canvas, API, AI
- workspace_flow.js: `KINDS.rows_get`, palette while the person reads a table, `rowsGetFields()` (table, conditions, sort, order, rows in the list), `whereFields()` shared with Read a row by match, money typed as an amount and kept in minor units (`whereKept()`), status and checkbox from a list. Comparisons and limits from `ws_flow_js_where()` (`FL.steps.where`). Icon in `FLOW_ICONS`.
- API `nodes`/`edges` texts of POST /workspace/flows; catalogue values `rows_get.match`, `rows_get.dir`, `row_get.by`, `row_get.match`; AI hints for both boxes.
