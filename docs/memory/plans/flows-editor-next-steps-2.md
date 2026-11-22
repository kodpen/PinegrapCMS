---
name: flows-editor-next-steps-2
description: Plan flows-editor-next, steps FE6 to FE9 (add at the arrow, several boxes, notes and frames, Tidy and the map) and the server steps beside them: FE10a the trial's walk, FE11a the counts
---

## Which steps follow FE5 on the canvas line?
- FE6 (M) after FE5: "+" on an arrow and after a free outcome, the add list with search, "Often next" and group chips, "/" for the search, the arrow's end dropped on the canvas (board B1). Pure parts with red tests first: the "Often next" table, the insert (arrow cut on the new box's first outcome), the shift of the boxes after it. Pattern "Add list".
- FE7 (M) after FE6: several boxes (board B2): Shift+click, the Shift+drag rectangle and Ctrl+A in the core (`assets/js/pg_graph_canvas.js`, an adapter hook, its `pgc_*` texts in `includes/fn/graph_canvas.php`); moving together, the floating tools, Duplicate, Copy and Paste with the arrows and threads among the boxes (`duplicateNode()`, `copiedNode()`), Delete. The Prototype view keeps its single selection unless its adapter asks.
- FE8 (M) after FE7: notes and frames: `frames` in `ws_flow_graph_normalize()` and the limits (`includes/workspace/flows.php`), the `WorkspaceFlowGraph` schema and the routes' `frames` body part (`api_flows.php`), red tests first in `tests/workspace_flows_test.php`; on the canvas the Note button, a note edited and resized in place, "Frame" from the tools, a frame's name, drag and fold.
- FE9 (S) after FE8: Tidy (a panel action over `ws_flow_auto_layout()`, in the ws_flow list of `includes/panel/actions.php`) and the map (the core's thumbnails), both on the strip.

## Which server steps run beside the canvas line?
- FE10a (M) any time: the trial's walk. `ws_flow_trial($viewer, $flow, $data)` in a new `includes/workspace/flow_trial.php`: the draft walked from its start with a record and the outcomes chosen so far, each box answered as `ws_flow_box_try()` answers it, reading boxes read, nothing sent or written, a Wait passed, values carried to the boxes after.
- In FE10a: the owner only, as a box's try; panel action `ws_flow_trial`; an API route beside the box try's (`'mcp' => false`, refused to an assistant's application); red tests first in a new `tests/workspace_flow_trial_test.php`; `tools/check_api_schema.php` passes.
- FE11a (S) after FE10a (both enter `api_flows.php` and the panel's action list): the counts. `ws_flow_counts($viewer, $flow, $days)`: per box id the runs waiting now and the steps passed and failed in the days, one grouped read over `ws_flow_runs` (`idx_flow`) and `ws_flow_run_steps` (`uq_node`); `ws_flow_runs_list` takes `node` and `state`. Rights as the runs list. Panel action, `GET /workspace/flows/{id}/counts`, red tests first.

Related: docs/memory/plans/flows-editor-next.md, docs/memory/plans/flows-editor-next-steps.md, docs/memory/plans/flows-editor-next-steps-3.md, docs/memory/plans/flows-editor-next-screens-4.md
