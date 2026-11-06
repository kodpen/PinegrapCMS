---
name: workspace-calendar-month-days
description: Which days of the Work Calendar a plan item is drawn on (ws_calendar_plan_place()): the end is exclusive, so a meeting until midnight draws no 00:00 line on the next day
---

## On which days does the Work Calendar draw a plan item?
- `ws_calendar_plan_place()` (includes/workspace/calendar.php) puts it on every grid day with `starts_at < day end` and `ends_at > day start`: the end is the moment the item stops, as for bookings (`ws_booking_calendar_place()`) and the board's minutes (`ws_event_minutes_on()`).
- An all-day item ends at 23:59:59 of its last day (`ws_event_check()`, holiday feeds), so it covers its days and no more.

## What did this fix?
- A timed item ending exactly at midnight (22:00–00:00) was also drawn on the next day as a "00:00" line, because the test was `ends_at < day start`. Test: `tests/workspace_calendar_plan_place_test.php`.

## What was hunted in the calendar files and found clean?
- calendar_feed.php: token shape checked before the query, hash lookup, one 404 for every closed address, ICS text escaping and folding at 75 octets, whole-day DTEND as the day after (noon arithmetic across a clock change), 29 February yearly rule.
- calendar_sync.php: Google/Graph bodies and their read-back compare the same way, a move only when the written hash still matches, refused moves written back, Graph delta links only from graph.microsoft.com.
