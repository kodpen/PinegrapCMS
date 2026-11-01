---
name: checkout-requested-arrival-date
description: How the designed checkout refills a recipient's custom requested arrival date and keeps a bad date out of the "cannot ship here" notice
module: widgets_express_order
---

## Where does the date box get its value when the checkout reopens?
- `_eo_custom_arrival_value($posted, $r, $aid)`: the visitor's last posted value, else `ship_tos.arrival_date` when the row's `arrival_date_id` is that choice and the date is real (`_eo_real_date()`). `ship_tos` has no `custom_arrival_date_<id>` column; reading one left the box empty after a reload and the server's method lookup ran without a date (#4535, #4568).
- The same value goes to `get_shipping_methods()` as ISO: `validate_date()` and `prepare_form_data_for_input()` accept ISO.
- The box's `max` is printed only for a real `custom_maximum_arrival_date` (never `0000-00-00`).

## What happens when the date is missing or invalid?
- `get_shipping_methods()` answers `code => 'arrival_date'` with a translated message. The checkout (server render and its script) shows that message as plain status text, not inside `_eo_no_shipping_notice_html()`: the address is not the problem.

## Why did each request log two warnings?
- `get_delivery_date()` read `only_from_carrier` and `formatted` without a default; the method lookup's call passes neither. Both read `?? false` now.
