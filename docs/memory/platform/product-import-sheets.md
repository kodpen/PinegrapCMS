---
name: product-import-sheets
description: How the product import turns a .xlsx, .ods or .xls upload into its CSV (pg_products_import_to_csv(), import_products_f.php): its own .xlsx walk, the sheet reader for .ods and .xls, the refusal texts
---

## Which path reads which file?
- `pg_products_import_to_csv($path, $file_name, &$error)` picks by extension (`pg_sheet_kind()`): .xlsx and .xlsm keep the importer's own walk (`pg_products_import_xlsx_to_csv()`, rows written as they go past); .ods and .xls go through the sheet reader (`pg_products_import_sheet_to_csv()` → `pg_sheet_rows()`, `pg_sheet_cell_text()`), first sheet. No PHPExcel since #10501; the library is removed (#10528).
- The same function serves Products > Import, the workflow table import (`workspace/workflow-table-import.md`) and a channel's bulk product drafts (`workspace/workspace-bulk-sources.md`).

## What is a row?
- A cell's text: a boolean 1/0, a date `Y-m-d` (with ` H:i:s` when it has a time of day), anything else as read. A row with no text is dropped; every row is squared to the widest row of the sheet.
- The sheet-reader rows are held until the walk ends, so a late error drops the whole file; an .ods is held to the reader's cell ceilings and an .xls is read whole by the reader anyway.

## Which texts does a refusal show?
- Reader `read` → "The file could not be read."; `large` (an .ods past its ceilings) → "The file is too large."; `sheet`, no row with text, or an unknown extension → "The file was empty.". No new text.

## What reads otherwise than with PHPExcel?
- Kept on purpose (`sheet-reader-differences.md`): an .xls date is a date, not its day count; a time alone is `1899-12-30 hh:mm:ss`, not `1970-01-01 …`; an .ods number is its own text (`2500000000000000`, not `2.5E+15`); .ods `text:s` spaces are kept; a merged range or a large second sheet in an .ods no longer refuses the file.
- `tests/products_import_sheet_test.php` reads the fixtures of `tests/fixtures/sheet_read/` with the importer and compares every cell with the rows PHPExcel gave, frozen in `phpexcel_product_rows.json` before the library was removed; the differences above are listed.

## Which helpers are left?
- The `pg_products_import_xlsx_*()` helpers serve the importer's own .xlsx walk; the bank statement import reads through the sheet reader. `pg_products_import_reader()`, `pg_products_import_ods_check()`, `pg_products_import_ods_load()` and the `PG_PRODUCTS_IMPORT_ODS_*` constants went with PHPExcel (#10528); the .ods ceilings are the sheet reader's (`ods-cell-ceiling.md`).
