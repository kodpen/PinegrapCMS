---
name: workflow-tables-rollup
description: Rollup columns of workflow tables (type rollup, wtable_rollup.php, PR #8584): count, sum, average, smallest or largest over the rows of another table that link to the row, worked out on read
---

## What is a rollup?
- Column type `rollup`, "Rollup", GROUP BY over a row link (`workflow-tables-row-links.md`): Customers › sum of their Orders' Amount. Options (`ws_wtable_rollup_options_check()`): `table` the other table, `link` its row column pointing at this table, `op` count|sum|avg|min|max, `column` the column totalled (0 for count, which counts rows), `where` step 2 conditions on the other table's columns, `match` all|any.
- Two row columns of one table may point here (Customer, Seller): `link` keeps them apart.
- `ws_wtable_type_stores()` is false: never written (`computed`). No schema change.

## What does the store check?
- `ws_wtable_rollup_column_refusal()`, on create and on an options change: the person reads the other table (`rollup_table_unread`), `link` is its row column to this table (`rollup_link_invalid`), a non-count totals a number, money or formula column (`rollup_column_invalid`), the conditions fit its columns (`rollup_where_invalid`, `ws_wtable_query_refusal()`).
- A rollup does not total a lookup or another rollup, and its conditions do not read them (`ws_wtable_total_type()`, no operators). Reason: both are worked out per page after the other table's rows are read; totalling them would read a third table per row.

## How is the value worked out?
- `ws_wtable_rollup_fill($viewer, $columns, $rows)`: one read of each other table the reader reads per page: the linking rows from ws_refs (`ws_wtable_linking_rows()`, every row of the table before ws_refs takes the source), formulas worked out; `ws_wtable_rollup_values()` (pure) groups them by `link`, keeps those meeting `where` and totals them with `ws_wtable_rows_totals()`.
- Money stays in minor units, its average `round()`ed. A count of no row is 0; a sum, average, smallest or largest of no value is empty. A table the reader does not read gives an empty cell, a count too (owner 2026-11-09, `workflow/decisions-made-product-40.md`). A flow reads with its owner's rights.
- `ws_wtable_joined_fill()` runs lookups then rollups; every reader of 6a calls it (screen page, row form, board, Excel download, API and MCP rows, Read a row, Read rows). `ws_wtable_joined_targets()`: a count and a number total show as a number, a money total as its money column.

## Screen
- Columns card: "Rollup · Sum of Orders (Customer) › Amount", "· with conditions". New column form: "Rows that link here" (`table:link:column`, "› Rows" for a count), "Work out", "Only the rows that meet" (a JSON list, as the API takes it), "Conditions join".

## What does a rollup not do?
- As a lookup (`workflow-tables-lookup.md`), for the same reasons: no formula reads it (`ref`); no condition, query bar, totals line or sort (`ws_wtable_joined_type()`, "The rows cannot be sorted by a lookup or rollup column."); not on the row start box; import skips it; Change type refuses it both ways.
- Canvas (workspace_flow.js): `rowWriteColumns()` leaves out formula, lookup and rollup; `rowQueryColumns()` leaves lookup and rollup out of the conditions and the sort of Read rows and Read a row by match.

## Tests
- `tests/workspace_wtable_rollup_test.php`.
