---
name: workspace-api-task-reminder
description: A task's due time and e-mail reminder over the Workspace API and MCP (PR #1742): due_time, remind_minutes on the task writes and answers, how null clears them, the drawer's checks they go through
---

## Which routes take them?
- `workspace.tasks.create` and `workspace.tasks.update` (so their MCP tools): `due_time` (HH:MM) and `remind_minutes` (0-86400, minutes before the task is due), both nullable. Every `WorkspaceTask` answer carries them, null when unset or on a site whose `ws_tasks` lacks the columns.
- Before #1742 only the task drawer set them: an application could give a task a date but no time, and no reminder.

## How are they checked?
- `ws_api_task_input()` hands them to `ws_task_validate()`, which runs the drawer's `ws_task_reminder_validate()`: a time the drawer refuses, a reminder over 60 days or a reminder without a due date answers 422 on that field. The reminder moment is set again by `ws_task_reminder_sync()`, as from the drawer.
- null or an empty value clears either (the router turns null into ''; `ws_task_time_or_null()` and `ws_task_remind_minutes_or_null()` read '' as none). Left out, they are left alone.

## Why are the limits written out in the route row?
- The row's description and `max` repeat `WS_TASK_REMIND_DAY_TIME` (09:00) and `WS_TASK_REMIND_MAX` (86400) as literals; tests/workspace_api_task_reminder_test.php fails if they drift from the constants.
