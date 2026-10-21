---
name: order-completion-stock-and-spots
description: What a completed order does to stock and event spots (submit_order.php, loop after the order is written): the decrement, when out_of_stock is set and warned once, the first reservation row of an event
---

## Where is it?
- `submit_order.php`, the `foreach ($products as $product)` loop after the order is saved (stock, reservation spots, contact groups, memberships). `$products` holds one row per order item, so a product sent to two recipients comes twice.

## How does the stock go down?
- Only for `inventory = 1` and a stock that was not already 0 when the cart was read: `inventory_quantity - '<quantity>'`. The column is `int unsigned` and the session `sql_mode` is `NO_ENGINE_SUBSTITUTION` (not strict), so selling through the stock leaves 0, not a negative number or an error.

## When is the product marked out of stock?
- After each row's decrement, by one conditional update: `out_of_stock = '1'` and `out_of_stock_timestamp` where `inventory_quantity <= 0 AND out_of_stock != '1'`. The `out_stock` bell warning is raised only when that update changed a row, so once per product per order.
- Before PR #1082 (issue #734) it compared the row's quantity with the stock read before the loop (`$quantity == $inventory_quantity`): stock 5 sold as 2 + 3 to two recipients, or 3 sold from a stock of 2, reached 0 with no flag and no warning.
- Other writers follow the same "stock left <= 0" rule: marketplace import (`mp_order_take_stock()`), the API inventory endpoints, ERP stock, the product builder (derived on save, reset to 0 on restock).

## How are event spots counted?
- Only with `CALENDARS`, a reservation item and `limit_reservations = 1`. The instance's row in `remaining_reservation_spots` is decremented by the quantity; when the event has no row yet, the first order creates it as `number_of_initial_spots - quantity`.
- Before PR #1082 (issue #734) the first row was written as initial − 1, so a first booking of 3 seats left 9 of 10 and the event oversold by quantity − 1.
