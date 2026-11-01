---
name: product-price-bound-and-export
description: Catalog Manager and Edit Selected refuse a price past PG_PRODUCT_PRICE_MAX instead of MySQL's silent cut; the Catalog Manager export leaves the Recycle Bin out (#4441, #4443, PR #4515)
---

## What do the bulk edits and the quick price box do with a price past the top?
- `products.price` is a signed INT of minor units; `PG_PRODUCT_PRICE_MAX` is 2147483647 (₺21.474.836,47) and `pg_product_price_too_large()` (`includes/fn/ecommerce.php`) is the check, as a float before any int cast. `pg_bulk_price_change()` (Edit Selected) refuses such a result with `'too_large' => true` and its own count message; the Catalog Manager bulk edit skips it (`price_too_large` in the answer, status `partial`); its quick price box answers "Price is too large." (#4441).
- Before, the session is not strict, so MySQL stored 2147483647, the screen said "price changed" and the price history recorded the cut value.

## Which products does the Catalog Manager export write?
- `export_products.php` builds its WHERE with `pg_products_export_where($ids, $group_ids, pg_catalog_bin_live_sql('products'))` (`export_products_f.php`): every scope ("all", picked products, picked groups with their subtree) leaves the Recycle Bin out, as Products › Export does.
- Symptom it fixed (#4443): binned products were written to the file, counted in the Site Log line and updated again by "Update existing products".

## Tests?
- `tests/catalog_manager_bugs_test.php` (helpers and source pins); `tests/bulk_price_change_test.php`. No browser run.
