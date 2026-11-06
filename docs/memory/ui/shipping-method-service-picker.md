---
name: shipping-method-service-picker
description: The Service field of the shipping method form offers only carriers that are set up, keeps a stored service the picker does not offer, and is left out with no carrier
---

## What does the Service field offer?
- `pg_shipping_method_service_choices($ready, $stored)` (pure, `includes/forms/shipping_method_form.php`): the services of `pg_shipping_method_services()` grouped by carrier (USPS, UPS, FedEx), only for carriers set up (USPS: `USPS_USER_ID` or `pg_carrier_api_ready('usps')`; `UPS`; `FEDEX`).
- A stored service none of those offers (its carrier switched off later, or set by the API) comes back as `extra` and is printed as its own option "{name} (not set up)".
- `pg_shipping_method_service_field()` prints the select with the stored value `selected` on the server. It replaced `includes/templates/shipping_method_service.php` and an inline `$("#service").val()` script.

## Why?
- The script matched no option for a service of a carrier not printed, so the select posted nothing and a plain Save stored `service = ''` and `realtime_rate = 0` without a word.

## What happens with no carrier set up?
- With no carrier and no stored service the field is left out: a hidden `service` input posts `''`, as stored. Before, the screen showed a select holding only a blank option.

## Tests?
- `tests/shipping_method_form_test.php` (choices, field); `tests/api_store_shipping_methods_test.php` checks the picker offers the ENUM.
