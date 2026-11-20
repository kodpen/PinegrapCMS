---
name: cart-stock-below-minimum
description: Checkout stock check against a product's minimum quantity: a line the stock can only cover below the minimum is removed, not cut, in a cart with or without a bundle
module: bundles, ecommerce
---

## What happens when the stock is below a product's minimum quantity?
- `submit_order.php` runs `check_quantity()` (raises a line to `products.minimum_quantity`) and then `check_inventory()` (checks a line against the stock). Each change adds a notice, and a notice sends the visitor back to the cart.
- A line the stock would cut below its minimum allows 0 and is removed; its units go to the lines after it. Before, it was cut to the stock and raised back on the next submit, so the order bounced between two notices and never went through (#10683).
- The notice is "not currently available" when the stock alone, before any other line, cannot cover the line (or its minimum), else the "limited" one. Stock that covers the minimum still cuts the line to the stock with the "reduced" notice.

## Where is the rule?
- Cart with no bundle: `pg_cart_line_stock_allowance()` in `includes/fn/ecommerce.php`, used by the per-line loop of `check_inventory()`. Tests: `tests/cart_stock_below_minimum_test.php`. Fixes #10683.
- Cart holding a bundle: `pg_bundle_cart_allowance()` (through `pg_bundle_check_inventory()`) takes an optional `minimum` per line. Tests: `tests/bundles_test.php` (`test_bundle_cart_allowance_below_minimum`).
- The same rule as the cart quote link (`pg_cart_quote_plan()`: stock below the minimum is skipped as `stock`).
