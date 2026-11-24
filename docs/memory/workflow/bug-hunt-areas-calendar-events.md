---
name: bug-hunt-areas-calendar-events
description: Bug hunt 29 of calendar events, repeats and the calendar feeds (.ics, Workspace subscription): four faults fixed, what was read clean, what was left
---

## What was the area?
- Calendar events, their repeats and the calendar feeds: `includes/fn/calendar.php` (occurrence dates, `get_calendar_event()`, the .ics, the room check), the `?icalendar=true` answer of `get_page.php`, the calendar and event view pages, and the Workspace calendar subscription (`includes/workspace/calendar_feed.php`). Hunt 29, given by the coordinator from the architect's list (2026-11-24).
- To look at: a repeat across a clock change, an all-day event shifted by a time zone, an edited single occurrence, a deleted series or occurrence still in a feed, a private event in a shared view or feed.

## What did it fix? (the PR lists each with file and line)
- An occurrence's end was its start plus the event's length in seconds: across a clock change a two-day all-day weekly event ended at 00:59 on a third day (event page, room check), an overnight event stored on the night of a change ended an hour early. Now days and clock time (`ui/calendar-location-availability.md`).
- An occurrence removed from the calendar stayed in the event's .ics: now an EXDATE (`commerce/calendar-event-ics.md`).
- A daily repeat on chosen days was written as a bare `FREQ=DAILY`: now `BYDAY`.
- A monthly repeat on the 29th to 31st (and 29 February yearly) skipped the shorter months in a visitor's calendar: now the month's last day, as the site counts it.

## What was read clean?
- Unpublished events: the site calendar views ask for `published`, the event page and the .ics refuse an unpublished event, the event must sit on a calendar of the page.
- Removed occurrences on the site views (left out for visitors, struck through in the panel) and on reservations (`commerce/calendar-reserve-occurrence.md`).
- Daily, weekly, monthly and yearly occurrence dates step calendar days with `strtotime('+1 day')` / `mktime()`, not seconds; the extracted `pg_calendar_occurrence_date()` matched the old switch on 3,000 random events.
- The Workspace subscription: plan items only through `ws_event_applies()`, a person's own iCal events and private plans are never in `ws_events`, all-day items as dates, skipped days left out, yearly 29 February as the last day of February.

## What was left?
- An edited single occurrence: a site calendar event has no per-occurrence edit (only Remove), so there is nothing to carry; Workspace plans are `workspace/workspace-personal-plan-repeat.md`.
- A timed event's .ics stays floating (`commerce/calendar-event-ics.md`, on purpose).
- API all-day events: a `start` with an offset is read as that moment on the site's clock, as the route's description says, so a date sent as midnight UTC can land on the day before in a zone west of UTC. Documented behaviour, not changed.
- The order form's "is any occurrence still ahead" check (`includes/fn/ecommerce.php`) still adds the length in seconds; an hour at most, left alone.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/calendar-event-ics.md
