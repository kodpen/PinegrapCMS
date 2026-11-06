---
name: import-woocommerce
description: WooCommerce products CSV import (2026.11.0, #6206): reader, item shape, variable products as variant sets, categories as groups, the product writer and its undo
module: importers
---

## Where is it?
- Reader `includes/import/woocommerce.php`, writer `includes/import/write_product.php` (kind `product`, shared with the Shopify reader). Tests: `tests/import_woocommerce_test.php` on `tests/fixtures/import/woocommerce.csv`. Framework: `import-framework.md`.

## How is the file read?
- `pg_import_woocommerce_open()` reads the header (lower case, BOM dropped, separator by `pg_csv_read_separator()`) and makes one pass: for every variable parent its keys (`id:<ID>` and the SKU in lower case) and, for every parent reference, the byte offsets of its variation rows. Variations may stand anywhere; the cursor holds the offsets.
- `_next()` gives one item per product: a variation of a parent in the file is read with it and passed over; one whose parent is missing is an item that fails ("Its parent product … is not in the file"). Grouped and external products are items the writer skips.
- Key `woo:product:<ID>`, else `woo:sku:<sku>`, else `woo:line:<n>`.

## Which cells go where?
- SKU → `products.name` (empty: `woo-<ID>`; a variation: parent SKU + `-<ID>`), Name → `short_description`, Description → `full_description`, Short description → `details` (both through `pg_sanitize_rich_text()` staff and the editor's input prep), Tags → `keywords`, Published `1` → enabled, the weight column → `weight` in pounds through `pg_import_woocommerce_weight()` (unit from the header: `Weight (kg)`, `(g)`, `(lbs)`, `(oz)`; none is kg; a decimal comma is read).

## Why pounds?
- `products.weight` is pounds (the product screen converts typed kilograms, `product-screen-units-and-commission.md`). Both readers stored the shop's figure as written: a 1 kg product became 1 lb (0.45 kg), 250 g in a gram shop 250 lb, so weight-based shipping and carrier labels were wrong. Tests: `test_import_woocommerce_weight_units`, `test_import_weight_pounds`, `test_import_shopify_weight`.
- Prices through `pg_products_import_price_cents()`: a sale price is the price and a higher regular price the compare-at price; a cell that is not an amount fails the product, or leaves that variation out with a note. A variation without its own price takes the parent's.
- Stock: a `Stock` figure tracks it (negative is 0); empty with `In stock?` 0 tracks 0; otherwise untracked.
- Categories (`A > B, C`, `\,` a comma in a name) → browse groups found by name (short description or name) under their parent, else made with `pg_catalog_group_create()`, starting at the mapped `product_group` (else the top). A simple product is filed in each category's last group (none: the mapped group).

## How is a variable product written?
- Variations whose SKU another product holds are left out with a note, as is one with an empty ("any") value; one left becomes a simple product named as its parent.
- Attributes and options found by name / label, made when missing; `pg_catalog_variant_set_create()` with own prices (`price_manual`), base the lowest, parent = the first category's group (else the mapped one), `price_source` import. A variation that was not published is switched off after.
- The framework lists the first product; the writer adds `import_items` rows for the others (their own keys).

## What does it not do?
- No `product.created` announcement and no SEO rescore, as the product CSV import: the nightly job scores them. Pictures only once `includes/import/media.php` is there (`pg_import_media_fetch()`, images only); without it the made item carries a note.

## How is it undone?
- `pg_import_unwrite_product()`: `pg_catalog_bin_product()` (Recycle Bin), then each of its groups that holds no live product or group is binned when it is a variant set, or a group the import's person made (`user`, `timestamp` ≥ the import's start), walking up to the parents. Groups have no `import_items` kind, so this is how a category made on the way goes.
