---
name: order-completion-stock-and-spots
description: What a completed order or local sale does to stock and event spots: fresh stock read before the decrement, the out_of_stock flag and bell, what is announced, event spots
---

## Where is it?
- `submit_order.php`, the `foreach ($products as $product)` loop after the order is saved (stock, reservation spots, contact groups, memberships). `$products` holds one row per order item, so a product sent to two recipients comes twice.

## How does the stock go down?
- The first row of each product reads `inventory, inventory_quantity` again right before its decrement: `$products` was read before the payment call, so a parallel sale or a restock meanwhile is not counted from the old level. Later rows of the same product carry on from what the row above left (`$stock_left`).
- Only for `inventory = 1` and a stock that is not already 0: `inventory_quantity - '<quantity>'`. The column is `int unsigned` and the session `sql_mode` is `NO_ENGINE_SUBSTITUTION` (not strict), so selling through the stock leaves 0, not a negative number or an error.

## When is the product marked out of stock?
- After each row's decrement, by one conditional update: `out_of_stock = '1'` and `out_of_stock_timestamp` where `inventory_quantity <= 0 AND out_of_stock != '1'`. The `out_stock` bell warning is raised only when that update changed a row, so once per product per order.
- Before PR #1082 (issue #734) it compared the row's quantity with the stock read before the loop (`$quantity == $inventory_quantity`): stock 5 sold as 2 + 3 to two recipients, or 3 sold from a stock of 2, reached 0 with no flag and no warning.
- A local sale (`local_sale_complete()` step 5) uses the same statement and bell. Before issue #1163 it compared one line's quantity with the stock read before the loop, so a product on two lines (2 + 3 of 5) reached 0 with `out_of_stock = '0'` while `inventory.changed` said `out_of_stock: true`.
- Other writers follow the same "stock left <= 0" rule: marketplace import (`mp_order_take_stock()`), the API inventory endpoints, ERP stock, the product builder (derived on save, reset to 0 on restock).

## What does the sale tell the outside?
- After each row's decrement, `pg_product_stock_changed($product_id, $before, null, 'checkout')`: the product's `timestamp` moves (so `GET /products?updated_since=` lists it), `inventory.changed` is announced, `stock.low` once on the crossing, and the marketplace is queued. `$before` is the fresh read (first row) or `$stock_left` (later rows); the stock left is read back. A local sale does the same with source `local_sale`, also from a fresh read.
- Before issue #1163 `$before` was the stock read before payment: with a line at 5 and stock 6, two parallel checkouts both announced `stock.low` (6 -> 5, then stale 6 -> 4), and a restock to 10 during payment followed by a sale of 6 announced none.
- Before PR #1141 (issue #871) the checkout and the local sale only queued the marketplace. The helper and the other writers: `docs/memory/core/api-webhook-stock-events.md`.
- A barcode scan that leaves the stock unchanged (a decrease at 0) calls only `pg_marketplace_product_changed()`, no `inventory.changed` (issue #1163).

## How are event spots counted?
- Only with `CALENDARS`, a reservation item and `limit_reservations = 1`. The instance's row in `remaining_reservation_spots` is decremented by the quantity; when the event has no row yet, the first order creates it as `number_of_initial_spots - quantity`.
- Before PR #1082 (issue #734) the first row was written as initial − 1, so a first booking of 3 seats left 9 of 10 and the event oversold by quantity − 1.
