---
name: calendar-location-availability
description: How check_calendar_event_location_availability() finds a room already booked, including recurring, overnight and multi-day events
module: calendar
---

## Where is a location's availability checked?
- `check_calendar_event_location_availability($location_id, $start, $end, $event_id)` in `includes/fn/calendar.php`. Callers: the event form (`includes/forms/calendar_event_form.php`), `update_calendar_event_exception.php` (unremoving an occurrence) and the API calendars resource. It answers `'available'` or the id of the clashing event.
- Every event on the location is read (the edited one left out); a recurring event is expanded occurrence by occurrence (exceptions skipped) until an occurrence starts after the asked end.

## How long does one occurrence of a recurring event last?
- As long as the event itself: `pg_calendar_occurrence_window($occurrence_date, $start, $end)` starts the occurrence on its own date at the event's start time and adds the event's length in seconds.
- Before #4632 the end was the end time on the occurrence's start date, so a recurring overnight event (22:00 to 02:00) ended before it started and a multi-day one lasted only its first day: such events never blocked the room, and the location could be double-booked.
- Tests: `tests/calendar_occurrence_window_test.php`.

## How are a one-off event's times read?
- In PHP: `pg_calendar_single_event_conflicts($start, $end, $new_start, $new_end)` runs `strtotime()` on the stored DATETIME, like the new booking's times, and keeps the three overlap cases (back to back is free).
- Before the fix the query read `UNIX_TIMESTAMP(start_time)`. The MySQL session sits at the request day's fixed offset (`init.php`, `date('P')`), so in a daylight saving zone an event of the other season was an hour off: booked in winter, a summer 10:00-11:00 event blocked 11:00-12:00 and let 10:30 through.
- Tests: `tests/calendar_location_conflict_test.php`.
