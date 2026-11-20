---
name: workspace-api-task-links
description: A task's links over the Workspace API (#10343): links on GET /workspace/tasks/{id} in the task drawer's shape, bare ids on the lists, null without the table
---

## What does GET /workspace/tasks/{id} answer about links?
- `links` (object `WorkspaceTaskDetail`, #10343): `ws_task_links_detail()`, the same function the panel's task drawer reads: `blocked_by` (the tasks it waits for, at most `WS_TASK_LINK_MAX` 20), `blocks` (the tasks waiting for it, no cap), `can_edit` (`ws_can_edit_task()`), `max`.
- A linked task: `id`, `number`, `title`, `status`, `status_label`, `open`, `visible`. A task the caller cannot see keeps number and status, with `title` `''` and `visible` false; a deleted one has `status` `''` and `open` false.
- `links` is null while the links table is missing (`ws_task_links_ready()`).

## Why not just the ids?
- A team device client had only `blocked_by` / `blocks` as ids; drawing one row cost a GET per linked task, and a hidden task answered 404, so no status could be shown where the panel shows one.

## What do the lists answer?
- `GET /workspace/tasks` and every other route returning `WorkspaceTask` keep `blocked_by` and `blocks` as bare ids (one read for the page, `ws_task_links_map()`); `links` is only on the single task.
- `tests/workspace_api_plan_test.php` checks the schema declares every key `ws_task_links_detail()` answers.
