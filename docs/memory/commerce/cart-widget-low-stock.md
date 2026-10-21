---
name: cart-widget-low-stock
description: Cart widget low-stock warning (define, tokens, exact condition), cart query stock columns and out_of_stock_message.
---

## How does the low-stock warning work? (2026.1.28)
- Config define `ECOMMERCE_LOW_STOCK_THRESHOLD` (int; undefined = feature OFF). Operator enables it in `data/config.php`: `define('ECOMMERCE_LOW_STOCK_THRESHOLD', 5);`.
| Cart loop token | Returns |
|---|---|
| `__item_has_low_stock` | `'1'` (low) or `''` (normal/unlimited); usable as a visibility flag |
| `__item_stock_warning` | "Son 3 adet kaldı" when low, else `''` |
| `__item_inventory_quantity` | remaining stock (if inventory is tracked), else `''` |
- Shown only when `inventory=1 AND backorder=0 AND quantity > 0 AND quantity <= threshold`. No warning if stock is not tracked or backorder is allowed.
- Empty tokens are removed by the standard loop preg_replace cleanup, so a designer that did not place the token is unaffected.

## Which stock columns must the cart query SELECT?
- `inventory` / `inventory_quantity` / `backorder` must be in the cart query. They were never SELECTed, so the low-stock warning could never fire (found 2026.1.30).
- Per-line out-of-stock message via `out_of_stock_message` (passes through `_pg_rich_text_to_inline()`).

Save for Later (the other opt-in cart feature of 2026.1.28): docs/memory/commerce/cart-save-for-later.md
