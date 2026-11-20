---
name: stock-holds-features
description: Plan stock-holds, features H1 to H9: the hold record, units free, placing under a short lock, the checkout's check, release, expiry, bundles and locations, what the visitor and the shop see
---

## What is a hold?
- (H1) A row of `stock_holds`: the order, the product, the location (0 without locations), the units, when it runs out. One row per order, product and location; placing again replaces the order's rows.
- (H2) Units free for an order = the stock (at the location, with locations) less the unexpired holds of other orders, never below 0. A pure function; the order's own holds are not counted against it.

## When is it placed?
- (H3) In `submit_order.php`, after every check has passed and right before the payment is asked for: for each tracked product with back orders off, in id order, under `GET_LOCK(pg_stock_hold_lock_name(), 5)` (`pg_stock_hold_<database hash>_<product id>`; placing is `pg_stock_hold_place()`): read the units free, write the row, release the lock. A lock not got in time counts as "not enough".
- (H4) Not enough for any line: the order's holds are removed, nothing is charged, the cart is shown again with `check_inventory()`'s notice for that line (cut or removed by its rules, the minimum quantity rule of `commerce/cart-stock-below-minimum.md` included).
- (H5) A bundle line holds each tracked component for quantity per bundle times the line's quantity; a component also bought alone is one pool, as `pg_bundle_check_inventory()` counts.

## Who reads it?
- (H6) `check_inventory()` and `pg_bundle_check_inventory()` count the units free (H2) in place of the stock, on the cart, the express order and the submit. Nothing else reads a hold.

## When does it end?
- (H7) At completion: the stage that takes the stock deletes the order's holds in the same pass (`pg_order_complete_line_stock()`, `pg_bundle_take_stock()`); a failed or refused payment and the visitor's return to the cart delete them; a deleted order and a deleted product take their rows along.
- (H8) By time: 15 minutes after placing. An expired row counts for nothing from that second; the general job deletes rows a day old. A payment finished after the hold ran out completes as today; when the stock is gone it is flagged by the `oversold` bell (`workflow/decisions-made-product-86.md`).

## What do people see?
- (H9) The visitor: when another checkout holds the last units, the notice says so and that they may free up in a few minutes (translated, `php tools/tr_add.php`). The shop: Edit Product's stock box shows "Held in checkouts: n" while n is above 0. No other screen changes.
