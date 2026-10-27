---
name: workspace-flows-retry-skip
description: A failed box of a flow run tried again on its own (3d, PR #2275, retry/retry_after) or by hand or skipped (F5a, PR #2098, ws_flow_step_mend()): which boxes, owner only, double click, routes, canvas
---

## Which boxes?
- A box with status `failed` (one that stopped half way too: `ws_flow_stale_action()` fails it; there is no `interrupted` status), of a run running, waiting or failed (`ws_flow_step_mend_refusal()`, pure). Done or cancelled runs: 422. A box the run did not reach: 404 (`node_id`). A box done on a routed `failed` outcome is not failed.

## What does each do?
- Retry: the row back to `ready` (ready_at now, effect, wait_*, outcome, claimed_at, finished_at cleared), so the claim counts a new attempt; then `ws_flow_advance()` for the run, as a start does. A begun step may post or send again (the canvas asks first); a task box opens a new task: `ws_flow_step_task_find()` looks only from ready_at, so a cancelled task of an earlier try is not adopted.
- Skip: status `skipped`; the walk treats it as a branch not taken, so a join with another taken arrow still runs.
- Both: the result says who and the failure sentence ("Tried again by X after: …") until the box settles; a Site Log line. The boxes skipped behind it (`ws_flow_skipped_behind()`, pure) are deleted where `attempt = 0` (written by the walk, never run), the run set running (finished_at 0) from running/waiting/failed, then `ws_flow_run_refresh()` writes them again. A run that ends again tells the owner again.

## Why never twice?
- The box UPDATE is `AND status = 'failed'` with affected rows counted before anything else: a second click gets 409.

## Who may?
- The flow's owner only (`ws_flow_share_manager()`, 403 before the boxes are read): the box runs in their name and rights, as publish and box try. An edit share or the run's starter may cancel a run but not retry or skip a box.

## Where is it offered?
- Engine `ws_flow_step_mend_check()` / `ws_flow_step_mend($viewer, $run, $node_id, 'retry'|'skip')` in `flow_runs.php`.
- API `POST /workspace/flow-runs/{id}/steps/{node}/retry` and `/skip` (`workspace.flow_runs.step_retry`, `step_skip`; `workspace:write`, dry run answers `boxes_walked`, `'mcp' => false`, `ws_api_is_assistant()` refused first, `ws_api_flow_run_or_404()`), answer WorkspaceFlowRun.
- Panel action `ws_flow_step_mend` (`run_id`, `node_id`, `how`), listed in `includes/panel/actions.php`.
- Canvas: `stepDetail()` in `workspace_flow.js` shows "Try again" and "Skip" on a failed box while `state.flow.manage`; `mendStep()` asks, disables both, applies the run and polls again. Not in the outline view.
- Tests: `tests/workspace_flow_mend_test.php`.

## Does a box try again on its own?
- A step box set to (PR #2275): `retry` 0-7 more tries (`WS_FLOW_RETRY_MAX`, 8 in all: the depth a scheduled action starting itself again stops at) and `retry_after` minutes (1 to `WS_FLOW_DELAY_MAX`), kept by `ws_flow_config_normalize()` for step kinds only; tries without minutes are a `bad_config` problem (`ws_flow_retry_problems()`).
- `ws_step_run()` sends each failure to `ws_flow_retry_wait()` first: while `attempt <= retry` (`ws_flow_retry_until()`) the box goes running → waiting with effect cleared (a try may post again), wait_key `retry:<until>`, wake_at; tries used, it settles as before (arrow on failed or always, else the run fails).
- `ws_flow_step_recheck()` → `ws_flow_retry_recheck()`: at the moment the box is ready again (from waiting only), the claim counts the attempt. Canvas `retryFields()`; tests `tests/workspace_flow_retry_test.php`.
