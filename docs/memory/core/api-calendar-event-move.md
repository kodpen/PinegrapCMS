---
name: api-calendar-event-move
description: External API POST /calendars/events/{id}: what moving an event (start, end, all_day) does to its cancelled repeats and its room bookings
---

## When does the API count an event as moved?
- `api_calendar_events_update()` (`includes/api/resources/calendars.php`) works the times out again whenever `start`, `end` or `all_day` is sent. The event is moved only when the result differs from the stored `start_time` / `end_time`; sending the same times, or a name alone, changes nothing about its repeats.

## What happens to the cancelled repeats when it moves?
- They are deleted (`calendar_event_exceptions` rows of the event), as the event screen does when its start or end changes (`edit_calendar_event.php`). A cancelled repeat is stored by its number in the series, not by its date, so after a move the number points at another day.
- The answer's `skipped_occurrences` is then empty; a dry run lists the number it would clear as `detail.skipped_occurrences_cleared` (absent when there is none).
- Symptom it fixed (#921): moving a daily repeating event one day forward through the API kept `recurrence_number = 2`, so the cancelled day came back on the public calendar and the repeat that now had number 2 (one day later) disappeared.

## What about rooms?
- A one-off event that books a room is checked against the room's other bookings (409 `room_taken`); a repeating event that books a room is refused (409 `repeating_event_books_room`) and is moved on its own screen, which checks every repeat. `remaining_reservation_spots` is not touched: the API does not write the reservation fields.
