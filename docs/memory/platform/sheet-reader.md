---
name: sheet-reader
description: The importers' own sheet reader (pg_sheet_rows(), includes/fn/sheet_read.php, #10466): .xlsx, .ods and .xls rows one at a time as typed cells, its limits, how it differs from PHPExcel
module: sheet_read
---

## What is it for?
- The importers read workbooks through it (#10466): the product import reads .ods and .xls through it (`product-import-sheets.md`); the bank statement import reads every format through it, .xlsx too (#10503).
- PHPExcel is gone (#10528): `includes/phpexcel` is deleted and listed in `clean_up.php`, so an upgraded site loses it too. The Parasut exports are written by the shared xlsx writer (`erp/parasut-import-sheets.md`); nothing in the product loads a spreadsheet library.
- Architect (2026-11-19): no format is dropped in the move (banks still give statements as .xls) and no library is added (`office-files-scope.md`). A sheet chooser in an import or styled export heads is a later question for the architect.
- `pg_sheet_rows($path, $kind, &$error, $sheet = 0)` is a generator: key the row number, value the row's cells from column A, gaps null; rows without cells are not given. `$kind` from `pg_sheet_kind($file_name)`: xlsx (also .xlsm), ods, xls.
- `$error` is set when the walk ends: `read`, `large` (an .ods ceiling) or `sheet` (no such sheet). Rows given before an error must be dropped by the caller. The callers map them to their existing texts; the reader has none.

## What is a cell?
- `array('t' => type, 'v' => string)`: `s` text, `n` number, `b` boolean (`1`/`0`), `d` date (day count from 1899-12-30; a 1904 workbook is moved to that count; a time alone is a fraction of a day), `e` error (`#DIV/0!`).
- A number's text is the file's: .xlsx its `<v>`, .ods its `office:value` (PHPExcel gave a float's text: `2.5E+15`, `1.0E-7`), .xls `(string)` of the float, as PHPExcel gave it.
- `pg_sheet_cell_text($cell, $time_format = 'Y-m-d H:i:s')` is the product importer's text. The bank import keeps `erp_bank_sheet_value()` (time `Y-m-d H:i`, two decimals) on `v` and the type (`erp_bank_xls_cell()`); its `$error` maps to the import's texts: `large` "The file is too large.", the rest "The file could not be read."
- Both importers read the first sheet (index 0) today.

## How is each format read?
- .xlsx: the preview's helpers (`pg_office_xlsx_sheets()`, `pg_office_xlsx_date_styles()`, `pg_office_xlsx_cell_parts()`), with no row, column or string limit: `pg_office_xlsx_strings($path, $part, false)` drops the preview's count and byte limits. The workbook, rels and styles keep the 8 MB DOM ceiling (`office-dom-ceiling.md`). A cell past column ZZZ refuses the file (`read`), as PHPExcel refused it, instead of padding a row to millions of cells.
- .ods: `content.xml` streamed node by node, held to the ceilings of `ods-cell-ceiling.md` (`PG_SHEET_ODS_MAX_PART`, `PG_SHEET_ODS_MAX_CELLS`, `PG_SHEET_ODS_MAX_RANGE`); empty runs cost one step; a repeated row or cell with a value is given once per repeat.
- .xls: `excel_reader2.php` through `Pg_Sheet_Xls_Reader` / `Pg_Sheet_Xls_Ole` (`includes/sheet_read_xls.php`), subclasses so the library file stays as it is. They read only the sheet asked for and run on the library before or after its PHP 8 patch (#10446).

## Where are its fixes, differences and tests?
- `sheet-reader-differences.md`: what the .xls subclasses fix in the library, where it reads otherwise than PHPExcel on purpose, the tests.
