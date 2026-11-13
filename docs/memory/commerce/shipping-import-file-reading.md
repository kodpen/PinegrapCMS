---
name: shipping-import-file-reading
description: Import Tracking Numbers: how the CSV is opened and read (pg_csv_open, separator, byte order mark before quoted headings, CR-only line breaks, Windows-1254, row lines) (#9137)
module: shipping_import
---

## How is the uploaded file opened?
- `pg_shipping_import_read_file()` (`includes/fn/shipping_import.php`) opens it with `pg_csv_open()`, as the contacts, users, products, key codes and submitted forms importers do: a leading UTF-8 byte order mark is stepped over, and a file whose line breaks are a lone CR (classic Mac, some carrier and till exports) is copied with LF breaks. A file that cannot be opened gives "The file could not be read."
- `pg_shipping_import_read()` takes any handle at the start of its first line and steps over a byte order mark there too (the tests hand it in-memory streams).

## How is the separator found?
- `pg_shipping_import_delimiter()` is `pg_csv_separator()`: of the comma, the semicolon (Excel in a Turkish locale) and the tab, the one the header line holds most outside quoted cells; the comma when none. `pg_csv_read_separator()` reads the line and goes back to where it started, never onto the mark.

## What is a row?
- A first row whose first cell is `order_number` (any case) is the header and is skipped; blank rows are skipped; cells are trimmed and lose the formula guard an orders export put on them (`pg_csv_formula_unguard()`, `core/csv-formula-guard.md`).
- A cell that is not UTF-8 is read as Windows-1254 (Excel's Turkish CSV): before #5717 'Yurtiçi Kargo' was refused as an unknown carrier and shown blank.
- A row's line is the file line it starts on: a quoted cell holding a line break counts its lines (`pg_csv_row_lines()`).

## What was the symptom? (#9137)
- The reader opened the file with `fopen()`, took the separator from `fgets()` and `rewind()`ed to byte 0, then cut the mark off the first cell after `fgetcsv()`: with the mark before a quoted heading the cell kept its quotes, so the header was listed as a refused row ("An order number is made of digits only.") and counted as not imported.
- A CR-only file was one row whose first cell was `order_number`, skipped as the header: "The file has no rows." and nothing imported.
- Commas inside quoted semicolon headings were counted, so such a file was read with commas.

## Tests
- `tests/shipping_import_test.php`: in-memory streams and temporary files (mark with quoted headings and CRLF, CR only, a missing file, quoted separators, line breaks in a cell, the export guard).
