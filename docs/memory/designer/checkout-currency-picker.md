---
name: checkout-currency-picker
description: Currency picker of the Order Preview, Shipping Method and Order Form widgets (#3164): controls in the tree, a separate update_currency.php form after the widget, has_currency_choice
---

## What does it draw?
- The classic custom layouts' currency switcher (#3164). The starter part `_swCurrencyPicker()` (in each of the three JS starters, under the heading): a `div` shown by `has_currency_choice`, a label, a `select` named `currency_id` and a `button` named `currency_update` ("Update", the no-script submit). None of the three has a PHP default tree, so the JS starter is the only default.

## Why a separate form?
- The three widgets wrap their tree in their own form (order_preview.php, shipping_method.php, order_form.php), and forms cannot nest. `pg_sw_currency_picker()` points both controls at the form `pg-cur-w<widget id>` by their `form` attribute (the select also gets `onchange="this.form.submit()"`, which follows that attribute), and the renderer appends `pg_sw_currency_form_html()` after the widget's own form: action update_currency.php, `get_token_field()`, `send_to` (`get_request_uri()`). update_currency.php checks the token and stores the choice as before.
- Order Preview: liveform `prepare()` rebuilds the `currency_id` options from the values `get_order_preview()` set (same rows, same choice); the attributes stay.

## Where do the currencies come from?
- `pg_sw_currency_rows()` (`includes/fn/widgets_cart.php`): none while multi-currency is off, else the currencies with an exchange rate, base first. The cart's `_pg_cart_currency_selector()` uses it too: one query.

## When is it shown?
- `has_currency_choice` in `pg_op_flags()`, `pg_sm_flags()`, `pg_of_flags()` (last argument, the rows): more than one currency. Without it the controls are dropped and no form is printed.

## What could not be checked?
- No browser round yet: switching on each of the three pages, the Order Preview totals redrawn in the new currency.
