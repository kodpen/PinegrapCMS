---
name: export-import-columns
description: The Contacts and Products exports write every column their importer reads, pinned by tests/export_import_columns_test.php; new columns go at the end
---

## Does an export write every column its importer reads?
- Yes for contacts and products. `tests/export_import_columns_test.php` reads the `convert_column_name()` returns of `import_contacts.php` and `import_products.php` and requires each one in the `view_contacts.php` export (header, SELECT and row) and in `pg_products_export_columns()` (`export_products_f.php`, shared by Products > Export CSV/XLSX and the File Manager export).
- Before 2026.11.0 contacts left out `salutation` and `suffix`, products `minimum_quantity` and `maximum_quantity`. A record exported and imported as new (another site, or after Delete All; Import Contacts always creates) came back with them blank: a product with Min. 6 / Max. 12 got 0 / 0 and the cart took 1.

## Where does a new column go?
- At the end: contacts after the affiliate columns, products after the customs block. No older column moves; the importers read headings, not positions (`csv-exports.md`).
- A column the importer reads only where the table has it (`compare_at_price`, `hs_code`, `origin_country`) is exported on the same `*_ready()` condition.
