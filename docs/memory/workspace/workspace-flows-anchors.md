---
name: workspace-flows-anchors
description: Flow start boxes at a time, on an event, on a join (F4d, #2006, flow_anchors.php): the hidden anchor, the converted anchor (anchor column 11.0.88), publish sync, starts, write-back, lists
---

## What is it?
- Start boxes `schedule` (rule at), `event`, `join`, `row` (rule row, `workspace-flows-row-box.md`) (`ws_flow_anchor_kinds()`), trigger, outcome next. Settings are the rule's keys (`ws_flow_anchor_normalize()`), turned into the rule by `ws_flow_anchor_rule()` and checked with the owner by `ws_flow_anchor_check()` = `ws_scheduled_rule_input()`, plus a single time that has passed is refused. Code `includes/workspace/flow_anchors.php`; tests `tests/workspace_flow_anchors_test.php`.

## What is the anchor?
- One `ws_scheduled_actions` row with `flow_id` = the flow: kind action, channel 0, no card or message, created_by the owner, rules `[the rule]`, action `{type: flow, flow_id}`, no follow-ups.
- `ws_flow_publish()` → `ws_flow_anchor_sync()`: written or changed in place (id, lock, history kept), active, next time from the rule; a flow now started by hand cancels it. Pause → paused, delete → cancelled (`ws_flow_anchor_set_status()`). Publishing or resuming (`ws_flow_resume()`, `workspace-flows-versions.md`) makes it active again.
- The engine keeps it: `ws_scheduled_run()` by time, watch.php events and `ws_scheduled_greets()` joins read every active `kind = 'action'` row, anchors included (`ws_scheduled_actions_only()` is unchanged).

## How does it start a run?
- `ws_scheduled_execute()` holds the rules, then for a row with `flow_id` calls `ws_flow_anchor_start()` instead of `ws_scheduled_do()` ('flow' is no step: the parity step count stays).
- `ws_flow_anchor_start()`: flow live and still the owner's, started by an anchor kind, open-run and hourly limits (`ws_flow_start_refusal()`), start_key `sched:<ws_scheduled_runs id>` (a second try finds the run), started_by and actor the owner, depth from the chain, start box done "Started on its own: …". Context (`ws_flow_anchor_context()`): trigger kind, node, home channel, flow name, `sched_run`, event + payload (over 8 KB left out) or newcomer + join channel.

## Hidden or converted? (11.0.88, decided in #2396)
- `ws_scheduled_actions.anchor` VARCHAR(12): '' an action of its own, `hidden` a start box's row, `converted` an action a flow now carries out (written by the 2026.11.0 conversion). The step marks every `flow_id <> 0` row hidden; `ws_flow_anchor_sync()` inserts hidden. Before the column: `flow_id > 0` is hidden (`ws_scheduled_anchor_hidden()`, `ws_scheduled_anchor_ready()`).
- Hidden: `ws_scheduled_action()` null (card, history, run now, chain target); `ws_scheduled_anchors_out()` (`anchor <> 'hidden'`) in `ws_scheduled_list()`, `ws_scheduled_targets()`; `ws_scheduled_report()` silent, a failed start one Site Log line.
- Converted: an action everywhere; how it runs: `workspace-flows-converted-anchor.md`. Event and join starts read every anchor through `ws_scheduled_actions_only()`, never `ws_scheduled_anchors_out()` (parity test `anchor_filters`).

## What do the boxes read of the start?
- Every step box gets the event, payload, newcomer and channel joined (`ws_step_trigger_context()`, #2205): `{{record}}`, `{{newcomer}}` and the rest fill as in an action (`workspace-flows-steps.md`).

## Left?
- A manual Run of an anchored flow from Flows is refused ("not started by hand"); converting every row and the edit path for any staff: slice 5b.
