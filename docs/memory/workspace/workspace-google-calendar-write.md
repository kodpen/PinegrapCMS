---
name: workspace-google-calendar-write
description: A person's Workspace tasks written to their own Google Calendar (calendar_sync.php, #4387): app-made calendar, derived event ids, sealed state per person, the hourly job
---

## What does the person get?
- On Calendar subscription (`workspace_calendar_feed.php`, card `#ws-google-calendar`) a person connects their own Google account (purpose `calendar_sync`, owner `person`), then turns writing on. The site makes a calendar "Workspace tasks (host)" in that account and keeps their tasks there: the same tasks the outgoing subscription carries (`ws_calendar_feed_task_items()` without descriptions, and `ws_calendar_feed_copy_items()` as tentative). Write now, Stop writing (deletes that calendar, best effort). Shown only when the Google application is set up and tokens can be sealed (`ws_gcal_offered()`).

## Why the scope calendar.app.created?
- It reaches only calendars the site made: the site never sees or changes the person's other calendars, and slice 3 can read back changes of that calendar with the same scope.

## Why no table or column?
- The event id is derived: `ws_gcal_event_id(HOSTNAME_SETTING, uid)` = `pg` + sha1 (hex is inside Google's base32hex). A write is `PUT …/events/{id}`; a 404 becomes `POST` with that id; a PUT also revives an event the person deleted (it is only `cancelled` in Google), so the body always carries `status`.
- What was written (id => sha1 of the body, `ws_gcal_body_hash()`), the calendar id, the connection, `synced_at`, `error` and `left` live in a sealed service entry `ws_gcal:<user id>` (`pg_connect_service_put()`). An unchanged task costs no call (`ws_gcal_plan()`: deletions first, at most `WS_GCAL_WRITES_MAX` calls a run, `left` for the next).

## When does it run?
- `ws_gcal_tick()` from `workspace_recurring_job.php`: the entry with the oldest `last_used_timestamp`, written again after `WS_GCAL_JOB_EVERY` (1 h), sooner while `left` > 0. One person a run.
- An account that left the team: its entry is deleted, the calendar stays in their Google account. A disconnected or `reauth` account: the error shows on the card. A 404 on the POST means the person deleted the calendar: writing stops with that message until they turn it on again.

## What is a body?
- `ws_gcal_event_body()`: whole day = `date` with the end the day after; timed = `dateTime` (`date('c')`) + site `timeZone`, a deadline with end = start; `transparency` transparent, `reminders.useDefault` false (the workspace reminds), `source.url` the task link, `extendedProperties.private.pinegrap_uid` the item uid for reading back.

## What is unverified and what is left?
- No live Google call was made in CI (zero-length timed events and the 404 paths are from the API reference).
- Left (`plans/reverse-oauth-leftovers.md`): slice 2 Microsoft Graph write, slice 3 reading changes back (Google `syncToken`, Graph delta).
