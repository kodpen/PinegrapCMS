---
name: excel-reader2-embedding
description: The bundled excel_reader2.php (php-excel-reader 2.22) that reads an uploaded .xls for the site search index, and the local patch that makes it run and stay quiet on PHP 8
---

## Which code loads excel_reader2.php?
- Only `convert_xls_to_text()` in `update_search_index.php`: the cells of an uploaded .xls file, every sheet, become the file's text in the site search index.
- The bank statement import reads .xls through it too, by subclasses in `includes/sheet_read_xls.php` (`platform/sheet-reader.md`); the product import reads .xls with PHPExcel (`phpexcel-embedding.md`).

## What broke on PHP 8?
- `OLERead` and `Spreadsheet_Excel_Reader` had PHP 4 constructors (a method named like the class). PHP 8 never runs those, so the reader was never set up and `convert_xls_to_text()` returned '' without a notice.
- Symptom: since PHP 8 every .xls went into the search index without its text, so site search found it by name only. Fixed in #10446.

## What does the patch change?
- Both constructors are `__construct()`. No code calls the old names as methods, so none is kept.
- 17 properties are declared (PHP 8.2 deprecates creating them on the fly): `OLERead`'s `$error` and its header and chain fields, `Spreadsheet_Excel_Reader`'s `$_encoderFunction`, `$store_extended_info`, `$version`, `$nineteenFour`, `$sn`. Declared as null, they read as before.
- The column-name table in the constructor gives `chr()` `(int)` of the division: the same letters, but no "loses precision" deprecation (PHP 8.1), which came 209 times per file.
- Each change carries a `Pinegrap:` comment; the header stays. A library update (none is expected: the project ended) re-applies it.

## What keeps it?
- `tests/excel_reader2_php85_test.php` takes `convert_xls_to_text()` from the job's source and runs it in its own PHP process that reports every error.
- It reads `tests/fixtures/excel_reader2/products.xls` (two sheets: Turkish text, decimals, a negative number, a formula, a date in built-in format 14, written by the bundled PHPExcel) and checks the exact text; a file that is no .xls and a missing one give '' quietly.

## What does the reader still not do?
- A custom number format stored as UTF-16 (PHPExcel's writer stores every one so) is not decoded: such a cell's text is its format with NUL bytes. This is the library's own limit on every PHP version, left as it is.
