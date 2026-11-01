---
name: product-price-bound-and-export
description: Every price writer refuses more than PG_PRODUCT_PRICE_MAX (INT top) instead of MySQL's silent cut; the Catalog Manager product export leaves the Recycle Bin out (#4441, #4443, #4444, PR #4515)
---

## What do the bulk edits and the quick price box do with a price past the top?
- `pg_product_price_too_large()` (`includes/fn/ecommerce.php`) is the check. `pg_bulk_price_change()` (Edit Selected) refuses such a result with `'too_large' => true` and its own count message; the Catalog Manager bulk edit skips it (`price_too_large` in the answer, status `partial`); its quick price box answers "Price is too large." (#4441).
- Before, the session is not strict, so MySQL stored 2147483647, the screen said "price changed" and the price history recorded the cut value.

## How large a price does the product screen take?
- `products.price` and `extra_shipping_cost` are signed INTs of minor units: the top is `PG_PRODUCT_PRICE_MAX` (2147483647, ₺21.474.836,47).
- The Price, Compare-at Price and Extra Shipping Cost boxes (`product_builder.php`) have `maxlength="16"`: the masked top is 13 characters, and one more digit still fits so a larger amount reaches the server whole.
- `pg_pb_validate_product_post()` refuses a price or extra shipping cost above the top with "{field} is too large." (`pg_pb_price_input_too_large()`, a float check before any int cast); the compare-at price had its own check already.
- Symptom it fixed (#4444): with `maxlength="12"` the box took at most 9.999.999,99 and dropped the eighth digit without a word: 25000000 saved ₺2.500.000.

## Which products does the Catalog Manager export write?
- `export_products.php` builds its WHERE with `pg_products_export_where($ids, $group_ids, pg_catalog_bin_live_sql('products'))` (`export_products_f.php`): every scope ("all", picked products, picked groups with their subtree) leaves the Recycle Bin out, as Products › Export does.
- Symptom it fixed (#4443): binned products were written to the file, counted in the Site Log line and updated again by "Update existing products".

## Tests?
- `tests/catalog_manager_bugs_test.php` (helpers and source pins); `tests/bulk_price_change_test.php`. No browser run.
