---
name: workspace-flows-webhook-body
description: The webhook box's answer as its body output (D9a, PR #2985, decision #2414-2): 16 KB UTF-8 cut, the run row's result budget, held back from readers who may not change the flow
---

## What does the webhook box keep of the answer?
- `body`: the answer as read, both on success and on an HTTP error; null when nothing answered or the answer is not valid UTF-8 (`ws_scheduled_webhook_body()`, pure). Longer than `WS_SCHEDULED_WEBHOOK_BODY_BYTES` (16384) is cut at a character; the request reads 3 bytes more (`ws_scheduled_webhook_body_fetch()`) so the cut character is seen whole. Tests `tests/workspace_flow_webhook_body_test.php`.

## How does it fit the run row?
- It fits the run row without a schema change: `WS_FLOW_RESULT_BYTES` is 65535, the `ws_flow_run_steps.result` TEXT column (was 16384). `ws_flow_result_pack()` nulls the longest text values first when the JSON is still too large (16 KB of control characters is ~96 KB escaped), so the numbers stay.

## Who sees it?
- Held back from readers who may not change the flow: `ws_step_outputs_held()` (change, start_flow: all; webhook: `body`) through `ws_step_outputs_shown()`, used by `ws_api_flow_step_outputs()` and `ws_flow_run_event_outputs()`; status code and time stay shown.
