---
name: api-bulk-price-bound
description: How POST /products/prices (batch) reads each item's price: whole minor units from 0 to 2147483647, the single-product routes' bound, refused per item instead of rounded or cut by the INT column
---

## What does a batch item's price accept?
- Whole minor units, as a JSON number or a numeric string (`1999`, `"1999"`), from 0 to `API_PRODUCT_PRICE_MAX` (2147483647, the top of the signed INT `products.price`). The same bound `products.create` and `products.update` declare for `price` in `includes/api/schema.php`, where the router enforces it.
- The batch item is not a route parameter, so `api_price_batch_price()` in `includes/api/resources/products.php` reads it: the int; `null` when it is not a whole number (decimal, text, boolean, list, missing); `false` when it is one outside the bound.
- `null` reports the item `invalid` with "price must be a whole number of minor units."; `false` with "price cannot be negative." below 0, and above the top with the router's own sentence, "price is above the largest accepted value." The other items are still applied; a dry run judges the same way.

## What did it do before?
- Only whole-number and `< 0` were checked. `{"items":[{"id":100,"price":3000000000}]}` was answered `"status":"ok","price":3000000000` (dry run too), and the non-strict session stored 2147483647 in `products.price` and a 2147483647 row in the price history (issue #750).

## What guards it?
- `tests/api_price_batch_test.php`: whole numbers and strings, decimals and text, the top itself and one past it, negatives, and that the constant matches both single routes' `min`/`max`. The batch inventory endpoint follows the same pattern (`api-bulk-inventory-quantity.md`).
