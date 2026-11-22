---
name: stock-holds-placing
description: Where submit_order.php places the stock hold before the payment (SH3), the way back when units are not free, a payment's return, and the releases on a failed payment and at the cart
module: stock_holds
---

## Where is it placed? (SH3)
- `submit_order.php`, once every check has passed (the "if an error or notice exists" way back) and right before the payment switch: `pg_stock_hold_place($stock_hold_order_id)`, for every submit but a payment's return (`pg_payment_is_return_mode()`), with or without a charge.
- Not placed: the cart is checked again with `check_inventory()`, which now counts the other checkout's hold and cuts or removes the line with its notice; a lock not got (no notice from the check) adds "We're sorry, the availability of an item in your order could not be confirmed…". Logged, nothing charged, back to the checkout page.
- A payment's return (3-D Secure, a service's page, PayPal) is not held again: the payment may be taken. One that outlived its 15 minutes completes as before and, the stock gone, raises the `oversold` bell (`order-oversold-warning.md`).

## When does the visitor's own way release it?
- `submit_order.php`: every way back to the checkout page after the placing (a refused card in the request or on the 3-D Secure return, a gateway error, a method not available) releases first, and so does the "error or notice" way back before it (a hold left by an earlier submit).
- The return to the cart: `get_shopping_cart()` and `get_express_order()` release on render; the next submit places again.
- A payment a service reports failed by its own message keeps its rows until they run out (15 minutes).
- Completion (`pg_order_complete_lines()` after the takes) and the rest: `stock-holds-record.md`. Pins: `tests/stock_holds_test.php` (`test_stock_holds_submit_*`, `_completion_releases_with_take`, `_cart_return_releases`).
