---
name: calendar-event-edit-screen
description: Edit calendar event screen (edit_calendar_event.php): which recurring instance it edits (recurrence_number) and how a missing or array value is read
---

## Which recurring instance does the screen edit?
- `recurrence_number` in the query string. The edit button on a calendar event page adds it (`get_page_content.php`); the form posts it back in a hidden field, and a form error redirects with it.
- It only matters when reservations are separated per instance (`separate_reservations`): the remaining spots shown and saved are that instance's row in `remaining_reservation_spots`. Otherwise instance 0 is used.
- Opened from the calendars list it is not given: the hidden field stays empty and the lookup reads instance 0, as before.

## How is the value read?
- Once, at the top, into `$requested_recurrence_number`: `pg_request_single_values(array('recurrence_number'))` first (before liveform copies `$_REQUEST`), then missing or "" stays "", anything else is `(string) max(0, (int) value)`, the same reading as `get_calendar_event_view_screen_content.php`.
- Do not read `$_GET['recurrence_number']` anywhere else on the screen; `tests/calendar_event_edit_test.php` checks it.

## What did it do before (PR #490)?
- Every open without the parameter wrote two "Undefined array key" warnings (lines 215 and 455), printed on the page where PHP errors are shown (seen on dev 2026-10-16).
- `?recurrence_number[]=x`, or a posted array with separated reservations, answered 500 (`escape()` on an array); a posted array with a form error redirected to `recurrence_number=Array`.
- The page HTML and the saved remaining spots are unchanged for a real number or no number.

Related: `core/request-parameter-arrays.md` (array = not given).
