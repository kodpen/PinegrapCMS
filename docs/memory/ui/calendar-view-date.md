---
name: calendar-view-date
description: The date a calendar view is asked for (?date=, kept in the session): format, check and why it is written with two-digit month and day
module: calendar
---

## What date does a calendar view take?
- `get_calendar()` (`includes/fn/calendar.php`) takes `MM-DD-YYYY`, the format its ‹ › links and the calendar system widget build. It arrives from the query string of the calendar view page or the Calendars screen and is kept in the session.
- `pg_calendar_view_date($date)` checks it first: not text, not three digit-only parts or not a real date (checkdate) gives `''`, the current month or week. A good one comes back as `sprintf('%02d-%02d-%04d')`.

## Why is the date padded?
- The views compare each event's `YYYY-MM-DD` with the range start and end as text. Before #PR a typed `?date=3-1-2026` passed the check unpadded, the range became `2026-3-01`…`2026-3-31` and every event fell outside it: the month showed empty.
- Tests: `tests/calendar_view_date_test.php`; `tests/calendar_session_params_test.php` checks that `get_calendar()` runs the date through the helper before splitting it.
