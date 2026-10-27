---
name: workspace-flows-outputs
description: Values a flow box gives later boxes (data-passing D1, D2): outputs per kind, ws_step_outputs(), ws_step_result_data(), null rules, report counts, one-pass fill, canvas config
---

## Where are they declared?
- `ws_step_kinds()` gives each step kind `outputs` (key, type, label; `mode` on web_check ones); `ws_flow_kinds()` carries them for every kind (the waiting `task` box: `task_id`; delay: `waited_until`; start, condition, end: none). Types: `ws_step_output_types()` (number, text). Tests `tests/workspace_flow_outputs_test.php`.
- Per kind: post, report, checkin `message_id`; email `sent`; notify `told`; task_open and task `task_id`; webhook `status_code`, `duration_ms`; web_check status mode `status_code`, `duration_ms`, ssl mode `days_left`; task_digest `sent`, `tasks`; change `record_id`; lead `channel_id`; start_action none.
- The webhook's answer body is not an output yet (open question 2 of the data-passing plan).

## How does a box write them?
- `ws_step_outputs($kind, $config, $labels)` is the box's list: a web check's mode only; a report adds one number per count it sends, keyed `<metric>` or `<metric>_<param>` (`tasks_open_4`), labelled from `$labels` (pass `ws_scheduled_metrics()` labels; pure without them). These are the "counts as thread outputs" of the scheduled parity.
- `ws_step_result_data()` (pure) builds `result.data` from `ws_scheduled_do()`'s answer: ids at the top (`message_id`, `task_id`, `channel_id`), the rest under its `data`, a report's under `data.counts` in metric order. Every declared key is written: a value the step did not give is null, an id of 0 is null.
- The scheduled functions answer `data` (email, notify, task digest, webhook, web and ssl check, summary, change); scheduled actions ignore it.
- A box refused before its work (check, starter) writes no data; the task box writes `task_id` as it waits and as it settles.
- `ws_step_node_values()` reads scalars only, so a null fills `{{node.*}}` as ''.

## Can a value be read again as a placeholder?
- No (D2, PR #2121). Before, a node value or a `{{customer}}`/`{{form_fields}}`/`{{result}}` value holding `{{customer}}`, `{{node.…}}` or `{{count:…}}` (typed by a form visitor, returned by a webhook) was expanded by the next filler.
- `ws_step_fill_nodes()` puts a word joiner (U+2060) after every `{` of a value, never removed, so `ws_scheduled_fill()` cannot match it, not even with a brace of the text beside it. Values without `{` are inserted unchanged.
- `ws_scheduled_fill()` reads the counts from the text as written and fills everything with one `strtr()`, which never rescans what it put in.
- Tests `tests/workspace_flow_fill_test.php`.

## What does the canvas get?
- `ws_flow_js_config()` `outputs`: kind => declared list (report: `message_id` only; its counts follow from `steps.metrics` and the box config). Used by the reference check (D4) and the value picker (D6); no canvas UI reads it yet.

## What does each destination do with a filled value?
- Message (post body; task title, description; report title; check-in question; approval title, text): stored as written, `ws_render_body()` escapes the HTML when it draws it, but a `<@user:N>` / `<#order:N>` in it became a real mention or record tag (bell rings, ws_refs row, approval record).
- E-mail box: plain text (`format` plain_text; a page body is never filled); PHPMailer encodes the subject. Task e-mail: subject and intro escaped where the HTML is written (`h()`, `nl2br(h())`, `ws_scheduled_digest_html()`); escaping in the fill would show `&amp;lt;`.
- Webhook: `json_encode()` makes the text one JSON string in every format (values come from `json_decode()`, so valid UTF-8). The chat formats read markup: Slack/Google Chat `<!channel>`, `<@U…>`, `<url|label>`; Discord `<@id>`, `@everyone`, `@here`.
- Notify: an inbox text shown as text. Line fields (`lines` of `ws_step_kinds()`): control characters become a space after the fill.
