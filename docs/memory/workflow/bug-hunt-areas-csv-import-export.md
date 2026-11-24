---
name: bug-hunt-areas-csv-import-export
description: The bug hunt of the panel's CSV imports and exports (area 27, 2026-11-24): the four faults fixed, what was read clean, what was not run
---

## Which area was hunted?
- Area 27 "csv-import-export", named by the architect: the panel's CSV and spreadsheet imports and exports (products, contacts, orders, redirects, key codes, campaign profiles, submitted forms, tracking numbers): who may, the formula guard, encoding and separators, broken rows, prices in minor units, large files.

## What did the hunt's PR fix?
- A Windows-1254 file (Excel in Turkish, "CSV (Comma delimited)") lost its letters: `Güral` was cut to `G` by the utf8mb4 column and Turkish headings did not match. `pg_csv_open()` now hands every importer UTF-8 lines: a line that is not UTF-8 is read as Windows-1254 (`pg_csv_utf8_handle()`, `includes/fn/core.php`); UTF-8 lines beside it stay as they are.
- The products CSV (Products screen and File Manager export) had no formula guard: a name like `=HYPERLINK(...)` from a supplier feed, marketplace or the API ran in Excel. Text columns and submit form field values now go through `csv_cell()` (`pg_products_export_csv_line()`); money, numbers and stock stay plain; the .xlsx needs none (inline strings never run). Import Products takes the guard off every cell (`pg_products_import_unguard_row()`).
- The campaign profiles CSV wrote its text cells with `escape_csv()` only; now `csv_cell()`, and Import Campaign Profiles unguards each cell.
- Import Contacts, Submitted Forms, Campaign Profiles, Key Codes and Tracking Numbers kept PHP's default 30 seconds while writing a row at a time: a file of tens of thousands of rows stopped part way with no result screen. Each sets `max_execution_time` 9999 as Import Products and Import Users do.

## What was read clean?
- Access: products, key codes, tracking numbers by `validate_ecommerce_access()`; contacts by `validate_contacts_access()` and group access per group; users and short links by Manager; campaign profiles by e-mail access; submitted forms by forms access; every import posts with the token.
- The contacts, orders, gift cards, key codes, short links and customer report exports guard their text cells; contacts, key codes, submitted forms, short links and tracking numbers take the guard off on import.
- Separators from the header line, the byte order mark, CR-only files; product prices read into minor units and written back as `%01.2lf` of `/ 100`.

## What was not run?
- No database here: the importers' loops were not run. The fixes are pinned by `tests/csv_import_export_hunt_test.php` (temporary files through `pg_csv_open()`, the line writer, source checks of the screens).

Related: docs/memory/workflow/bug-hunt.md, docs/memory/core/csv-formula-guard.md, docs/memory/core/csv-import-feedback.md
