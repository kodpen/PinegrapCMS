---
name: counter-sale-site-line-stages
description: A counter sale (Create Order, POST /orders) runs the site checkout's line stages for the buyer's account; rights follow pg_order_rights_user_id(); gift cards are refused at the counter (#10376)
---

## What does a counter sale do with each line?
- `pg_local_order_complete()` step 5 runs the site checkout's own `pg_order_complete_lines()` (PR for #10376): stock, an event's spots, a membership, folder access, reward points, a product's forms, comment and e-mailed page, its product_ordered campaigns and contact group. Then, as `pg_order_complete()` does: `pg_order_complete_private_access()`, `pg_order_complete_rewards()` (+ the membership group) and `pg_order_complete_contact_groups()`.
- The stock comes off through `pg_order_complete_line_stock()` with source `local_sale` (`stock_source` in the order array; the stage's `$source`, default `checkout`), so the counter's stock moves, flag, bell and `inventory.changed` are the checkout's.
- Not run (not line work): the receipt, order_completed and order_abandoned campaigns, commission, ship dates, visitor and experiment.
- Before, the counter took only the stock: a membership, folder access, points or a contact group bought at the till was never given.

## Whose account gets the rights?
- `orders.user_id` of a counter sale is the operator (`orders-table-columns.md`), so the rights go to the buyer: `pg_order_rights_user_id($order)` = the user whose `user_contact` is the order's `contact_id` (lowest user_id) for `type = 'local'`, else `orders.user_id`. A walk-in sale, or a contact with no account, gets no folder access or points; a membership needs a contact.
- The buyer contact's `email_address` stands for the billing address of the e-mailed page and the reward e-mail; nothing is e-mailed when there is none (both stages skip an empty address).
- Taking back follows the same account: `pg_order_folder_access_cancelled()`, `_restored()`, `_lines_refunded()`, `pg_credit_points_order_cancelled()`, `pg_credit_points_lines_refunded()`. Before, cancelling a counter sale took points and folder access back from the operator.
- `pg_order_folder_access_other_order_holds()` counts the account's site orders and the counter sales to its contact (`pg_order_rights_orders_sql()`).
- A counter sale completed before this change gave no rights; cancelling one now takes its lines' points and access back from the buyer all the same.

## Why is a gift card refused at the counter?
- A gift card is issued from the recipient form of the site's checkout (`order_item_gift_cards`), which the counter does not have: before, a gift card was sold and no card was issued.
- `local_sale_product_refusal($product)` gives "Gift cards cannot be sold at the counter: each card needs the recipient form of the site's checkout." The scan and a pick from the search (`local_sale_add_item()`), the search list (shown in place of the stock, the row disabled), `pg_local_order_line_errors()` (a gift card line a cart already held) and `POST /orders` ("Product {id}: …", before any line is written) refuse with it.
- Selling gift cards at the counter is not built.

Tests: `tests/local_order_test.php`, `tests/local_sale_cart_test.php`. Related: docs/memory/commerce/counter-sale-tax-switched-off.md, docs/memory/erp/erp-counter-sale-completion.md
