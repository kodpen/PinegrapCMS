---
name: workflow-tables-conditions
description: Conditions over workflow table rows (wtable_where.php, PR #8400): comparisons per column type, how each type compares, all or any, sorting with empty cells last, sum/average/smallest/largest
---

## What is it for?
- SQL's WHERE, ORDER BY and its totals for workflow tables (PR #8400, `workflow-tables-relational.md`). Pure functions over the rows a read gives (`ws_wtable_rows()`, formulas worked out), since cells are JSON per row and filtering happens in PHP (`workflow-tables.md`).
- Used by the flow boxes "Read rows of a table" and "Read a row" by match (`workspace-flows-rows-get-box.md`), and by the table screen's query bar and the rows API (`workflow-tables-query.md`).

## What does a condition look like?
- `{column, op, value}`, at most `WS_WTABLE_WHERE_MAX` (10) in a list, joined by `all` or `any` (`ws_wtable_where_joins()`, an unknown word is all). No condition keeps every row.
- `ws_wtable_where_list()` reads a list or its JSON text: column a whole number (0 when it names none), op '' when unknown, value text (true/false as 1/0). Anything but a list of objects is null, so the editor names it.
- Comparisons (`ws_wtable_where_operators()`): eq, neq, lt, gt, lte, gte, contains, not_contains, starts, empty, not_empty. Per type (`ws_wtable_where_type_operators()`):
  - text: eq, neq, contains, starts, empty, not_empty;
  - number, money, formula, date: eq, neq, the four sizes, empty, not_empty;
  - status, person, link: eq, neq, empty, not_empty;
  - checkbox: eq, neq;
  - list: contains, not_contains, empty, not_empty (`workflow-tables-lists.md`).

## How is each type compared?
- Text without case, the Turkish capitals and dotted/dotless i alike (`ws_fold()`), trimmed. Equal to '' is an empty cell.
- Number, money and formula by size, to ten places. Money in minor units (1250 for 12.50), as cells keep it.
- A date as ISO text, at the coarser of the two: 2026-11-09 takes the whole day of a cell with a time.
- A status by its key (not its label), a checkbox as 1 or 0 (an empty value is 0, as a read row gives an unticked box), a person and a record link by their numbers.
- A size never holds for an empty cell; "is not" does. A value that does not fit the column (a word for a number) holds for no row, "is not" included.
- `ws_wtable_where_value_fault()` words a typed value that does not fit: not_number, not_minor_units, not_date, not_status, not_checkbox, not_whole, empty_value, operator.

## How are rows sorted?
- `ws_wtable_rows_sorted()`: by a column asc or desc, empty cells last either way, rows alike in table order (stable on any PHP). No column (0, or one the table lacks) is the table order, reversed when descending (the latest rows first).
- Text by its folded form without marks (`pg_transliterate_to_ascii()`: Şeker among the S), a status by the order of its choices, a checkbox unticked first.

## What are the totals?
- `ws_wtable_rows_totals()`: per number, money and formula column (`ws_wtable_total_type()`), sum, avg, min, max and the count of values. Empty cells (a formula error) are left out.
- No value: sum 0, the rest null. Money's average is a whole minor unit, `round()`ed; other sums and averages are kept to ten places.

## Tests
- `tests/workspace_wtable_where_test.php`.
