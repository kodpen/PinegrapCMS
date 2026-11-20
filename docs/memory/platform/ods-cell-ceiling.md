---
name: ods-cell-ceiling
description: The .ods cell and used-range ceilings of the importers' sheet reader (PG_SHEET_ODS_MAX_CELLS, PG_SHEET_ODS_MAX_RANGE): repeated rows/columns counted, empty runs stepped over, "The file is too large."
module: sheet_read
---

## What was wrong?
- PHPExcel's .ods reader (removed in #10528) made a cell for every repeat of `table:number-rows-repeated` × `table:number-columns-repeated` and every cell a merged range covers, and walked an empty run one column at a time. A 745-byte .ods filled 128 MB in 15 s; an empty run of 2e9 columns spun for minutes; one value in the far corner made both importers walk 1.7e10 empty cells. PR #803 gave the library a cell ceiling; the sheet reader keeps the same limits.

## What does the sheet reader do? (`includes/fn/sheet_read.php`)
- `content.xml` may declare at most `PG_SHEET_ODS_MAX_PART` (8 MB) and is streamed node by node (`zip-entry-declared-size.md`).
- Value cells are counted with every repeat of a repeated row or column; past `PG_SHEET_ODS_MAX_CELLS` (the part ceiling / 64 = 131,072) the walk ends with `large`.
- The first sheet's used range (last data row × last data column) may span at most `PG_SHEET_ODS_MAX_RANGE` (1,048,576), as both importers square their rows to the widest; past it, `large`.
- An empty run (no value) moves the column in one step: LibreOffice's runs that fill each row to column 16,384 cost nothing.
- A merged range makes no cells: its value stands in its first cell, so a span of 0, a negative one or one past ZZZ reads the one value. Other sheets are not read.
- Both importers show `large` as "The file is too large." and drop the rows read (`product-import-sheets.md`, `erp/erp-bank-statements.md`).

## Which honest files does it refuse?
- None of LibreOffice's usual shapes: row-filling empty runs, the trailing repeated empty row, identical rows and cells written as repeats, merged titles.
- Refused: more than 131,072 value cells (8 MB of `content.xml` holding mostly repeated values; LibreOffice writes 90–150 bytes a cell), or a used range past 1,048,576 cells (a stray value in column 350 of a 3,000-line sheet).

## What about .xlsx and .xls?
- Not limited here. A .xlsx cell past column ZZZ refuses the file (`sheet-reader.md`); a BIFF8 sheet stops at 256 × 65,536.

## Where are the tests?
- `tests/ods_cell_ceiling_test.php` (both importers on honest and expanding files), `tests/sheet_read_test.php` (the reader's own ceilings), `tests/zip_entry_contents_test.php` (the part ceiling).
