---
name: checkout-requested-arrival-date
description: Requested arrival date at checkout: which one a visitor may post, how the designed checkout refills and bounds the custom date box, where an unusable date is reported
module: widgets_express_order, shipping
---

## Which arrival date may a visitor post?
- Only one the list offers: enabled, `start_date` today or earlier, `end_date` + `ECOMMERCE_END_OF_DAY_TIME` still ahead. `pg_arrival_dates_offered_sql($end_of_day_time)` (`includes/fn/shipping.php`) builds that condition; a setting that is not `H:i` / `H:i:s` reads as `23:59:59`.
- `shipping_address_and_arrival.php` (classic page and Shipping Address widget) reads the posted id through it; one outside the window (disabled, not started, ended, deleted) is refused with "The selected arrival date is not available. Please select another one." and the recipient keeps no arrival date id. Before, any posted id was saved with its code and date. Express order checks the id against its own active list.

## Where does the date box get its value when the checkout reopens?
- `_eo_custom_arrival_value($posted, $r, $aid)`: the visitor's last posted value, else `ship_tos.arrival_date` when the row's `arrival_date_id` is that choice and the date is real (`_eo_real_date()`). `ship_tos` has no `custom_arrival_date_<id>` column; reading one left the box empty after a reload and the server's method lookup ran without a date (#4535, #4568).
- The same value goes to `get_shipping_methods()` as ISO: `validate_date()` and `prepare_form_data_for_input()` accept ISO.
- `_eo_custom_arrival_bounds($max, $today)` prints the box's `min` (today) and its `max` only for a real `custom_maximum_arrival_date` (never `0000-00-00`).
- `_eo_custom_arrival_input()` draws the box, named (`aria-label`) after its arrival choice. A hidden box (another arrival choice is picked) is `disabled`, on the server render and when the script switches choices: its date is not posted, and a day outside its bounds would block the purchase submit with no visible message (#4811).

## What happens when the date cannot be used?
- `get_shipping_methods()` answers `code => 'arrival_date'` with a translated message for every date problem of a custom choice: an invalid date, a day before today (`pg_arrival_date_passed()`, `includes/fn/shipping.php`), and a date no method can meet. The checkout (server render and its script) shows that message as plain status text, not inside `_eo_no_shipping_notice_html()`: the address is not the problem. The last two went into the "We cannot ship to this address" notice before #4811.

## Why did each request log two warnings?
- `get_delivery_date()` read `only_from_carrier` and `formatted` without a default; the method lookup's call passes neither. Both read `?? false` now.
