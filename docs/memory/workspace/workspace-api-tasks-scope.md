---
name: workspace-api-tasks-scope
description: Which Workspace API reads need tasks:read for the tasks they carry (my-day, search, channel context, record refs, customer history), and what they answer without it
---

## Which Workspace reads hand out tasks, and under which scope?
- Every read that carries tasks needs `tasks:read` for them; `workspace:read` alone opens channels, messages and notes, never the tasks filed there.
- GET /workspace/my-day: tasks, timer and plan items empty, `left_out` names `tasks:read`.
- GET /workspace/search: the tasks group is dropped (403 when only tasks were asked for).
- GET /workspace/channels/{id}/context: `open_tasks` empty and not read (`ws_api_channel_context_tasks()`), `left_out` names `tasks:read`.
- GET /workspace/refs: `tasks` (ids) empty.
- GET /workspace/channels/{id}/customer/history: `history_tasks` false (`workspace-customer-history.md`).

## Why does it matter for MCP?
- The channel context is the `channel_context` resource and is embedded by the `channel_summary` prompt; the refs route is the `record_refs` resource (`api/api-mcp-server.md`). An OAuth client or key granted `workspace:read` without `tasks:read` read every open task's title, assignees and due date of a channel through them until the bug-hunt PR on this branch (`bughunt-ws-context-tasks-scope`); the refs route listed task ids.

## What guards it?
- `tests/workspace_context_tasks_scope_test.php`: the gate reads nothing without the scope, the declaration matches the presenter, both routes ask for the scope.
