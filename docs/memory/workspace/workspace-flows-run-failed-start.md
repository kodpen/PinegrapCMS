---
name: workspace-flows-run-failed-start
description: A failed run as a flow start (PR #7410): workspace.flow.run.failed kept by ws_event(), its payload, who it starts for, never the flow that failed, chain depth
---

## Where is a failed run kept for flows?
- `ws_flow_run_announce_end()` (flow_run_events.php) calls `ws_flow_run_keep_failed()` right after the `workspace.flow.run.failed` webhook, so it follows the same end UPDATE that took the row: once per end, a run reopened by a retry may fail and be kept again.
- `ws_event()` keeps it only while an active rule waits for it (`ws_events_awaited()`); the event is in `ws_events_own()`, never in `pg_event_workspace_events()`.
- Listed in `ws_events_catalog()` only where flows exist (`ws_flows_ready()`): record `channel` (none for a run of no channel), right `member`, no filter, narrowable to a channel (the run's).

## What does it carry?
- `ws_flow_run_failed_payload()`: `id` and `run_id` (the run), `flow_id`, `flow_name`, `channel_id` (0 for none), `node_id`, `box` (the box that failed first) and `sentence`, as a reader without edit reads it (`ws_flow_run_event_box()` with words).
- Start box outputs (`ws_flow_event_outputs()`): flow_id, flow_name, run_id, box, sentence, channel_id. Placeholders: `{{record_title}}` the flow's name, `{{message}}` box and sentence.
- Unlike the webhook, the words go out for a private channel's run too: they stay in the site, and the start is checked against the reader.

## Who may it start for?
- `ws_events_creator_sees()`: the rule's creator must read the run (`ws_flow_run_access()` not ''), whatever its channel. The fill of placeholders asks the same.

## Why can no loop run away?
- A flow never starts on its own failure: `ws_events_start()` reads each rule's `flow_id` and skips it when `ws_events_own_failure()` (the payload's flow_id is the rule's flow).
- Two flows failing into each other: the event is kept at the failed run's depth (`ws_event_depth($run['depth'])` around `ws_event()`), so each start stands one step further down and stops at `WS_SCHEDULED_CHAIN_DEPTH`.

## Tests
- `tests/workspace_flow_run_failed_start_test.php`; parity lines in `tests/workspace_flows_parity_test.php`.
