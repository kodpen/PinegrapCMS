---
name: n11-warehouse
description: Products n11 ships from its own warehouse (n11depom): read into the catalogue with sender=ALL and marked, their stock never pushed, their orders not pulled
---

## What is an n11 warehouse product?
- A product the store keeps in n11's warehouse (n11depom). n11 ships it and keeps its stock; the seller may change only its price (developer.n11.com, n11depom page).
- The product query (`/ms/product-query`) and the order list (`shipmentPackages`) take `sender`: `SELLER` (the default, which lists no warehouse records), `N11` or `ALL`. Each product and order line carries its own `sender`.

## How does the catalogue read them? (#2228)
- `mp_n11_remote_products()` asks `sender=ALL`. A product whose `sender` is `N11` gets `N11_Warehouse` in its status (`marketplace_remote_products.remote_status`), shown on the marketplace products screen as "Shipped from the n11 warehouse".
- Why: the owner wanted them on the matching screen (2026-10-27). Before, the default sender left them out, so a store's warehouse products could not be matched.

## What is pushed for them?
- `mp_n11_push_stock_price()` asks the account's catalogue which codes are warehouse products (`mp_n11_warehouse_codes()`: exact code, status token) and leaves their quantity out (`mp_n11_stock_price_skus()`). Their price is still sent when the account sends prices.
- On an account that sends stock alone such a product has nothing to send. When nothing else is left, the push answers ok without a task and the queue rows close as done, not failed.
- A catalogue never read (or read before #2228) marks nothing, and every quantity is sent as before.

## Are their orders imported?
- No. `mp_n11_pull_orders()` asks `shipmentPackages` without `sender`, so warehouse packages are not listed. n11 ships them from its own stock; importing them would take the shop's stock down for goods it does not hold. This was not decided by the owner: ask before changing it.
