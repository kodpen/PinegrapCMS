---
name: tables-relational-steps
description: Steps 1-8 of plans/tables-relational.md: row choice, conditions and Read rows, screen query and totals, keys, row links, joined columns, lists, type changes
---

## How are the steps taken?
- Claim a step with a draft `[plan:tables-relational] <step> — <topic>`. A step is about 1,000 product lines.
- Each step waits for the merge of the one before it. Steps 7 and 8 need only step 2.
- Tests go in `tests/workspace_wtable_*_test.php` and `tests/workspace_flow_*_test.php`, red first. Texts go in through `tr_add.php`. After editing workspace_flow.js, run `php tools/build_js.php`.

## Steps
- 1 (PR #8381): "Read a row" and "Change a row" find their row (`by`): by its number in the table (`position`, the default of a new box, "1"), by its id (`id`, also for a box saved before), or as the last row (`last`).
  - One check in the editor, at publish and at run (a whole number from 1, or one value alone), a message per fault. "Add value" with nothing before it names the start. A template keeps a row number, clears an id.

- 2: a condition engine and a "Read rows of a table" box (`rows_get`), the SQL `SELECT … WHERE … ORDER BY … LIMIT`.
  - `ws_wtable_rows_where()` (pure): conditions, all or any; each a column, an operator (equals, differs, less, more, at most, at least, contains, starts with, empty, not empty) and a value, typed or a run value, compared by the column's type.
  - The box takes conditions, a sort and a limit (1-50, the For each cap). It gives `count`; `sum_<id>`, `avg_<id>`, `min_<id>`, `max_<id>` per number and money column (and number formulas) over the matching rows; `rows`, a list For each walks (`row_id`, `position`, `cell_<id>`).
  - "Read a row" gains the way `match`: the first row that meets the conditions. That is the lookup, a join done in a flow.

- 3: table screen, a query bar: the step 2 conditions and a sort over the rows. The paging follows the filtered rows. A totals line under the table shows the sum, average, smallest and largest value of each number and money column, for the rows shown or for every row. The tables API `GET …/rows` takes the same `where` and `sort`.

- 4: a key column, like a primary key: one column per table (text or number) marked as the key; a value must be filled in and must differ from every other row's.
  - The store refuses a duplicate (`duplicate_key`) under GET_LOCK per table and a PHP scan. Rows are found by key (`by: key`, API `?key=`); import matches by key. A column holding repeats: open question.

- 5: row links (a foreign key): a column type `row` whose options name another table. A cell keeps the linked row's id; the screen shows that row's key, else its first text.
  - The writer reads the linked table; the row exists. A deleted linked row: open question. Flows read a link as a row id. `ws_refs` (source `wtable_row`) finds the rows that link to a row.

- 6: joined columns, worked out on read like formulas:
  - a lookup shows one column of the linked row (Orders › Customer › Phone);
  - a rollup works out the count, sum, average, smallest or largest value of a column over the rows of another table that link to this row (Customers › sum of their Orders' Amount), with step 2 conditions if any.
  - On the screen, board, API, MCP and every flow box that reads a row; empty for a reader who does not read the other table.

- 7: a list column (an array): several texts or several numbers in one cell, typed one per line. A flow gives it as a JSON list, so For each can walk it. Conditions read "contains". Formulas count it. The privacy reader and the import learn it.

- 8: changing a column's type, like ALTER … MODIFY: text, number, money, date, list and checkbox can turn into one another. A dry run first lists each cell that does not convert. What happens to those cells is the open question.
