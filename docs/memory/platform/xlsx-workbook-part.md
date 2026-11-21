---
name: xlsx-workbook-part
description: Where .xlsx readers and editors find the workbook part: through _rels/.rels (pg_sheet_xlsx_workbook(), PR #10905), else xl/workbook.xml; which editor refuses one kept elsewhere
module: sheet_read
---

## How is the workbook part found?
- `pg_sheet_xlsx_workbook($zip)` (includes/fn/sheet_read.php) reads `_rels/.rels` and takes the officeDocument relationship's target, as PHPExcel and Excel find it (`book/main.xml`, `xl/Workbook2.xml`). It gives `part`, `folder` (the base for that part's relationship targets) and `rels` (`book/_rels/main.xml.rels`).
- A package without `_rels/.rels`, without that relationship, or naming a part it does not hold, keeps `xl/workbook.xml`, `xl/`, `xl/_rels/workbook.xml.rels`.

## Who reads through it?
- `pg_office_xlsx_sheets()` (the sheet list of the preview, the sheet question, every importer and every save), `pg_sheet_xlsx_part()` (shared strings, styles), the sheet reader's date-1904 flag (`pg_sheet_xlsx_rows()`), and the editor's `pg_office_xlsx_style_kinds()`, which now takes its styles through `pg_sheet_xlsx_part($zip, 'styles', 'xl/styles.xml')` as the date styles do.
- The preview's own workbook read in `pg_office_xlsx_read()`: its date-1904 flag, the "password to modify" and the sheet tabs' edit info. Before PR #10996 it took `xl/workbook.xml`, so a workbook named otherwise that counts from 1904 showed its dates four years early in the preview only.
- The preview's shared strings, through `pg_sheet_xlsx_part($zip, 'sharedstrings', 'xl/sharedStrings.xml')`: the part the workbook's relationships name, else `xl/sharedStrings.xml`, else none (text cells empty, no error). Before PR #11032 it looked at `xl/sharedStrings.xml` alone, so a workbook naming its strings otherwise (`xl/strings/Shared.xml`) showed every text cell empty in the preview only.
- The cell save (`pg_office_xlsx_save_parts()`, `pg_office_xlsx_recalc_parts()`), the format save and the row/column shift read the workbook, and its "password to modify", from that part and write it back there.
- Before #10764 (PR #10905) such a file read as having no sheets ("The file could not be read.") in Quick Look, the file window and every importer.

## What is refused?
- Adding, renaming, deleting and moving a sheet (office_sheets.php) write `xl/workbook.xml`, its relationships and `xl/worksheets/` by name, so a workbook kept elsewhere is refused with "The workbook's sheets cannot be changed here." (`sheets_unusual`).

## What is left?
- Still a fixed path: the product import's fallback first-sheet walk (`pg_products_import_xlsx_first_part()`), used only when `pg_office_xlsx_sheets()` finds nothing, and the upload kind guess (`pg_sheet_import_content_kind()`, which falls back to the file name).

## Tests
- `tests/sheet_read_xlsx_parts_test.php`: `book/main.xml` and `xl/Workbook2.xml` with relative sheet and styles parts and `date1904` read their sheet, the date moved to the 1900 count and the editor's text and date styles; a package without `_rels/.rels` reads as before; the preview (`pg_office_xlsx_read()`) shows the 1904 date of `book/main.xml`, `xl/Workbook2.xml` and `xl/workbook.xml` as 2026-11-17.
- Same file: the preview (`pg_office_xlsx_read()`) reads the text of `xl/strings/Shared.xml` named in the relationships, and of `xl/sharedStrings.xml` with no relationship, and an empty text cell when neither is there.
