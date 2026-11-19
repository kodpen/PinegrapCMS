---
name: excel-reader2-embedding
description: The bundled excel_reader2.php (php-excel-reader 2.22) that reads an uploaded .xls for the site search index, and the local patch that makes it run on PHP 8
---

## Which code loads excel_reader2.php?
- Only `convert_xls_to_text()` in `update_search_index.php`: an uploaded .xls file's cells go into the site search index as its text.

## What broke on PHP 8?
- Both classes, `OLERead` and `Spreadsheet_Excel_Reader`, had PHP 4 constructors (a method named like the class). PHP 8 never runs those, so the reader was never set up, `convert_xls_to_text()` returned '' and every .xls went into the index without its text.
