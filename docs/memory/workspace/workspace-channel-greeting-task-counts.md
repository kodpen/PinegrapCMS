---
name: workspace-channel-greeting-task-counts
description: How a channel's greeting counts its open tasks (ws_channel_task_counts()): a 0000-00-00 due date is no due date, as on the dashboard and Today
---

## How does the channel greeting count its open tasks?
- `ws_channel_briefing()` counts the channel's open tasks (todo, doing, waiting) with the pure `ws_channel_task_counts()`: overdue, due today, waiting, and the reader's own.
- A due date of NULL or `0000-00-00` is no due date: neither late nor due today. The dashboard greeting (`ws_dashboard_signals()`), Today (`ws_home()`, `ws_home_due_pick()`) and `ws_task_due_label()` read it the same way.

## What did it fix?
- An undated task stored as `0000-00-00` compared below today as a string, so the greeting said "The one open task here is past its due date", the catch-up line offered "an overdue task" and the advice was "Look at the late tasks" while the task list showed "No due date".

## Where is it tested?
- `test_workspace_channel_task_counts_leave_an_empty_due_date_out` (tests/workspace_channel_catchup_test.php).
