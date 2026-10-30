---
name: workflow-table-import
description: A CSV/xlsx/xls/ods file into a workflow table (T6a, #3022): a new table with guessed column types or rows added by column name, confirmed first, every row through ws_wtable_row_create(), no webhooks
---

## Where is it, and how does a person use it?
- `includes/workspace/wtable_import.php`, screen `workspace_wtables.php`, tests `tests/workspace_wtable_import_test.php`.
- "Table from a file" card: a new table; "Rows from a file": a writable table with columns. Upload (`wt_action=import_read`) → confirmation (`?import=1`, `#ws-wt-import`) → `import_write`; Cancel posts `import_cancel` through the hidden action form.
- Between the two posts the read table waits as JSON in `sys_get_temp_dir()` (`tempnam()` prefix `pg_wtimport_`), its path in `$_SESSION['software']['ws_wtable_import']` with the target table id (0 = new). A new upload or the write drops it.

## How is the kept file found again, on Windows too?
- `kept()` and `drop()` both use `ws_wtable_import_kept_path()`: `realpath()` directly in the temp folder, a file, a name `ws_wtable_import_temp_name()` takes (`\` read as `/`; `pg_wtimport_*` or Windows' `pg_<1–4 hex>.tmp`, as Windows `tempnam()` keeps 3 prefix characters).
- Before #PR (#3390) a prefix check on `pg_basename()` failed on IIS: no confirmation, JSON never deleted.

## How is the file read?
- `ws_wtable_import_file_table($path, $name, $max)`: a spreadsheet via `pg_products_import_to_csv()`, a CSV with its first line's most used separator, BOM and blank lines dropped; `more` when longer than 5000 rows + header (only those are read, the screen says so). The one table-file reader: `ws_bulk_file_table()` (bulk_sources.php) calls it with a product card's 200 rows + 2 (#3044).
- First row = column names (`ws_wtable_import_names()`: empty → "Column N", a repeat gets " 2"). Over 50 columns is refused before the confirmation.

## How is a new table's column type guessed?
- `ws_wtable_import_guess()` over the non-empty values, in order: tick words only (yes/no/true/false/evet/hayır/x/✓; 1/0 alone stay numbers) → checkbox; all dates → date (`time` when one has a time other than 00:00); all amounts and one names a currency (₺ TL $ € £ or a code) → money, 2 decimals; all numbers → number (decimals = most written, ≤ 6); at least 4 values, ≤ 10 labels, each label at least twice on average → status (choices in the order met, keys `s1…`); else text. Person and link are never guessed; each column may be renamed, retyped (`ws_wtable_import_types()`) or left out.
- Numbers: with `.` and `,` the last is decimal; a lone mark is decimal (`1.250` = 1.25); a mark twice separates thousands. Dates: `Y-m-d`, `d.m.Y`, `d/m/Y` (day first), `Y/m/d`, optional time.

## How are rows written?
- Every row through `ws_wtable_row_create()` (rights, cell checks, the 5000 limit stay the store's). `ws_wtable_import_cell()` turns a value into what the cell check takes (money to minor units with `round()`, status by label, person by name or user name, link `kind:id`); what it cannot read goes as written, so the store's code answers. A row whose cells are all empty is skipped.
- New table: `ws_wtable_create()` and `ws_wtable_column_create()` per column; a refused column deletes the new table.
- Appending: file columns matched to table columns by name, case and spaces aside, a formula column never (`ws_wtable_import_match()`); another column or "(not imported)" may be chosen.
- Report (`#ws-wt-import-report`, once): rows added, rows refused with row number (header = 1) and each cell's code by column, first 100. At `too_many_rows` or any non-cell refusal the rest counts as refused and the import stops.
- `ws_wtable_row_announce_quiet(true)` for the run: no `workspace.wtable.row.*` event per row (api/ key rule); the catalogue says so.

## What else comes in this way?
- A note's table block (T6b, #3044): `workflow-table-import-notes.md`. Starters on the New table card (T6c): `workflow-table-starters.md`. Still missing: an API/MCP import route (`workflow-tables-later.md`).
