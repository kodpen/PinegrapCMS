---
name: calendar-event-location-shared-form
description: The calendar event location create and edit screens share includes/forms/calendar_event_location_form.php: what it holds, what stays in the screens
---

## Where is the calendar event location form?
- `includes/forms/calendar_event_location_form.php` (gate `PG_FUNCTIONS_DIR`). `add_calendar_event_location.php` and `edit_calendar_event_location.php` stay at the root as thin wrappers and `require_once` it.
- `pg_calendar_event_location_form_card($liveform)` prints the Options card (the location name).
- `pg_calendar_event_location_form_name_in_use($name, $id)` (`null` on create, the posted id on edit) checks the name through `db_q()`; the screen marks the error and redirects.

## What stays in the screens?
- The gates differ and stay as they were: create calls `validate_calendars_access()`, edit only refuses role 3 (both refuse role 3).
- Create: the required check, the INSERT, the log line and notice.
- Edit: the not-found guard, the record load (on every visit), delete (with the event xref rows), the required check, the UPDATE.
- Tests: `tests/calendar_event_location_form_test.php`; `db_q_small_functions_test.php` and `db_q_regions_styles_menus_test.php` count the `db_q()` calls of the screens and the form.
