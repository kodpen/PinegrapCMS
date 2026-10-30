---
name: workspace-flows-outputs
description: Values a flow box gives later boxes (data-passing D1-D3): outputs per kind, ws_step_outputs(), ws_step_result_data(), null rules, one-pass fill, escaping per destination
---

## Where are they declared?
- `ws_step_kinds()` gives each step kind `outputs` (key, type, label; `mode` on web_check ones); `ws_flow_kinds()` carries them for every kind (the waiting `task` box: `task_id`; delay: `waited_until`; start, condition, end: none). Types: `ws_step_output_types()` (number, text). Tests `tests/workspace_flow_outputs_test.php`.
- Per kind: post, report, checkin `message_id`; email `sent`; notify `told`; task_open and task `task_id`; webhook `status_code`, `duration_ms`, `body` (text); web_check status mode `status_code`, `duration_ms`, ssl mode `days_left`; task_digest `sent`, `tasks`; change `record_id`; lead `channel_id`; mcp `result` (text), `duration_ms`; row_add, row_set `row_id` (held from non-editors, `workspace-flows-table-boxes.md`); start_action none.

- The webhook's `body` (D9a, PR #2985): `workspace-flows-webhook-body.md`.

## How does a box write them?
- `ws_step_outputs($kind, $config, $labels)` is the box's list: a web check's mode only; a report adds one number per count it sends, keyed `<metric>` or `<metric>_<param>` (`tasks_open_4`), labelled from `$labels` (pass `ws_scheduled_metrics()` labels; pure without them).
- `ws_step_result_data()` (pure) builds `result.data` from `ws_scheduled_do()`'s answer: ids at the top (`message_id`, `task_id`, `channel_id`), the rest under its `data`, a report's under `data.counts` in metric order. Every declared key is written: a value the step did not give is null, an id of 0 is null.
- The scheduled functions answer `data` (email, notify, task digest, webhook, web and ssl check, summary, change); scheduled actions ignore it.
- A box refused before its work (check, starter) writes no data; the task box writes `task_id` as it waits and as it settles.
- `ws_step_node_values()` reads scalars only, so a null fills `{{node.*}}` as ''.

## Can a value be read again as a placeholder?
- No (D2, PR #2121): `ws_step_fill_value()` puts a word joiner (U+2060) after every `{` of a value, so `ws_scheduled_fill()` (one `strtr()`, counts read from the text as written) cannot match it. Before, a typed `{{customer}}` or `{{node.…}}` was expanded by the next filler.

## How is a value escaped for where it lands?
- D3 (PR #2293): `ws_step_fill_target($kind, $action)` names the destination, `ws_step_fill_value()` fills by it. Tests `tests/workspace_flow_fill_test.php`.
- message (post, task_open, the waiting task's try, report, checkin, approval): a `<` gets a word joiner, so `<@user:N>`/`<#order:N>` in a value is text; before it mentioned people and tagged records. The builder's own tags stay. `ws_render_body()` escapes the HTML.
- chat (webhook format text: Slack, Teams, Google Chat): `<` too (`<!channel>`, `<url|label>`); discord: `<` and `@everyone`/`@here`.
- text (e-mail box, plain text; task e-mail; notify; generic webhook): as given. The task e-mail escapes subject and intro where it writes its HTML (`h()`, `nl2br(h())`); `json_encode()` makes the text one JSON string (values come from `json_decode()`, valid UTF-8). Escaping in the fill would show `&amp;lt;`.
- json (MCP arguments, a table box's cells and row): the inside of a JSON string, nothing added.
- Line fields (`lines` of `ws_step_kinds()`): control characters and line breaks become a space after the fill. Addresses are never filled (decided exception #3410, unbuilt: digits in a webhook URL's path or query).
- Not covered: `ws_scheduled_fill()` values (`{{form_fields}}`, `{{result}}`) and Markdown marks in a message.

## What does the canvas get?
- `ws_flow_js_config()` `outputs`: kind => declared list (report: `message_id` only; its counts follow from `steps.metrics` and the box config). Read by the value picker (D6, `workspace-flows-value-picker.md`). References are checked on the server (`workspace-flows-references.md`).
