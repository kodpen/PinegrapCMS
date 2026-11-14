---
name: workspace-calendar-write-plans
description: A person's own plans written one way to their Google or Outlook calendar (calendar_sync.php, PC13): the My calendar switch, the second calendar, which plans, the body, off and deleted
---

## What does the person get?
- Under My calendar (`workspace_my_calendar.php`) a Look A card row with a switch (`ws_calsync_plans_card()`, post `my_calendar_action=calsync_plans`, `ws_calsync_plans_post()`). Off until switched on. Shown only when the site offers a provider (`ws_calsync_offered()`).
- Architect (2026-11-14): the title names the outside calendar, "Write my plans to Google Calendar" / "... to Outlook", or "Write my plans to my Google or Outlook calendar" without an account: "my calendar" read as the screen itself.
- The meta line: on, a fact line (calendar, plan count, last write); a failed last run, an amber "Last write failed" pill, the error and a link to Calendar subscription; just switched off, "Your plans calendar in X is removed at the next run; your plans here stay." until that run; off, what is written and what never is.
- Switching on needs a connected `calendar_sync` account: the one tasks are written with, else the first offered provider connected (`ws_calsync_plans_account()`). Without one the switch is disabled and the row links to Calendar subscription. On writes at once; Site Log "turned on/off writing their own plans to their own calendar" (no plan named).

## Where do plans go?
- Owner (2026-11-14): a second calendar the site makes, "Workspace plans (host)", apart from the tasks calendar. Its id is `plans_calendar_id` in the sealed `ws_calsync:<user id>` entry, beside `plans` (the switch) and `plans_written` (derived id => hash); `ws_calsync_state_clean()` keeps them. No column, no migration step.
- Tasks off and plans on is a valid entry (`calendar_id` ''): `ws_calsync_tasks_on()` tells the tasks part. "Stop writing" of the tasks card keeps the plans part (`ws_calsync_stop_tasks()`).

## Which plans are written?
- Owner (2026-11-14): the person's own (`user_id` = the person, whatever their visibility), read by `ws_personal_plans_between()` and passed again through `ws_personal_plan_can_see()` in `ws_calsync_plan_items()` (pure, tested). A plan someone else shared with the person is never written.
- Items go into the writer only, uid `ws-plan-<id>`; never into `ws_calendar_feed_task_items()` or `workspace_ical.php`, the outgoing iCal subscription (a test holds it).

## What does an event carry?
- Owner (2026-11-14): the title, the days and times, and the link `workspace_my_calendar.php?plan=N` as its description. No note, no customer, no steps. Free (transparent) as tasks are; the site keeps the reminder.

## What do a change, a delete and the switch off do?
- A changed plan changes its hash and is written again; a deleted plan leaves the list and its event is deleted (`ws_calsync_plan()`).
- Owner (2026-11-14): one way. The plans calendar is never read back (`ws_calsync_moves()` stays tasks only); an event moved or deleted there is written again as the plan stands at its next change, with no bell line.
- Switched off: the next run deletes the plan events, then the empty plans calendar (best effort); with tasks off too, the entry goes.
- Owner (2026-11-14): the plans switch runs alone when tasks are off.

## How is a run shared?
- `ws_calsync_sync()`: the tasks part as before (read back, moves, writes), then `ws_calsync_sync_plans()` with the calls the tasks left of `WS_CALSYNC_WRITES_MAX`; `left` adds both. A 404 on a plans create says the plans calendar was deleted: switch off and on to make a new one.

## Where do repeating plans fit?
- `ws_calsync_plan_times()` gives one time per plan today; a repeating plan gives one per time in the writer's window, uid `ws-plan-<id>-<Ymd>` (its `key`).

Related: `workspace-calendar-write.md`, `workspace-personal-plans.md`. Test: `tests/workspace_calendar_sync_plans_test.php`.
