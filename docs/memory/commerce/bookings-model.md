---
name: bookings-model
description: Appointment bookings model and slot engine: the three tables (11.0.157), hours JSON, slot rules (DST, buffers, lead, horizon, holidays), hold/confirm on the unique key, manage token
module: bookings
---

## What are the tables?
- Step `upgrade_2026_11_0_bookings()` (11.0.157), shape decided in #5032: `booking_services` (length, `buffer_before`/`buffer_after` in minutes, `price` minor units, `capacity` 1), `booking_staff` (one row per panel user, `service_ids`/`hours`/`exceptions` JSON text), `bookings` (times unix UTC, `status` ENUM held/requested/confirmed/cancelled/no_show/done, `hold_until`, `manage_token` = sha256 of the link token). `slot_at` = copy of `start_at`, NULL on a cancelled row; UNIQUE `idx_staff_slot (staff_id, slot_at)` (NULLs never collide, so a cancelled booking frees its slot and keeps its real time; #6135). A cancel writes `status = 'cancelled', slot_at = NULL` in one UPDATE; a reschedule moves `start_at`, `end_at`, `slot_at` in one UPDATE (1062 there is "just taken" too). `idx_start (start_at)` serves the agenda.
- Readers call `pg_booking_ready()` first: files land before the upgrade.
- Retention, export and erasure: [[booking-retention]].
- Deposits (11.0.169): `booking_services.deposit`/`deposit_product_id`, `bookings.order_id`/`paid_at`; a paid deposit confirms the booking ([[booking-deposits]]).

## How are hours stored?
- `hours`: `{"1":[["09:00","13:00"],["14:00","18:00"]],…}`, ISO weekday 1 (Mon) to 7, wall-clock times of the site zone, `"24:00"` ends the day. `exceptions`: `{"2026-12-24":[["09:00","12:00"]],"2026-12-25":[]}`; an empty list is a day off. `pg_booking_hours_clean()` / `pg_booking_exceptions_clean()` drop bad ranges and merge overlapping or touching ones.

## How does `pg_booking_slots()` pick slots?
- Pure; all input comes in (`$busy` ranges, `$now`, `$policy` zone, lead_minutes, horizon_days, step_minutes, holidays). Returns unix starts.
- The day's exception wins over the weekly hours; a holiday closes the day unless an exception sets hours for it.
- Ranges are walked in real time: a DST spring day offers an hour less, an autumn day an hour more (two 02:00 slots, an hour apart).
- The service length must fit in the hours; buffers only keep the slot apart from busy ranges (half-open), they do not shorten the hours.
- Busy from bookings: `pg_booking_busy_from_rows()` skips cancelled rows and holds past `hold_until`, and widens each row by its own service's buffers (read with `pg_booking_busy_sql()`, the LEFT JOIN on `booking_services`; a deleted service adds none), so a booking's buffer after is not given to a later booking whose own buffer looks only ahead. Overlap reads widen their window by `pg_booking_buffer_reach()` (480 minutes, the settings limit). The caller adds Workspace calendar busy times.
- The manage page takes the booking's own range out with its service's buffers (`pg_booking_manage_busy(..., $service)`).

## How are hold and confirm written?
- `pg_booking_insert()` runs under `GET_LOCK` per staff member (name carries the database): deletes an expired hold on the same start, refuses a live booking overlapping the slot with the new service's buffers, then one INSERT writing `slot_at` = `start_at`; error 1062 on the unique key is `'taken'` ("just taken"), never `ON DUPLICATE KEY` or a second row.
- `pg_booking_hold()` writes a `held` row until now + hold minutes. `pg_booking_confirm()` with an id turns a live hold into requested/confirmed (`'expired'` when it ran out); without an id it books at once (staff-made). It stores the token hash and returns the raw token.
- Status moves: `pg_booking_can_move()`; cancelled, no_show, done are final; nothing goes back to `held`.

## How does the manage link work?
- `pg_booking_manage_token($id, $end_at, ENCRYPTION_KEY)` = HMAC, so a later mail draws the same link from the row; a moved booking (new `end_at`) gets a new link and its hash must be stored again. `pg_booking_manage_token_valid()` compares with `hash_equals()` and stops after `end_at`.
