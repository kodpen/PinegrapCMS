---
name: workspace-flows-references
description: The reference check of a flow (data-passing D4, PR #2305): bad_reference for {{node.*}} of a missing, later or field-less box; a warning for a box that may not run; Problems list
---

## What is checked?
- `ws_flow_reference_problems()`, the last part of `ws_flow_graph_check()` (flows.php), reads the `{{node.<id>.<field>}}` of `ws_flow_node_texts()`: a step's `texts`, a task box's title and description (the texts a run fills).
- `bad_reference`: a box the flow does not have; a box that does not come before the reader (backwards along the arrows; the box itself included); a field outside `ws_flow_node_fields()` (text, outcome, status and the outputs, `ws_step_outputs()` for a step, so a report's counts count).
- `{{trigger.<field>}}`: `bad_reference` for a field the start box does not declare (`ws_flow_trigger_fields()`, D5, `workspace-flows-trigger-values.md`).
- Tests `tests/workspace_flow_graph_check_test.php`.

## When is it only a warning?
- A box that may not have run when the reader runs: `skippable_reference` with `warning: true`; the value is then '' at run time.
- Sure boxes (`ws_flow_sure_before()`) follow `ws_flow_walk()`: a box runs after one arrow in was taken, every box before it settled, none failed. So the boxes every arrow in brings are sure, and so is each earlier box an always-taken arrow (on always, or on the box's only outcome: a start's next, a wait's done) leads into from a sure box.
- So two threads from a start meeting again are clean; a condition's branch, or a step's done arrow (it may end failed), warns. There is no fork box: threads are several arrows out.

## Who sees warnings?
- `ws_flow_problems()` leaves them out: publishing, the catalogue and the API's `WorkspaceFlowProblem` list are unchanged. `ws_flow_warnings()` gives them; the panel's save and detail answer `warnings`.
- Flow window (`drawProblems()`, `markProblems()` in workspace_flow.js): listed under Problems with their own heading in the warning colour (`.wsf-warning`, `.wsf-warned`), "Warnings: n" on the button when there is no problem, a box's tooltip names them without outlining it; Publish is not held.
