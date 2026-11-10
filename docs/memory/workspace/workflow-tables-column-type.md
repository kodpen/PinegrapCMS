---
name: workflow-tables-column-type
description: Changing a workflow table column's type (wtable_retype.php, PR #8512): which types, how each cell converts, decimals and times never lost, the refusal listing cells, binned rows, screen, API
---

## What changes?
- ALTER … MODIFY for workflow tables (PR #8512, `workflow-tables-relational.md`): text, number, money, date, list and checkbox turn into one another, and a list of texts into a list of numbers and back (`ws_wtable_retype_types()`, `ws_wtable_retype_is_change()`). Person, status, record link, row link, lookup and formula do not (`type_not_changeable`); the same type is `type_same`.
- No schema change: the column's `type` and `options` are rewritten and each converted row's cells.

## How does a cell convert?
- `ws_wtable_retype_candidate()` then the store's own `ws_wtable_cell_check()` of the new type (`ws_wtable_retype_cell()`). An empty cell stays empty; an unticked box is empty.
- A text is read the site's way, as the file import reads it (`ws_wtable_import_number()`, `_money()`, `_date()`, `_checkbox()`); into a list, one item a line.
- A number or amount into text the site's way without grouping (12,50), so it reads back the same; into a tick only 0 and 1 (money: one unit); into a list, one item.
- A ticked box: "1", 1, one unit, [1]. A date: its text, or a one-item list of texts. A list: its items one a line into text; a list of one item converts as that item; several into one value is `list_many`.
- Never lost silently: a number the new column would round, or an amount with more decimals than money keeps, is `decimals_lost`; a text date with a time other than 00:00 into a date without time is `time_lost`. No form at all (a date into a number) is `no_conversion`.

## When is it refused?
- `ws_wtable_column_retype_check()` converts every cell of the column in the rows and the bin (`ws_wtable_retype_read()`, `ws_wtable_retype_rows()`): while any does not convert, `cells_not_converted` (422) with every such cell (row # number, binned or not, value cut to 80 characters, code). That check is the dry run.
- Binned rows convert too, since a restore writes their cells back as kept.

## How is it written?
- `ws_wtable_column_retype()`: one transaction; rows and binned rows read once `FOR UPDATE`, checked, each changed row written with its revision moved on (so an open edit form answers `changed_meanwhile`), the column updated, `ws_wtable_columns_changed()`. Refused, `ROLLBACK`. No `workspace.wtable.row.*` event.
- Flow boxes that typed a value or a condition for the column are checked again at publish and run (a comparison the new type does not take is named there).

## Where is it offered?
- Table screen: the column's form, "Change type" (`column_retype`): New type (Text, Number, Money, Date, Date with the time of day, List of texts, List of numbers, Checkbox, its own left out; `ws_wtable_screen_retype_choices()`), Decimals (empty: money 2, a number the money's decimals), a confirmation. A refusal opens the column and lists up to 100 cells (`ws_wtable_screen_retype_fault_text()`).
- API `POST /workspace/tables/{id}/columns/{column_id}/type` (`workspace.table_columns.retype`, MCP tool, `dry_run`): type, options; refusals 422 `validation_failed`, field `type`, the message listing up to 50 cells.

## And the key column?
- The key (step 4b, `options['key']`) is text or number, filled and unique: its type does not change (`key_column`, "make it an ordinary column first"), and options that would make a key are refused (`key_on_retype`), so the column form's key check is never skipped.

## Tests
- `tests/workspace_wtable_retype_test.php`; routes in `tests/workspace_api_wtables_test.php`.
