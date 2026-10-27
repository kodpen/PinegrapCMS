---
name: calendar-event-shared-form
description: includes/forms/calendar_event_form.php checks a submitted calendar event for add_calendar_event.php (edit_calendar_event.php next); what the edit call changes
---

## What is shared?
- `includes/forms/calendar_event_form.php` (loaded with `require_once(PG_FUNCTIONS_DIR . …)` after `init.php`) holds the submit check both screens write out; add_calendar_event.php calls it, edit_calendar_event.php still carries its own copy until the next slice: `pg_calendar_event_form_validate($liveform, $calendar_event_id = '0')`. It marks the errors on the liveform and returns `start_time`/`end_time` (as entered; all day adds 12:00 AM / 11:59 PM; null on error), `calendars` (every id) and `calendar_event_locations` (every location; empty when the schedule has an error, so only read after the error redirect).
- A screen keeps its gates, CSRF check, the error redirect (add: plain; edit: `id`, `recurrence_number`, `send_to`), the write and the success redirect.
- Next slices: the edit screen onto the shared check, then the form markup of `plans/refactor-candidates.md` item 2.

## What does the edit call change?
- edit_calendar_event.php is to pass the posted `id`: the location check then leaves the event itself out (`check_calendar_event_location_availability()` 4th argument) and skips instances listed in `calendar_event_exceptions`. The add call passes `'0'`, which is the function's default.

## How are repeating instances dated?
- `pg_calendar_event_recurrence_dates($start_date, $n, $type, $days, $month_type)` is pure (tests in `tests/calendar_event_form_test.php`): instance number => 'Y-m-d'. Day repeats run only on the chosen weekdays and stop when none is chosen; a missing day of the month becomes the month's last day; a monthly "day of the week" on the fifth week means the last week.
- `includes/fn/calendar.php` still carries three older copies of the same walk (calendar output, location availability, event lookup); they were left as they are.
