---
name: workflow-tables-lookup
description: Lookup columns of workflow tables (type lookup, wtable_lookup.php, PR #8574): a column of the linked row, worked out on read for whoever reads, never stored
---

## What is a lookup?
- Column type `lookup`, "Lookup", the join of a row link (`workflow-tables-row-links.md`): `options.link` is a `row` column of the same table, `options.column` a column of the table it links to. Orders › Customer › Phone.
- `ws_wtable_type_stores()` is false, as for a formula: the store, the API, the import and the flows never write its cell (`computed`; steps.php "is worked out from the others"). No schema change.

## What does the store check?
- `ws_wtable_lookup_column_refusal()`, on create and on an options change: the link is a row link column of the table (`lookup_link_invalid`), the person reads the linked table (`lookup_table_unread`), the column is one of its columns a lookup may show (`lookup_column_invalid`). The options are not fixed: changing them changes no cell.
- A lookup shows any type but `row` and `lookup` (`ws_wtable_lookup_shows()`). Reason: one step reads one linked row; a chain would read a table per step.

## How is the value worked out?
- `ws_wtable_lookup_fill($viewer, $columns, $rows)`: one read of each linked table per page (`ws_wtable_link_rows()` of the ids the rows link to), checked with `ws_wtable_link_readable()`; `ws_wtable_lookup_values()` (pure) lays the values in.
- The value is the linked cell as its column keeps it: money in minor units, a date text, a person id, a status key, a formula worked out. The screen writes it by that column's type (`ws_wtable_lookup_targets()` rides in the people list under `WS_WTABLE_LOOKUPS_KEY`).
- Empty: no link, the linked row gone, the link or the linked column deleted, or a reader who does not read the linked table (owner 2026-11-09, `workflow/decisions-made-product-40.md`). A flow reads with its owner's rights.

## Where is it filled?
- Screen: the page shown, the row form (read-only field), the board, the Excel download. Columns card: "Lookup · Customer › Phone". New column form: "Column of the linked row", one select `options[pick]` = `link:column`.
- Every reader calls `ws_wtable_joined_fill()` (lookups, then rollups) and `ws_wtable_joined_targets()`.
- API and MCP: every row read (one, by key, a page, a query) through `ws_api_wtable_rows_present()`. Not the bin rows and not the delete answer.
- Flows: Read a row and Read rows (its listed rows) give `cell_<id>`; the AI hints for `row_get` and `rows_get` say it is read, never written.

## What does a lookup not do, and why?
- A formula does not read it (`ref`): formulas are worked out from the row's own cells, before the linked rows are read.
- Conditions, the query bar, the totals line and sorting do not take it (no operators; sort refused "The rows cannot be sorted by a lookup column."): they run over every row before the page is read, and a lookup is read per page.
- The row start box gives no lookup (`ws_flow_row_outputs($columns, false)`): its event body is built once for every listener, for no reader. A Read a row box after it gives the value.
- Import skips it; Change type refuses it both ways (`type_not_changeable`, `type_unknown`).
- The canvas leaves it out of Add/Change a row and of conditions and sorts (`rowWriteColumns()`, `rowQueryColumns()`, `workflow-tables-rollup.md`).

## Tests
- `tests/workspace_wtable_lookup_test.php`.
