---
name: workspace-channel-agents-api-reads
description: The agents a channel can still take (addable on GET /workspace/channels/{id}/agents) and the agent card route over the API and MCP: fields, readers, refusals (PR #9766)
---

## What does addable on GET /workspace/channels/{id}/agents give? (PR #9766)
- Beside `data` (the channel's agents), `addable` lists the registered agents the channel does not have yet: `id`, `handle`, `name`, `status` (`active`, `paused`, `off`), never a door or a key. An `id` there is the `agent_id` `POST /workspace/channels/{id}/agents` takes; before it a client had no way to learn one.
- It is the panel's "Add an agent" list of Channel settings: `ws_channel_agents_addable(ws_agents_list(), $in)`, shown by `ws_api_addable_agents_present()`. Claude is left out there (`claude-not-channel-agent.md`).
- Every reader of the channel gets it, as the panel's list action gives it to every reader; adding stays the set route's check. The registry not updated yet leaves it empty, the list still answers.

## What does GET /workspace/channels/{id}/agents/{agent_id} give? (PR #9766)
- The agent card as the channel opens it on the agent's name or picture: `ws_agent_card($viewer, $channel, $agent_id, $now)`, shown by `ws_api_agent_card_present()`, which adds `channel_id` and turns a window's `resets_at` into an API time. Every other field is the panel's, words fields (`role`, `seen`, `wake`, `reported`, `state_words`, a window's `resets`, `last_message.when`) in the site's language.
- `channels` lists only the channels the owner of the application may read (`ws_agent_reader_channels()`).
- Scope `workspace:read`, an MCP tool `workspace_channels_agents_card`. Refusals: a channel the owner may not read or an agent not in it 404 `not_found`; the registry not updated yet 503 `service_unavailable`.
- Tests: `tests/workspace_channel_agents_api_test.php`.
