---
name: cart-widget-stock-and-save-later
description: Cart widget low-stock warning (define, tokens, exact condition), cart query stock columns, out_of_stock_message, and the opt-in Save for Later feature.
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

## How does Save for Later work? (opt-in, 2026.1.28)
- Config define `ECOMMERCE_SAVE_FOR_LATER` (bool, undefined/false = off). Upgrade `2026.1.28` adds `order_items` columns `saved_for_later TINYINT(1) DEFAULT 0`, `saved_at INT(10) UNSIGNED` and index `idx_saved_for_later`.
- `cart_action.php` endpoint works only when the define is on:
  - POST `submit_save_for_later=1` + `save_item_id=<int>`: moves the line to the saved list.
  - POST `submit_restore_saved=1` + `save_item_id=<int>`: back to the cart.
  - No CSRF (general cart_action pattern); ownership check `order_items.order_id === session_order_id`.
  - Defensive column probe: feature on but column missing = silent no-op.
- The active cart query filters `saved_for_later = 0`, so saved items do not show in the cart. There is NO widget/section rendering the saved list yet; a designer can build UI with the existing remove_url + custom POST pattern (full UI planned for a later cycle).
