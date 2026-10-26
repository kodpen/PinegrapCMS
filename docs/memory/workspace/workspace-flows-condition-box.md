---
name: workspace-flows-condition-box
description: The condition box of a flow (F4c, PR #1980, flow_conditions.php): one scheduled-action condition, yes/no outcomes, a no without an arrow ends the branch, owner rights, what non-editors read
---

## What is it?
- Kind `condition` in `ws_flow_kinds()` (not a step, not a start), outcomes `yes`, `no` (`ws_flow_condition_outcomes()`); an arrow sent without an outcome leaves on yes. Code: `includes/workspace/flow_conditions.php` (loaded after steps.php); tests `tests/workspace_flow_conditions_test.php`.
- Settings `{rule: …}` (`ws_flow_condition_normalize()`): one rule of `ws_flow_condition_types()`: workday, weekdays (days), hours (from, to), metric (metric, param, op, value as written), record (record, id, field, op, value). Time, event and join rules are the anchor's (F4d), never a condition's.
- Problems (`ws_flow_condition_problems()`): no rule, no day, no hours, no count, no record or field.

## How is it checked and run?
- Publish: `ws_flow_publish_check()` runs `ws_flow_condition_check()` = `ws_scheduled_rule_input()` with the owner (rights to the count or the record kind, the record exists, enum values, money into minor units).
- Run: `ws_flow_step_run()` case condition, owner still on the staff, then `ws_flow_condition_run()`: checked again; a refusal fails the box (and the run, no outcome); else `ws_scheduled_rules_hold()` unchanged: done/yes with "The condition held: …", done/no with its reason.
- A no that no arrow leaves on: the walk skips the boxes behind it and the run ends done, like a scheduled action whose condition does not hold skips its run (parity rows "no is skipped, the reason kept").
- A count's "since the last run" reads the last day (`last_run_at` 0): a run has no earlier run of the box.

## Who reads what?
- Non-editors: `ws_flow_graph_present()` → `ws_flow_condition_masked()`: a record rule's id 0 and value '', a count's value ''; days, hours, the count, kind and field stay. Run sentences through `ws_step_result_shown()`: "The condition held." / "did not hold." / "could not be checked.".

## Canvas?
- `conditionFields()`, `conditionMetric()`, `conditionRecord()`, `conditionCompare()` in `workspace_flow.js`; lists `steps.records`, `steps.operators`, `steps.counts` of `ws_flow_js_config()` from `ws_scheduled_js_config()`. Day names `fl_day_mon`…: the canvas tests read only literal `fl_[a-z_]+` keys.
