---
name: workspace-flows-anchors
description: Flow start boxes at a time, on an event, on a join (F4d, PR #2006, flow_anchors.php): the hidden anchor scheduled action, publish/pause/delete sync, how it starts runs, why it is in no list
---

## What is it?
- Start boxes `schedule` (rule at), `event`, `join` (`ws_flow_anchor_kinds()`), trigger, outcome next. Settings are the rule's keys (`ws_flow_anchor_normalize()`), turned into the rule by `ws_flow_anchor_rule()` and checked with the owner by `ws_flow_anchor_check()` = `ws_scheduled_rule_input()`, plus a single time that has passed is refused. Code `includes/workspace/flow_anchors.php`; tests `tests/workspace_flow_anchors_test.php`.

## What is the anchor?
- One `ws_scheduled_actions` row with `flow_id` = the flow: kind action, channel 0, no card or message, created_by the owner, rules `[the rule]`, action `{type: flow, flow_id}`, no follow-ups.
- `ws_flow_publish()` → `ws_flow_anchor_sync()`: written or changed in place (id, lock, history kept), active, next time from the rule; a flow now started by hand cancels it. Pause → paused, delete → cancelled (`ws_flow_anchor_set_status()`). Publishing or resuming (`ws_flow_resume()`, `workspace-flows-versions.md`) makes it active again.
- The engine keeps it: `ws_scheduled_run()` by time, watch.php events and `ws_scheduled_greets()` joins read every active `kind = 'action'` row, anchors included (`ws_scheduled_actions_only()` is unchanged).

## How does it start a run?
- `ws_scheduled_execute()` holds the rules as usual, then for a row with `flow_id` calls `ws_flow_anchor_start($viewer, $row, $fill)` instead of `ws_scheduled_do()`: 'flow' is not a step, so the parity test's step count (thirteen with the lead step) does not change.
- `ws_flow_anchor_start()`: flow live and still the owner's, started by an anchor kind, open-run and hourly limits (`ws_flow_start_refusal()`), start_key `sched:<ws_scheduled_runs id>` (a second try finds the run), started_by and actor the owner, depth from the chain, start box done "Started on its own: …". Context (`ws_flow_anchor_context()`): trigger kind, node, home channel, flow name, `sched_run`, event + payload (left out over 8 KB) or newcomer + join channel. Boxes advance on the next tick.

## Why is it in no list?
- `ws_scheduled_action()` returns null for it (card, history, status, run now, API get); `ws_scheduled_list()`, `ws_scheduled_targets()` add `ws_scheduled_anchors_out()`; the API list already filtered `flow_id = 0`.
- `ws_scheduled_report()` is silent for it; a failed start writes one Site Log line "workspace flow … did not start on its own".

## Left?
- A flow's runs do not yet read the event payload or newcomer in `{{…}}` values; a manual Run of an anchored flow is refused ("not started by hand").
