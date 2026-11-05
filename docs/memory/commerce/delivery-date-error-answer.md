---
name: delivery-date-error-answer
description: get_delivery_date() (shipping.php) always answers with a delivery_date key, '' on every error, because its seven callers read it without a check
---

## What does get_delivery_date() answer on an error?

`status` `error`, a `message` and `delivery_date` `''`. The ship date error
from `get_ship_date()` is passed on with `delivery_date` `''` added.

## Why?

The callers (`get_shipping_methods()`, `check_shipping_method()`,
`get_shipping_method.php` twice, `shipping_address_and_arrival.php`,
`shopping_cart.php`, `validate_order_item_for_arrival_date()` in
`includes/fn/forms.php`) read `$response['delivery_date']` and treat an empty
one as "no date". Before, an error answer had no such key: a US zip code
shorter than five letters on a USPS method, or a method with no ship days,
gave an "Undefined array key" warning on PHP 8 on the shipping screens (PR #6959).
