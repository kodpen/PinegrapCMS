---
name: import-shopify
description: Shopify products and customers CSV import (2026.11.0, #6236): rows grouped by handle, product items as WooCommerce, the contact writer, consent line, tag groups, undo
module: importers
---

## Where is it?
- Reader `includes/import/shopify.php`, contact writer `includes/import/write_contact.php`; products go to `write_product.php` (`import-woocommerce.md`, #6206). Tests: `tests/import_shopify_test.php` on `tests/fixtures/import/shopify_products.csv` and `shopify_customers.csv`. Framework: `import-framework.md`.

## Which file is it?
- Both files detect as `shopify`; `pg_import_shopify_file_kind()` tells them apart by the header: `handle` → products, `email` with `first name` or `customer id` → customers. One upload is one file.

## How are products read?
- The rows of a handle stand together in the export; `_next()` reads them all and stops at the next handle (the cursor is the byte offset of that row). Key `shopify:product:<handle>`.
- Option1-3 names of the first row are the axes; every row with a price or SKU is a variant, a row with neither carries only a picture. One variant whose axis is `Title` ("Default Title") is a simple product.
- Variant price and compare-at price through `pg_products_import_price_cents()` (a compare-at price not above the price is dropped); tracked stock (`Variant Inventory Tracker` set) below 0 is 0; `Variant Grams` stored in pounds (`pg_import_weight_pounds()`, `includes/fn/importers.php`), the unit `products.weight` holds, whatever `Variant Weight Unit` the shop shows; `Variant Image` is the variant's picture, `Image Src` rows the product's in `Image Position` order.
- Body (HTML) → description, Tags → keywords, Type → one category, Published `true` with Status empty or active → enabled. A SKU-less variant takes `<handle>-<n>`.

## How are customers written?
- Key `shopify:customer:<id>` (the leading apostrophe dropped), else by address, else by line. The default address is the business address (the older `Address1`… columns are read too), `Phone` the mobile, `Note` the description.
- An address a contact holds already is skipped and named; nothing is overwritten. Values are cut to the `contacts` column lengths (`SHOW COLUMNS`, as `import_contacts.php`).
- `opt_in` is "Accepts Email Marketing" (or the older "Accepts Marketing"); a consent log line records it granted or refused, source `import`, no IP. Each tag is a contact group found by name or made (not removed by undo).
- Undo deletes the contact, its group links and `opt_in` rows as the contact list does, unless a user account (`user_contact`) or an order points at it: then it is Kept in the report.
