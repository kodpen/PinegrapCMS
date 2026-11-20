---
name: counter-sale-cart-session
description: Counter sale (add_order.php): own session cart, a local order; what completion writes; quantity 0 to 9999; the stock limit counts the default location; a deleted product's line name
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

## Which stock does the till count with stock locations?
- The units at the default location, where completion takes the sale (a till has no shipping method): `local_sale_stock_error()` and the line's "In stock" read `local_sale_product_stock()` (`pg_stock_quantity_at()`), never the total of every location (decision #5042). Without locations, the total. Before PR #10848 the cart allowed the total and completion took only the default's units, leaving the stock too high (#10815). Test: `tests/local_sale_location_stock_test.php`.

## What does a line show once its product is deleted?
- The code the order line was written with: `local_sale_lines()` and `local_sale_set_qty()` read `order_items.product_name` and `local_sale_product_label()` falls back to it (short description, product code, then that). Before PR #10384 the row, its Remove and Quantity labels and the removal message had no name. Test: `tests/local_sale_cart_test.php`.

Related: docs/memory/commerce/cart-order-creation.md, docs/memory/commerce/counter-sale-keyboard-focus.md, docs/memory/commerce/counter-sale-site-line-stages.md (the lines' stages, gift cards refused), docs/memory/commerce/counter-sale-tax-switched-off.md
