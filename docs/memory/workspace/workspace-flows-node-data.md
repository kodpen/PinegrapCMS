---
name: workspace-flows-node-data
description: What a flow box reads of the boxes before it: {{node.<id>.<field>}} from settled boxes' result.data (flow_runs.php), the values each box keeps, the task box and ws_scheduled_fill()
---

## Where is it?
- `includes/workspace/flow_runs.php`: `ws_flow_result_data()`, `ws_flow_node_values()`, `ws_flow_fill_nodes()`; tests `tests/workspace_flow_node_data_test.php`. No schema change: the values live in `ws_flow_run_steps.result` JSON `{text, data}` (`ws_flow_result_pack()`, 16 KB).

## Which boxes are read?
- Only settled ones (done, failed, skipped): a box still at work may change its values, a settled one never does. So a retry of a box fills the same text as its first try, which the task box needs: a retry finds its task by title (`ws_flow_step_task_find()`).
- A skipped box reads as a box with no values.

## How is a placeholder written?
- `{{node.<box id>.<field>}}`, deeper with more dots (`{{node.form.customer.name}}`, at most 4 keys). The box id follows the graph's id rule (`[a-z][a-z0-9_]{0,31}`).
- Unknown box or field, a list, a bool: empty. Values go in as they are and last, so a value holding `{{date}}` is never filled.

## What does each box keep?
- Start box: `started_by` (user id).
- Task box: `task_id`, `title` (the filled title it opened).
- End box: nothing. The steps.php boxes add theirs (`task_id`, `approval_id`, `data`) when they come.

## Where is it filled?
- Task box: title (one line, 200 characters; empty after filling → the box's name) and description.
- `ws_scheduled_fill()` fills it last when its context carries `nodes` (from `ws_flow_node_values()`), for the steps.php boxes of a flow; scheduled actions pass no `nodes` and are unchanged.

## What is left?
- The graph check does not yet flag a placeholder naming a box that is not before this one; such a placeholder fills empty. The canvas offers no picker for it yet.
