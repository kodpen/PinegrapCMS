---
name: workflow-tables-query
description: The table screen's query bar, sort and totals line, and the rows API's where/join/sort/dir (wtable_query.php, PR #8453)
---

## What is it?
- SQL's WHERE and ORDER BY over a workflow table's rows on the table screen and in `GET /workspace/tables/{id}/rows`, with the step 2 engine (`workflow-tables-conditions.md`). `includes/workspace/wtable_query.php`; tests `tests/workspace_wtable_query_test.php`.
- A query is `ws_wtable_query($where, $join, $sort, $dir)`: conditions as `ws_wtable_where_list()` reads them (null when not a list), join all/any, a sort column id (0 = table order), asc/desc. `ws_wtable_query_active()`: any condition, a sort column or desc.
- `ws_wtable_query_refusal($columns, $query)` words the first fault: no list, more than 10, a condition without column or comparison, a column the table lacks, a comparison its type does not take, a value that does not fit (`ws_wtable_where_fault_text()`, shared with the "Read rows" box), a sort column the table lacks.
- `ws_wtable_query_rows()` filters then sorts (empty cells last); `ws_wtable_query_page($rows, $from, $limit)` pages by an offset. An active query reads every row (`ws_wtable_rows()`, at most 5000), as the flow box does.

## How does the screen take it?
- A GET form above the rows (`ws_wtable_screen_query_bar()`): `q[n][c|o|v]`, `join`, `sort`, `dir`, `totals`, so a filtered view is a link. A line per condition plus one empty line; a line without a column is dropped. The comparisons offered follow the column's type once it is chosen and applied; a status, checkbox and person value is a list, a date a native picker.
- Values are typed the screen's way and turned into the engine's by `ws_wtable_query_screen_value()`: money the site's way into minor units (`round()`), numbers by `ws_calc_number()`, a date ISO or in the site's day/month order, a status by label or key, a checkbox yes/no or 1/0, a person by name or id, a record `kind:id` or its number.
- A refused query shows its words under the bar and the rows unfiltered.
- A column heading sorts: ascending, then descending, then the table order. With conditions the heading reads "N of M rows" and an empty result says "No row meets the conditions".
- Paging: without a query, by position (`?after=`) as before; with one, by offset (`?from=`). Edit, Cancel and the pager keep the query and the page.

## What is the totals line?
- A `tfoot` of four lines (Sum, Average, Smallest, Largest) under each number, money and formula column (`ws_wtable_rows_totals()`), shown when the page has rows. Money in the column's decimals and currency, its average already a rounded minor unit.
- A switch: "Rows shown" (default, the page) or "All rows" / "All matching rows" (`totals=all`, every row the query keeps, which reads the whole table).

## How does the API take it?
- `workspace.table_rows.list` gains `where` (JSON text, max 4000), `join`, `sort`, `dir`; values in the engine's form (money in minor units, a status by key), as the flow box takes them. The MCP tool reads the same params.
- A refusal is 422 `validation_failed`, field `where`, or `sort` when only the sort is wrong.
- With a query, `total` is the count of rows kept and the cursor is `(offset, 0)`; a plain listing's cursor (id past 0) sent with a query answers 400 `invalid_cursor`. Without one the listing is unchanged.
