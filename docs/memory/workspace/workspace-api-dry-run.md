---
name: workspace-api-dry-run
description: Every Workspace write in the external API takes X-Dry-Run; the panel's *_check() functions it runs, what each rehearsal answers, and the test that keeps new writes rehearsable
---

## What does a rehearsal do on the Workspace endpoints?
- Every POST/PATCH/DELETE route in `includes/workspace/api.php` (and the Claude queue in `api_claude.php`) carries `'dry_run' => true` since PR #481. With `X-Dry-Run: true` or `?dry_run=true` the handler runs the same checks as the real call and answers `200 {"dry_run": true, "would": ..., "resource": ..., "detail": {...}}`; nothing is written. A refusal is the real refusal (same status, code and field).
- The flag also adds the OpenAPI `X-Dry-Run`/`dry_run` parameters and the 200 `DryRun` answer, the console's "Can be rehearsed" line and the MCP tool's `dry_run` argument. No new scope, no new tool.
- Before #481 these 16 routes answered `400 dry_run_unsupported`: tasks create/update/complete, delegate and delegate/stop, recurrence set/end, notes create/update, plan items create/update, changes apply/dismiss, Claude requests claim/answer/fail.

## How is a write made rehearsable?
- The panel function's checks move into a `*_check()` function and the real function calls it first, so the panel and both API calls meet one set of refusals: `ws_task_create_check()`, `ws_task_update_check()`, `ws_task_set_status_check()` (tasks.php), `ws_recurrence_save_check()`, `ws_recurrence_end_check()`, `ws_note_save_check()`, `ws_event_save_check()` (plan items, schedule.php), `ws_task_delegate_check()`, `ws_task_delegation_run_check()`, `ws_task_delegation_stop_check()`, `ws_change_apply_check()`, `ws_change_apply_record_check()`, `ws_change_dismiss_check()`. Earlier ones: see the pinegrap-dis-api skill.
- The handler calls the check, then `api_dry_run_stop()`, then the write. `test_ws_api_every_write_rehearses` fails for a new write route without the flag; `test_ws_api_rehearsal_stops_before_the_write` checks the order.

## Which rehearsals have a catch?
- Change apply: a proposal for one record is laid against the record as it is now (`409 stale` when it moved, but the proposal is NOT marked stale as the real call does). A call of an MCP server's tool and a change to many records (`bulk`) are only checked up to taking the proposal; what they meet while running is not rehearsed.
- Claude answer: rehearsed in all four places (editor, note, task run, channel); the channel answer runs `ws_message_send_check()`. Claim, answer and fail stay for Claude's application only.
- Delegate: the run checks (assistant switched on, one run per task, per-person limit) run in the rehearsal; Claude is not fired.
- Task create detail carries `warnings` (capacity, department), so a client can show them before creating.

**Why:** an integration or an assistant can try a Workspace write without leaving a task, note or decision behind (queue item q14).
