---
name: workspace-flows-runner
description: Workspace flow runs (F3a, flow_runs.php, no schema change): start, the walk, task boxes that wait for their task, wake-ups, drivers, budgets, limits, end, cancel, API routes
---

## Where is it?
- `includes/workspace/flow_runs.php`; handlers at the end of `api_flows.php`; tests `tests/workspace_flow_runs_test.php`. Columns of 11.0.80 only. No panel action or JavaScript yet (run view: F3b).

## How does a run start?
- `ws_flow_run_start_check()` / `ws_flow_run_start()`: live flow with a version, run or edit access, a `manual` start box, owner still staff, under 500 open runs and 60 starts an hour. `start_key` `manual:<token>` (else random): a token used before answers its run (200). Writes the run (`actor_id` = owner), the start box done on `next`, then advances that run so the first task exists at once. `subject_*` stay empty until F4.

## How does it move?
- Pure `ws_flow_walk()`: a box whose arrows in have all settled is ready when one was taken and no box before it failed, else skipped (passed on), so a join fires once. `ws_flow_run_state()`: running, waiting, done (no box failed: an end reached or the branches ran out), failed. A failed branch lets a parallel one finish.
- Every move is a conditional UPDATE with affected rows counted; next boxes `INSERT IGNORE` on (run, node, pass 0).

## What does a task box do?
- Owner's rights now; `ws_task_create()` in the home channel (if any), due = day it became ready + `due_in`, description ending "Opened by the flow “X”, run N.". Named people as they are (a refusal fails the box); a rule as templates resolve it, `creator` = the starter, unfound or not assignable = nobody on it.
- One UPDATE: `effect`/`wait_key` `task:<id>`, `waiting`, `wait_event` `workspace.task.status_changed`, `wake_at` +1 h.

## How is a box woken?
- `ws_events_awaited()` adds waiting `wait_event`s (forgotten when a box starts waiting); `ws_events_process()` calls `ws_flow_wake()`, which reads the task: done → done; cancelled or gone → outcome `cancelled` with an arrow on it, else failed. A passed `wake_at` rechecks (lost events), then +1 h.
- Running over 600 s: effect → waits again; none → one retry (finds the first try's task by owner, title, run line), then failed. Why: no retry or skip by hand before F5.

## Drivers and limits?
- `ws_scheduled_run($limit, $flow_budget)` calls `ws_flow_advance()` before its early returns: 10 s on a screen, 25 s from job.php and workspace_recurring_job.php; 3 boxes a run, 30 a call. `ws_scheduled_due()` asks `ws_flow_due()`.
- 200 boxes a run, result 16 KB JSON `{text, data}` (data for F4), ended runs swept after 30 days (`ws_flow_runs_sweep()`, recurring job).

## End, cancel, delete?
- End: the owner told once, inbox kind `flow_finished`, sentence in `ws_scheduled_notices` (action_id 0), link workspace_flows.php. Starter not told.
- Cancel: flow editors and the starter; open boxes skipped, tasks stay. Deleting a flow cancels its runs; pausing stops starts only.

## Which routes?
- GET/POST `/workspace/flows/{id}/runs`, GET `/workspace/flow-runs/{id}` (steps, graph of its version), POST `/workspace/flow-runs/{id}/cancel`. Writes rehearse and carry `'mcp' => false`: an AI app must ask the person first (MCP plan). Runs are read by whoever reaches the flow, and by their starter while the home channel is still theirs.
