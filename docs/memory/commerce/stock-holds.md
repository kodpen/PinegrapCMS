---
name: stock-holds
description: Stock holds in short: units held 15 minutes for an order while its payment runs; what binds it, what is left out, where the detail is
module: stock_holds
---

## Why are units held?
- Owner (2026-11-20, agent channel poll 172, message 10457): "Uyarı şimdi, adet ayırma plana girsin" (`workflow/decisions-made-product-86.md`). The stock was read at submit and taken after the payment; two orders of the last unit were both paid and one could not be filled (#10682). The `oversold` bell (`order-oversold-warning.md`) flags what still slips through.
- Refused: a lock across the payment (3-D Secure can take minutes; every checkout of the product would queue) and an automatic refund.

## What binds it?
- One table, `stock_holds` (`workflow/decisions-made-schema-34.md`); no column on `products`, `orders` or `order_items`. A hold is a claim, never a stock figure.
- Architect (2026-11-20): placed in `submit_order.php` right before the payment, for `PG_STOCK_HOLD_MINUTES` (15), no setting. Each product in id order under its own `GET_LOCK` (5 s) for the read and the write only, never across a payment.
- Released at completion (with the take), on a failed payment, on the return to the cart, with a deleted order or product, and by time; expired rows count for nothing and the daily job sweeps them.
- Only the checkout's own checks (`check_inventory()`, `pg_bundle_check_inventory()`) read holds. The catalogue, product page, marketplaces, the API and the ERP read the stock as before: a held unit is still in stock.
- Marketplace import, the counter sale, a subscription renewal and `POST /orders` place no hold and take at once; a hold never stops them.
- Never held: untracked products and products with back orders on. A bundle holds its components, one pool with the same product bought alone. With stock locations the hold is at the order's location.
- Nothing is refunded or cancelled by the software: a payment back after its hold ran out completes as before and, the stock gone, raises the `oversold` bell.

## Which file says the detail?
- The table, the functions, the checks, the releases, the sweep: `stock-holds-record.md`.
- Placing in `submit_order.php`, the way back, a payment's return, the cart's release: `stock-holds-placing.md`.
- The cart notice and "Held in checkouts: n" on Edit Product: `stock-holds-shown.md`.
- Completion's take and release: `order-completion-stock-and-spots.md`; the location: `stock-locations-completion.md`.

## Which feature landed where?
- H1 the row, H2 units free, H5 bundles, the functions of H3, H7, H8: #10711 (step 11.0.253). H6 the checks read units free: #10759.
- H3 placing, H4 the way back, H7 the releases, H8 a late payment: #11148. H9 what people see: #11166.
- Checked against the code at the close-out (2026-11-24): every caller named above exists; `tests/stock_holds_test.php` pins them.

## What is left out?
- A setting for the length, held units taken off what marketplaces are sent, a countdown for the visitor, holds for event places (their own counter), a hold from the cart page on. Each needs a new yes from the owner.
- Not tried in a browser: the cart notice (tests only), the two-browser race on dev with 3-D Secure.
