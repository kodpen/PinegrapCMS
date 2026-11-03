---
name: store-credit-checkout
description: Store credit at checkout (PR #6234): open cards applied as applied_gift_cards rows before the totals, the "Use my store credit" box and per-order opt-out, the name on receipts
module: store_credit
---

## How does credit reach an order?
- Where the checkout totals are read (`get_express_order()`, `get_order_preview()`, the designer express order widget in `widgets_cart.php`), `pg_credit_checkout_sync($order_id)` runs first, so the total shown is the total charged. It adds every open card of `pg_credit_cards()` (oldest expiry first) as an `applied_gift_cards` row with `old_balance` = balance, refreshes a row whose balance moved and removes one whose card is no longer open. No amount per row: `submit_order()` takes `min(total, sum of old_balance)` and `pg_order_complete_gift_cards()` redeems by row id. Pure decision: `pg_credit_checkout_plan()`.
- Only `pg_credit_checkout_customer()`: gift cards on, columns there, a signed-in customer (not a guest, not a ghost order), at least one line that is not a gift card. Otherwise sync does nothing; `submit_order()` already drops credit someone else may not spend.

## Can a card count twice?
- No. Two views of one order (two tabs, `?r=` retrieve) could both insert the same card. `pg_credit_checkout_sync()` holds `GET_LOCK('pg_credit_checkout_<order>', 5)` around its read and writes (busy: writes nothing), and `submit_order()` deletes repeat rows before it sums balances (`pg_gift_card_applied_repeats()`: same `gift_card_id`, or same code for Givex), so a card is counted once also for the old typed-code race. Finding held in review of #6234.

## How does the customer say no?
- Built-in express order and order preview (`pg_credit_checkout_box_html()`) and both custom layout templates (`$store_credit`: show, checked, balance) draw "Use my store credit (<balance>)" above the gift card code, ticked by default, with a hidden `store_credit_shown=1` and an Update button the checkbox presses (`formnovalidate`).
- `express_order.php` and `order_preview.php` read the post with `pg_credit_checkout_take_post()`: a changed choice is stored and the rows synced, and the purchase is held back (page shown again), so nobody is charged a total they did not see.
- The choice lives in `$_SESSION['ecommerce']['store_credit_off']` (the order id unticked); a new order starts ticked. No column.
- Removing a credit row with the gift card list's X (`remove_gift_card_from_order.php`) counts as unticking (`pg_credit_checkout_removed()`), else the next view applies it again. The designer express order widget has no checkbox yet: credit is applied and the X takes it off.

## How is it named?
- `pg_credit_applied_name()`: "Store credit" for a row whose card has a `user_id`, the masked code otherwise; used by the built-in and custom layout checkout lists, the designer gift card list (`credit` flag in `pg_sw_list_checkout_gift_cards()`), the HTML and plain-text receipts. The panel order screen keeps the masked code (staff look cards up by it).

## What happens on cancel?
- Nothing new: credit is a gift card, so a cancelled order follows `order-cancel-gift-cards.md` (REFUND ACTION REQUIRED line; staff put the amount back).
