---
name: order-completion-lines
description: The per-line stage functions of the order completion (#2713, #2740): stock, reservations, membership, folder access, gift cards issued; what each takes and writes
module: order_completion
---

## Where are they called?
- In `submit_order()`'s `foreach ($products as $product)` loop after the order is complete, once per order line, where the code stood (`tests/order_completion_test.php` pins the order). The plan's last A2 step makes `pg_order_complete()` call them (`plans/order-completion-function.md`).
- Fed by the line (an order item joined to its product) and ids the caller passes, never the session.

## Which functions? (#2713)
- `pg_order_complete_line_stock($line, &$stock_left, $actor)`, `_line_reservation($line, $contact_id)`, `_line_membership($line, $contact_id, $reference_code)` (true → the caller adds the membership contact group), `_line_folder_access($line, $user_id)`. Pure: `pg_order_extended_until()`, `pg_order_folder_access_extends()`.
- Stock in detail: `commerce/order-completion-stock-and-spots.md`.

## How are gift cards issued? (#2740)
- `pg_order_complete_line_gift_cards($line, $order_id, $created_user_id, $actor)` (#2740): one card per unit (at most 100) worth the line's price, from that unit's `order_item_gift_cards` row, and its `gift_card_ordered` e-mail campaign. The caller passes `USER_ID` and the user name; a request without a visitor passes ''. Pure: `pg_order_gift_card_count()`, `pg_order_gift_card_expiration()` (validity days from the delivery date, else from today), `pg_order_gift_card_send_time()` (noon of the delivery date, else at once).
- Before #2740 the block stood inline in the loop and read the order id, `USER_ID` and the user name itself.

Related: commerce/order-completion.md, commerce/order-completion-stock-and-spots.md, commerce/order-cancel-gift-cards.md
