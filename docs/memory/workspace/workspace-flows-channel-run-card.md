---
name: workspace-flows-channel-run-card
description: Add › Start a flow in a channel's composer and the run's card there (C2, #3048): which flows are listed, the start, the system line tied by ws_refs, the card following the run
---

## What does Add › Start a flow list?
- The composer's insert menu (not in a discussion thread or an earlier era, only in a public or private channel, `CFG.flows.nav`) opens a drawer: panel action `ws_flow_list` with `runnable_in` = the channel → `ws_flows_runnable_in()` (`includes/workspace/flow_run_cards.php`), sorted by name, each with its `last_run` flag.
- Listed (`ws_flow_runnable_here()`, pure): live with a published version, start box `manual`, `run` or `edit` access, owner on the staff (`ws_can_schedule()`) who may write in the channel. The channel must pass `ws_flow_run_channel_allowed()` for the reader. No other start kind is started by hand (there is no "run on a record" start).

## How is it started?
- Asked as the canvas asks (`fl_run_ask`), then panel action `ws_flow_run_start` with `channel_id` and a `channel.` start token kept until answered: `ws_action_flow_run_fields()` passes `channel_id` on, so `ws_flow_run_start_check()` checks it (`ws_flow_run_channel_check()`, workspace-flows-run-channel.md). No new action or route.
- Only a run the call really started (`started`) writes its card: `ws_flow_run_card_post()`, a system line "{name} started the flow {flow}." (flow name stripped of tag and mark characters, `ws_flow_run_card_name()`). A retried token answers its run and writes nothing.

## How does the line know its run?
- A `ws_refs` row (`source_type` message, `ref_type` flow_run, `ref_id` the run). No column, no token in the body; record lists read `ws_refs` by their own types, so this row is never shown as a tag. Deleting the message deletes the row.
- `ws_flow_run_cards_map()` gives the message payload `flow_run` = `ws_flow_run_card_payload()`: the run as `ws_api_flow_run_present()` (no steps or graph, `result` from `ws_flow_runs_results()`), `flow_id`, `flow_name`, `can_open` (the reader reaches the flow). A reader who may not read the run (`ws_flow_run_for()`) gets the plain line.

## What does the card do?
- `flowRunCard()` in workspace.js draws `window.PGWsFlowFlag.runFlag()` under the system line: Stop (asks, `ws_flow_run_cancel`) while running and `can_cancel`; "Open the run" goes to `workspace_flows.php?flow=&run=`, which opens that run with the runs list (`openLastRun()`). Focus and an unfolded result survive a redraw.
- It reads the run again (`ws_flow_run_get`) while running or waiting: every 4 s for a minute, 15 s for ten more, then each minute (`flowRunWait()`); paused while the tab is hidden, stopped when the card leaves the page.

## Tests
- tests/workspace_flow_run_cards_test.php (the list rule, the payload, the name, the run fields, the bundle, the flag helpers and the timing under Node). The drawer and card were not run in a browser.
