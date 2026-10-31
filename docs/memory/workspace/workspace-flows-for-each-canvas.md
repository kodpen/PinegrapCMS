---
name: workspace-flows-for-each-canvas
description: The For each loop on the Flows canvas (D9 canvas, #3422): kinds, arrows, the body frame, the list and collect forms, which values a box inside or outside the loop is offered
---

## Which kinds and arrows?
- `KINDS` for_each / for_each_end (group logic, `loop` start/end as the server's), palette after Wait, `FLOW_ICONS` in workspace.js. A For each box's arrows offer each and failed, never always (`arrowOutcomes()`); an each arrow carries its label.

## How is the body shown?
- `flowLoops()` finds the body as `ws_flow_loops()` (the test compares both); `drawLoops()` (start of `drawEdges()`) frames For each, body and End in a dashed logic-coloured band labelled "Repeated for each item", inline styles, no CSS rule.

## What do the forms take?
- Forms: For each list (`valueField()` with `takes`: a webhook's `body` or an End box's `list`, `listValue()`), path, fields (comma list, 10); End: the loop (a new End takes the one loop without End) and `collect` (a body box's value, `fieldTakes()`). Each field holds one value: its thread target closes once filled (`openFields()`).

## Which values are offered where?
- `valueGroups()` hides body boxes and index/item/fields from boxes outside the loop (the End box reads the body). Problems come from the server check as on any box.
- Test `test_workspace_flows_canvas_for_each_loop` (tests/workspace_flows_canvas_test.php). The run view shows a box inside the loop by the last of its rows the run lists (`stepOf()`); a per-item picker is not built.

## Which values does the run view show for a box inside a loop?
- Those of the pass it is shown by (#4103, issue #3980), worked out on the client: `loopValueOf()` reads the For each row's `items` output (index = pass, item and fields = items[pass-1], texts as `ws_flow_loop_item_values()`); `runStepOf()` reads another body box at the same pass. Why the client: the run answer already holds `items`; no server or API change.
- Items held back from a reader who may not change the flow (`ws_step_outputs_held()`) or cut short by `WS_API_FLOW_RESULT_CHARS`: "cannot be shown for this item" (`fl_run_value_item`, `unknown`), so no fallback is put in. Before, these values read the For each row's outputs (count and items only) and showed "Empty" or the fallback.
