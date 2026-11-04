---
name: booking-manage
description: A customer's own booking (BK7): booking_manage.php from the mail's signed link, cancel and move within the policy window, .ics download, the my_account widget's bookings section
module: booking_manage
---

## How does the manage page find the booking?
- `booking_manage.php?b=<id>&t=<token>` (root, no account): `pg_booking_manage_find()` reads `pg_booking_message_row()` and checks `pg_booking_manage_token_valid()` (hash, until `end_at`); a hold, a wrong token and an unknown id are the same 404 card. Rate limited as `pg_booking_throttled()` read (GET) and hold (POST). `no-store`, `noindex`, `Referrer-Policy: no-referrer`.
- Opening it changes nothing; cancel and move are POSTs with the form token (`validate_token_field()`) and the link token. `&ics=1` sends `pg_booking_ics()` as an attachment. Drawn with `output_header_secure()` + `pg_secure_card()` like `confirm_subscription.php`.

## When may the customer cancel or move?
- `pg_booking_manage_rights()` (pure): a requested or confirmed booking, not started, until `start_at - cancel_hours` (Settings › Bookings); a move also needs `reschedule` on. Outside: "can no longer be changed online".
- A move keeps service, staff and status; it is offered only while the service is bookable and the staff member still gives it (`pg_booking_manage_engine()`, from `pg_booking_widget_data()`).

## How is a move chosen and written?
- No script: month links (`&m=`, this month to the horizon's, `pg_booking_manage_months()`), the free days (`pg_booking_manage_days()` → `pg_booking_month_days()`), then the day's times (`&d=`, `pg_booking_manage_times()` → `pg_booking_slots()`) as submit buttons. The visitor's policy applies (lead, horizon, step); the booking's own range is taken out of the busy times (`pg_booking_manage_busy()`: the first range equal to its times; bookings come first in `pg_booking_busy_between()`).
- The POST checks the start is still offered, then `pg_booking_manage_move()`: under the staff lock (`pg_booking_lock_name()`) the overlap check without the own row, one UPDATE of start, end, slot and the new link hash `WHERE id AND status AND start_at` (read values); 1062 is "just taken", 0 rows "changed meanwhile". Then `pg_booking_changed($id, 'rescheduled')` and a redirect to the new link (the token follows `end_at`).
- Cancel: `pg_booking_manage_cancel()`, one UPDATE (`status = 'cancelled', slot_at = NULL`) from the status read, then `pg_booking_changed($id, 'cancelled')`. The old link keeps opening the cancelled booking until its end.

## What does the account show?
- my_account section `bookings` (designer section option, starter heading bound to flag `has_bookings`): `pg_booking_customer_rows(USER_ID)` (`bookings.user_id`, latest 50, holds out), `pg_booking_customer_split()` upcoming (requested/confirmed not ended, soonest first) and past (latest first), `pg_booking_customer_section_html()` (Bootstrap 5.3.8). An upcoming one links to its manage page and its .ics; the staff name is the contact's only. Not tied to the shop.

Test: `tests/booking_manage_test.php`. Not run on a dev database or in a browser.

Related: docs/memory/commerce/bookings-model.md, docs/memory/commerce/booking-messages.md, docs/memory/designer/booking-widget.md
