---
name: workspace-calendar-read
description: Reading a person's Google/Outlook calendar back (calendar_sync.php, #4490): syncToken and delta, what is a move, the drawer's check, refused moves written back, calendar_refused
---

## When is the calendar read back?
- At the start of every `ws_calsync_sync()` (the hourly job and Write now), before anything is written: `ws_calsync_read()`. Google: `events.list` on the site's calendar with `syncToken` (pages by `pageToken`, 250 each). Graph: `/me/calendars/{id}/calendarView/delta` over the feed's window (60 days back, 400 ahead), then the `@odata.deltaLink`. At most `WS_CALSYNC_READ_PAGES` (10) pages a run.
- The token is `read_token` in the sealed `ws_calsync:<user id>` entry, with `read_started`; no schema. A 410 (expired token) reads everything again once; after `WS_CALSYNC_READ_ROUND` (30 days) a full read starts again, which also moves Graph's window on. A Graph link is followed only when it starts with `https://graph.microsoft.com/`.
- A failed read does not stop the writes; its error shows on the card when the writes went well.

## What counts as a move?
- `ws_calsync_moves()` (pure, tested): an event of a task (`ws-task-<id>`, not a `ws-series-…` copy) whose written hash still equals the body the task gives now, and whose start (`ws_calsync_google_when()` / `ws_calsync_graph_when()`, in the site's zone) differs from that body's start. So the site's own writes read back are no move, and a task changed on the site since the last write wins: the write that follows puts it over the calendar's.
- A deleted event (Google `cancelled`, Graph `@removed`) or a new title is ignored; the next write of that event restores it (a Google PUT revives a cancelled event, a Graph PATCH 404 creates it again).

## How is a move written to the task?
- `ws_calsync_apply_move()` as the calendar's owner (`ws_viewer()`): fields from `ws_calsync_move_fields()` (pure, tested) as the board's drag moves them: due date, a start date shifted by as many days, a start-only task gets the day as due date and loses its start; `due_time` set to the event's time, cleared by a whole-day event (only where the column exists). Then `ws_task_update_check()` and `ws_task_update()`.
- Skipped: a done task, a task with `recurrence_id` (a copy of a repeating task), nothing to change. Skipped and refused moves drop the event's `written` hash, so the same run writes the task's date back.
- Kept: a Site Log line in the person's name "moved workspace task #N to <due> from their calendar", and `ws_message_touch()` on the task's card message so open channels draw it again. A drawer date edit sends no bell line to the other assignees, so none is sent here either. Bodies are built again after a kept move.

## What does the person hear of a refused move?
- One bell line, inbox kind `calendar_refused` (task_id, actor 0), drawn in `ws_inbox_describe()` as "The move of #N in your calendar was not kept" with the title and due date; named in the inbox route's kind list of `includes/workspace/api.php`.

## What could not be verified?
- No live Google or Graph call ran; the token, paging, 410 and `@removed` handling follow the API references. Whether Graph's calendar-scoped `calendarView/delta` answers on every account type is unverified.
