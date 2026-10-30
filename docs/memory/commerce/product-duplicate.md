---
name: product-duplicate
description: What a product copy (duplicate_product() and the bulk duplicate_products.php) keeps: later columns via pg_product_copy_extra_sql(), the gallery, a bundle's contents and refresh
---

## Who calls it?
- `duplicate_product()` in `includes/fn/ecommerce.php`: Edit Product > Duplicate and every product Duplicate Product Group copies.
- The bulk Duplicate Products screen (`duplicate_products.php`, `duplicate_products()`) has its own column list (it rewrites the name, short description and an assortment option) but keeps the same things below with the same helper and lines (after #3705, pinned in `tests/product_duplicate_bulk_test.php`). A change to what a copy keeps goes into both.

## What does the copy keep?
- The fixed column list, zones, form fields and options, submit-form fields, attributes, group links, a price history of its own.
- Columns later versions added, each only where the `SELECT *` row has it: `compare_at_price`, `hs_code`/`origin_country` (`pg_product_customs_copy_sql()`), and through `pg_product_copy_extra_sql()` (pure, tested in `tests/product_duplicate_test.php`) `ai_fields` (`pg_ai_fields_copy()`, the texts copied unchanged keep their marks), `vat_exemption_code` (the ERP's zero-rated line code), `bundle`, `bundle_pricing` (anything but `sum` is `fixed`), `bundle_discount_bp` (outside 0–10000 is 0).
- The gallery: `products_images_xref` rows of the original (non-empty `file_name`) in read order; the table has no order column. `image_name` is the cover.
- A bundle: after the INSERT, `pg_bundle_items_save()` with `pg_bundle_items()` of the original, then `pg_bundle_refresh()` derives the copy's stock and price, behind `pg_bundles_ready()`. `$is_bundle` is read before the zone loop reuses `$row`.

## What was wrong before? (PR #3705, bulk screen after it)
- The copy showed only the cover picture, lost the AI marks and the VAT exemption code (the ERP default code was used), and a copied bundle became a plain product holding the bundle's cached stock as its own: it sold stock that did not exist and took none from its components.
