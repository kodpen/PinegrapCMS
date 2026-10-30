---
name: workspace-flows-references
description: The reference check of a flow (D4, PR #2305): bad_reference for {{node.*}} of a missing, later or field-less box; a warning for a box that may not run, unless a fallback is written
---

## What is checked?
- `ws_flow_reference_problems()`, the last part of `ws_flow_graph_check()` (flows.php), reads the `{{node.<id>.<field>}}` of `ws_flow_node_texts()` with `WS_STEP_VALUE_PATTERN`: a step's `texts`, a task box's title and description (the texts a run fills).
- `bad_reference`: a box the flow does not have; a box that does not come before the reader (backwards along the arrows; the box itself included); a field outside `ws_flow_node_fields()` (text, outcome, status and the outputs, `ws_step_outputs()` for a step, so a report's counts count).
- `{{trigger.<field>}}`: `bad_reference` for a field the start box does not declare (`ws_flow_trigger_fields()`, D5, `workspace-flows-trigger-values.md`).
- Tests `tests/workspace_flow_graph_check_test.php`.

## When is it only a warning?
- A box that may not have run when the reader runs: `skippable_reference` with `warning: true`; the value is then '' at run time, or the place's fallback. The canvas marks such a value "Empty if skipped" (decision 3a of #2414).
- Not raised when every place in the box's texts writes a "When empty, write" fallback for it (`{{node.<id>.<field>|text}}`, `workspace-flows-empty-fallback.md`); references are named without the fallback. A fallback never excuses `bad_reference`.
- Sure boxes (`ws_flow_sure_before()`) follow `ws_flow_walk()`: a box runs after one arrow in was taken, every box before it settled, none failed. So the boxes every arrow in brings are sure, and so is each earlier box an always-taken arrow (on always, or on the box's only outcome: a start's next, a wait's done) leads into from a sure box.
- So two threads from a start meeting again are clean; a condition's branch, or a step's done arrow (it may end failed), warns. There is no fork box: threads are several arrows out.

## Who sees warnings?
- `ws_flow_problems()` leaves them out: publishing, the catalogue and the API's `WorkspaceFlowProblem` list are unchanged. `ws_flow_warnings()` gives them; the panel's save and detail answer `warnings`.
- Flow window (`drawProblems()`, `markProblems()` in workspace_flow.js): listed under Problems with their own heading in the warning colour (`.wsf-warning`, `.wsf-warned`), "Warnings: n" on the button when there is no problem, a box's tooltip names them without outlining it; Publish is not held.

## What did the data-threads plan settle? (#2415, #2414)
- Threads are drawn only from the references kept in the fields; the picker, the API and an AI draft give the same picture (`workspace-flows-thread-drawing.md`). A value may skip boxes: a field reads any box before it.
- A field deciding where data goes (recipients, an address's host and scheme) takes no thread; a number may go into a webhook URL's path or query (`workspace-flows-webhook-url.md`).
- Value boxes are pure, computed when read, cached per run (`workspace-flows-value-boxes.md`); the empty fallback is per thread (`workspace-flows-empty-fallback.md`).
