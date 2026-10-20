---
name: ods-cell-ceiling
description: PHPExcel's .ods reader makes cells of repeated rows/columns and merged ranges only up to a ceiling, steps over empty runs at once; both importers also cap the first sheet's used range
---

## What was wrong?
- `PHPExcel_Reader_OOCalc` made a cell for every repeat of `table:number-rows-repeated` × `table:number-columns-repeated`, a reference for every cell a merged range covers (`mergeCells()` → `extractAllCellReferencesInRange()`), and walked an empty run one column at a time. A 745-byte .ods filled 128 MB in 15 s; a merged range did it in 0.2 s; an empty run of 2e9 columns spun for minutes; a span of 0, negative or past ZZZ was walked without end; one value in the far corner made both importers walk 1.7e10 empty cells.
- #730 measures `content.xml` and `meta.xml` (8 MB); this is what the reader makes of them.

## What does the reader do now? (PR #803)
- `setCellCeiling($n)` (0, the default, sets none): value cells × repeats and merged-range cells are counted over the whole workbook before they are made; one past the ceiling throws `PHPExcel_Reader_Exception` coded `PHPExcel_Reader_OOCalc::CELL_CEILING_REACHED`.
- An empty run (no `text:p`) moves the column in one step (`columnAfter()`, the string-increment sequence, counted to 2^30: nothing can be placed past ZZZ). The cells are the same as before; LibreOffice's runs that fill each row to column 16,384 now cost nothing.
- A merged range with a span below 1 or reaching past ZZZ stops the load, with or without a ceiling.
- Repeats and spans are read as integers (`attributeCount()`); a negative repeat counts as no cells.
- These are local changes to the bundled library, like #323's PHP 8 fixes in the same file: keep them if PHPExcel is ever replaced or updated.

## What do the importers do?
- `pg_products_import_ods_load($path, $data_only, &$error)` (import_products_f.php): `pg_products_import_ods_check()`, the reader with `PG_PRODUCTS_IMPORT_ODS_MAX_CELLS` (the part ceiling / 64 = 131,072), then the first sheet's used range (last data row × last data column) at most `PG_PRODUCTS_IMPORT_ODS_MAX_RANGE` (1,048,576). Over either → "The file is too large."; anything thrown → "The file could not be read."
- The product importer calls it with data only, `erp_bank_read_spreadsheet()` with formats.
- Cost at the ceilings (PHP 8.3, 128M): 131,072 cells in 5–6 s and 63 MB; both ceilings at once in the bank import 95 MB.

## Which honest files does it refuse?
- None of LibreOffice's usual shapes: row-filling empty runs, the trailing repeated empty row, identical rows and cells written as repeats, merged titles. Files saved by LibreOffice 24.2 gave the same rows as the old reader.
- Refused: more than 131,072 cells (inside 8 MB of `content.xml` that takes mostly repeated values; LibreOffice writes 90–150 bytes a cell), or a used range past 1,048,576 cells (a stray value in column 350 of a 3,000-line sheet).

## What about .xlsx and .xls?
- Not limited here. The .xlsx readers pad a row up to its cell reference (`r="ZZZZZ2"`: 128 MB in 0.1 s); the product importer squares every row to the widest. The .xls reader's merged ranges fill memory in the bank import (data-only skips them); a BIFF8 sheet's used range stops at 256 × 65,536 (3 s walk).

## Where are the tests?
- `tests/ods_cell_ceiling_test.php`.
