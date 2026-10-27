---
name: calendar-event-shared-form
description: add_calendar_event.php and edit_calendar_event.php draw and check the event form through includes/forms/calendar_event_form.php; what differs between the two calls
---

## What is shared?
- `includes/forms/calendar_event_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`) holds the submit check both screens used to write out (PR #2354 add, #2376 edit): `pg_calendar_event_form_validate($liveform, $calendar_event_id = '0')`. It marks the errors on the liveform and returns `start_time`/`end_time` (as entered; all day adds 12:00 AM / 11:59 PM; null on error), `calendars` (every id) and `calendar_event_locations` (every location; empty when the schedule has an error, so only read after the error redirect).
- A screen keeps its gates, CSRF check, the error redirect (add: plain; edit: `id`, `recurrence_number`, `send_to`), the write and the success redirect.
- The form markup too: `pg_calendar_event_form_choices()` loads every calendar and location (id, name), `pg_calendar_event_form_cards($liveform, $user, $calendars, $calendar_event_locations, $calendar_event_id = 0)` returns the five cards and the closing `toggle_calendar_event_recurrence()` script. A screen keeps the page shell, its script tags, the form tag, hidden fields (edit: `id`, `send_to`, `recurrence_number`) and buttons (edit adds Duplicate, workspace and Delete). PR #<n>.

## What does an event id change in the cards?
- Create (0): start and end prefilled with the next half hour and one hour later; initial spots row shown; product picker `get_product_options()` (same as `true, 0`).
- Edit (id): no default times; a stored unpublish 0 shows as empty; a Remaining Spots field; the initial spots row starts hidden and the separate reservations switch re-runs `toggle_calendar_event_recurrence()`, which tells the screens apart by `#number_of_remaining_spots_row`; the stored product stays in the picker.

## What does the edit call change?
- edit_calendar_event.php passes the posted `id`: the location check then leaves the event itself out (`check_calendar_event_location_availability()` 4th argument) and skips instances listed in `calendar_event_exceptions`. The add call passes `'0'`, which is the function's default.

## What changed for a failed query?
- The edit screen's old exceptions query had no failure check (`mysqli_fetch_assoc(false)` is a TypeError on PHP 8); in the shared check a failed query reads as no exceptions.

## How are repeating instances dated?
- `pg_calendar_event_recurrence_dates($start_date, $n, $type, $days, $month_type)` is pure (tests in `tests/calendar_event_form_test.php`): instance number => 'Y-m-d'. Day repeats run only on the chosen weekdays and stop when none is chosen; a missing day of the month becomes the month's last day; a monthly "day of the week" on the fifth week means the last week.
- `includes/fn/calendar.php` still carries three older copies of the same walk (calendar output, location availability, event lookup); they were left as they are.
