---
name: phpexcel-own-code
description: Plan (2026-11-19): PHPExcel's four callers move to the product's own sheet reader and xlsx writer, then includes/phpexcel is removed; no format dropped; XL1-XL5
---

## What did the owner ask?
- Owner (2026-11-19, agent channel message 9747): "daha önce sarkittik bu phpexceli acaba artık icabına baksak mi? Excel göstericimiz var kendi kodumuz ise onu gwlistirip phpexcel yerine mi koysak modern ve daha fonksiyonel şekilde? Çok mu zor iş olur".
- Architect's answer (request 1552; record in message 9750): yes, a middle-sized job; a technical decision, as no file format and no screen changes.

## What is there today? (read in the code and memory on 2026-11-19, not tried)
- `includes/phpexcel`: 258 files, 5.2 MB, ended at 1.8.x, patched by hand for each PHP version (`core/phpexcel-embedding.md`).
- It reads .xlsx, .xls and .ods for the product import (`import_products_f.php`) and the bank statement import (`includes/erp/bank_import.php`).
- It fills a template and writes .xlsx for the Parasut export of Orders (`edit_orders.php`) and the ERP export (`includes/erp/export.php`).
- The product's own: the xlsx reader of `includes/fn/office.php` (`pg_office_xlsx_cell_parts()`, `pg_office_reader_text()`, already used by the product import's own walk), the shared xlsx writer (`erp/erp-accountant-pack.md`), and `excel_reader2.php`, a one-file .xls reader the search index uses.

## What is built?
- XL1, one sheet reader. Done in #10466 (`platform/sheet-reader.md`). A function in a new `includes/fn/` module gives a file's rows one at a time for .xlsx, .ods and .xls, with the import's value rules (booleans 1/0, its date text) and without the preview's row, column and string limits (`platform/office-dom-ceiling.md`); .ods keeps its cell ceiling (`platform/ods-cell-ceiling.md`); .xls is read through `excel_reader2.php`.
  Tests read the same fixture files with PHPExcel and with the new reader and compare cell by cell.
- XL2, the product import reads through it.
- XL3, the bank statement import reads through it.
- XL4, the Parasut export and the ERP export are written by the shared xlsx writer; a template's fixed rows become data in code. A test compares the written cells with those PHPExcel writes today from the same input.
- XL5, removal. `includes/phpexcel` is deleted and listed in `clean_up.php` so an upgraded site loses it too; `tests/phpexcel_library_php85_test.php` and the PHPExcel part of `tests/bundled_library_php85_test.php` go; every other mention found by a search is cleared.
- Order XL1, then XL2, XL3 and XL4 in any order, XL5 last; one PR each; no schema, no new text on a screen.

## What is decided?
- Architect (2026-11-19): no format is dropped: banks still give statements as .xls. A step that finds a file the new reader cannot read as PHPExcel does says so in the channel and does not land.
- Architect (2026-11-19): no other library is added (`platform/office-files-scope.md`). "More functional" (a sheet chooser in an import, styled export heads) is a later question, asked when this plan has closed.

## How does the plan close?
- `core/phpexcel-embedding.md` is deleted, `platform/office-files-scope.md` and the importers' files say the new state, and this plan is deleted.
