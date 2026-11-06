---
name: claude-draft-decide-once
description: A task Claude or Pinegrap AI proposes in a channel (ws_ai_drafts) is accepted or dismissed once: conditional UPDATE on the pending row, checked by affected rows, before the task is opened.
---

## How is a proposed task decided?
- `ws_claude_draft_accept()` runs its read-only checks (channel, clash), then claims the row with `UPDATE … SET status = 'accepted' … WHERE id = … AND status = 'pending'`; no affected row means somebody decided first, and no task is opened.
- Only then `ws_task_create()` runs; the task id is written to the row afterwards.
- A failed task create puts the row back to `pending` (only while it is still `accepted` with `task_id = 0`).
- `ws_claude_draft_dismiss()` dismisses only a pending row, checked the same way.

## What did it fix?
- Two presses at once (a double click, two people) both passed the read `status === 'pending'` and opened two tasks; a dismiss arriving meanwhile overwrote an accepted proposal that already had its task.
- Test: `tests/workspace_claude_draft_decide_once_test.php`.

## What is left open?
- A request killed between the claim and the task create leaves the row `accepted` with `task_id = 0`; nothing reopens it.
