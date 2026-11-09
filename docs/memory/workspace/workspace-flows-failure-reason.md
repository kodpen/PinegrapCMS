---
name: workspace-flows-failure-reason
description: Why a flow box failed (FR1, PR #8425): data.empty_values, the added sentence on card, bell and run view, the canvas buttons and the card link to the failed box
---

## What does a failed box keep?
- `ws_step_run()`: when a step box fails (before the retry wait), `ws_flow_empty_values(ws_flow_step_texts($kind, $action), $values)` lists the `{{node.*}}` / `{{trigger.*}}` of its texts, as written, that the run gave no value or an empty one and that have no "When empty, write" text; each once, at most `WS_FLOW_REASON_VALUES` (3).
- `ws_flow_empty_entries()` names them: `node_id` (the giving box, the start box for a trigger value, '' for a box the graph lacks), `token` (the bare reference), `key`, `box` (`ws_flow_box_name()`), `name` (`ws_flow_condition_field_label()`, else the key), `said` (the giving box's own sentence when it is a `row_get` with `found` 0).
- Kept as `data.empty_values` of the box's result (`ws_flow_result_pack()`). An array: `ws_step_node_values()` and the API outputs read only scalars, so no later box reads it. No schema.
- A box that failed for another reason (check, refusal, address) keeps no list and shows nothing new. Done boxes never carry one.

## Where does the sentence show?
- `ws_flow_text_with_reason($text, $status, $stored, $edit)` adds "The value “Name” from the box “Box” was empty." per value to a failed box's sentence; the row box's own sentence follows only for `$edit`, as `ws_step_result_shown()` hides a row read's text (it names the table) from the others.
- Run card and lists: `ws_flow_run_result_of()`. Bell line: `ws_flow_run_failure()` (the owner's line, `$edit` true). Run view and API: `ws_api_flow_run_step_present()` `result`, plus `empty_values` (node_id, token, key, box, name; no `said`).

## What does the canvas offer?
- `emptyFixes()` under a failed box's result (side panel and outline): per value "When empty, write" (only when the run was opened from an editable draft, `state.stash.edit`) and Open the box (selects the giving box in the run view). With more than one value each row is labelled.
- `fixEmpty()`: `closeRun('')`, selects the failed box in the draft and clicks its chip whose `tokenBare()` is the token, which opens `askFallback()`. A draft without the box or the chip toasts `fl_reason_gone`.
- Run card (`workspace.js`): payload `box` (`ws_flow_run_cards_failed_boxes()`, the first failed box by finish time, '' unless failed); a followed run takes it from `steps`. Open goes to `?flow=&run=&box=`; `openAsked()` selects the box after the run shows (`openLastRun()` returns `openRun()`'s promise).

## Tests?
- `tests/workspace_flow_failure_reason_test.php`; card payload in `workspace_flow_run_cards_test.php`.
