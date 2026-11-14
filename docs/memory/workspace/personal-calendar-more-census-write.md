---
name: personal-calendar-more-census-write
description: PC10 census (2026-11-14) for PC13: own plans written to Google or Outlook by the task writer (calendar_sync.php), what it keeps per person, the switch in My calendar, no column
---

## What does the task writer keep per person today?
- No table or column: one sealed service entry `ws_calsync:<user id>` (`ws_calsync_state()` / `ws_calsync_state_clean()`): provider, connection_id, calendar_id (the calendar the site made), `written` (derived id => body hash), `remote` (Graph ids), synced_at, error, left, read_token, read_started (`workspace-calendar-write.md`, `workspace-calendar-read.md`).
- What it writes comes from `ws_calsync_bodies()`: the person's bookings, `ws_calendar_feed_task_items()` and `ws_calendar_feed_copy_items()`, cut at `WS_CALENDAR_FEED_LIMIT`. Ids are derived from a uid (`ws_calsync_event_id()`); a body that left the list is deleted, an unchanged one costs no call (`ws_calsync_plan()`). The hourly tick takes one person a run.

## What does PC13 add, and where?
- Plan items in `ws_calsync_bodies()` only, uid `ws-plan-<id>` (with PC11, one item a time: `ws-plan-<id>-<Ymd>`, in the writer's window). Never in `ws_calendar_feed_task_items()` or calendar_feed.php: those also build the outgoing iCal subscription, which must not carry a plan.
- Which plans: `user_id` = the person, whatever their `visibility` (a private plan goes only to its owner's calendar). A plan shared with the person (`people` or `team`, another owner) is never written. Read through `ws_personal_plan_can_see()` as every plan reader is.
- Body: title, the days and times, a link to My calendar on the plan (`?plan=N`); no note, no customer, no step list (as tasks go without descriptions). A plan's own reminder stays the site's (`reminders.useDefault` false, `isReminderOn` false).
- Change or delete follows by the existing diff: a changed plan changes its hash, a deleted plan leaves the list and is deleted from the calendar.

## Where is the switch kept?
- "Write my plans to my calendar" in My calendar, off until switched on: a key `plans` (bool) in the same sealed entry, kept by `ws_calsync_state_clean()`. No column. Switching it on needs a connected account as the task writer does (`ws_calsync_offered()`, `ws_calsync_start()`); turning it off deletes the plan events at the next run (they leave the list).

## What is a question for the owner?
- One calendar or two: plans in the same "Workspace tasks (host)" calendar, or a second calendar the site makes (its id then a second key, e.g. `plans_calendar_id`, in the entry; still no column).
- A plan's event moved or deleted in Google or Outlook: one way means it is not read back (`ws_calsync_moves()` takes only tasks). Proposed: written back as it is on the site at the next change, like a refused task move, with no bell line.
- Tasks off and plans on: whether the writer may run for plans alone.

## What must PC13 not change?
- The task writer's behaviour for tasks and bookings, the read-back of task moves, the outgoing iCal subscription, the Work Calendar, the planning board and capacity.

Related: `personal-calendar-more-census-repeat.md`, `personal-calendar-more-census-template.md`.
