---
name: api-bookings
description: Bookings API (BK8): list, get, free slots, staff-made create, status and cancel through the agenda's write path, bookings:* for roles 0-2 and role-3 staff (own rows), events
---

## Which routes are there?
- `GET /bookings` (status comma list, staff_id, service_id, from, to; by `start_at` then id, cursor on both; holds only when `status` names `held`), `GET /bookings/slots?service=&staff=&day=`, `GET /bookings/{id}`, `POST /bookings`, `POST /bookings/{id}/status` (confirmed, no_show, done), `POST /bookings/{id}/cancel` (reason) in `includes/api/resources/bookings.php`. MCP tools come from the same rows. `/bookings/slots` sits before `/bookings/{id}` (first match wins).
- The three writes take `dry_run`. Services and staff hours stay in the panel.

## Which code do they share with the agenda?
- Slots: `pg_booking_agenda_free_slots()` per enabled staff member who gives the service (staff rules: no lead time or horizon), so `POST /bookings` accepts exactly the starts the slots route offers; anything else is 409 `slot_taken`, as is `pg_booking_confirm()`'s `'taken'` (unique key hit at the same moment).
- Create: `pg_booking_agenda_check()` (422 naming the field), then `pg_booking_confirm()` without an id, source `api`; it announces `booking.created` (+ `booking.confirmed`).
- Status and cancel: `pg_booking_agenda_moves_now()` decides (409 `booking_state`); a live hold confirmed goes through `pg_booking_confirm()` with its id, every other move through `pg_booking_agenda_status_write()` (the agenda's UPDATE, moved out of `edit_booking.php`, WHERE names the status read; 0 rows is 409 `booking_state` "changed meanwhile"). A cancel's reason goes into the notes with `pg_booking_cancel_notes()`.
- Every write leaves the agenda's audit line (`pg_booking_agenda_audit()`). `api_booking_move_event()` (pure) names the event: a request confirmed or a non-hold cancelled calls `pg_booking_changed()`; done and no-show announce nothing. The four `booking.*` events were already in `api_webhook_events()` (BK5).

## What does a Booking carry?
- `api_booking_present()` (pure): ids, service and staff names (null when gone), user_id (null without an account), name, e-mail, phone, notes (null when empty), ISO UTC times, status, source, hold_until (only on a hold), order_id, order_number and paid_at of a deposit (null without; `pg_booking_deposit_rows()` reads the number). Never `manage_token` or `slot_at`.

## Who may delegate it?
- `bookings:read` / `:write` in `api_scope_groups()`; ceiling in `api_owner_scopes()`: roles 0-2, and a role-3 user with an enabled `booking_staff` row (`pg_booking_agenda_user_scope()`). Every handler narrows rows with `pg_booking_agenda_scope_sql()`: another staff member's booking is 404, their staff id on slots or create is 404 / 422.
- 503 `service_unavailable` before the bookings upgrade.

Test: `tests/api_bookings_test.php`. Not run against a dev database.

Related: docs/memory/commerce/bookings-model.md, docs/memory/ui/booking-agenda.md, docs/memory/commerce/booking-messages.md
