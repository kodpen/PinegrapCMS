---
name: order-screen-offer-code-escape
description: The panel order screen (view_order.php) escapes the order's special offer code and reference code; an offer code is free text up to 50 characters set by any commerce user
---

## Why must the offer code be escaped on the order screen?
- An offer's code (`edit_offer_f.php`) is any text of up to 50 characters, written by any user with the commerce right (role 3 included). A customer who enters that code at checkout stores it in `orders.special_offer_code` (the cart, express order and the session code all check that the code matches an offer or key code first).
- `view_order.php` printed it raw in the totals card, so a code with markup ran in the session of every administrator who opened the order. It is written with `h()` now, as `print_order.php` already did; the reference code beside it too.

## How is it kept?
- `tests/view_order_offer_code_escape_test.php` checks both rows are escaped. Any other screen that prints an order's offer code escapes it (the CSV export guards it with `pg_csv_formula_guard()`).
