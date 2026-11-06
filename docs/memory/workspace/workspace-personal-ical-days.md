---
name: workspace-personal-ical-days
description: On which days and Today lines a person's own iCal events are drawn (ws_personal_ical_touches(), end exclusive), how a repeating all-day event ends across a clock change, the gzip unpack limit
---

## On which days is an event of a person's own calendar drawn?
- `ws_personal_ical_touches($event, $day_start, $day_end)` decides for the Work Calendar (`ws_personal_ical_place()`) and the Today card (`ws_home_calendar()`): `starts_at < day end` and `ends_at > day start`, the end being the moment the event stops; an event with no length counts at its start.
- Before, both used `ends_at < day start` / `>= start`, so an event until midnight (22:00–00:00) was also drawn on the next day as a "00:00" line, and on the next day's Today card. The Work Calendar's plan items had the same fault (`workspace-calendar-month-days.md`).

## How does a repeating all-day event end?
- At 23:59:59 of its last calendar day: `ws_personal_ical_events()` turns its length into whole days (`round(length / 86400)`) and adds them to each occurrence with `modify('+N days')`.
- Before, the master's length in seconds was added: an occurrence on the 23-hour day of spring forward ended at 00:59:59 of the next day (drawn on two days), one on the 25-hour day of fall back at 22:59:59.
- Test: `tests/workspace_personal_ical_test.php` (midnight end, clock change in Europe/Berlin).

## Why is a gzip body unpacked with a limit?
- A body that starts with the gzip magic bytes is unpacked with `gzdecode($body, 8 MB)`; above that it stays packed and the read fails with "did not answer with a calendar". The download is capped at 4 MB, but 4 MB of gzip can unpack to gigabytes and stop the job that reads every calendar.

## What was hunted in personal_ical.php and found clean?
- Address check (https/webcal, no credentials, no local names or blocked IP literals) with `api_http_request()` resolving again and following no redirects; errors never name the address.
- RRULE (INTERVAL, COUNT, UNTIL, weekly BYDAY, the 31st and 29 February skipped), EXDATE and RECURRENCE-ID matched by moment across zones; timed repeats keep their wall-clock time across a clock change.
- Only the reader's own layer is shown (person filter 0 or themselves; Today card for the session user); every card action goes through the owner filter; texts are escaped with `h()`.
