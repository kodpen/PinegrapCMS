---
name: workspace-flows-for-each
description: Flows For each loop (D9 server, #3312): For each / End boxes, body per item at pass = item, graph check, 50-item cap, End outputs and 16 KB list, 20 side effects a run
---

## Where is it?
- `includes/workspace/flow_loops.php` (kinds in `ws_flow_kinds()`, wired in `ws_flow_run_refresh()`, `ws_flow_step_run()`, `ws_step_run_values()`); tests `tests/workspace_flow_loops_test.php`. The canvas half (workspace_flow.js KINDS, palette, form, body drawing) is not built yet.

## What are the two boxes?
- `for_each`: config `list` (one `{{node.X.f}}` or `{{trigger.f}}`, a JSON array such as a webhook's `body`), `path` (dot path inside it, `data.items`), `fields` (up to 10 keys of an item given by name). Outcomes `each` (into the body) and `failed` (out of the loop); never `always` (`ws_flow_arrow_outcomes()` reads `loop` = start). Outputs `count`; inside the body also `index`, `item` (a list or object as JSON) and each field.
- `for_each_end`: config `for_each` (the box whose loop it ends), `collect` (one value of a body box, gathered per item). Outcome `done`. Outputs `count`, `done`, `failed`, `list`.

## What does the graph check refuse? (`ws_flow_loop_problems()`)
- bad_loop: an End box naming no For each box or a second End box; a For each box without End box or each arrow; an empty body; a body that never reaches its End box; an arrow leaving the body other than into its End box, entering it other than on each, or into the End box from outside; a loop inside or across another (not supported yet).
- bad_config: list empty or not one value; a field named like a value of the box; `collect` not one value of a body box.
- bad_reference: a box outside the loop reading a body box or `index`/`item`/a field (read the End box's outputs instead; `count` stays readable).
- The body (`ws_flow_loops()`): boxes reached from the each arrows, not through the End box nor any box after it.

## How does it run?
- The For each box reads its list with the owner's rights now (`ws_flow_loop_items()`): not a list, nothing at the path, more than `WS_FLOW_LOOP_ITEMS_MAX` (50) items or items over 32 KB fail it with the reason (nothing is cut). Done on `each` keeps `count` and `items` (JSON).
- `ws_flow_loop_walk()`: boxes outside loops walk at pass 0 as `ws_flow_walk()`; each item walks the body as a graph of its own at pass = item (1-based, fits UNIQUE (run_id, node_id, pass)), item n+1 only after every box of item n settled; the For each box reads as running till then, so its End box comes once, after every item. A graph without loops walks exactly as before.
- A failed body box fails its item, not the run (`ws_flow_loop_run_state()` counts failed pass-0 boxes only); the End box counts it in `failed`.
- `{{…}}` inside the body: `ws_step_run_values($run, $graph, $owner, $context, $step)` reads pass 0 and the step's own pass, plus the item (`ws_flow_loop_item_values()`); conditions and start-a-flow boxes read the same way.
- `WS_FLOW_MAX_BOX_RUNS` (200) still caps the whole run.

## What does the End box give?
- `ws_flow_loop_end_outputs()`: count, done, failed, and `list`, the JSON array of the gathered value per item (null where none), whole values up to `WS_FLOW_LOOP_LIST_BYTES` (16 KB); the sentence says when it kept fewer.

## Side effects inside a loop?
- Allowed; task, task_open, approval and email boxes inside loops begin at most `WS_FLOW_LOOP_EFFECTS_MAX` (20) a run (rows with pass > 0 and an effect written), the next fails with the reason (`ws_flow_loop_effects_check()`). The webhook `Idempotency-Key` and the start-a-flow key carry the pass.

## API and retry?
- A run's box carries `pass` (`ws_api_flow_run_step_present()`); `items` of the For each box and `list` of the End box are held from readers who may not change the flow (`ws_step_outputs_held()`).
- Retry/skip takes the box's latest failed row; only rows skipped in that item are taken back.
