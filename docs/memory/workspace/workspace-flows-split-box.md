---
name: workspace-flows-split-box
description: The Split box of flows (kind split, FE12): two to five arms with whole-percent shares adding up to 100, one arm picked at random and kept on the step, arms as named arrows, trial, try, canvas
---

## What is it?
- Kind `split` ("Split", group Logic) in `ws_flow_kinds()`: a logic box like Condition, not a step of a scheduled action (`step` false); code in `includes/workspace/flow_split.php`, tests `tests/workspace_flow_split_test.php`.
- Settings (`ws_flow_split_normalize()`): `arms`, at most 5, each `name` (one line, 60 characters, its letter A–E when empty) and `share` (whole percent 0–100).
- Problems (`ws_flow_split_problems()`, in `ws_flow_graph_check()`): fewer than 2 arms, an arm at 0, shares not adding up to 100, two arms of one name (any case).

## Which arrows?
- Kind outcomes `arm_1`…`arm_5`; per box (`ws_flow_split_arrow_outcomes()` through `ws_flow_node_arrow_outcomes()`) one per arm by its place, so renaming an arm keeps its arrow, plus `always`. An arm with no arrow ends that branch, as a condition's no.
- Quick setup chains on from it on `always` (`ws_flow_quick_on()`); it is in the Logic group (`ws_flow_quick_groups()`).

## How does a run pick?
- `ws_flow_split_pick($arms, $roll)` (pure): the shares laid end to end, the roll brought into 0 … total-1, the arm whose share covers it; an arm of 0 is never taken.
- `ws_flow_split_run()` (case `split` in `ws_flow_step_run()`): `random_int(0, 99)`, settled once from running with outcome `arm_N`, "Took the arm “A” (50%)." and the value `arm` (the arm's name, output "Arm taken"). The outcome is on the step, so the run view, a healed walk and later boxes read the same arm; a split never fails but with arms a draft never published as such.
- Agent tier: read (`ws_flow_box_tiers()`). Not a role-3 member kind (`ws_flow_member_kinds()`): that is an owner question.

## Try and trial?
- Try (`ws_flow_box_try()`): "Takes one arm at random by its share: “A” 50%, “B” 50%.", outcome `arm_1`; a problem is its refusal.
- Trial: in `ws_flow_trial_person_kinds()`, so the first arm is taken and the step offers the others as its Outcome select; the walk hands on `arm` for the arm taken.
- Counts need nothing of their own: they count by box id.

## Canvas? (workspace_flow.js)
- `KINDS.split` (two arms, a new box's), `kindOf()` gives one outcome per arm, `outcomeText()` names `arm_N` by the arm's name (else its letter), `edgeNamed()` always names an arm. Palette after Condition; a new box is A 50 / B 50 (`addItem()`).
- `splitFields()`: each arm a name, a share and %, Remove (not below two; `splitEdgesWithout()` drops that arm's arrow and moves later ones up), Add an arm (up to five), the total "Together N% of 100%" red while not 100. `splitMeta()` lists "A 50% · B 50%". Icon `FLOW_ICONS.split` in workspace.js.
- Tests: `test_workspace_flows_canvas_split_arms` (Node) and the kinds/groups parity in `tests/workspace_flows_canvas_test.php`. Not run in a browser.

## Pinegrap AI?
- `split.arms` is a list of objects (`ws_flow_setting_overrides()`), with a hint and the kind's line in `ws_flow_ai_catalogue_hints()`.
