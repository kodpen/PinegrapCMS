---
name: cart-stock-below-minimum
description: Checkout stock check against a product's minimum quantity: a line the stock can only cover below the minimum is removed, not cut (bundle path); the plain check_inventory() path still cuts it
module: bundles
---

## What happens when the stock is below a product's minimum quantity?
- `submit_order.php` runs `check_quantity()` (raises a line to `products.minimum_quantity`) and then `check_inventory()` (cuts a line to the stock). Each change adds a notice, and a notice sends the visitor back to the cart.
- With stock 2 and minimum 3, the first pass raises the line to 3 and cuts it to 2; every later submit does the same, so the order never goes through and the visitor only sees the two notices again.

## What does the bundle path do now?
- `pg_bundle_cart_allowance()` (a cart holding a bundle, through `pg_bundle_check_inventory()`) takes an optional `minimum` per line. A line the stock would cut below it allows 0 and takes no units, so they go to the lines after it. The line is removed with the "not currently available" notice when the stock alone, before any other line, cannot cover the minimum, else with the "limited" one.
- The same rule as the cart quote link (`pg_cart_quote_plan()`: stock below the minimum is skipped as `stock`).
- Tests: `tests/bundles_test.php` (`test_bundle_cart_allowance_below_minimum`).

## What is still open?
- The plain path of `check_inventory()` (`includes/fn/ecommerce.php`, a cart with no bundle) still cuts below the minimum (its own comment calls it a bug). The fix is the same rule in its per-line loop; it was left out because other open PRs held `ecommerce.php`.
