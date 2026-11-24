---
name: workspace-task-timer-guards
description: The task timer's guards (PR #11413): a drawer's Stop stops only its task's timer, one running timer per person, the drawer total over every entry, one repeat series per task
---

## What does the drawer's Stop stop?
- Only a timer running on the drawer's task: the panel action `ws_task_time_stop` passes `task_id` to `ws_task_time_stop()`, which refuses with "Your timer runs on another task now." when it runs elsewhere (`ws_task_time_stops_here()`).
- `/timer stop` (`/süre dur`) names no task and stops it wherever it runs.
- **Why:** a tab drawn before the timer moved to another task stopped that one with a "saved" toast (hunt 28).

## Can a person have two running timers?
- No. `ws_task_time_start()` stops one running elsewhere first, and after its insert stops and keeps any other running row of the person (`ws_task_time_extra_running()`): two tabs starting at the same moment both read none and both inserted.

## What total does the task drawer show?
- The task's whole time, `ws_task_time_totals()`, as the task card and the lists count it; the list itself still stops at 200 rows. Before, the total summed only those 200.

## Can one task get two repeat series?
- No. `ws_recurrence_save()` ties a new series with `ws_recurrence_attach_where()`: only while the task still holds the series it was read with. A save that loses the race deletes its series and answers unchanged.

Related: docs/memory/workspace/workspace-api-task-time.md, docs/memory/workspace/workspace-command-plan-items.md, docs/memory/workflow/bug-hunt-areas-workspace-tasks-time.md
