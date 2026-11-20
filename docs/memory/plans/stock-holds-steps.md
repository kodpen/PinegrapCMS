---
name: stock-holds-steps
description: Plan stock-holds, steps SH1 to SH5: the table and its functions, the checkout's check, placing and releasing around the payment, what people see, close-out; which files other work holds
---

## Which steps are there?
- SH1 (M) first: the record and its functions; nothing calls them yet. Done: #10711.
  - Migration step in 2026.11.0: `stock_holds` as `workflow/decisions-made-schema-34.md` writes it, idempotent helpers, rerun twice on a dev database.
  - New `includes/fn/stock_holds.php` (module `stock_holds`): units free (pure), the rows an order needs (pure, bundles and locations), place under the lock, release, the sweep; the sweep's call in the general job; `delete_order.php` and the product delete take the rows along.
  - Tests, red first: units free with other orders' holds, expired rows, the order's own rows; a bundle's components as one pool; a location. Features: H1, H2, H5, the functions of H3, H7 and H8.
- SH2 (S) after SH1: the check reads the units free. `includes/fn/ecommerce.php` (`check_inventory()`), `includes/fn/bundles.php` (`pg_bundle_check_inventory()`). With no holds written yet nothing changes; a source pin test keeps the no-holds path's statements. Feature: H6. Done: #10759.
- SH3 (M) after SH2: placing and releasing. `submit_order.php` (place before the payment, H4's way back, release on a failed payment), `includes/fn/order_completion.php` and `includes/fn/bundles.php` (release with the take), the return to the cart.
  - Tried on dev with two browsers on the last unit, once with a card paid in the request and once through 3-D Secure; the PR says what was seen. Features: H3, H4, H7, H8.
- SH4 (S) after SH3: the notice and "Held in checkouts: n" on Edit Product (`edit_product.php`'s stock box). Feature: H9.
- SH5 (S) close-out: every H number checked against the code with its PR; `commerce/stock-holds.md` written; `commerce/order-completion-stock-and-spots.md` and `commerce/stock-locations-completion.md` updated; the plan's files deleted; the changelog `[NEW]` line named in its PR.

## Which steps run side by side?
- None: each waits for the one before it.
- `includes/fn/bundles.php`, `includes/fn/ecommerce.php`, `includes/fn/order_completion.php` and `submit_order.php` are entered by the fixes of #10683, #10684, #10666 and #10682 and by open plan steps: a step here starts only when no open PR holds its files.
- SH3 starts after the `oversold` warning of #10682 has landed: it relies on that flag for a payment that outlives its hold.

## What does each step's PR carry?
- `Decision: channel message 10457`; SH1 names the migration step and the table's columns in its body and asks the architect in the agent channel before it is marked ready.
