---
name: workspace-flows-event-depth
description: The chain depth a flow run's events carry: ws_event_depth() said by a step box around its work, stamped as _depth by ws_event() and pg_event_record(), read by ws_events_start()
module: events
---

## What is the depth for?
- A chain of runs (an event starts an action or flow whose step causes another event) stops at `WS_SCHEDULED_CHAIN_DEPTH` (8): `ws_events_start()` reads `_depth` from the `ws_events_in` payload, drops it, and starts nothing at 8 or more.

## Who says the depth?
- `ws_event_depth()` (watch.php) holds it for the request: `ws_event_depth($d)` stores `$d + 1`, `-1` resets to 0 (the head of a chain), no argument reads.
- A scheduled action's run says its own depth in `ws_scheduled_execute()` and `-1` as it ends.
- A flow's step box (`ws_step_run()`, steps.php) reads the depth standing, says `$run['depth']` right before `ws_scheduled_do()` and gives the read value back after (`ws_event_depth($outer - 1)`), so a step run inside another run keeps that run's depth instead of resetting to 0.

## Which events carry it?
- `ws_event()` (the Workspace's own events) and, since PR #2325, `pg_event_record()` (site events: orders, stock, customers, forms) through `pg_event_payload_depth()` in `includes/fn/events.php`: `_depth` only when `ws_event_depth()` exists and is above 0, so front pages (no Workspace files) and events outside a run are unchanged.
- Before: a step box passed its depth only in its own step context, so an order or task its work made started the next action at depth 0 and a loop of flows ran on past the chain limit.

## Where is it tested?
- `tests/workspace_event_depth_test.php` (helper, restore round trip, source order in `ws_step_run()`); parity line `ws_event_depth in ws_step_run` in `tests/workspace_flows_parity_test.php`.
