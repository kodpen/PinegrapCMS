---
name: order-completion-lines
description: The per-line stage functions of the order completion (#2713, #2740, #2766): stock, reservations, membership, folder access, gift cards, variable fields, product forms, submitted form update
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

## What do a line's form, comment and page read? (#2766)
- `pg_order_line_variable_fields($order, $quantity)` (pure): the variables (`^^billing_first_name^^`, `^^total^^` in major units, `^^order_number^^`, `^^order_date_and_time^^`, `^^quantity^^`, plural suffixes) from the order row's values; `submit_order()` builds `$order_variables` once before the loop.
- `pg_order_line_product_form_fields($order_item_id)`: the line's product form answers per unit (`pg_order_product_form_fields()`, pure: a check box group joined by commas). `pg_order_product_form_number()` (pure): the unit's own form, else the first unit's, 0 for none. `pg_order_line_fill()` fills a text with both.
- `pg_order_complete_line_form_update($line, $variable_fields, $product_form, $user_id, $pretty_urls)`: the oldest submitted form of the product's form page whose "where" field matches gets the product's update fields (`^^quantity^^` adds up); returns its id or 0. `submit_order()` creates a form when it returns 0 and creation is on.
- When neither updates nor creates a form, `$submitted_form_id` is now 0 for the unit; before, it kept the id of an earlier unit or line.
- Still in `submit_order()` (A2c3b): the submitted form creation (its `http_referer`, IP, tracking and affiliate code come from the visitor's request today), its submitter and administrator e-mails, the watcher.

Related: commerce/order-completion.md, commerce/order-completion-stock-and-spots.md, commerce/order-cancel-gift-cards.md
