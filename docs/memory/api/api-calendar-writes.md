---
name: api-calendar-writes
description: Calendar create, rename, delete over the API (calendars:write) through fn/calendar_writes.php shared with the screens; role-3 rules, name_taken, calendar_has_events
module: calendar_writes
---

## Where is a calendar written?
- `includes/fn/calendar_writes.php`: `pg_calendar_name_taken()`, `pg_calendar_save()` (0 creates), `pg_calendar_event_count()`, `pg_calendar_delete()`. `add_calendar.php`, `edit_calendar.php`, `pg_calendar_form_validate_name()` and the API handlers (`includes/api/resources/calendars.php`) call them; the screens hold no calendar SQL of their own but the name read.
- Nothing there checks who asks: the screens gate on `validate_calendars_access()` / `validate_calendar_access()`, the API on `calendars:write` and `api_calendar_ids_visible()`.

## What does the API refuse?
- Create from an owner with the user role (3): 403 `forbidden`, as `add_calendar.php` refuses that role. Rename and delete reach only the calendars assigned to such an owner (`api_calendar_or_fail()`, 404 outside).
- A name another calendar has: 409 `name_taken`; an empty name: 422. `name` max 100 (`calendars.name` varchar(100)).
- Delete of a calendar with an event on it: 409 `calendar_has_events`, as the screen's disabled Delete button.

## What does a delete take?
- The `calendars` row and its rows in `users_calendars_xref`, `calendar_views_calendars_xref`, `calendar_event_views_calendars_xref` and `calendar_events_calendars_xref`, as `edit_calendar.php` always did.

## Events?
- None: the plan names only `calendar_event.reserved` for the calendars step (locations and reservations, X10b).
