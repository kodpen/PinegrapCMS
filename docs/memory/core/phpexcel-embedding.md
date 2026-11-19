---
name: phpexcel-embedding
description: The bundled PHPExcel (includes/phpexcel, 1.8.x, no longer maintained): which screens load it, and the local patch that keeps it quiet on PHP 8.5
---

## Which code loads includes/phpexcel?
- The Parasut export of Orders (`edit_orders.php`) and the ERP export (`includes/erp/export.php`): a template from `includes/phpexcel/templates/` filled and written as .xlsx.
- The bank statement import (`includes/erp/bank_import.php`): .xls and .ods read cell by cell (Excel5, OOCalc readers) through `pg_products_import_reader()` and `pg_products_import_ods_load()`. The product import reads through the sheet reader since XL2 (`platform/product-import-sheets.md`).
- The CSV reader and writer, the Excel5 writer, charts and the PDF and HTML writers are not used by the product.

## Why a local patch instead of a newer release?
- PHPExcel ended at 1.8.x and was archived; its successor PhpSpreadsheet has another API and asks for a newer PHP than 7.1. Moving to it rewrites the four callers above.
- The library already carried Pinegrap changes before (the .ods reader's cell ceiling, #803; dates independent of the time zone, #323).

## What does the PHP 8.5 patch change?
- Compile time, in every file: `(integer)`/`(boolean)`/`(double)` casts are `(int)`/`(bool)`/`(float)`; `Type $x = null` is `?Type $x = null` (7.1 syntax); a default before a required parameter is dropped, as PHP never used it; `continue` inside a `switch` in `Shared/OLE.php` is `break`, which it already meant.
- On the product's paths: a column letter is advanced through `PHPExcel_Cell::stringFromColumnIndex(columnIndexFromString())` (or the .ods reader's `columnAfter()`), not `++` on a string (8.3).
- Declared properties instead of dynamic ones (8.2): `colorIndex` on `Style_Border` and `Style_Font`, `startcolorIndex`/`endcolorIndex` on `Style_Fill`, `textObjRef` on the Excel5 reader, the header fields of `Shared_OLERead`, `_debugLog` on `PHPExcel_Calculation`.
- Null offsets checked before use in the formula parser and the .xlsx sheet writer; the string table tests a cell's type before using its value as a key (a float key loses precision on 8.1).
- `#[\ReturnTypeWillChange]` on `PHPExcel_WorksheetIterator`; `strftime('%Y')` and its siblings in DATEVALUE() are `date('Y')`; the CSV reader passes `fgetcsv()` its escape `'\\'`.
- `@libxml_disable_entity_loader()` in `Settings.php` stays: the `@` keeps it out of PHP's log and `pg_error_record()` skips silenced notices.

## What keeps it?
- `tests/phpexcel_library_php85_test.php` runs the export and the three readers in their own PHP process that reports every error, and checks the values read back; static checks catch a default before a required parameter and `strftime()`.
- `tests/bundled_library_php85_test.php` reads `includes/phpexcel` for non-canonical casts and implicitly nullable parameters.
- Checked 2026-11-17: the written .xlsx parts are byte for byte those of the unpatched library. A library update (none is expected) re-applies the patch.
