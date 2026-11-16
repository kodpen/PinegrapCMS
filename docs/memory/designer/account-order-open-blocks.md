---
name: account-order-open-blocks
description: My Account blocks opened (CB6e1): order history on account.orders, bookings on two booking lists and their flags, the sections built from the same rows; subscriptions, store credit in CB6e2
module: widgets_account
---

## What did CB6e1 open?
- Step CB6e of `plans/designer-closed-blocks-steps.md`, cut by size. The census: [[canvas-closed-blocks-widgets]]; the shop steps before it: [[checkout-open-blocks]], [[checkout-open-recipients]].
- Tree: My Account's `order_history` on the existing `account.orders` list ([[widget-lists-order]]) and `bookings` on `account.upcoming_bookings` and `account.past_bookings` (new, key `account_id`).
- CB6e2: `subscriptions` and `store_credit` (both carry action forms with the form token).
- Order view: the return actions already open as the return moves part (W16, [[widget-default-parts-order-forms]]); `__return_reason_options_html` is a select's option list, kept by the census rule.

## Where do the rows come from?
- Order history: `pg_sw_list_account_rows()`; the section is `pg_acct_order_history_html($rows, $linked, $totals)`, byte for byte development's (the totals printed by `prepare_amount()`, the rows carry them decoded).
- Bookings: `pg_booking_customer_view_rows($rows, $now)` (`includes/fn/booking_manage.php`) splits them, with each one's status words, badge tone (`warning`, `success`, `secondary`), one details line (date, time, staff, deposit) and an upcoming one's manage page, button words and .ics address. `pg_booking_customer_view_html()` prints the section from it (`pg_booking_customer_section_html()` its wrapper); `pg_sw_list_account_booking_rows()` files the list rows.
- `_pg_my_account_extras()` keeps the view in `bookings` and sets `has_upcoming_bookings`, `has_past_bookings`; the widget publishes the booking rows with the orders.

## How does a page get the trees?
- As the order preview's: "Convert to design elements" on the section's panel (`pg_acct_part($section)`, twin `_sdAccountPart()`); the my account starter places both open; a saved page keeps its sections.
- The order history row is a link while the site has a view order page (`__order_url` neq `#`), else a plain item, as the section prints it. The badge's colour is a condition on `__booking_tone`: a class takes no value.
- Opened, the order row's number, date and badge are separate inline boxes: one space falls between them beside the date's `ms-2` margin, where the section's spans touched. The architect accepted it (channel 7029); it could not be made equal.

## What does the canvas draw?
- An unopened section: `_sdAccountOrderHistoryHtml()` and `_sdAccountBookingsHtml()` with the sample order and a cancelled one, two upcoming bookings and a past one.

## How was it checked?
- `tests/account_closed_blocks_test.php`: development's builders against the new ones, the opened parts through their lists against the sections' words and classes, the rows, the twins, the wiring.
