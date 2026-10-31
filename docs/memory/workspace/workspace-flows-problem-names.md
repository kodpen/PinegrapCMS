---
name: workspace-flows-problem-names
description: Flow problem texts and the small drawings name boxes as the canvas (ws_flow_box_names(), numbered when untitled boxes share a kind), values as Box › Value; no id or {{…}} (#4061, PR #4189)
---

## Which helper names a box in a problem?
- `ws_flow_box_names($nodes, $kinds = null)` (flows.php), id => name, like `nameOf()` in workspace_flow.js: the title (`ws_flow_box_raw_title()`: a task, task_open or approval box's task title, else its label); a value in the title as `[Box › Value]`, a value no box gives makes it untitled.
- Untitled boxes take their kind's name (`ws_flow_kinds()` label); when several share a kind they are numbered in the graph's order: "Post a message (1)", "Post a message (2)". The canvas draws the plain kind name; the number only tells them apart in the list, and each problem carries the box id for selecting it.
- Used by `ws_flow_graph_check()` (and the per-kind `*_problems($config, $name)` it calls), `ws_flow_reference_problems()`, `ws_flow_loop_problems()`, `ws_flow_problems()`, `ws_flow_publish_check()`'s refusals, `ws_flow_member_problems()`, `ws_flow_member_graph_refusal()`.
- `ws_flow_card_graph()` names the boxes of a draft card, the flow window and the scheduled flow window (its section headings) the same way: they showed `start` and `post`.
- `ws_flow_box_name()` (label, else id) stays for run records, AI draft lines and box tries, where an id is a fair stand-in.

## How is a read value named?
- `ws_flow_value_label($box, $field, $nodes, $names)`: "Check a web address › Outcome" (field label from `ws_flow_condition_field_label()`, the box from `$names`); `$box` '' is the start box; a field the box does not give keeps its key; a box not in the flow gives "a value of a box that is not in the flow".
- A reference to a missing box reads "The box X reads a value of a box that is not in the flow."; a start value the start does not give: "The box X reads the value k, but <start>, which starts the flow, gives no value k."
- Codes `duplicate_id` and `bad_id` still name the id: they are about the id itself.

## Which test holds it?
- `tests/workspace_flow_problem_names_test.php`: names, numbering, titles with values, value labels, and a graph whose problems hold no `value_1`, `end_1` or `{{`.
