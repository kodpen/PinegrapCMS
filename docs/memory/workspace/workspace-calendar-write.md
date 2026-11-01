---
name: workspace-calendar-write
description: A person's Workspace tasks written to their own Google or Outlook calendar (calendar_sync.php, #4387, #4420): app-made calendar, derived ids, sealed state per person, the hourly job
---

## What does the person get?
- On Calendar subscription (`workspace_calendar_feed.php`, card `#ws-calendar-sync`) a person connects their own Google or Microsoft account (purpose `calendar_sync`, owner `person`) and turns writing on for one of them. The site makes a calendar "Workspace tasks (host)" there and keeps their tasks in it: the tasks the outgoing subscription carries (`ws_calendar_feed_task_items()` without descriptions, `ws_calendar_feed_copy_items()` as tentative). Write now, Stop writing (deletes that calendar, best effort). Only providers whose application is set up are offered, and only when tokens can be sealed (`ws_calsync_offered()`).

## Which scopes, and why?
- Google `calendar.app.created`: only calendars the site made. Microsoft has nothing narrower than `Calendars.ReadWrite`; the code touches only the calendar id it made and events it wrote. Graph calls pass that scope (tokens are per resource).

## Why no table or column?
- Each item's id is derived: `ws_calsync_event_id(HOSTNAME_SETTING, uid)` = `pg` + sha1 (hex is inside Google's base32hex). Google takes it as the event id: write = `PUT`, 404 → `POST` with the id; a PUT also revives an event deleted in Google (only `cancelled` there), so the body always carries `status`.
- Graph makes its own ids: kept as `remote` (derived id => Graph id). Write = `PATCH /me/events/{remote}`, missing or 404 → `POST /me/calendars/{id}/events` with `transactionId` = derived id, so a repeated create makes no second event.
- State in a sealed service entry `ws_calsync:<user id>` (`pg_connect_service_put()`): provider, calendar id, connection, `written` (id => sha1 of the body, `ws_calsync_body_hash()`), `remote`, `synced_at`, `error`, `left`. `ws_calsync_state_clean()` reads it; no provider means Google. An unchanged task costs no call (`ws_calsync_plan()`: deletions first, at most `WS_CALSYNC_WRITES_MAX` a run).

## When does it run?
- `ws_calsync_tick()` from `workspace_recurring_job.php`: the entry with the oldest `last_used_timestamp`, again after `WS_CALSYNC_JOB_EVERY` (1 h), sooner while `left` > 0; one person a run; an unreadable entry is deleted.
- An account that left the team: entry deleted, calendar left in their account. Disconnected or `reauth`: error on the card with a Connect button. A create answered 404: the calendar was deleted; writing stops until turned on again.

## What is a body?
- Google (`ws_calsync_google_body()`): whole day = `date`, end the day after; timed = `date('c')` + site `timeZone`; a deadline has end = start; transparent, `reminders.useDefault` false, `source.url`, `extendedProperties.private.pinegrap_uid`.
- Graph (`ws_calsync_graph_body()`): UTC times (Graph does not read every IANA zone), whole day midnight to midnight with `isAllDay`, text body with the link, `showAs` free (tentative for copies), `isReminderOn` false.

## What does a change made in the calendar do to the task? (#4490)
- Owner (2026-11-01, `workflow/decisions-made-workspace-6.md`): a moved event writes the task's due date and time back through the drawer's check (`ws_task_update_check()`, `ws_task_update()`), with a "from your calendar" note on the log and the bell lines; a refused move is written back as it was plus one bell line to the person; repeating-task copies and done tasks are skipped; deletion and a new title are ignored, the next write restores them. Read back with Google `events.list?syncToken=` on the site's calendar and Graph `calendarView/delta` on it; both tokens go in the sealed entry.

## What is unverified and what is left?
- No live provider call ran in CI; zero-length events and the 404 paths follow the API references.
- Left (`plans/reverse-oauth-leftovers.md`): slice 3, reading changes back as decided above; not started.
