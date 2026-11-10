---
name: claude-draft-decide-once
description: A task Claude or Pinegrap AI proposes in a channel (ws_ai_drafts) is decided once: conditional claim checked by affected rows; a claim left with no task opens again after 10 minutes, never twice
---

## How is a proposed task decided?
- `ws_claude_draft_accept()` runs its read-only checks (channel, clash), then claims the row with `UPDATE … SET status = 'accepted', decided_by, decided_at = now … WHERE id = … AND ws_claude_draft_open_sql()`; no affected row means somebody decided first, and no task is opened.
- Only then `ws_task_create()` runs; the task id is written to the row right after it, before the card is sent.
- A failed task create puts the row back to `pending` (only while it is still `accepted` with `task_id = 0`).
- `ws_claude_draft_dismiss()` dismisses only an open row, checked the same way.

## What is an open row?
- `ws_claude_draft_open()`: pending, or stuck. Stuck (`ws_claude_draft_stuck()`, pure): `accepted`, `task_id = 0`, `decided_at` at least `ws_claude_draft_stuck_after()` (600 s) ago, far past any request's max_execution_time. A younger claim is left alone: its request may still be opening the task, and it is never raced.
- The claim moves `decided_at` to now, so of two presses on a stuck row only one changes it.
- `ws_claude_drafts_map()` gives a stuck row `status` pending, no `decided_by` and `can_decide`, so the card's existing Open and Dismiss buttons show; workspace.js is unchanged.
- The dashboard's AI card lists only `status = 'pending'` rows as chips (includes/dashboard/v2/cards/ai.php), so a stuck row is not a chip there; it is decided on its channel card.

## How is a second task avoided?
- The dead request may have opened the task: `ws_task_create()` writes it first, then tells people and announces it. `ws_claude_draft_orphan_task()` looks for it after the new claim and before any create: the proposal's channel, `creator_id` = the old `decided_by`, the same title, `created_at` within the old claim's 600 s.
- Found: no new task; its id goes to the row, and the card is sent only if no `ws_messages` task card for it exists.
- Dismiss on a stuck row whose task is found links the task instead (the row stays accepted, the card says "Opened as") and answers "Somebody has already decided": the proposal was accepted, and dismissing would hide a real task. With no task found it is dismissed.

## What did it fix?
- Two presses at once (a double click, two people) both passed the read `status === 'pending'` and opened two tasks; a dismiss arriving meanwhile overwrote an accepted proposal that already had its task.
- PR #8583: a request killed between the claim and the task left the row `accepted` with `task_id = 0` for good. The card showed "Opened as" with an empty number, whose link tried to open task 0, and the proposal could not be accepted or dismissed again.
- Test: `tests/workspace_claude_draft_decide_once_test.php` (pure stuck/open/SQL helpers; source order: claim, affected rows, orphan search, then `ws_task_create(`).
