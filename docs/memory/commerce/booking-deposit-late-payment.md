---
name: booking-deposit-late-payment
description: A booking deposit paid after its hold ran out (#10378): booked again under the staff lock while free, else an open request, deposit awaiting refund, honest widget text
module: booking_deposits
---

## What happens when a deposit is paid after the hold ran out?
- The visitor stayed on the bank's page past the hold; meanwhile another visitor may book the start (`pg_booking_clear_expired_hold()` cancels the held row, `slot_at = NULL`) or a booking of another length may overlap it.
- `pg_booking_deposit_mark_paid()` asks `pg_booking_deposit_outcome()`: `confirm` (a live hold, or a request paid on arrival), `rebook` (ran out, time still free: confirmed, `slot_at = start_at`, announced created + confirmed with its mail), `lost` (time gone: `requested`, `slot_at = NULL`), `record` (confirmed, no_show, done, or cancelled by someone: only `paid_at`).
- Architect (2026-11-19, channel message 9604): book the time again when free, under the same lock; else an honest text, a staff notice, an open request, the deposit "awaiting refund". No automatic refund (a new feature) and no new column.

## Which holds count as ran out?
- `pg_booking_deposit_lapsed()`: `held` past `hold_until`, or `cancelled` with `hold_until > 0`. Only the two hold cancels (`pg_booking_clear_expired_hold()`, `pg_booking_release_session_hold()`) keep `hold_until`; the agenda and API (`pg_booking_agenda_status_write()`), the manage page and an erasure (`_pg_privacy_erase_apply_bookings()`) write 0, so a booking someone cancelled is never booked again.

## How is double booking kept out?
- Check and write run under `pg_booking_lock_name($staff_id)`, the lock of `pg_booking_insert()` and `pg_booking_pay_keep_hold()`. Free = `pg_booking_deposit_time_free()`: the start not begun and `pg_booking_widget_still_offered()` with `lead_minutes` 0 (picked while it was offered).
- The UPDATE names the status it read. A `rebook` refused by the unique key (1062: a writer without the lock) becomes `lost`; no lock within 10 s is `lost` too.

## What do the customer and staff see for a lost time?
- Widget (`pg_booking_pay_done_text()`): "Your payment arrived after the time we kept for you ran out, and the time is no longer held for you. Our team will get in touch with you about your deposit." Also for a booking found cancelled; the site's own done text is not used there. It used to say "We have your request and will confirm it by e-mail."
- Staff: `pg_booking_created($id, 'requested')`, the path of every new request: booking.created for webhooks, Flows and Workspace watch, no customer mail. A Site Log line; the agenda shows it as Requested.
- `pg_booking_deposit_view()` state `refund`: a paid deposit on a `requested` booking (only `lost` makes one). "Deposit awaiting refund", red coin (`pg_booking_deposit_tone()`), a note on `edit_booking.php`; manage page and My bookings show the label. The refund is made by hand; confirming (the time free again) or cancelling ends it.

## Tested?
- `tests/booking_deposit_test.php` (outcomes, statements, events, lock order) and `tests/booking_deposit_pay_test.php` (refund state, badge, closing words). No database or real payment run.
