---
name: visitor-currency-zero-rate
description: init.php uses a visitor's chosen currency only while its exchange rate is above zero (pg_visitor_currency_row()); a 0 rate falls back to the base currency
module: currency_rates
---

## When is the visitor's chosen currency used?
- `init.php` reads the `currencies` row of `$_SESSION['ecommerce']['currency_id']` through `pg_visitor_currency_row()` (`includes/fn/currency_rates.php`). No row, or an `exchange_rate` that is not above zero, gives an empty array and the `VISITOR_CURRENCY_*` constants take the base currency.
- The pickers already list only `exchange_rate != '0'`, but `update_currency.php` stores any posted id, and a session keeps an id whose rate was set to 0 later (the old way to hide a currency).

## What was the symptom?
- With a 0 rate every price showed as 0.00 in that currency, and the donation paths of `shopping_cart.php`, `express_order.php` and `order_form.php` divide the typed amount by `VISITOR_CURRENCY_EXCHANGE_RATE`: `@` does not stop `DivisionByZeroError`, so the page died on PHP 8.

## Tested?
- `tests/visitor_currency_rate_test.php` (the helper and its use in `init.php`).
