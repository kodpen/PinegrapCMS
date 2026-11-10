---
name: tables-relational-steps
description: Steps 1-8 of plans/tables-relational.md: row choice, conditions and Read rows, screen query and totals, keys, row links, joined columns, lists, type changes
---

## How are the steps taken?
- Claim a step with a draft `[plan:tables-relational] <step> — <topic>`. A step is about 1,000 product lines.
- Each step waits for the merge of the one before it. Steps 7 and 8 need only step 2.
- Tests in `tests/workspace_wtable_*_test.php` and `tests/workspace_flow_*_test.php`, red first; texts through `tr_add.php`; `php tools/build_js.php` after workspace_flow.js.

## Steps
- 1 (done, PR #8384): "Read a row" and "Change a row" find their row (`by`): its number in the table (`position`), its id, or the last row.

- 2 (done, PR #8400): a condition engine (`ws_wtable_rows_where()`, `workspace/workflow-tables-conditions.md`) and a "Read rows of a table" box (`rows_get`), the SQL `SELECT … WHERE … ORDER BY … LIMIT`: `count`, `sum_`/`avg_`/`min_`/`max_<id>` and `rows` for For each. "Read a row" gains `by: match`, the join step of a flow (`workspace/workspace-flows-rows-get-box.md`).

- 3 (done, PR #8453): table screen, a query bar (step 2 conditions, a sort) and a totals line; the API `GET …/rows` takes `where` and `sort` (`workspace/workflow-tables-query.md`).

- 4 (cut in two): keys, both kinds.
  - 4a (done, PR #8461): the stable number, `workspace/workflow-table-row-numbers.md`.
  - 4b (done, PR #8509): the column key, `workspace/workflow-table-key-column.md`.

- 5 (done, PR #8557): row links (a foreign key), `workspace/workflow-tables-row-links.md`: a column type `row` whose options name another table. A cell keeps the linked row's id; the screen shows that row's key, else its first text.
  - The writer reads the linked table; the row exists. A deleted row clears the links to it, and its delete confirmation counts them. Flows read a link as a row id. `ws_refs` (source `wtable_row`) finds the rows that link to a row.

- 6 (cut in two): joined columns, worked out on read like formulas, on the screen, board, API, MCP and every flow box reading a row; empty to a reader who does not read the other table.
  - 6a (done, PR #8574): a lookup shows one column of the linked row (Orders › Customer › Phone), `workspace/workflow-tables-lookup.md`.
  - 6b: a rollup works out the count, sum, average, smallest or largest value of a column over the rows of another table that link to this row (Customers › sum of their Orders' Amount), with step 2 conditions if any (`ws_wtable_rows_where()`, `ws_wtable_rows_totals()`, the rows from `ws_wtable_linking_rows()`).
    - Also carries: `rowWriteColumns()` in workspace_flow.js drops `lookup` and `rollup` beside `formula` from the Add/Change a row form, and the canvas offers neither as a sort or condition column. Whether a rollup may sum a lookup is 6b's choice; formulas, conditions and sorts do not read lookups (6a).

- 7 (done, PR #8486): a list column (an array): several texts or several numbers in one cell, typed one per line. A flow gives it as a JSON list, so For each can walk it. Conditions read "contains". Formulas count it. The privacy reader and the import learn it (`workspace/workflow-tables-lists.md`).

- 8 (done, PR #8512): changing a column's type, like ALTER … MODIFY: text, number, money, date, list and checkbox can turn into one another. A dry run lists each cell that does not convert, and the change is refused while any is left.
