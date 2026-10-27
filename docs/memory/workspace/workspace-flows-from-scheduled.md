---
name: workspace-flows-from-scheduled
description: A scheduled action as a flow graph (ws_flow_graph_from_scheduled(), PR #PR): how each part maps to a box or arrow, self-retry, money, what is told in left
---

## Where is it, and who uses it?
- `ws_flow_graph_from_scheduled($data, $money, $self_id)` in `includes/workspace/flow_templates.php` (pure); tests `tests/workspace_flow_from_scheduled_test.php`.
- `$data` is the form's shape (`ws_scheduled_form_data()`, a draft's data). Meant for a scheduled draft opened on the canvas (merge plan 4c2) and the 2026.11.0 conversion (slice 5).

## How does each part map?
- First "when": at → schedule, event → event, join → join (config the rule minus type); trigger or none → manual.
- Each condition → a condition box, chained on yes; money counts back to major units (`ws_scheduled_rules_as_sent()` with `$money`).
- Step → its box: task → task_open, trigger → start_action, others the same kind; `to` as a list → one text.
- Follow-ups → boxes then1… on done, failed or always from the step; skipped → the no arrow of every condition. `{{result}}` → `{{node.step.text}}`.
- On failed, a trigger of the action itself (`$self_id`) → the step box's `retry` = `WS_FLOW_RETRY_MAX`, `retry_after` = its delay (at least 1).
- Layout: `ws_flow_template_build()`.

## What is told in left?
- A follow-up on skipped without a condition (dropped).
- An approval's follow-up on done goes on approved: a flow's approval box waits for the decision, the scheduled step only asked.
