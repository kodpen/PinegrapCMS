---
name: workflow-table-import
description: A CSV/xlsx/xls/ods file into a workflow table (T6a, #3022): a new table with guessed column types or rows added by column name, confirmed first, every row through ws_wtable_row_create(), no webhooks
---

## Where is it, and how does a person use it?
- `includes/workspace/wtable_import.php` (loaded by `bootstrap.php`), screen `workspace_wtables.php`. Tests `tests/workspace_wtable_import_test.php`.
- The list's "Table from a file" card makes a new table; a writable table with columns has a "Rows from a file" card. Upload (`wt_action=import_read`) → confirmation (`?import=1`, `#ws-wt-import`) → `import_write`; Cancel posts `import_cancel` through the hidden action form.
- Between the two posts the read table waits as JSON in `sys_get_temp_dir()` (`pg_wtimport_*`), its path in `$_SESSION['software']['ws_wtable_import']` with the target table id (0 = new); `ws_wtable_import_kept()` reads back only a file of that prefix in that folder. A new upload or the write drops it.

## How is the file read?
- `ws_wtable_import_file_table($path, $name, $max)`: a spreadsheet through `pg_products_import_to_csv()`, a CSV with the separator its first line uses most, BOM and blank lines dropped; `more` when longer than 5000 rows + header (only those are read, the screen says so). The one table-file reader: `ws_bulk_file_table()` (bulk_sources.php) calls it with a product card's 200 rows + 2 (#3044).
- First row = column names (`ws_wtable_import_names()`: empty → "Column N", a repeat gets " 2"). More than 50 columns is refused before the confirmation.

## How is a new table's column type guessed?
- `ws_wtable_import_guess()` over the non-empty values, in order: tick words only (yes/no/true/false/evet/hayır/x/✓; 1/0 alone stay numbers) → checkbox; all dates → date (`time` when one has a time other than 00:00); all amounts and one names a currency (₺ TL $ € £ or a code) → money, 2 decimals; all numbers → number (decimals = most written, ≤ 6); at least 4 values, ≤ 10 labels, each label at least twice on average → status (choices in the order met, keys `s1…`); else text. Person and record link are never guessed. The person may rename, retype (`ws_wtable_import_types()`) or leave out each column.
- Numbers: with both `.` and `,` the last is the decimal mark; a lone mark is decimal (`1.250` is 1.25); the same mark twice separates thousands. Dates: `Y-m-d`, `d.m.Y`, `d/m/Y` (day first), `Y/m/d`, optional time.

## How are rows written?
- Every row through `ws_wtable_row_create()` (rights, cell checks, the 5000 limit stay the store's). `ws_wtable_import_cell()` turns a value into what the cell check takes (money to minor units with `round()`, status by label, person by name or user name, link `kind:id`); what it cannot read goes as written, so the store's code answers. A row whose cells are all empty is skipped.
- New table: `ws_wtable_create()` and `ws_wtable_column_create()` per column; a refused column deletes the table made so far.
- Appending: file columns matched to table columns by name, case and spaces aside, a formula column never (`ws_wtable_import_match()`); the person may choose another column or "(not imported)".
- Report (`#ws-wt-import-report`, once, by session): rows added, rows refused with their row number (header = row 1) and each cell's code by column name, first 100 listed. At `too_many_rows` or any non-cell refusal the rest counts as refused and the import stops.
- `ws_wtable_row_announce_quiet(true)` for the run: no `workspace.wtable.row.*` event per row (api/ key rule); the catalogue text says imports are not announced.

## What else comes in this way?
- A note's table block (T6b, #3044): `workflow-table-import-notes.md`. Starters on the New table card (T6c): `workflow-table-starters.md`. Still missing: an API/MCP import route (`workflow-tables-later.md`).
