---
name: product-duplicate
description: What duplicate_product() (Edit Product > Duplicate, Duplicate Product Group) copies: the columns added later through pg_product_copy_extra_sql(), the gallery, a bundle's contents and refresh
---

## Who calls it?
- `duplicate_product()` in `includes/fn/ecommerce.php`: Edit Product > Duplicate and every product Duplicate Product Group copies. The bulk copy `duplicate_products.php` has its own code.

## What does the copy keep?
- The fixed column list, zones, form fields and options, submit-form fields, attributes, group links, a price history of its own.
- Columns later versions added, each only where the `SELECT *` row has it: `compare_at_price`, `hs_code`/`origin_country` (`pg_product_customs_copy_sql()`), and through `pg_product_copy_extra_sql()` (pure, tested in `tests/product_duplicate_test.php`) `ai_fields` (`pg_ai_fields_copy()`, the texts copied unchanged keep their marks), `vat_exemption_code` (the ERP's zero-rated line code), `bundle`, `bundle_pricing` (anything but `sum` is `fixed`), `bundle_discount_bp` (outside 0–10000 is 0).
- The gallery: `products_images_xref` rows of the original (non-empty `file_name`) in read order; the table has no order column. `image_name` is the cover.
- A bundle: after the INSERT, `pg_bundle_items_save()` with `pg_bundle_items()` of the original, then `pg_bundle_refresh()` derives the copy's stock and price, behind `pg_bundles_ready()`. `$is_bundle` is read before the zone loop reuses `$row`.

## What was wrong before? (PR #3705)
- The copy showed only the cover picture, lost the AI marks and the VAT exemption code (the ERP default code was used), and a copied bundle became a plain product holding the bundle's cached stock as its own: it sold stock that did not exist and took none from its components.

## What is still open?
- `duplicate_products.php` copies `ai_fields` but not the gallery or a bundle's contents.
