---
name: workspace-flows-wait-timeout
description: The timeout of a flow task box (F5d): hours to wait, the timeout outcome, counted from the claim, next look capped at the deadline, the task end first, why approvals have none
---

## What is it?
- Task box setting `timeout`: '' (waits as long as it takes, the default, so published flows keep working) or 1 to `WS_FLOW_TIMEOUT_MAX` = 720 hours, clamped by `ws_flow_config_normalize()`. Outcome `timeout` added to the task kind (done, cancelled, timeout). Helpers in `includes/workspace/flow_runs.php`; tests `tests/workspace_flow_timeout_test.php`.
- 720 hours is the approval box's deadline (`WS_STEP_APPROVAL_CLOSES_MAX`); hours as there.
- Publish problem (`ws_flow_timeout_problems()`, `bad_config`): an arrow on timeout from a box without a timeout.

## When does it run out?
- `ws_flow_timeout_at($config, $since)`: the box's claim (`claimed_at`) + hours. No new column: `wake_at` stays the next look, `ws_flow_timeout_next_look()` = the hourly look or the deadline, whichever is sooner. A box tried again counts from its new claim.
- `ws_flow_step_task()` writes the first look with it and adds "leaves on timeout at …" to the result; `ws_flow_step_recheck()` brings a look the hourly pass moved past the deadline back to it (`AND status = 'waiting' AND wake_at > deadline`).

## How does it settle?
- `ws_flow_step_recheck()` reads the task first: done, cancelled or gone wins even after the deadline. Still open and `ws_flow_timed_out()`: `ws_flow_timeout_settlement()` = done/timeout where an arrow leaves on timeout, else failed/timeout (the run fails), as a cancelled task without its arrow. The task stays open; its later status event finds no waiting box.
- Event and timeout never both win: both go through `ws_flow_step_settle()`, whose UPDATE holds only `AND status = 'waiting'`.

## Which boxes have it?
- The task box only. The approval box already has its deadline (`closes_in` → `expired`, which also closes the request, so nobody decides after the flow moved on); a delay box is a timeout itself.

## Try and canvas?
- Try: "…waits until it is done or cancelled, at most N hours."
- `workspace_flow.js`: task `out` gains `timeout` (label `fl_on_timeout` "Not done in time"), `taskFields()` a Timeout (hours) number, meta `fl_timeout_meta`.
