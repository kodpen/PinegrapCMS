---
name: sheet-reader-differences
description: The sheet reader's .xls fixes to excel_reader2.php (SST, memory), .xlsx parts found through the relationships, its deliberate differences from PHPExcel, its tests
module: sheet_read
---

## What do the .xls subclasses fix in the library?
- Hangs: every OLE block chain is bounded; the workbook stream and the sheet are walked first and refused without an EOF inside them (the library's own walk past the end never stops).
- Numbers: signed RK values (a negative whole number read as 1,073,741,817; -0.01 as -1) and NUMBER doubles read exactly (its arithmetic made 0 a tiny number).
- Dates by the number format decoded in full (the library cannot read a UTF-16 format, as PHPExcel and Excel write custom ones), then `pg_office_xlsx_format_kind()`.
- BOOLERR booleans and errors, a formula's boolean, error or text result, LABEL text in the workbook's code page.
- The shared string table (#10537): `pg_shared_strings()` reads the SST and its CONTINUE records, and the library passes over the record. The library refused the whole file when a split fell in a string's formatting runs (a few thousand styled cells) and kept only the last character of a string that went on two bytes a character after a split.
- Memory (#10537): `addcell()` keeps each cell as its type letter and value in one string, the workbook's bytes are let go after the read and rows are made as given. The library's cell, text and details took 3 to 5 times PHPExcel's memory (60,000 cells: over 100 MB against 24.5 MB).

## Which .xlsx parts are found through the relationships?
- The shared strings (sheet reader and product import) and the product import's styles: `pg_sheet_xlsx_part()` (#10537), as PHPExcel found them; before, a writer naming them otherwise lost every text cell. The sheet reader's styles and workbook part are still fixed names (#10544).

## Where does it differ from PHPExcel? (on purpose)
- .ods text keeps `text:s` spaces, tabs, line breaks and link text (PHPExcel kept only a paragraph's own text: `  spaced  ` read `spaced `); a boolean comes from `office:boolean-value` (PHPExcel compared the shown text with TRUE, so a Turkish DOĞRU read false); a value cell with no paragraph keeps its value.
- A time alone is a fraction of a day: PHPExcel gave today's date (.xlsx, .xls) or 1970-01-01 (.ods) with it.
- An .xls date with the product importer's data-only read was a day count; it is a date now, as in .xlsx.
- Formulas: the result the file stores; PHPExcel recalculated them.
- An .ods number is its `office:value` text (`2500000000000000`, `0.0000001`); PHPExcel's float gave `2.5E+15`, `1.0E-7`.
- An .ods merged range makes no cells (its value stands in the first) and other sheets are not read, so a small file spanning millions of merged cells, or a huge second sheet, is read, not refused as too large.

## How is it tested?
- `tests/sheet_read_xls_test.php` builds .xls files byte by byte (compound file, SST split across records, a 60,000-cell memory budget); `tests/sheet_read_xlsx_parts_test.php` a .xlsx with renamed parts.
- `tests/sheet_read_test.php`: the fixtures in `tests/fixtures/sheet_read/` (LibreOffice 24.2 .xlsx/.ods/.xls from one openpyxl workbook, a PHPExcel Excel5 .xls, 1904 workbooks) and `tests/fixtures/excel_reader2/products.xls` read by PHPExcel in its own process and by the reader, compared cell by cell; the differences above are listed there.
- Also: no preview limits, rows one at a time, .ods ceilings, cut and looping .xls files refused within seconds.
- The product import's CSV from the same fixtures against PHPExcel's: `tests/products_import_sheet_test.php` (`product-import-sheets.md`).
