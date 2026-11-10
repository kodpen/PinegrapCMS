---
name: workflow-table-key-column
description: The key column of a workflow table (wtable_keys.php, plan tables-relational 4b): text or number, filled in and unique, refusals, the lock, by: key, the API ?key=, the import matching by key
---

## What is the key?
- Owner (2026-11-09, `workflow/decisions-made-product-40.md`): a column may be made the table's key, filled in and different on every row. One key column per table, beside the stable `#` number (`workflow-table-row-numbers.md`).
- `options.key = true` on a text or number column (`ws_wtable_column_options_check()`, `key_type` for any other type). No schema: the flag lives in `ws_wtable_columns.options`. Options sent without `key` keep it; `key: false` takes it away.
- Compared by `ws_wtable_key_value()`: a text folded without case and trimmed (`ws_fold()`, Turkish I alike), a number by its value (12 = 12.0).

## What does the store refuse?
- Making a column the key (`ws_wtable_column_key_check()`): `key_taken` 409 when another column is the key; `key_not_unique` 409 while rows are empty in it or repeat a value, with `rows` (the `#` numbers, at most 20), `rows_count` and `empty`. A new key column is empty on every row, so it is accepted only on an empty table.
- A row (create, update, restore from the bin): `cells_invalid` with the key column's code `key_empty` or `duplicate_key` (`ws_wtable_key_cells_refusal()`).
- Create and update run under `GET_LOCK('pg_wtable_key_<table id>', 5)` when the table has a key; the bodies are `ws_wtable_row_create_write()` / `_update_write()`, the public functions wrap them with `try/finally`.

## Where is it used?
- Screen: a "The table's key" checkbox on text and number columns, a Key badge in the columns card; the refusal names the rows (`ws_wtable_screen_refusal_text()`).
- Row boxes: `by: key` ("By its key"), any text or one value; `ws_wtable_row_keyed()` reads the rows in PHP. A table without a key finds no row.
- API: `GET /workspace/tables/{id}/rows?key=` lists that row or none; 422 on a table without a key. 409 messages name the rows.
- Import (`ws_wtable_import_rows()`): a file row whose key the table holds changes that row with the file's filled cells (`updated` in the report and the API answer); others are added. The dry run (`ws_wtable_import_rows_check()`) still counts every file row as an add.

## Tests
- `tests/workspace_wtable_keys_test.php`.
