---
name: workspace-flows-run-channel
description: The channel a flow run opens its tasks in: the flow's home as the run starts, or the channel_id named on POST /workspace/flows/{id}/runs (decision #2413); ws_flow_run_channel_check(), shared with C2
---

## Where does a run open its tasks?
- In the channel stored in its context (`ws_flow_run_context_new()`, read by `ws_flow_run_channel_id()`): the flow's home as the run starts, kept whatever the home becomes; 0 for a flow with no home.
- A start may name another: `POST /workspace/flows/{id}/runs` takes an optional `channel_id` (body, int ≥ 1). Without it, the home.

## When is a named channel accepted?
- `ws_flow_run_channel_check($viewer, $flow, $channel_id, $owner = null)`: a public or private channel (the kinds a run's readers are counted in) that the starter reads (`ws_can_read_channel()`) and the flow's owner may write in (`ws_can_post_channel()`): the tasks carry the owner's name. Else 403, field `channel_id`; a channel that is gone gets the same 403, so its existence is not told. Pure part `ws_flow_run_channel_allowed()`.
- Decision #2413 (a). The composer's Add › Start a flow (C2 of plans/workspace-flows-channels.md) uses the same function.

## Where is it checked?
- In `ws_flow_run_start_check()` after the token's run is answered and the owner is known, so the API's dry run meets the same refusal; the check answers `channel_id` (null for the home) and `ws_flow_run_start()` writes it into the context.
- A retry with a used token answers the run it started, in that run's channel, whatever channel the retry names.
- The dry-run answer carries `channel_id`: the channel the run would open its tasks in (null for none, or for the token's run).

## Who reads such a run?
- As every run: by its channel (`ws_flow_run_access_from()`), so the starter reads it there; the flow's editors read all runs.

Tests: `tests/workspace_flow_runs_test.php` (`test_workspace_flow_runs_start_names_its_channel`).
