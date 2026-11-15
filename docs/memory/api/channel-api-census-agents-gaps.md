---
name: channel-api-census-agents-gaps
description: CA1b gap list (2026-11-15): seven gaps, each with the route it would be and the function that already does the work; for the architect to cut CA2 and on
---

## Which gaps did CA1b find?
- G1 "Add an agent" list of Channel settings -> `addable` on `GET /workspace/channels/{id}/agents` (or `GET /workspace/agents`): `ws_channel_agents_addable(ws_agents_list(), $in)`, id, handle, name, status only, never a door or key. Without it a client cannot learn the `agent_id` the set route needs.
- G2 the agent card (`ws_agent_card` action) -> `GET /workspace/channels/{id}/agents/{agent_id}`: `ws_agent_card($viewer, $channel, $agent_id, $now)`, every reader of the channel, its `channels` already narrowed by `ws_can_read_channel()`.
- G3 the person card (`ws_person_card` action) -> `GET /workspace/channels/{id}/members/{user_id}`: `ws_person_card()`, team readers only, `seen` only for a manager (`person-card-facts.md`).
- G4 `/wake @agent` typed in a channel -> `POST /workspace/channels/{id}/agents/{agent_id}/wake` (every, at, off; dry run): `ws_agent_wake_problem()`, `ws_agent_wake_values()`, `ws_agent_wake_save()`, the system line as `ws_command_wake()` writes it.
- G5 a registry agent's and a model assistant's requests -> `agent` values `agent:<id>` and `model:<id>` on `GET /workspace/assistant-requests`: `ws_agent_activity_keys()` and `ws_agent_request_state()`, rows narrowed as that route narrows Claude's.

## Which gaps are words, not routes?
- G6 `ws_agent_brief_tool_lines()`: say the agent's own routes are HTTP only, off MCP.
- G7 the answer route's refusals and description: the 403 names fail as the way to close; the channel-0 422 names `POST /workspace/channels/{id}/messages`; "their own routes" names the polls, approvals and changes routes.

## What does each step keep?
- A read gives no more than the panel shows its reader; a write reuses the panel's check and writer, dry run, schema row, tests (`api-panel-coverage.md`). G1-G4 are tools unless the architect says otherwise; G4 is a person's or an integration's write, beside the agent's own agent-wake.
