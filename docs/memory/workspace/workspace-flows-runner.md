---
name: workspace-flows-runner
description: Workspace flow runs (F3a, flow_runs.php, no schema change): start, the run's own channel, the walk, task boxes, wake-ups, half-way recovery, drivers, limits, end, cancel, API routes
---

## Where is it?
- `includes/workspace/flow_runs.php`; handlers at the end of `api_flows.php`; tests `tests/workspace_flow_runs_test.php`. Columns of 11.0.80 only. Panel actions: `workspace-flows-canvas.md`; the run view: `workspace-flows-run-view.md`.

## How does a run start?
- `ws_flow_run_start_check()` / `ws_flow_run_start()`: run or edit access, then the token (`start_key` = `ws_flow_start_key()`, manual: + sha1: the column ignores case) answers its run (200) before the live, manual-start, owner-on-staff, 500-open and 60-an-hour refusals. The run row is `INSERT … SELECT` from a live flow. `context` (once): trigger, box, the flow's `channel_id` and `flow_name` then.

## Which channel is a run's?
- The one in its context, never the flow's home now: a draft save changes the home at once. Its tasks open there; non-owners read it (`ws_flow_run_access()`) only while they read that channel. An application key or OAuth connection may not move a published flow's home (`ws_api_flow_home_move_gate()`).

## How does it move?
- Pure `ws_flow_walk()`: a box whose arrows in have all settled is ready when one was taken and none comes from a blocked box, else skipped. Blocked = failed, or skipped behind a blocked box, worked out from the statuses on every walk, so a join any number of boxes behind a failure is skipped. `ws_flow_run_state()`: running, waiting, done, failed.
- Conditional UPDATEs, affected rows counted; next boxes `INSERT IGNORE` on (run, node, pass 0).

## What does a task box do?
- Owner's rights now; `ws_task_create()`, due = ready day + `due_in`, last line `ws_flow_task_marker()` (flow name of the context, run, `ws_flow_box_unique_name()`: label, with the id when shared). One UPDATE: effect and wait_key `task:<id>`, waiting, wake_at +1 h.

## How is a box woken?
- `ws_events_awaited()` adds waiting `wait_event`s; `ws_events_process()` calls `ws_flow_wake()`: done → done; cancelled or gone → `cancelled` where an arrow leaves on it, else failed. A passed `wake_at` rechecks; a box of an ended run is let go.

## What if a request dies half way?
- Running over 600 s: effect → waits again; none → one retry, which takes the task whose description ends with its marker, held by no other box of the run (LIKE escapes `\ % _`), and looks at it at once; then failed (no retry or skip by hand before F5).
- `ws_flow_step_settle()` marks the run running first (`ws_flow_run_touch()`); `ws_flow_runs_heal()` walks again a run running but untouched for 600 s, writing a missing start box. `ws_flow_due()` sees it.
- A box of a deleted flow's run cancels the run (`ws_flow_runs_cancel_ids()`); deleting cancels every open run.

## Drivers and limits?
- `ws_scheduled_run($limit, $flow_budget)` calls `ws_flow_advance()` before its early returns: 10 s on a screen, 25 s in the jobs; 3 boxes a run, 30 a call. `ws_scheduled_due()` asks `ws_flow_due()`.
- 200 boxes a run, result 16 KB JSON `{text, data}`, ended runs swept after 30 days (`ws_flow_runs_sweep()`, recurring job).

## End and cancel?
- End: the owner told once, inbox `flow_finished`, a line of its own per run (`ws_notify()` `separate`: its ids are all 0), sentence in `ws_scheduled_notices`.
- Cancel: flow editors and the starter; open boxes skipped, tasks stay.

## Which routes?
- GET/POST `/workspace/flows/{id}/runs`, GET `/workspace/flow-runs/{id}` (with `channel_id`), POST `/cancel`. Writes rehearse, carry `'mcp' => false` and refuse `ws_api_is_assistant()`: Claude's routine calls REST. Claude's key reads others' flows and runs only via channels Claude may be asked in (`ws_api_flow_reachable()`).
