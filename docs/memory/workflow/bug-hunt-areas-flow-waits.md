---
name: bug-hunt-areas-flow-waits
description: The bug hunt in Workspace flows that wait, the run side (architect 2026-11-26, request 3068): one fault fixed (the claim time a waiting box counts from), what was read clean, what was left
---

## Which area was it?
- The run side of flow boxes that wait: approval, MCP asking first, Pinegrap AI, Wait (delay), sending hours, retry waits, the task timeout. Code: `includes/workspace/flow_runs.php` (claim, settle, wake, hourly look, stale pass), `flow_ai_box.php`, `flow_send_hours.php`, `approvals.php` (expiry, deciding), `steps.php` (`ws_step_run()`'s ask and wait).
- Memory read first: `workspace/workspace-flows-approval-box.md`, `workspace-flows-ai-box.md`, `workspace-flows-delay-box.md`, `workspace-flows-delay-time.md`, `workspace-flows-mcp-box-writes.md`, `workspace-flows-wait-timeout.md`, `workspace-flows-run-events.md`.

## What was fixed?
- A Pinegrap AI box (and an Ask an agent box) counted its first look from the row as read before the claim (`claimed_at` 0 on a first try): the look lay in 1970, the next tick looked at it at once and queued a second model call beside the one on its way, spending the quota twice. `ws_flow_advance()` now runs the box as `ws_flow_step_claimed($row, time())` leaves it (running, try counted, claimed now). Test: `test_workspace_flow_ai_box_wait_counts_from_this_claim`.

## What was read clean?
- Event and hourly look at the same moment: every settle goes through `ws_flow_step_settle()` from `waiting` only; an MCP or change box made ready by `ws_flow_approved_ready()` from its own wait key only; the hourly look takes a box by moving `wake_at` conditionally first.
- A run cancelled or a flow binned while a box waits: the look skips the box, the request stays open; republished flows: the run reads its own version's graph.
- Approval decided twice (pending first, `closed_at = 0` first), by someone who left the channel (`ws_can_post_channel()` in the check), after `closes_at` (expires then).
- Deadlines: approval `closes_in` '' is none; AI `closes_in` 0 reads 1 hour; the AI and task next look capped at the deadline; a Wait date already past settles at once or ends the run as set; time of day and sending hours in the site's zone across clock changes.
- A failed or expired wait: the run fails once through the end UPDATE, so the owner's bell line and the webhook come once; a settled box is never looked at again.

## What was left?
- The `workspace.flow.run.waiting` body gives `for: ''` for a box waiting on Pinegrap AI, an agent or a table's owner (`ws_flow_run_waiting_for()` names task, approval, delay, retry, hours only): a new value of a public event field is an API choice, asked in the PR.
- An AI answer asked before the deadline that arrives after it still settles the box done; the look at the deadline fails a box whose call is still on its way. Both kept: the call was made in time.
- Approval deadlines wait for the Workspace to be on (`automation-flows-gate.md`); the agent request queue and wakes were not read.
