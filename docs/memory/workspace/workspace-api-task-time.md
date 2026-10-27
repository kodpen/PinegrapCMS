---
name: workspace-api-task-time
description: Task time over the Workspace API (PR #2288): GET /workspace/time (own rows across tasks, range, hidden task, running timer, total) and POST /workspace/tasks/{id}/time/{entry_id}/update
---

## Which routes give task time?
- Per task: `GET/POST /workspace/tasks/{id}/time` and the delete `DELETE /workspace/tasks/{id}/time/{entry_id}` (POST accepted too, for IIS).
- Across tasks: `GET /workspace/time?from=&to=` (`tasks:read`, since #2288), the owner's own rows as My time reads them.
- Changing one entry: `POST /workspace/tasks/{id}/time/{entry_id}/update` (`tasks:write`, dry run, since #2288). It has its own path because a POST on the entry deletes it, as `POST /webhooks/{id}/update` beside `POST /webhooks/{id}`.
- Starting and stopping a timer is not in the API.

## What does GET /workspace/time answer?
- Only the caller's rows, newest day first (`worked_on DESC, id DESC`), stopped entries and the running timer alike (`running: true`, `minutes: 0`, on the day it started).
- `from`/`to` are site days, both included, 92 days at most (as plan items); left out, `to` is today and `from` the Monday of `to`'s week. A bad day, `to` before `from` or more than 92 days is 422 on the field (`ws_task_time_range_check()`).
- Each row is `WorkspaceTaskTime` plus `task` (`WorkspaceTimeTask`: id, title, visible). A task the caller can no longer see has an empty title and `visible: false`: the same `ws_task_time_seen_task()` the panel's `ws_task_time_mine()` asks.
- Cursor paged, `limit` up to 250 (default 250); the cursor is the day as `Ymd` and the id (`ws_task_time_position_pack()`). `total_minutes` sums the whole range, not the page.

## Who may change an entry, and how?
- Whoever may delete it: their own entry, any for staff (`ws_task_time_is_manager()`); otherwise 403.
- 422 on `entry_id` for an entry on an ERP invoice draft and for a running timer.
- Only the fields sent change (`minutes`, `worked_on`, `note`, `billable`); `note` null or `''` clears it; `worked_on` cannot be emptied nor be a day to come.
- `ws_task_time_update_check()` (task_time.php) holds the rules; `ws_task_time_update()` runs it and writes only while the row is still off any invoice and not running. The panel has no edit yet; it would call the same pair.
- Not announced as an event (`workspace.task.time_logged` is for new time only), so the flows parity table has no line for it. Site Log: "time was changed on workspace task (N)".

**Why:** a team device client's My day needs the week's own time with the timer across tasks, and a mistyped entry could only be deleted and written again (#2080).
