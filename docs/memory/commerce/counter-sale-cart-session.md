---
name: counter-sale-cart-session
description: Counter sale (add_order.php): cart in its own session key, born a local order; what completion writes; quantity 0 to 9999, lowering never refused for stock; a deleted product's line name
---

## Where does the counter cart live?
- `$_SESSION['ecommerce']['local_sale_order_id']`, apart from the site cart's `order_id` (#5294). Before, both used `order_id`: a site cart showed at the till and a scanned product ended in a web order.
- `initialize_order()` and `add_order_item()` still work on `order_id`; `local_sale_with_cart()` puts the site's id aside, runs them on the counter cart and gives it back. A cart it creates is set to `type = 'local'` at once, so an unfinished counter cart is never an online cart.

## What does completing a sale write?
- `order_date` = the moment of completion (#5286), not when the cart opened.
- The buyer's name: the picked contact's first/last name and company, else the picked account's title as `billing_company` (`local_sale_billing_name()`, #5299); a walk-in sale keeps none. The order list, its name filter and search find it.
- The cart's `myself` recipient is dropped (lines get `ship_to_id = 0`, the `ship_tos` row is deleted): the order screen draws no empty shipping card.
- `payment_method` stays empty (the payment is in the ERP receipts); the order list's payment chart counts these as "Counter sale", not "None".

## Which quantities does the till take?
- `local_sale_qty_input()`: a whole number from 0 (removes the line) to 9999. Empty, letters, a minus, `12,50`, `99999` are refused with "The quantity must be a whole number from 1 to 9999."; the box returns to its quantity (#5301). The "received" box reads an amount with a minus as nothing taken.
- Only a raise is held against the stock (`local_sale_set_qty()`): a line above what the shelf holds now (sold elsewhere since) is lowered with the minus button or a smaller figure; completing the sale still refuses it while it is above. Before PR #10384 the minus answered "Only N in stock; no more can be added." and kept the line.

## What does a line show once its product is deleted?
- The code the order line was written with: `local_sale_lines()` and `local_sale_set_qty()` read `order_items.product_name` and `local_sale_product_label()` falls back to it (short description, product code, then that). Before PR #10384 the row, its Remove and Quantity labels and the removal message had no name. Test: `tests/local_sale_cart_test.php`.

## What is still open?
- Issue #10376: the completion skips the site's per-line work (gift cards, memberships, folder access, event spots, reward points).
- Issue #10377: with tax switched off the counter still adds the zone's tax.

Related: docs/memory/commerce/cart-order-creation.md, docs/memory/commerce/counter-sale-keyboard-focus.md
