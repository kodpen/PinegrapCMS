---
name: workspace-flows-run-events
description: Webhook events of flow runs (A7, #2406): workspace.flow.run.finished, .failed, .cancelled, .waiting; where each is announced once, the body, words only in public channels, what is never announced
---

## Which events, from where?
- `includes/workspace/flow_run_events.php`; listed in `ws_webhook_events()` (api.php). Tests: `tests/workspace_flow_run_events_test.php`.
- `finished` (done) and `failed`: `ws_flow_run_announce_end()`, called in `ws_flow_run_refresh()` beside `ws_flow_run_tell_owner()`, only when the end UPDATE took the row (affected rows 1), and in `ws_flow_runs_heal()` for a run that has no start box and fails.
- `cancelled`: `ws_flow_run_announce_cancelled($run_id, $by)` in `ws_flow_run_cancel()` after its UPDATE took the row (else 409 first) and the boxes were skipped. Decision #2406: an event of its own, so `finished` keeps meaning done.
- `waiting`: `ws_flow_run_announce_waiting($step_id, $wait_key)` after the walk, only when the box's own UPDATE from running to waiting took the row: a task box (`ws_flow_step_task()`), a delay (`ws_flow_delay_run()`), a retry wait (`ws_flow_retry_wait()`), an approval (`ws_flow_approval_wait()`). Once per box wait, also when another box keeps the run running (`run.status` says which).

## Why is each move told once?
- The announce follows the conditional write that made the move; a request that lost the race announces nothing.
- Nothing announces: a look that keeps a box waiting (rechecks), a box that stopped half way and waits again for its effect (`ws_flow_steps_stale()` resume) or is made ready for its retry, a wake, the runs a deleted flow cancels (`ws_flow_runs_cancel_ids()`), a start.
- A failed box tried again by hand reopens the run (`ws_flow_step_mend()`); its next end is a new move and is announced again. Each retry wait of a step box is a wait of its own (`for: retry`, attempt).
- The run is read again before sending: an end goes out only if the run still stands in that state; a wait only if the box still waits on the key written and the run is open.
- At most once, not at least once: a request that dies between its write and the announce loses the event (no outbox).

## What does the body hold?
- `id` (the run), `channel_id` (key rule; null for a run of no channel, heard by whole-site subscriptions only), `flow_id`, `flow_name`, `run`: `ws_api_flow_run_present()`'s fields without `steps`, `graph`, `access`, `can_cancel` (one body for all subscribers; the test keeps the fields in step with `ws_api_flow_run_schema()`).
- Cancelled: `cancelled_by` (person id).
- End: `result`, the box the result comes from (`ws_flow_run_result_step()`): failed = the first failed box (as the owner's notice); done = the box that finished last on done, end boxes passed over, the start box only when nothing else ran. Fields as WorkspaceFlowRunStep: node_id, kind, name, status, outcome, task_id, attempt, finished_at, outputs, result.
- Waiting: `waiting` = the box (no outcome, finished_at) plus `for` (task, approval, delay, retry), `task_id`, `approval_id`, `until`.
- There is no run-level result yet: C1 of `plans/workspace-flows-channels.md` defines the run's flag; it may add it to these bodies then.

## What do readers outside the channel not get?
- Words (flow name, box name, sentence, outputs) only for a run of a public channel, as a task note's text (`ws_flow_run_event_words()`); a private channel, guest room or no channel gives ids and states alone. Even in public: the sentence and values a reader without edit reads (`ws_step_result_shown(..., false)`, no change or start-a-flow values).

## Is a flow started or woken by these?
- No: they are not in `pg_event_workspace_events()`, so the workspace never records them and no flow triggers or wakes on another run (no loops). Chaining is the start-a-flow box.
