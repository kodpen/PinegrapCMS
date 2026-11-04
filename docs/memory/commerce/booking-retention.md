---
name: booking-retention
description: How long bookings are kept (cancelled rows pruned after 400 days by the general job) and what the personal data export and erasure do with a person's bookings (#5032)
module: booking_retention
---

## How long is a booking kept?
- A `cancelled` row goes 400 days after its last change (`GREATEST(updated_at, created_at)`), decided in #5032. Every other status stays: a held, done or no-show booking is the shop's record. `pg_booking_retention_limits()`, `pg_booking_retention_cutoff()`, `pg_booking_retention_where()` are pure (`tests/booking_retention_test.php`).
- `job.php` calls `pg_booking_retention_due()` / `pg_booking_retention_run(5)` (`includes/fn/booking_retention.php`): once a day, behind `pg_booking_ready()`, `DELETE … LIMIT 1000` until a batch comes back short; state file `data/temp/booking_retention.json` written only when through. A pass that removed rows writes one Site Log line. No index on status: the table is small, a scan a day is enough; no schema change.
- A pruned booking's `<#booking:N>` Workspace tag no longer opens; its deposit order stays (the order carries nothing about the booking).

## What does the personal data export hold?
- Section `bookings` (after `stock_notify`): rows by `bookings.user_id` or `bookings.email`, with `service_name`; `manage_token`, `slot_at`, `hold_until` left out ([[personal-data-export]] lives in `core/`).

## What does the erasure do?
- Entry `bookings` of `pg_privacy_erase_tables()` (after `stock_notify`), found by `user_id` or `email`, anonymised: name `{name}`, email `{token}`, phone, notes, `manage_token` emptied (the mailed link stops working), `user_id` 0. The row stays on the agenda with its service, staff, times, status and deposit.
- First `_pg_privacy_erase_apply_bookings()` cancels the person's live bookings not yet begun (`pg_booking_erase_cancel_where()`: a status that `pg_booking_can_move()` lets go to cancelled, `start_at` > now) as the agenda does (`status`, `slot_at = NULL` in one UPDATE), so the slot opens. No `booking.cancelled` event or mail: the person is being erased. A booking under way or past keeps its status.
- Booking audit lines carry no personal field (`pg_booking_agenda_audit_fields()`), so the audit field list needs no booking entry.
