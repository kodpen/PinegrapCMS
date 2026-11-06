---
name: order-report-screen
description: view_order_report.php: default filters (no carts, no cancelled orders), first-group rule, cart figures and shipping, form state after a save error, order date filter parsing.
module: order_report
---

## Which orders does a new report count?
- `pg_order_report_default_filters()`: `order_status is not equal to incomplete` and `… cancelled`, so a new report sums placed sales only. Saved reports keep their own filters; nothing is added to them. The status filter lists Cancelled beside Incomplete / Complete / Exported (PR #5812, issue #5758).

## How does a row open a group?
- `pg_order_report_new_group($name, $previous)`: the previous-name trackers start at `null`, so the first row always opens its groups even when its value is empty (no billing state, no payment method). With `''` as the start an empty first value never built its bucket and every figure raised "Undefined array key" (PR #5812, #5759).

## How is a cart summed when a filter names a product or a recipient?
- The report's WHERE only picks the carts (`orders.id IN (SELECT … $where AND status = 'incomplete')`); each picked cart is summed from all its active lines (`order_items AS cart_items`). Filtering the lines themselves dropped the cart's other products, and joining `ship_tos` on `orders.id` counted each line once per recipient (#5762).

## Where does a cart's shipping come from?
- `SUM(ship_tos.shipping_cost)` over the cart's recipients that hold an active line, merged by `pg_order_report_carts()`. `order_items.shipping` is only a line's own rate per unit, rounded: `SUM(shipping * quantity)` left out the method and zone base rates and the shipping offer, so a cart's Shipping and Total differed from what checkout charged (#7248). Placed orders read `orders.shipping`.

## What survives a save that comes back with an error?
- "Submitted" is `field_in_session('last_filter_number')` (the hidden field is posted with every save; there is no `submit` field). Defaults and the stored filters are loaded only when it is not set, so the levels and filters typed before the error stay. An existing report's form opens again on an error, and the heading reads the stored name from `order_reports`, not the typed one (#5760).

## How is an order date filter read and stored?
- Through `pg_date_input_iso()` (21.10.2026 included), stored as `YYYY-MM-DD`; a value that does not read as a date is rejected with "Please enter a valid date.". A "day(s) ago" count must pass `pg_order_report_days_ago_valid()` (whole number, 0 to INT UNSIGNED). The screen prints `get_date_picker_format() . pg_fields_script()` so the `data-pg-date` box gets the picker (#5761).

## How are group names shown?
- Order status through `pg_order_log_status()`, payment method through `pg_payment_method_label()`; the summarize-by optgroup closings use distinct keys (`#optgroup-end-N`): a shared `''` key kept only one `</optgroup>` (#5763, #5764).

## How are the filter row controls named?
- `create_filter()` / `update_value_cell()` in `backend.src.js` (shared with the Visitor Report and form list view) give each control `aria-label` "<column heading>, Filter N" (`pg_filter_control_name()`); the delete button is named "Delete filter, Filter N" with its icon `aria-hidden`. The "Day(s) Ago" count is `type=number min=0 step=1` with a visible "day(s)" unit, and a stored 0 (today) shows again on edit. The words come through the `$translate` map in `includes/fn/output.php` (#5761, #5764).
