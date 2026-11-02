---
name: appointment-booking-steps
description: Steps BK1-BK5 of plans/appointment-booking.md: schema and slot engine, settings, the staff agenda, the booking widget, mails, SMS and reminders; claim as [plan:appointment-booking] BK<n>
---

## Which run side by side?
- Wave 1: BK1 alone (questions 1-2 first). Wave 2: BK2, BK3, BK4, BK5 at once (BK5 after question 3), four workers on different files. Wave 3: BK6, BK7, BK8 at once. Wave 4: BK9, then BK10. BK6-BK10: `appointment-booking-steps-2.md`.
- S under 400 product lines, M about 1,000, L cut into PRs. Tests in `tests/booking_<topic>_test.php` (pure functions, red first), memory under `commerce/` for the model and `designer/` for the widget, texts through `tr_add.php`, `php tools/build_js.php` after a bundled script.

## Which steps are there?
- BK1 (M) Schema, model and the slot engine. `free` after questions 1-2.
  - Migration sub-step with `install_create_table()` for the three tables (in `get_tables()`, rerun twice on a dev DB). New `includes/fn/bookings.php`: statuses and the allowed transitions, `pg_booking_hours_clean()` (weekly hours JSON, exceptions), `pg_booking_slots($service, $staff, $day, $busy, $now, $policy)` (pure: length, buffers, hours, holidays, busy ranges, lead time, horizon, DST days, the site zone), `pg_booking_hold()` and `pg_booking_confirm()` (one INSERT on the unique key; a duplicate is the "just taken" answer, never a second row), `pg_booking_manage_token()` (HMAC, `ENCRYPTION_KEY`, expires with the booking). Tests: slots on a DST change day, buffers, a hold that expired, the duplicate. Memory: `commerce/bookings-model.md`.

- BK2 (M) Settings. After BK1.
  - Settings › Bookings (`includes/settings/`): services (list, add, edit, order, enabled), staff (pick panel users, their services, weekly hours grid with a copy-to-all, day exceptions, time off), policies (lead time, horizon, cancellation window, reschedule allowed, hold minutes, reminder hours), the widget's texts; every box through the panel's own controls (`pgDatePicker`, chips). Memory: `ui/booking-settings.md`.

- BK3 (M) Staff agenda. After BK1.
  - `view_bookings.php` (day, week and list views with the period navigator of `ui/list-period-navigator-and-clock.md`, staff filter, status chips, server paging for the list) and `edit_booking.php` (a staff-made booking against the same slot engine, status changes, notes, the customer card link, cancel with a reason); the audit trail writes every change (`core/audit-trail-contacts.md` pattern); the panel search finds a booking by name or e-mail. Memory: `ui/booking-agenda.md`.

- BK4 (L, two PRs) The booking widget. After BK1.
  - 4a: system widget `booking` in `includes/fn/widgets_booking.php` with its starter layout and palette entry (`designer/system-widget-change-checklist.md`): the service and staff steps, the month view on `pg_datetime.js` with disabled days from a slot query endpoint (`booking_slots.php`, root, rate-limited, anonymous), the time list, the hold. 4b: the details step (name, e-mail, phone, notes; a signed-in account prefilled; consent linking the privacy page as the templates do), the confirmation panel, the "just taken" and "expired hold" paths, phone layout, keyboard and screen reader names. Browser try on desktop and phone. Memory: `designer/booking-widget.md`.

- BK5 (M) Mails, SMS, .ics and reminders. After BK1 and question 3.
  - Campaign profile actions (question 3) with the mail-merge fields (service, staff, time in the site zone, place, manage link), the `.ics` built by `pg_booking_ics()` (pure, tested), SMS kinds through `pg_sms_enqueue()` as `platform/sms-order-messages.md` does, the reminders by the general job (24 h and 2 h, once each, `bookings.reminded` bits), `pg_announce()` events `booking.created`, `booking.confirmed`, `booking.rescheduled`, `booking.cancelled` (webhooks and Flows event starts see them). Memory: `commerce/booking-messages.md`.
