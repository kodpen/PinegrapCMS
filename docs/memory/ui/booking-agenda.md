---
name: booking-agenda
description: Booking agenda (view_bookings.php, edit_booking.php, #6231): views, who sees what, staff-made bookings, status moves, cancel reason, audit, search
module: booking_agenda
---

## What do the screens show?
- `view_bookings.php`: `?view=day|week|list` (week by default), `?date=YYYY-MM-DD` (today by default), `?staff=<booking_staff id>`, `?status[]=` chips. `pg_booking_agenda_period()` (pure) gives the wall-clock days of the site zone, the real start/end (a week across a clock change is 7 days ± 1 hour) and the ‹ › dates: a day, the week from `pg_list_period_week_first_day()`, or the date's month for the list.
- Day and week draw a card per day, bookings from `pg_booking_agenda_rows()` laid out by `pg_booking_agenda_by_day()`. The list view is server-paged and searched by `booking_list_rows` (`includes/panel/lists.php`, filters start/end/staff/status checked again on the server): name, e-mail, phone, service name, status label.
- Default chips: requested, confirmed, no-show, done; cancelled and held show only when chosen (`pg_booking_agenda_status_filter()`). Chips are v6 toggles (`label.btn.btn-check` holding the input).
- Menu: Calendars › Bookings, once a `booking_services` row exists and the viewer's scope is not false. A role-3 staff member without calendar access has no Calendars menu (search or a link).

## Who sees what?
- `pg_booking_agenda_scope($role, $staff_id)`: roles 0-2 every staff member (null), a role-3 user with an enabled `booking_staff` row only their own (`staff_id`), anyone else nothing (false: 403). The screens, the list action and the search all go through `pg_booking_agenda_user_scope()`; `edit_booking.php` reads the row with `pg_booking_agenda_scope_sql()` (another's booking is "not found").

## How is a staff-made booking written?
- A GET step chooses staff, service (only those the staff member gives) and the day; `pg_booking_agenda_free_slots()` runs `pg_booking_slots()` over the staff member's hours, their other bookings and their Workspace busy times (`pg_booking_agenda_ws_busy()`: own, department and company `ws_events`, an all-day event +1 s). Staff are not held to the visitors' lead time or horizon; policy is only the zone (step = service length).
- The POST checks `pg_booking_agenda_check()` (staff gives the service, a time, a name, a valid e-mail or a phone), that the start is still offered, then `pg_booking_confirm()` without an id (source `panel`, status confirmed or requested): `'taken'` is "This time has just been taken".
- No reschedule on this screen (BK5 owns the moved-booking messages).

## How do status changes and cancels work?
- Offered moves: `pg_booking_agenda_moves_now()` = `pg_booking_can_move()` limited to confirmed / cancelled / no-show / done; a hold past `hold_until` only cancels. A live hold confirmed here goes through `pg_booking_confirm()` with its id (stores the manage token).
- Other changes are one UPDATE `WHERE id AND status = <status read>`; 0 rows is "changed meanwhile". A cancel writes `slot_at = NULL` in the same UPDATE and appends "Cancelled: <reason>" to the notes (`pg_booking_cancel_notes()`): there is no reason column.

## What goes into the audit trail?
- `pg_audit()` type `booking`, actions create / update / cancel, label `#id`, fields from `pg_booking_agenda_audit_fields()`: service, staff, start, end, status, source and `notes_length`. Name, e-mail, phone and notes stay out, so erasure of a person (later step) does not need the trail. `audit_view.php` links the type to `edit_booking.php?id=`.

## How does the panel search find a booking?
- `includes/panel/search.php`: type `booking`, name or e-mail LIKE, holds left out, the viewer's scope applied, sub line = time and e-mail; last in the type order. `backend.src.js` `TYPE_META.booking` opens `edit_booking.php`.

Test: `tests/booking_agenda_test.php` (views, periods, clock change, by-day in the zone, chips, scope, staff filter, cancel notes, form checks, moves, expired hold, audit fields).

Related: docs/memory/commerce/bookings-model.md
