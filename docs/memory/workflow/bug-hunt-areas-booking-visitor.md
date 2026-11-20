---
name: bug-hunt-areas-booking-visitor
description: The bug hunt of bookings on the visitor's side (2026-11-20): the widget's endpoint, the slot engine, hold and confirm, the manage page, the deposit payment; what it fixed and what read clean
---

## What was the area?
- Given on 2026-11-20 (channel message 10888, to K2): bookings on the visitor's side; the panel screens were hunted in #10399. Files: `booking_slots.php`, `booking_manage.php`, `includes/fn/bookings.php`, `includes/fn/booking_manage.php`, `includes/fn/widgets_booking.php`, `includes/fn/booking_deposits.php`, `includes/fn/booking_deposit_pay.php`.
- Not touched: `booking_messages.php`, `includes/fn/events.php` (held for FD3), the meeting link files (`booking_links.php`, `book_with.php`).
- Decided, no fault: `commerce/bookings-decisions.md`, `commerce/booking-deposit-late-payment.md` (#10378).

## What did it fix? (PR #10799)
- A day ending at "24:00" on the day the clock skips midnight (Chile, Egypt, Lebanon) ended an hour late and offered a slot past the hours: `pg_booking_wall_time()` reads the next date's midnight.
- A Workspace holiday marked "Every year" closed its day only in its own year: `pg_booking_busy_between()` adds the later years' copies (`pg_booking_ws_yearly()`).
- My bookings listed holds given back (Back at the deposit's payment, a lapsed hold taken by another visitor) as cancelled bookings: `pg_booking_customer_rows()` and `pg_booking_customer_split()` leave out a cancelled row with `hold_until` > 0.

## What became an issue?
- #10804: Bookings › New booking (panel, `pg_booking_agenda_ws_busy()`) misses yearly holidays the same way.
- #10805: a booked service's buffer is kept or lost depending on which booking came first; needs the architect's word on which buffers count.

## What read clean?
- Two visitors on one start or overlapping starts: the hold, the confirm, the rebook after a lapsed hold and the deposit's keep all write under the staff lock or the unique key; the confirm's UPDATE names `status = 'held'` and `hold_until >= now`.
- DST spring and autumn days, the busy window of a day and a month (±1 day, so buffers past midnight are seen), lead, horizon, the day's own times on a move.
- The manage link: one 404 for a hold, wrong token and unknown id; a move renews the token; cancel and move only inside `cancel_hours`; the form token on every POST.
- The deposit: hold kept under the lock, order reused on retry, offline as a request, the return and the state answer.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workflow/bug-hunt-areas-4.md, docs/memory/commerce/bookings-model.md
