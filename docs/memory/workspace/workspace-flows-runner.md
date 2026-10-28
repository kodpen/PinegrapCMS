---
name: workspace-flows-runner
description: Workspace flow runs (flow_runs.php): start, run channel, walk, task boxes, wake-ups, recovery, drivers, limits, end, cancel, routes
---

## Where is it?
- `includes/workspace/flow_runs.php`; handlers end `api_flows.php`; tests `tests/workspace_flow_runs_test.php`. 11.0.80 columns only. Panel, run view: workspace-flows-canvas.md, workspace-flows-run-view.md.

## How does a run start?
- `ws_flow_run_start_check()` / `ws_flow_run_start()`: run or edit access, then the token (`start_key` = `ws_flow_start_key()`, manual: + sha1: the column ignores case) answers its run (200) before the live, manual-start, owner-on-staff, 500-open and 60-an-hour refusals. Paused or a view share: the token's run answers to its readers (`ws_flow_run_for_token()`), else 404 / 403. The run row is `INSERT … SELECT` from a live flow. `context` (once): trigger, box, the flow's `channel_id` and `flow_name` then.

## Which channel is a run's?
- Its context's, never the home now (a draft save moves it at once). Its tasks open there; non-editors read it (`ws_flow_run_access()`) while they read that channel; a starter's own run: `workspace-flows-shares.md` (`ws_flow_run_own_reach()`). A key or OAuth connection may not move a published flow's home (`ws_api_flow_home_move_gate()`).

## How does it move?
- Pure `ws_flow_walk()`: a box whose arrows in have all settled is ready when one was taken and none comes from a blocked box, else skipped. Blocked = failed, or skipped behind a blocked box: a join however far behind a failure is skipped. `ws_flow_run_state()`: running, waiting, done, failed.
- Conditional UPDATEs, affected rows counted; next boxes `INSERT IGNORE` on (run, node, pass 0).

## What does a task box do?
- Owner's rights now; `ws_task_create()`, due = ready day + `due_in`, last line `ws_flow_task_marker()` (context's flow name, run, `ws_flow_box_unique_name()`: label, plus id when shared). One UPDATE: effect and wait_key `task:<id>`, waiting, wake_at +1 h.

## How is a box woken?
- `ws_events_awaited()` adds waiting `wait_event`s; `ws_events_process()` calls `ws_flow_wake()`: done → done; cancelled or gone → `cancelled` if an arrow takes it, else failed; timeout → `timeout`. A passed `wake_at` rechecks; an ended run lets it go.

## What if a request dies half way?
- Over 600 s running: effect → waits again; none → one retry, adopting the task ending with its marker that no other box of the run holds, looks at it at once; then failed (`workspace-flows-retry-skip.md`).
- `ws_flow_step_settle()` marks the run running first (`ws_flow_run_touch()`); `ws_flow_runs_heal()` walks again, 600 s untouched, a running run (missing start box written; in `ws_flow_due()`) and a waiting one with a box ended at or after its `updated_at` (an early walk wrote waiting over the mark; not in `ws_flow_due()`).
- A box of a deleted flow's run cancels it (`ws_flow_runs_cancel_ids()`); deleting cancels every open run.

## Drivers and limits?
- `ws_scheduled_run($limit, $flow_budget)` calls `ws_flow_advance()` before its early returns: 10 s on a screen, 25 s in jobs; 3 boxes a run, 30 a call (`ws_flow_advance_boxes()`). `ws_scheduled_due()` asks `ws_flow_due()`.
- 200 boxes a run, result 16 KB JSON `{text, data}`, ended runs swept at 30 days (`ws_flow_runs_sweep()`, recurring job).

## End and cancel?
- End: the owner told once, inbox `flow_finished`, a line per run (`ws_notify()` `separate`, ids 0), sentence in `ws_scheduled_notices`; webhooks: `workspace-flows-run-events.md`.
- Cancel: flow editors and the starter; open boxes skipped, tasks stay. A task box rereads the run before `ws_task_create()`; a task opened mid-cancel goes on its box if skipped or failed (a ready one's retry finds it).

## Which routes?
- GET/POST `/workspace/flows/{id}/runs`, GET `/workspace/flow-runs/{id}` (with `channel_id`), POST `/cancel`, `/steps/{node}/retry|skip`. Writes rehearse, `'mcp' => false`, refuse `ws_api_is_assistant()`. Claude's key: `workspace-flows-shares.md` (`ws_api_flow_reachable()`, `ws_api_flow_run_reachable()`).
