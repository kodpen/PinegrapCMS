---
name: task-app-mark
description: Task application mark (ws_tasks.app_id, #9994): routes that write it, "Made by <application>", the "Made by agents" filter on My Tasks, created_by_app and made_by in the API
---

## What is the mark?
- `ws_tasks.app_id` (step 11.0.230, `upgrade_2026_11_0_ws_task_app()`, index `idx_app (app_id, status)`): the application that created the task, 0 for a person. Owner (2026-11-16, poll 80, `workflow/decisions-made-schema-23.md`): a task made with an application's key read as its owner's.
- It only says who created the task, never who may read or change it. No backfill: earlier tasks read as a person's. Read only after `ws_task_app_ready()`; before the upgrade every task is a person's and the filter is hidden.

## Which routes write it?
- `ws_task_create()` takes `app_id` through `ws_task_app_of()`: a route that knows it names it, otherwise the request's application (`ws_task_request_app()`: an API key or an OAuth connection; 0 for the panel, a scheduled run, a person's own device).
- A flow run an application started keeps `app_id` in its context, passes it to the runs it chains, and its boxes mark the tasks they open. An assistant's proposed task takes the answer's application (`ws_claude_draft_accept()`, `ws_message_app_id()`). A repeating task's copy keeps its template's mark.

## Where is it shown?
- "Made by <application's name>" (robot icon, `.ws-task-made-by`) beside the creator on My Tasks' row, the Planning Board card and the task drawer (`ws_task_app_label()`); a deleted application reads "Made by an application".
- My Tasks' "Made by agents" select: Made by anyone, Made by people, Made by agents (`made_by` '' | people | apps, `ws_task_made_by_sql()`). "Created by me" lists a task an application made with the reader's key only under Made by agents.

## What does the API carry?
- The task object's `created_by_app`: id and name (name null once deleted), null for a person. `GET /workspace/tasks` takes `made_by=people|apps`.
- Tests: `tests/workspace_task_app_mark_test.php`.

## What is not marked?
- A task a person creates in the panel, a scheduled action's run (one no application started) or a task from a message they turn into a task themselves. Who may read a task is unchanged; agent channels are kept apart by the channel, not by the mark (`agent-channel-readers.md`).
