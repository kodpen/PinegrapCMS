---
name: workflow-table-rows
description: Workflow table rows (wtable_rows.php, T1b): add, change, delete, move; row_count and the 5000 limit in one UPDATE; 409 changed_meanwhile; person/link cells open; formulas on read
---

## Where are rows read and written?
- `includes/workspace/wtable_rows.php`, loaded after `wtables.php`. Each write has a `*_check()` the API's dry run will call: `ws_wtable_row_create`, `_update`, `_delete`, `_move`; access `write` (edit+) or 404. Reads: `ws_wtable_row()` (stored), `ws_wtable_rows()` (in order, as read). Tests `tests/workspace_wtable_rows_test.php`.
- Input: `cells` (column id => value), `revision`, `position` (move, 1-based). Cell refusals answer `cells_invalid` (422) with `errors` by column id.

## How are row_count and the limit kept?
- Add: `UPDATE ws_wtables SET row_count = row_count + 1 … AND row_count < 5000` first; no affected row is `too_many_rows` (409), so two adds at once cannot pass the limit. A failed INSERT gives the count back.
- Delete: only when the DELETE removed the row, the rows after it move up one and `row_count` drops (never below 0). New rows go last (`MAX(position) + 1`); positions are 1..n.

## How does a change made meanwhile get refused?
- A change must name `revision` (`revision_missing` 422 otherwise); a different one is `changed_meanwhile` 409 with the row's `revision` now. The write is one `UPDATE … WHERE revision = ?` bumping it, so a race is refused too.
- Delete takes `revision` optionally, checked the same way. A move keeps the revision: the cells did not change.
- Cells are checked against the columns as they are at write time (a deleted column's id is `unknown_column`); the table's own `revision` is not asked.

## Are person and record-link cells written?
- Yes, since T1b2: the privacy export and erasure read them (`pg_privacy_wtable_rows()`, `core/personal-data-erasure.md`), so `ws_wtable_row_closed_types()` is empty. A later type holding personal data the privacy paths cannot read goes into that list; its non-empty value is then `not_writable_yet`, emptying allowed.

## How are formula columns worked out?
- On every read, never stored (`ws_wtable_formula_values()`). The expression names other columns by name (`Price * Quantity`), bound once per read by `ws_calc_bind()` with the column id as slot, then `ws_calc_evaluate()` per row.
- Operands: number as stored, money in whole units (minor / 10^decimals), checkbox 1/0, empty 0; text, date, status, person, link `value`; an unknown name or a sheet reference (`A1`) `ref`; a loop `cycle`. Results rounded to 10 decimals.
- A read row carries the value in `cells` (null on error) and the code in `formula_errors`.
