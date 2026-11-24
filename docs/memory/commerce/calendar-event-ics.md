---
name: calendar-event-ics
description: The .ics file a calendar event view page answers with ?icalendar=true: escaping, folding, all-day dates, what is still floating.
module: calendar
---

## Who builds the file?
- `get_page.php` checks the event (found, published, allowed on the page's calendars), reads its removed occurrences and hands the rest to the pure `pg_calendar_event_ics()` (`includes/fn/calendar.php`); `tests/calendar_event_ics_test.php`, `tests/calendar_event_repeat_test.php`.

## How are the text values written?
- SUMMARY, LOCATION and DESCRIPTION go through `pg_booking_ics_escape()` (backslash, semicolon, comma, line breaks as `\n`) and every line through `pg_booking_ics_fold()` (75 octets, never inside a UTF-8 character); lines end with CRLF. Before, a line break in the short description started a new property (any `ATTACH:`/`URL:` line an editor typed was taken as one) and a comma split the value.

## How are the times written?
- A timed event: local `Ymd\THis` without a zone (floating), as before; a visitor's calendar shows the site's clock time in its own zone. Not changed on purpose: a UTC DTSTART would move a weekly RRULE by an hour across a DST change.
- DTSTAMP: the event's start in UTC (`gmdate('Ymd\THis\Z')`), as RFC 5545 requires. Before, it was the local time without `Z`, which validators reject and a strict client reads as a wrong stamp (#8599, `test_calendar_event_ics_stamp_is_utc`).
- An all-day event: `DTSTART;VALUE=DATE` on the start's day and `DTEND;VALUE=DATE` the day after the end's day (exclusive end), an end before the start taken as the start. Before, it was written with its stored clock times, so calendars showed it as a timed event (or one day short).

## How is a repeat written?
- `pg_calendar_event_rrule()` (pure) gives the same dates as `pg_calendar_occurrence_date()`, which the event page uses for an occurrence: COUNT is the stored repeat count plus one; a daily repeat on chosen days names them (`FREQ=DAILY;BYDAY=MO,WE,FR`); a monthly repeat on the 29th to 31st is `BYMONTHDAY=d,-1;BYSETPOS=1` (the month's last day when it has no such date), 29 February yearly the same with `BYMONTH=2`; the fifth weekday of a month is `BYDAY=-1..`.
- Before the calendar events hunt every daily repeat was a bare `FREQ=DAILY` (a weekday class showed on weekends in the visitor's calendar and ended early), and a 31st monthly event skipped every shorter month.

## Is a removed occurrence left out?
- Yes: each number in `calendar_event_exceptions` inside the event's range becomes an EXDATE of the same value type as DTSTART (a date for an all-day event, the floating start time for a timed one). Before, a removed (cancelled) date stayed in the .ics.
