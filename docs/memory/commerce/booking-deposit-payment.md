---
name: booking-deposit-payment
description: The booking widget's deposit payment (BK9b): hold kept, payment form posted to booking_slots.php, return and retry, paid on arrival, paid shown on the screens and the API
module: booking_deposit_pay
---

## When does the widget ask for a payment?
- `pg_booking_confirm_request()` asks `pg_booking_widget_deposit($service)` (store on, columns there, `pg_booking_deposit_due()`). Then it does not book: `pg_booking_pay_keep_hold()` (a live hold, or one that ran out while the day still offers it via `pg_booking_widget_still_offered()`, held again for the policy's minutes), `pg_booking_pay_start()` writes the details on the held row, `pg_booking_deposit_order_write()`, and answers `{ok, status: 'pay', pay: {html}}`.
- Session `software.booking_pay`: booking_id, order_id, page (`pg_booking_pay_page()`: a path of this site only, sent by the script as `page`), driver, state, error.

## How is it paid?
- `pg_booking_pay_html()`: a plain form posted to `booking_slots.php` (`pg_booking_pay=1` + `get_token_field()`), the form step's markup (`pg_form_payment_step_html()` with `offline_note`), offline labelled "Pay on arrival". `pg_booking.js` puts it in the details step in place of the form and loads its scripts in order (card service, `payment_hosted.js`), so the card box works as on a form.
- `booking_slots.php`: a `?mode=<return mode>` request → `pg_booking_pay_return()`; a form post → token check, hold rate limit, `pg_booking_pay_retry()` → `pg_booking_pay_charge()`. The charge keeps the hold first (gone → "expired"), then as `pg_form_payment_charge()`: `authorize()` with `basket_id` `booking-<id>`, `return_url` `booking_slots.php?mode=`; `pg_form_payment_answered()` settles and completes the order.
- Every end goes back to the page with `#pg-booking-payment`; the widget then posts `{"action":"payment"}` (`pg_booking_pay_state_request()`): `open` (the payment again, the refusal said once, the same order), `done` (closing words from the booking's status), `expired`, `none`. The page stays the same for everyone (page cache).

## What does each payment do to the booking?
- Card paid: the order's completion runs `pg_booking_deposit_mark_paid()` (confirmed; a hold that ran out → requested).
- Paid on arrival: `pg_form_payment_finish()` completes the order unpaid, then `pg_booking_confirm($row, 'requested')` (manage token, mails).
- Not paid: the hold runs out. A held row with an order is never deleted: Back/release (`pg_booking_release_session_hold()`) and an expired hold on a start taken again (`pg_booking_clear_expired_hold()`, also used by a move) cancel it with `slot_at = NULL`, so a late payment still finds it.

## Where is paid shown?
- `pg_booking_deposit_view()` (pure: none / unpaid / paid, paid_at, order_number), `pg_booking_deposit_label()`, `pg_booking_deposit_rows()` (order numbers in one query). Agenda: `pg_booking_deposit_badge()` beside the status in day/week cards and the list; `edit_booking.php` fact "Deposit" with the time and the order (link for roles 0-2 or `manage_ecommerce`). Manage page fact and My bookings line. API Booking: `order_id`, `order_number`, `paid_at` (null without).

## Tested?
- `tests/booking_deposit_pay_test.php`: the view, badge, return page, offline line, API fields, wiring. No browser, Stripe test key or dev database run.

Related: docs/memory/commerce/booking-deposits.md, docs/memory/designer/custom-form-payment-step.md, docs/memory/designer/booking-widget-confirm.md

## Why does keeping the hold take the staff lock?
- `pg_booking_pay_keep_hold()` runs under `pg_booking_lock_name()` like `pg_booking_insert()`, re-reads the row there and is true only when its UPDATE kept a `held` row. An expired hold counts as free for other visitors, so unlocked, a hold on an overlapping start could be written between its check and its write (double booking), and a row cancelled meanwhile was still "kept" and charged (bug hunt, PR #7082).
