---
name: api-bulk-inventory-quantity
description: How POST /products/inventory (batch) reads each item's quantity: the same whole-number rule and ±1000000 bound as the single-product endpoint, refused per item instead of rounded or clamped
---

## What does a batch item's quantity accept?
- A whole number from -1000000 to 1000000, as a JSON number or a numeric string (`5`, `"-3"`, `12.0`). The same bound the single-product route `inventory.write` declares in `includes/api/schema.php`, where the router enforces it.
- The batch item is not a route parameter, so `api_inventory_quantity()` in `includes/api/resources/inventory.php` reads it: the int, `null` when it is not a whole number (`"2.9"`, `"1e3"`, text, missing, a boolean, a list), `false` when it is one outside the bound. The bound is the constant `API_INVENTORY_QUANTITY_LIMIT`.
- `null` reports the item `invalid` with "quantity must be a whole number."; `false` with "quantity must be between -1000000 and 1000000."; the other items are still applied.

## What did it do before?
- The batch cast the value with `(int)`: `2.9` became 2, and `5000000000` was clamped to 4294967295 by the unsigned INT column, since the session's non-strict mode does not refuse it. The single endpoint already answered 422 for both. Issue #818, fixed in PR #904.

## What guards it?
- `tests/api_inventory_test.php`: whole numbers, fractions and text, the bound itself and one past it, and that the constant matches the `inventory.write` route's `min`/`max`, so the two endpoints cannot drift apart.
