---
name: workspace-flows-condition-values
description: A condition box comparing a value of the run (data-passing D7): the value rule, its operators, reference check, how it holds at run time, masking, the canvas picker
---

## What does a value rule hold?
- `rule.type` `value` (`ws_flow_condition_types()`): `ref` is one `{{node.<id>.<field>}}` or `{{trigger.<field>}}` (anything else is '' and a `bad_config` problem), `op` of `ws_flow_condition_value_operators()` (eq, neq, gt, gte, lt, lte, contains, empty, not_empty; else eq), `value` one line of 500.
- `ws_flow_node_texts()` gives the ref to the reference check (D4), so a later or missing box is `bad_reference`, a box that may be skipped a warning.
- Check at publish and run: no record, no rights to ask; trigger values are gated as they are read (`ws_step_trigger_values_for()`).

## How does it run?
- `ws_flow_condition_run()` reads the settled boxes (`ws_step_node_values()`) and the trigger's values, then `ws_flow_condition_value_holds()` (pure): `ws_scheduled_compare()` as text, case and Turkish i letters folded (`ws_fold()`, workspace-text-fold.md); gt/gte/lt/lte only between two numbers, so a skipped box's '' is never "less than 5" and "503" > "99".
- Sizes (#3500, bug #3399): the run's value is a machine number when `is_numeric()` (1500), else read the site's way; the typed value always through `ws_flow_typed_number()` (flow_values.php), the one reader of typed flow numbers: in Turkish `1.000` is a thousand, `1.000,50` has decimals; `1500`, `1000.5`, `0.125` keep their value; `1.000.50` (the canvas stores a typed comma as a dot) is `1.000,50`. It wraps `ws_calc_number()` (workspace-calc-numbers.md), so `1.000` is still one on an English site.
- An unreadable typed number with a size: a `bad_config` problem at publish (`ws_flow_condition_number_missing()` in `ws_flow_condition_problems()`), and `ws_flow_condition_check()` refuses it, so a flow published earlier fails the box instead of a silent no.
- Sentence: `The condition held: node.web_1.status_code is at least 500 (it was "503").`, a size's number as read, the site's way (`ws_flow_typed_number_shown()`: `1.000,5`); non-editors read only "The condition held." (`ws_step_result_shown()`). `ws_flow_condition_masked()` empties `value`.
- Results that changed with #3500 on stored flows: Turkish `1.000`-shaped values (were one), Turkish decimals with a comma and English `1,000` (were never met), "is"/"contains" with I/İ/ı.
- Tests `test_workspace_flow_condition_value_*` (tests/workspace_flow_conditions_test.php), tests/workspace_flow_site_numbers_test.php (both languages).

## What does the canvas offer?
- `conditionValueRule()` in workspace_flow.js: the values of `valueGroups()` (the D6 picker's, a skippable branch marked "Empty if skipped"), the comparisons of the value's type (`VALUE_OPS`: a number's more/less, a text's contains; a switch to a text resets to "is"), a number typed with a comma kept with a dot. The box reads the value's name (`conditionMeta(rule, node)`). Test `test_workspace_flows_canvas_condition_value_rule`.
