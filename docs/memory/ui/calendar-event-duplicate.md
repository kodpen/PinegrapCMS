---
name: calendar-event-duplicate
description: A duplicated calendar event starts unpublished, named "… (copy)", with its calendars, cancelled repeats and locations copied; the location check asks on its first save
---

## How is the copy made? (#5582, PR #6372)
- `duplicate_calendar_event.php`: `published = 0` for every user (before, a published event's copy was published unless the user lacked publish rights), name `pg_calendar_event_copy_name()` (`includes/fn/calendar.php`): `lang('{var:1} (copy)')`, the original cut so the result fits the 100-character `name`.
- Copied: the calendars the user may open, the cancelled repeats (`calendar_event_exceptions`) and the locations (`calendar_events_calendar_event_locations_xref`).
- Why: until the copy was edited the site showed the event twice, and a copy without its places was not a copy.

## What about the location held twice?
- The copy holds the same locations at the same time; the edit screen's location check (`pg_calendar_event_form_check_location()`, `calendar-location-availability.md`) marks it on the copy's first save, so the operator moves the time or the place.
- Only new copies change. Tests: `tests/calendar_event_duplicate_test.php`.
