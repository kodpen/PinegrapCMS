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
- The cell save (`pg_office_xlsx_save_parts()`, `pg_office_xlsx_recalc_parts()`), the format save and the row/column shift read the workbook, and its "password to modify", from that part and write it back there.
- Before #10764 (PR #10905) such a file read as having no sheets ("The file could not be read.") in Quick Look, the file window and every importer.

## What is refused?
- Adding, renaming, deleting and moving a sheet (office_sheets.php) write `xl/workbook.xml`, its relationships and `xl/worksheets/` by name, so a workbook kept elsewhere is refused with "The workbook's sheets cannot be changed here." (`sheets_unusual`).

## What is left?
- The preview's own date-1904 read in `pg_office_xlsx_read()` (office.php) takes `xl/workbook.xml`: a workbook whose part has another name and counts from 1904 shows its dates four years early in the preview only (the importers read them right). It was left out beside OE3b #10871, which held that function.
- Architect (2026-11-20): after #10871 lands, one small PR changes that line to `pg_sheet_xlsx_workbook($zip)['part']` with its test (a rels-named workbook with date1904), given out with the next Excel fix.
- Still a fixed path: the product import's fallback first-sheet walk (`pg_products_import_xlsx_first_part()`), used only when `pg_office_xlsx_sheets()` finds nothing, and the upload kind guess (`pg_sheet_import_content_kind()`, which falls back to the file name).

## Tests
- `tests/sheet_read_xlsx_parts_test.php`: `book/main.xml` and `xl/Workbook2.xml` with relative sheet and styles parts and `date1904` read their sheet, the date moved to the 1900 count and the editor's text and date styles; a package without `_rels/.rels` reads as before.
