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
