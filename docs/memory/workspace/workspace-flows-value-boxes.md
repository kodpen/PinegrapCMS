---
name: workspace-flows-value-boxes
description: Value boxes of a flow (T3, decision #2415 1a): the constants box's rows, no arrows and no run row, how value boxes are read in a run, the check, the canvas form
---

## What is a value box?
- A kind with `pure` in `ws_flow_kinds()` (includes/workspace/flow_values.php): no outcomes, no arrows, no side effect, no run row. Its values reach boxes only through threads (`{{node.<id>.<key>}}`). Decision 1a of #2415: computed when a box reading them runs, never placed on the arrows as steps.
- Kinds: `constants` (PR #2550, this file) and `value`, the derived value box (PR #2701, workspace-flows-derived-box.md).

## What does a constants box hold?
- `config.rows`: up to `WS_FLOW_CONSTANTS_MAX` (20) of `key` (small letters, digits, _; cut as typed on the canvas and by `ws_flow_constants_normalize()`), `type` text or number (`ws_flow_constant_types()`), `value` one line of 500.
- `ws_flow_constants_outputs()`: one output per row with a valid key, the first of a key twice. `ws_flow_node_fields()` gives those only: no text, outcome or status (it never runs).

## How are its values read in a run?
- `ws_flow_pure_values($graph, $values)` (pure) gives the value boxes' node id => key => value; `$values` are the run's settled boxes (`ws_step_node_values()`) with the trigger's under `WS_STEP_TRIGGER`, which a derived box works from. Each reader builds those first, then `$values = ws_flow_pure_values($graph, $values) + $values`: a step box's run (`ws_step_run()`), a box try (always, even without a run), a value condition (`ws_flow_run_graph($run)`), a start-flow box's source result (flow_runs.php). A reader that skipped the call would read derived values empty.

## What does the check say?
- Not `unreachable` without arrows; an arrow into it `bad_edge`; one out of it `bad_outcome` (no outcomes).
- A reference to it is never "does not come before" nor `skippable_reference`; a field it does not give is `bad_reference`.
- `ws_flow_constants_problems()`: no row, a name that is not a key, a name twice, a number that is not one (`bad_config`).
- Tests `tests/workspace_flow_values_test.php`.

## And on the canvas?
- `KINDS.constants` (`pure: true`, `into: false`, `out: []`): no ports, a dashed border in the value colour (`#e58fd0` dark, `#a8468f` light), the palette's tag. `boxValues()` gives its rows; `valueGroups()` offers it to every box, never "Empty if skipped". `constantsFields()` edits the rows; `FL.limits.constants`.
- Test `test_workspace_flows_canvas_constants_are_offered_to_every_box`.
