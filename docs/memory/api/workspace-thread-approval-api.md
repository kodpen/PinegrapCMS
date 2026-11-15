---
name: workspace-thread-approval-api
description: The API routes that conclude or rename a discussion and take back or close an approval request: the panel function each runs, the check it shares for the dry run, who may and the refusals
---

## Which routes, and which panel function does each run?
- `POST /workspace/threads/{id}/close` (`workspace.threads.close`, tool, dry run): `ws_thread_close()` (`includes/workspace/threads.php`), as Conclude in the discussion's menu.
- `POST /workspace/threads/{id}` (`workspace.threads.update`, also PATCH, tool, dry run): `ws_thread_rename()`, as Rename; `title` at most `WS_THREAD_TITLE_MAX` (80).
- `POST /workspace/approvals/{id}/close` (`workspace.approvals.close`, tool, dry run): `ws_approval_close()` (`includes/workspace/approvals.php`), as Take back and Close on the approval card; outcome `withdrawn`.
- Routes and handlers live in `includes/workspace/api_thread_close.php`, merged into `ws_api_routes()`. `id` of a discussion is `thread.id` of its message; it is found with `ws_api_thread_room_or_404()`.

## Who may, and what does a refusal answer?
- The panel function's own rule, checked by a `*_check()` it shares: `ws_thread_close_check()`, `ws_thread_rename_check()`, `ws_approval_close_check()`. No person-only gate: an application key acts as its owner.
- Conclude: the owner must be in the discussion, else 403; already concluded is no error (`concluded` in the dry run's detail).
- Rename: the owner must be able to write in the discussion (in it, not concluded), else 403; an empty title 422 on `title`.
- Approval: already closed 422; not the asker and of the User role (3) 403; the channel unread 404.
