---
name: appointment-booking-steps
description: Steps BK1-BK5 of plans/appointment-booking.md: schema and slot engine, settings, the staff agenda, the booking widget, mails, SMS and reminders; claim as [plan:appointment-booking] BK<n>
---

## Which run side by side?
- Wave 1: BK1 alone (questions 1-2 first). Wave 2: BK2, BK3, BK4, BK5 at once (BK5 after question 3), four workers on different files. Wave 3: BK6, BK7, BK8 at once. Wave 4: BK9, then BK10. BK6-BK10: `appointment-booking-steps-2.md`.
- S under 400 product lines, M about 1,000, L cut into PRs. Tests in `tests/booking_<topic>_test.php` (pure functions, red first), memory under `commerce/` for the model and `designer/` for the widget, texts through `tr_add.php`, `php tools/build_js.php` after a bundled script.

## Which steps are there?
- BK1 (M) Schema, model and the slot engine. Done in #6137 (unique key on `slot_at`, NULL when cancelled: #6135).
  - Migration sub-step with `install_create_table()` for the three tables (in `get_tables()`, rerun twice on a dev DB). New `includes/fn/bookings.php`: statuses and the allowed transitions, `pg_booking_hours_clean()` (weekly hours JSON, exceptions), `pg_booking_slots($service, $staff, $day, $busy, $now, $policy)` (pure: length, buffers, hours, holidays, busy ranges, lead time, horizon, DST days, the site zone), `pg_booking_hold()` and `pg_booking_confirm()` (one INSERT on the unique key; a duplicate is the "just taken" answer, never a second row), `pg_booking_manage_token()` (HMAC, `ENCRYPTION_KEY`, expires with the booking). Tests: slots on a DST change day, buffers, a hold that expired, the duplicate. Memory: `commerce/bookings-model.md`.

- BK2 (M) Settings. After BK1. Done in #6237 (`ui/booking-settings.md`; rules and texts in `config_text`).
  - Settings › Bookings (`includes/settings/`): services (list, add, edit, order, enabled), staff (pick panel users, their services, weekly hours grid with a copy-to-all, day exceptions, time off), policies (lead time, horizon, cancellation window, reschedule allowed, hold minutes, reminder hours), the widget's texts; every box through the panel's own controls (`pgDatePicker`, chips). Memory: `ui/booking-settings.md`.

- BK3 (M) Staff agenda. Done in #6231 (`ui/booking-agenda.md`).
  - `view_bookings.php` (day, week and list views with the period navigator of `ui/list-period-navigator-and-clock.md`, staff filter, status chips, server paging for the list) and `edit_booking.php` (a staff-made booking against the same slot engine, status changes, notes, the customer card link, cancel with a reason); the audit trail writes every change (`core/audit-trail-contacts.md` pattern); the panel search finds a booking by name or e-mail. Memory: `ui/booking-agenda.md`.

- BK4 (L, two PRs) The booking widget. After BK1. Done: 4a in #6224 (reads BK2's `pg_booking_policy()`), 4b in #6596 (`designer/booking-widget-confirm.md`).
  - 4a: system widget `booking` in `includes/fn/widgets_booking.php` with its starter layout and palette entry (`designer/system-widget-change-checklist.md`): the service and staff steps, the month view on `pg_datetime.js` with disabled days from a slot query endpoint (`booking_slots.php`, root, rate-limited, anonymous), the time list, the hold. 4b: the details step (name, e-mail, phone, notes; a signed-in account prefilled; consent linking the privacy page as the templates do), the confirmation panel, the "just taken" and "expired hold" paths, phone layout, keyboard and screen reader names. Browser try on desktop and phone. Memory: `designer/booking-widget.md`.

- BK5 (M) Mails, SMS, .ics and reminders. Done in #6254 (`commerce/booking-messages.md`; reminders keep a job cursor in `config_text`, no `reminded` column). Left: BK7/BK8 call `pg_booking_changed()`; BK6b (#6629) added the four `booking.*` events to `ws_events_catalog()`.
