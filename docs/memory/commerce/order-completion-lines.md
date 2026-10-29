---
name: order-completion-lines
description: Per-line stage functions of the order completion (#2713, #2740, #2766, A2c3b): stock, reservations, membership, folder access, gift cards, product forms, submitted forms, watcher
module: order_completion
---

## Where are they called?
- In `submit_order()`'s `foreach ($products as $product)` loop after the order is complete, once per order line, where the code stood (`tests/order_completion_test.php` pins the order). The plan's last A2 step makes `pg_order_complete()` call them (`plans/order-completion-function.md`).
- Fed by the line (an order item joined to its product) and ids the caller passes, never the session.

## Which functions? (#2713)
- `pg_order_complete_line_stock($line, &$stock_left, $actor)`, `_line_reservation($line, $contact_id)`, `_line_membership($line, $contact_id, $reference_code)` (true → the caller adds the membership contact group), `_line_folder_access($line, $user_id)`. Pure: `pg_order_extended_until()`, `pg_order_folder_access_extends()`.

## How are gift cards issued? (#2740)
- `pg_order_complete_line_gift_cards($line, $order_id, $created_user_id, $actor)` (#2740): one card per unit (at most 100) worth the line's price, from that unit's `order_item_gift_cards` row, and its `gift_card_ordered` e-mail campaign. The caller passes `USER_ID` and the user name; a request without a visitor passes ''. Pure: `pg_order_gift_card_count()`, `pg_order_gift_card_expiration()` (validity days from the delivery date, else from today), `pg_order_gift_card_send_time()` (noon of the delivery date, else at once).

## What do a line's form, comment and page read? (#2766)
- `pg_order_line_variable_fields($order, $quantity)` (pure): the variables (`^^billing_first_name^^`, `^^total^^` in major units, `^^order_number^^`, `^^order_date_and_time^^`, `^^quantity^^`, plural suffixes) from the order row's values; `submit_order()` builds `$order_variables` once before the loop.
- `pg_order_line_product_form_fields($order_item_id)`: the line's product form answers per unit (`pg_order_product_form_fields()`, pure: a check box group joined by commas). `pg_order_product_form_number()` (pure): the unit's own form, else the first unit's, 0 for none. `pg_order_line_fill()` fills a text with both.
- `pg_order_complete_line_form_update($line, $variable_fields, $product_form, $user_id, $pretty_urls)`: the oldest submitted form of the product's form page whose "where" field matches gets the product's update fields (`^^quantity^^` adds up); returns its id or 0. `submit_order()` creates a form when it returns 0 and creation is on.
- When neither updates nor creates a form, `$submitted_form_id` is now 0 for the unit; before, it kept the id of an earlier unit or line.

## How is a line's submitted form created? (A2c3b)
- `pg_order_line_watcher($line)`: the user `order_items.add_watcher` names (id, email_address), once per line.
- `pg_order_complete_line_form_create($line, $order, $variable_fields, $product_form, $watcher, $pretty_urls)`, `$order` = id, user_id, contact_id, billing_email_address: when the update found no form; the form and its create fields, the submitter e-mail (buyer and watcher, a copy each, signed links in the buyer's only), the administrator e-mail (`pg_order_form_admin_addresses()`), the `custom_form_submitted` campaigns. Returns the id, 0 for no form page.
- Origin from the order row (`pg_order_form_origin()`): `tracking_code`, `affiliate_code`, `http_referer`, `ip_address` (the INET_ATON number, written as is; `pg_order_form_insert_query()`, pure). Before, the request's; the completion writes the same into the order just before, except a tracking code the visit lost (the order keeps the cart's).
- `pg_order_complete_line_watcher($line, $watcher, $submitted_form_id)`: after each unit, the watcher watches the unit's form on the add comment page, once.
- `tests/form_mail_links_test.php` pins its e-mail path.
Related: commerce/order-completion.md, commerce/order-completion-line-comments.md, commerce/order-completion-stock-and-spots.md, commerce/order-cancel-gift-cards.md
