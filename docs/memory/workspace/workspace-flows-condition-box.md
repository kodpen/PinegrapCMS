---
name: workspace-flows-condition-box
description: The condition box of a flow (F4c, PR #1980, flow_conditions.php): one scheduled-action condition, yes/no outcomes, a no without an arrow ends the branch, owner rights, what non-editors read
---

## What is it?
- Kind `condition` in `ws_flow_kinds()` (not a step, not a start), outcomes `yes`, `no` (`ws_flow_condition_outcomes()`); an arrow sent without an outcome leaves on yes. Code: `includes/workspace/flow_conditions.php` (loaded after steps.php); tests `tests/workspace_flow_conditions_test.php`.
- Settings `{rule: …}` (`ws_flow_condition_normalize()`): one rule of `ws_flow_condition_types()`: workday, weekdays (days), hours (from, to), metric (metric, param: a channel or the number it is measured against, op, value as written), record (record, id, field, op, value as the record keeps it: minor units, '1'/'0', YYYY-MM-DD, YYYY-MM-DD HH:MM:00, a decimal with a dot). Time, event and join rules are the anchor's (F4d), never a condition's. A value of the run (D7): `workspace-flows-condition-values.md`.
- Problems (`ws_flow_condition_problems()`): no rule, no day, no hours, no count, no record or field.

## How is it checked and run?
- Publish: `ws_flow_publish_check()` runs `ws_flow_condition_check()` = `ws_scheduled_rule_input()` with the owner (rights to the count or the record kind, the record exists, enum values). Only a count's money is turned into minor units there; a record's value is compared as it is kept, so the canvas sends it in the record's own units.
- Run: `ws_flow_step_run()` case condition, owner still on the staff, then `ws_flow_condition_run()`: checked again; a refusal fails the box (and the run, no outcome); else `ws_scheduled_rules_hold()` unchanged: done/yes with "The condition held: …", done/no with its reason.
- A no that no arrow leaves on: the walk skips the boxes behind it and the run ends done, like a scheduled action whose condition does not hold skips its run (parity rows "no is skipped, the reason kept").
- A count's "since the last run" reads the last day (`last_run_at` 0): a run has no earlier run of the box.

## Who reads what?
- Non-editors: `ws_flow_graph_present()` → `ws_flow_condition_masked()`: a record rule's id 0 and value '', a count's value ''; days, hours, the count, kind and field stay. Run sentences through `ws_step_result_shown()`: "The condition held." / "did not hold." / "could not be checked.".

## Canvas?
- `conditionFields()`, `conditionMetric()`, `conditionRecord()`, `conditionCompare()`, `conditionValue()` in `workspace_flow.js`; lists `steps.records`, `steps.operators`, `steps.counts` of `ws_flow_js_config()` from `ws_scheduled_js_config()`. Day names `fl_day_mon`…: the canvas tests read only literal `fl_[a-z_]+` keys.
- `conditionValue()` builds the value by the field's kind as `saValueInput()` (workspace.js, not lent to the canvas) does: an amount shown as a decimal and kept in minor units (`changeInput()`), yes/no a '1'/'0' select, a date or a moment picker, a decimal typed the site's way kept as a machine number (#3541: `siteNumber()`, a JS reading of `ws_calc_number()` by `FL.decimal` from `ws_flow_js_config()`: Turkish `1,500` → `1.5`, `1.500` → `1500`, `1.250,5` → `1250.5`; shown back with the site's mark by `siteShown()`; text that is no number kept as typed); always text, since `ws_flow_text()` keeps a true or false as ''. Why: a typed 100 kept as it was held for any price over 1.00. No value for empty, not empty, has gone by.
- A count measured against a number (products low in stock) asks "At most", 5 when the count is picked, as the retired form did; left at 0 it counted only products out of stock.
- Tests: `tests/workspace_flows_canvas_test.php` (`…_condition_values_are_kept_as_the_field_holds_them`, under Node).
