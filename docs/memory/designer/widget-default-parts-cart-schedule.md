---
name: widget-default-parts-cart-schedule
description: The cart's payment schedule part with controls (W16): the cart.item_schedule list, the gateway's rules on the controls, the required control that comes back, the old token
---

## What does the part draw?
- `pg_sw_part_cart_item_schedule()` (editor `_sdPartCartItemSchedule()`): a `cart.item_schedule` list of `fieldset.pg-cart-recurring-set` (`data-pg-recurring-item="{{__schedule_item}}"`), its legend "Payment Schedule", then three `div.mb-3.pg-cart-recurring-field`: a label (words, a `span.text-danger` star) and the control. Frequency is a `select`; the number of payments' star shows by `__schedule_payments_required`, its help line (`__schedule_payments_help`, the gateway's range) when not empty; the start date's box by `__schedule_start_shown`.
- No wrapper: a line whose schedule is fixed has no row, so nothing prints.

## Who owns a control?
- Owner (2026-10-27): every part is designable; the system owns what a control sends and whether it is required ([[widget-designability-decisions-2]]).
- A `semantic` select (frequency) or input bound `_bindings.value` = `schedule_period` / `schedule_payments` / `schedule_start` (`pg_sw_cart_schedule_control_specs()`, editor `SW_CART_SCHEDULE_CONTROLS`): `pg_sw_cart_schedule_control()` sets id and name (`recurring_payment_period_^^__schedule_item^^` …, the names cart_action.php reads), value, type, the cart form and the gateway's rules from `pg_cart_schedule_rules()`: required, min/max/step of the number, the date's earliest day; a start date ClearCommerce does not ask is `disabled`. The select's options are the system's (`__schedule_period_options_html`, raw). The error class is appended to the design's classes as `^^__schedule_<field>_invalid^^` ("is-invalid" or empty). A label beside it gets `for` (`pg_sw_cart_gift_card_label_for()`).
- Required: the frequency always, the number of payments on ClearCommerce and First Data, the start date where it is asked (`pg_sw_cart_schedule_required()`). A schedule without one gets it back at the end of its box (`pg_sw_cart_schedule_restore()`), on the server only.

## Where do the rows come from?
- `_pg_cart_recurring_schedule($row, $lf)` (`widgets_cart.php`) gives the values (the rejected attempt, then the stored schedule), names, error flags and options; the old block `_pg_render_cart_item_recurring_schedule()` is built from it too. The cart puts it on the item as `schedule`; `pg_sw_list_cart_schedule_rows()` files one row by item id. Not offered to the cart link.

## How does an old layout get it?
- A node bound to `__item_recurring_schedule_html` becomes the part's host; a row placing neither the token nor the list gets it at the end of the main loop once, after the gift card part (`pg_sw_default_loop_tail_parts()`). The old block is still appended only when the row places neither (a placeholder tree).
- Same words, classes, names, values and marker as the old block (`tests/widget_default_parts_cart_schedule_test.php`); ids carry the list's row suffix too.

## What is left?
- A browser round (editor load, a subscription in the cart, a rejected update) is owed.
