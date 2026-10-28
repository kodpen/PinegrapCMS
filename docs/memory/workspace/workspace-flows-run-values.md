---
name: workspace-flows-run-values
description: What GET /workspace/flow-runs/{id} gives per box beyond its status (A5, #2284): threads_in as the walk reads them, outputs from the result's data, what a reader without edit does not get
---

## What does a run's box carry?
- `threads_in`: every arrow into the box, `{from, on, taken}`. `ws_api_flow_step_threads_in()` (pure, api_flows.php) reads `taken` as the walk does: the box it comes from is `done` and `ws_flow_edge_takes($on, $outcome)` (any outcome for `always`). A failed box takes no arrow, not even `always`.
- `outputs`: `ws_api_flow_step_outputs()` (pure) reads the result's `data`, which `ws_step_result_data()` wrote per declared output: int, string (cut to `WS_API_FLOW_RESULT_CHARS`) or null; anything else is left out. Answered as a JSON object, `{}` when empty.
- The run's states come from `ws_flow_step_states()` of the run's rows (a later pass of a box stands for it); `ws_api_flow_run_present()` passes them to each box. The listing has no `steps`, so neither field.

## What does a reader without edit not get?
- A change box's `record_id` and a start-a-flow box's `run_id`: their `outputs` are `{}`, the rule `ws_step_result_shown()` applies to their sentence. Other kinds' values (message id, task id, status code, counts) go to every reader of the run.

## Who else reads them?
- The canvas's panel actions answer with the same presenter (actions.php), so the run view has the fields; nothing draws them yet.

## What is left of A5?
- The run's flag (state and one-line result) comes with C1 of `plans/workspace-flows-channels.md`, which defines the run's result (#2385). Retry and skip were already API routes (F5a, #2098).

## How is it tested?
- `tests/workspace_flow_run_values_test.php`.
