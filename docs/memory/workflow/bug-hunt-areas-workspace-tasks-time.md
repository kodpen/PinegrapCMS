---
name: bug-hunt-areas-workspace-tasks-time
description: Bug hunt 28, Workspace tasks and time (2026-11-24, PR #11413): timer in two tabs and over midnight, a task changed while its timer runs, totals, who edits another's time, a repeat made twice
---

## What was the area?
- Hunt 28, given 2026-11-24: Workspace tasks, their statuses, due dates and timers. To look at: a timer running in two tabs or over midnight, a task moved or deleted while its timer runs, time totals against the entries, who may edit another person's time, a repeated task made twice.
- Files read: `includes/workspace/task_time.php`, the time cases of `includes/workspace/actions.php`, the time routes of `api_resources.php`, `includes/workspace/recurrence.php` (save, run, tick), `assets/js/workspace_task_time.js`.

## What did it fix? (PR #11413)
- A Stop in a stale tab stopped the timer on another task: the drawer's Stop now stops only a timer on its own task, else "Your timer runs on another task now." (`ws_task_time_stops_here()`, `workspace-task-timer-guards.md`).
- Two tabs pressing Start at once left two running timers, the older one never stopped: after the insert the others are stopped and kept (`ws_task_time_extra_running()`).
- The drawer's total summed only the 200 entries it lists, so it fell short of the task card and lists on a long task: now `ws_task_time_totals()`.
- A task's repeat saved twice at once (two tabs, a double send) made two series, each handing out a copy: the tie to the task is guarded and the losing series deleted (`ws_recurrence_attach_where()`).

## What read clean?
- Over midnight: a timer's entry stays on the day it started (`worked_on` set at start), counted by elapsed seconds, capped at `WS_TASK_TIME_MAX`.
- Stop pressed twice: the close is guarded by `minutes = 0`, the time is written once.
- Who edits another's time: their own row, anybody's for staff (role < 3), never a row on an invoice draft or a running timer; panel and API share `ws_task_time_update_check()`; the task must be visible.
- Copies of a repeat from two screens at once: `ws_recurrence_run()` claims each date with a guarded update.
- Tasks are never deleted nor moved to another channel; a board move changes dates only, the timer keeps running.

## What is left as a question?
- A timer keeps running after its task is marked done or cancelled; whether finishing a task stops it is product behaviour, not changed here.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/workspace/workspace-api-task-time.md, docs/memory/workspace/workspace-task-timer-guards.md
