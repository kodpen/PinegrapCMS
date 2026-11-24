---
name: csv-formula-guard-products
description: The products and campaign profiles CSV exports guard their text cells against formulas; Import Products and Import Campaign Profiles take the guard off
---

## Which cells of the products CSV are guarded?
- `pg_products_export_table()` (`export_products_f.php`) returns `guard`, one flag a column: text columns (name, descriptions, code, SEO fields, custom fields...) and the `sfc_`/`sfu_` submit form field values. `pg_products_export_csv_line()` puts those through `csv_cell()` (`core/csv-formula-guard.md`).
- Money (`price`, `compare_at_price`...), numbers, yes/no and the `stock:<location>` cells stay plain: a negative quantity stays a number.
- The .xlsx export is not guarded: every cell is an inline string, which a spreadsheet never runs.
- Before (bug hunt of 2026-11-24): every cell went through `escape_csv()` only, so a product name `=HYPERLINK(...)` from a supplier feed, marketplace or the API ran when the file was opened.

## Does Import Products take the guard off?
- Yes: `pg_products_import_unguard_row()` (`import_products_f.php`) runs `pg_csv_formula_unguard()` on every cell of a row right after `fgetcsv()`, so an unchanged export imports back as stored (`'+90` is `+90`, `'-5` is `-5`). A cell typed as `'=x` loses its apostrophe too.

## And the campaign profiles CSV?
- `view_email_campaign_profiles.php` writes every text cell (name, action item, subject, body, page, from, reply, bcc, user names) with `csv_cell()`; Import Campaign Profiles runs `pg_csv_formula_unguard()` on each trimmed cell.
- Tests: `tests/csv_import_export_hunt_test.php`.

Related: docs/memory/core/csv-formula-guard.md, docs/memory/workflow/bug-hunt-areas-csv-import-export.md
