---
name: workspace-flows-outputs
description: Values a flow box gives later boxes (data-passing D1): outputs per kind, ws_step_outputs(), ws_step_result_data(), null rules, report counts, canvas config
---

## Where are they declared?
- `ws_step_kinds()` gives each step kind `outputs` (key, type, label; `mode` on web_check ones); `ws_flow_kinds()` carries them for every kind (the waiting `task` box: `task_id`; start, condition, end: none). Types: `ws_step_output_types()` (number, text). Tests `tests/workspace_flow_outputs_test.php`.
- Per kind: post, report, checkin `message_id`; email `sent`; notify `told`; task_open and task `task_id`; webhook `status_code`, `duration_ms`; web_check status mode `status_code`, `duration_ms`, ssl mode `days_left`; task_digest `sent`, `tasks`; change `record_id`; lead `channel_id`; start_action none.
- The webhook's answer body is not an output yet (open question 2 of the data-passing plan).

## How does a box write them?
- `ws_step_outputs($kind, $config, $labels)` is the box's list: a web check's mode only; a report adds one number per count it sends, keyed `<metric>` or `<metric>_<param>` (`tasks_open_4`), labelled from `$labels` (pass `ws_scheduled_metrics()` labels; pure without them). These are the "counts as thread outputs" of the scheduled parity.
- `ws_step_result_data()` (pure) builds `result.data` from `ws_scheduled_do()`'s answer: ids at the top (`message_id`, `task_id`, `channel_id`), the rest under its `data`, a report's under `data.counts` in metric order. Every declared key is written: a value the step did not give is null, an id of 0 is null.
- The scheduled functions answer `data` (email, notify, task digest, webhook, web and ssl check, summary, change); scheduled actions ignore it.
- A box refused before its work (check, starter) writes no data; the task box writes `task_id` as it waits and as it settles.
- `ws_step_node_values()` reads scalars only, so a null fills `{{node.*}}` as ''.

## What does the canvas get?
- `ws_flow_js_config()` `outputs`: kind => declared list (report: `message_id` only; its counts follow from `steps.metrics` and the box config). Used by the reference check (D4) and the value picker (D6); no canvas UI reads it yet.
