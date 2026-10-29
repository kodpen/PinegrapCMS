---
name: workspace-flows-row-box
description: The row start box of flows (#3064): a table row added, changed or deleted starts the flow; rule type row, ws_event() in ws_wtable_row_announce(), the table gate, cells as trigger values
---

## What is it?
- Start box kind `row` ("When a table row changes"), an anchor kind beside schedule/event/join (`ws_flow_anchor_kinds()`, flow_anchors.php). Settings (`ws_flow_anchor_normalize('row')`): `table_id`, `what` (`created`/`updated`/`deleted`, `ws_flow_row_whats()`, unknown → created), `column_id` (only for updated, 0 = any column written). Tests `tests/workspace_flow_row_events_test.php`, canvas `test_workspace_flows_canvas_row_box`.
- In the start filter it counts as `event` (`ws_flow_start_names()`): the API enum did not change.

## How does a row reach the flow?
- `ws_wtable_row_announce()` (wtable_shares.php), after the import's quiet check, calls `ws_event('workspace.wtable.row.<what>', ws_wtable_row_flow_payload())` while some anchor waits for it (`ws_events_awaited()`), whether or not the table is shared in a public channel; the webhooks still need such a share. The row is read only when a flow waits or a webhook goes out. A file import starts nothing.
- The three events are in `ws_events_own()` but not in `ws_events_catalog()`: the event box does not offer them and `ws_scheduled_rules_hold()` lets a `row` rule through.
- The anchor's rule is `{type: row, event, table_id, column_id}` (`ws_flow_anchor_rule()`); it keeps `event` so `ws_events_awaited()` and `ws_events_start()` read it like an event rule. `ws_events_rule_matches()` hands a row rule to `ws_flow_row_rule_matches()`: same table, and for a column the payload's `changed` (column ids written) holds it.
- Payload: `table_id`, `table_name`, `row_id`, `actor_id`, `changed`, `cells` (as `ws_wtable_row_present()` keys them, a deleted row's too). Over 8 KB the anchor context drops it and every trigger value is empty.
- Chain depth comes with `ws_event()` (`_depth`), so a flow writing rows stops at `WS_SCHEDULED_CHAIN_DEPTH`.

## Who may, and when is it checked?
- Publish: `ws_flow_anchor_check()` → `ws_flow_row_check()`: `ws_wtable_flow_check($owner, $table_id, 'view')`, refusals from `ws_flow_row_refusal()`: owner of the table gone (`paused`) "starts no flow until they are back", not reached "Choose a table you may read.", a column not of the table.
- Each row: `ws_events_creator_sees()` asks the same gate before queuing (owner gone or not reached → nothing queued), and `ws_flow_anchor_start()` asks again as the run starts.

## What does a box read? ({{trigger.*}})
- `ws_flow_row_outputs($columns)`: `table_id`, `table_name`, `row_id`, `actor_id`, then `cell_<column id>` labelled by the column's name (number/money typed number). `ws_flow_trigger_outputs('row', $config)` reads the columns from the store unless `$config['columns']` gives them.
- `ws_step_trigger_values()` fills them only while the owner reads the table now (`ws_step_trigger_values_for()`); each cell via `ws_flow_row_cell_text()`: scalar as is, ticked box 1, a record link its id, anything else ''. Trigger values never reach run readers without edit (they are not in run results).

## Canvas
- workspace_flow.js: `KINDS.row`, palette entry only while `FL.steps.tables` (from `ws_flow_row_tables()`, tables reached, at most 200, with columns) is not empty; `rowFields()` (table, what, column for a change), `rowMeta()`, `rowCellValues()` add the cells in the value picker. Texts `fl_row_*`, `fl_kind_row`.
- How the start reads in lists: `ws_flow_row_start_text()` names the table and column only to a reader of the table.

## Left?
- Add/set row boxes: plans/workspace-flows.md (F6b). Run from a table: workspace-flows-from-table.md.
