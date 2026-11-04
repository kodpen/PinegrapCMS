---
name: booking-retention
description: Open work left by the appointment booking plan: prune cancelled bookings after 400 days, personal data export and erasure cover bookings; claim as [plan:booking-retention] BR1
---

## What is missing?
- #5032 decided that cancelled `bookings` rows are kept 400 days and that the privacy erasure covers bookings. Nothing deletes old cancelled rows, and `pg_privacy_export_sections()` (`includes/fn/privacy_export.php`) and the erasure do not read `bookings` (`commerce/bookings-decisions.md`).

## Which steps are there?
- BR1 (S) free. A prune of `status = 'cancelled'` rows whose `updated_at` is older than 400 days in the general job (as other pruners, behind `pg_booking_ready()`); a `bookings` section in the personal data export matched by the person's e-mail or user id (`core/personal-data-export.md`); the erasure deletes or anonymises the person's bookings (name, e-mail, phone, notes; a live future booking cancelled first through the status moves). Tests in `tests/booking_retention_test.php` for the pure parts (the cutoff, the row selection). Memory: `commerce/bookings-model.md` grows, this plan is deleted.
