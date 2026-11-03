---
name: counter-sale-cash-and-add-quantity
description: Counter sale (add_order.php): cash received short of the total is refused before the sale is claimed; the next product's quantity is a whole 1-9999 and a line never passes 9999 (#5772, #5774)
---

## Why is a short cash payment refused?
- The cash receipt is always written for the invoice's whole total (`local_sale_complete()`); `received` only works out the change. Received 2 on a total of 6 completed the sale, recorded 6 in the till and marked the invoice paid (#5772).
- Now `local_sale_cash_short($method, $received, $total)` (pure, kurus) gives the shortfall; `local_sale_complete()` refuses it before the order is claimed: "The amount received is X short of the total. Correct it or split the payment." The screen's `cashShort()` (`assets/js/add_order.js`) checks first and puts the cursor back in Received.
- An empty Received box is the whole total taken, as before. Only `cash` is checked.

## How is the next product's quantity read?
- The "×" box beside the product box is read by `qtyValue()` like a line's own box: a whole number 1 to 9999, else null; `qtyOk()` shows "The quantity must be a whole number from 1 to 9999." and focuses the box. An empty box is 1. Before, 1.5 became 1 and 10000 became 9999 silently (#5774).
- The server reads it with `local_sale_qty_input()` (0 refused here) instead of clamping, and `local_sale_add_qty_error($in_cart, $quantity)` refuses an add that takes the product's line past 9999 ("This line can hold at most 9999."): `add_order_item()` adds to the existing line, which the + button then refused.

## Tests?
- `tests/local_sale_qty_input_test.php`. Not run on a live instance.
