---
name: sheet-reader
description: The importers' own sheet reader (pg_sheet_rows(), includes/fn/sheet_read.php, #PR): .xlsx, .ods and .xls rows one at a time as typed cells, its limits, how it differs from PHPExcel
module: sheet_read
---

## What is it for?
- Plan `phpexcel-own-code` XL1: the product import and the bank statement import read workbooks through it instead of PHPExcel (XL2, XL3 move them). Until then no screen calls it.
- `pg_sheet_rows($path, $kind, &$error, $sheet = 0)` is a generator: key the row number, value the row's cells from column A, gaps null; rows without cells are not given. `$kind` from `pg_sheet_kind($file_name)`: xlsx (also .xlsm), ods, xls.
- `$error` is set when the walk ends: `read`, `large` (an .ods ceiling) or `sheet` (no such sheet). Rows given before an error must be dropped by the caller. The callers map them to their existing texts; the reader has none.

## What is a cell?
- `array('t' => type, 'v' => string)`: `s` text, `n` number, `b` boolean (`1`/`0`), `d` date (day count from 1899-12-30; a 1904 workbook is moved to that count; a time alone is a fraction of a day), `e` error (`#DIV/0!`).
- A number's text is the file's: .xlsx its `<v>`, .ods its `office:value`, .xls `(string)` of the float, as PHPExcel gave the importers.
- `pg_sheet_cell_text($cell, $time_format = 'Y-m-d H:i:s')` is the product importer's text. The bank import keeps `erp_bank_sheet_value()` (time `Y-m-d H:i`, two decimals) on `v` and the type.
- Both importers read the first sheet (index 0) today.

## How is each format read?
- .xlsx: the preview's helpers (`pg_office_xlsx_sheets()`, `pg_office_xlsx_date_styles()`, `pg_office_xlsx_cell_parts()`), with no row, column or string limit: `pg_office_xlsx_strings($path, $part, false)` drops the preview's count and byte limits. The workbook, rels and styles keep the 8 MB DOM ceiling (`office-dom-ceiling.md`). A cell past column ZZZ refuses the file (`read`), as PHPExcel refused it, instead of padding a row to millions of cells.
- .ods: `content.xml` streamed node by node. The cell and range ceilings of `ods-cell-ceiling.md` carry over as `PG_SHEET_ODS_MAX_CELLS` and `PG_SHEET_ODS_MAX_RANGE`, plus `PG_SHEET_ODS_MAX_PART` on `content.xml`; empty runs cost one step; a repeated row or cell with a value is given once per repeat.
- .xls: `excel_reader2.php` through `Pg_Sheet_Xls_Reader` / `Pg_Sheet_Xls_Ole` (`includes/sheet_read_xls.php`), subclasses so the library file stays as it is. They read only the sheet asked for and run on the library before or after its PHP 8 patch (#10446).

## Where are its fixes, differences and tests?
- `sheet-reader-differences.md`: what the .xls subclasses fix in the library, where it reads otherwise than PHPExcel on purpose, the tests.
