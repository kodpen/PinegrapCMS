---
name: stock-holds
description: Plan (owner 2026-11-20, agent channel poll 172): units are held for an order while its payment runs, so two orders cannot both take the last unit; SH1 to SH5
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 172, message 10457): "Uyarı şimdi, adet ayırma plana girsin" (`workflow/decisions-made-product-86.md`). The warning is a fix of its own (#10682); this plan is the cure.

## What is there today?
- `check_inventory()` (`includes/fn/ecommerce.php`; a cart with a bundle through `pg_bundle_check_inventory()`) reads the stock with no lock when the cart is shown and in `submit_order.php` before the payment; a line the stock cannot cover is cut or removed with a notice.
- The stock goes down at completion (`pg_order_complete_line_stock()`, `pg_bundle_take_stock()`), after the payment: in the same request, or minutes later when the bank's 3-D Secure page or the service's own page finishes it. The take stops at 0.
- Nothing holds the units between. Two orders of the last unit are both paid; one cannot be filled (#10682).

## What binds it?
- One new table, `stock_holds`, through a migration step of the open version (`workflow/decisions-made-schema-34.md`); no column on `products`, `orders` or `order_items`.
- A lock is held only around the read and the write of the hold rows, never across a payment (`decisions-made-product-86.md`).
- Nothing is refunded or cancelled by the software. A payment that comes back after its hold ran out completes as today and is flagged as sold beyond the stock.
- Every reader of the stock outside the checkout's own check stays: the catalogue, the product page, marketplaces, the API, the ERP. A held unit is still in stock.
- A site with stock locations holds at the cart's location (`commerce/stock-locations-completion.md`); a product with back orders on (`products.backorder = 1`) or untracked is never held.

## What did the architect decide? (2026-11-20)
- The hold is placed in `submit_order.php` right before the payment is asked for, and lasts 15 minutes (`PG_STOCK_HOLD_MINUTES`); no setting in this plan.
- Placing: the products of the order in id order, each under `GET_LOCK(pg_stock_hold_lock_name())` (`pg_stock_hold_<database hash>_<product id>`) for the read and the insert only: units free = stock less the unexpired holds of other orders. Not enough: no hold is kept, the cart is shown again with the notice `check_inventory()` gives.
- Released when the order completes (with the take), when the payment fails or the visitor comes back to the cart, and by time. An expired row counts for nothing and is swept by an existing daily job.
- Marketplace import, the counter sale, a subscription renewal and `POST /orders` place no hold: they take at once. Their takes are not stopped by a hold.
- A bundle holds its components, one pool with the same product bought alone, as its check counts.

## What is built?
- Features: `stock-holds-features.md` (H1 to H9). Steps: `stock-holds-steps.md`. Claim as `[plan:stock-holds] <step> — <topic>`.

## What is left out?
- A setting for the length, held units taken off what marketplaces are sent, a countdown shown to the visitor, holds for event places (their own counter), a hold from the cart page on. Each is a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): one cart notice and one line on Edit Product, seen on dev after SH4.
