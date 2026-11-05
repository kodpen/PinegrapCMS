---
name: api-calendar-locations-reservations
description: /calendars/locations CRUD (fn/calendar_writes.php, role 3 refused), GET /calendars/events/{id}/reservations, the calendar_event.reserved webhook
module: calendar_writes
---

## Where is an event location written?
- `includes/fn/calendar_writes.php`: `pg_calendar_location_name_taken()`, `pg_calendar_location_save()` (0 creates), `pg_calendar_location_delete()`. `add_calendar_event_location.php`, `edit_calendar_event_location.php`, `pg_calendar_event_location_form_name_in_use()` and the API handlers call them.
- A delete takes the `calendar_event_locations` row and its `calendar_events_calendar_event_locations_xref` rows: the events stay, without that room, as the screen always did. A dry run answers `event_count`.

## Who may use the location routes?
- Roles 0 to 2: the location screens refuse role 3 (`view_calendar_event_locations.php` role > 2), so every location route answers 403 `forbidden` to such an owner (`api_calendar_locations_require()`), reads included. A name another location has: 409 `name_taken`; `name` max 100 (varchar(100)).

## What does GET /calendars/events/{id}/reservations list?
- The `order_items` rows with that `calendar_event_id` whose order is placed (`orders.status != 'incomplete'`, lines saved for later left out), oldest first, cursor on `order_items.id`. A cancelled order's line stays listed with `order_status`: a cancellation does not give the spots back.
- `recurrence_number` is 0 for a one-off event or a repeating one whose repeats share their spots (`reserve_calendar_event.php`).
- The event follows the calendar row rule (`api_calendar_event_visible_or_fail()`, 404 outside the assigned calendars). `customer` (contact_id, name, e-mail) is null unless the owner may open orders (role < 3 or `manage_ecommerce`) **and** the key holds `orders:read` (`api_calendar_reservation_customer_shown()`): `calendars:read` alone does not open order data. Before the scope check (PR #7197) an administrator's key with only `calendars:read` received every buyer's name and e-mail.

## When is calendar_event.reserved announced?
- From `pg_order_complete_line_reservation()` (`includes/fn/order_completion.php`), after the reservations check and the spot count, so every path that completes an order announces it once per line. Payload `pg_order_reservation_event()`: id (the event's), recurrence_number, quantity, order_item_id, order_id, order_number, contact_id.

## Tests?
- `tests/api_calendar_locations_test.php`.
