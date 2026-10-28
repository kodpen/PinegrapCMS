---
name: workspace-flows-condition-values
description: A condition box comparing a value of the run (data-passing D7): the value rule, its operators, reference check, how it holds at run time, masking, the canvas picker
---

## What does a value rule hold?
- `rule.type` `value` (`ws_flow_condition_types()`): `ref` is one `{{node.<id>.<field>}}` or `{{trigger.<field>}}` (anything else is '' and a `bad_config` problem), `op` of `ws_flow_condition_value_operators()` (eq, neq, gt, gte, lt, lte, contains, empty, not_empty; else eq), `value` one line of 500.
- `ws_flow_node_texts()` gives the ref to the reference check (D4), so a later or missing box is `bad_reference`, a box that may be skipped a warning.
- Check at publish and run: no record, no rights to ask; trigger values are gated as they are read (`ws_step_trigger_values_for()`).

## How does it run?
- `ws_flow_condition_run()` reads the settled boxes (`ws_step_node_values()`) and the trigger's values, then `ws_flow_condition_value_holds()` (pure): `ws_scheduled_compare()` as text (case aside); gt/gte/lt/lte only between two numbers, so a skipped box's '' is never "less than 5" and "503" > "99".
- Sentence: `The condition held: node.web_1.status_code is at least 500 (it was "503").`; non-editors read only "The condition held." (`ws_step_result_shown()`). `ws_flow_condition_masked()` empties `value`.
- Tests `test_workspace_flow_condition_value_*` (tests/workspace_flow_conditions_test.php).

## What does the canvas offer?
- `conditionValueRule()` in workspace_flow.js: the values of `valueGroups()` (the D6 picker's, a skippable branch marked "Empty if skipped"), the comparisons of the value's type (`VALUE_OPS`: a number's more/less, a text's contains; a switch to a text resets to "is"), a number typed with a comma kept with a dot. The box reads the value's name (`conditionMeta(rule, node)`). Test `test_workspace_flows_canvas_condition_value_rule`.
