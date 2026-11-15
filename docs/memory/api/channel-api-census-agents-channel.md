---
name: channel-api-census-agents-channel
description: CA1b census, a channel's agents (add, remove, tier, wake rule, loop guard, /wake), model assistants, the agent card and the person card, each beside its route or none
---

## Which panel actions on a channel's agents have a route?
- List the channel's agents (`ws_channel_agents_list`, its `agents`): `GET /workspace/channels/{id}/agents` (`workspace.channels.agents.list`, tool).
- The "Add an agent" list (`addable`, `ws_channel_agents_addable(ws_agents_list(), $in)`): none. G1.
- Add, or change wake rule, prefix, tier, loop guard (`ws_channel_agents_set`): `POST /workspace/channels/{id}/agents` (`workspace.channels.agents.set`, dry run, tool).
- Remove from the channel: `DELETE` or `POST /workspace/channels/{id}/agents/{agent_id}` (`workspace.channels.agents.remove`, dry run, tool).
- `/wake @agent every|at|off`, `/wake @agent` (`ws_command_wake()`): none for a person; `GET|POST /workspace/agent-wake` is the agent's own (off MCP). G4. `GET /workspace/commands` says "what a command does has its own endpoint": for /wake there is none.

## Which model assistant actions have a route?
- Added, woken, tiered and removed as any channel agent: the same three routes (kind `model`).
- Asked: a message that mentions it (`POST /workspace/channels/{id}/messages`); the site answers (`model-assistants.md`).
- Its requests: none. `GET /workspace/assistant-requests` takes `agent` claude, ai or mcp only. G5.
- Provider, model, key, address, Try, token limit: the Agents card, none on purpose (`channel-api-census-agents-differences.md`).

## What of the agent card has a route?
- The member row's facts (state, waiting, seen_at, model, usage windows, reported_at, usage_high, role, wake, tier): on each row of `GET /workspace/channels/{id}/agents` (`ws_api_channel_agent_present()`).
- The card's own reads (`ws_agent_card`, `ws_agent_card()`): today's answered and failed, `last_message`, the agent's other channels the reader may read, the windows' "resets …" words, `reported` sentence: none. G2.
- The plain words of the panel (wake_words, tier_words, state_words): none; a client draws its own from the fields.

## What of the person card has a route?
- `ws_person_card` (`ws_person_card()`: role label, title, departments with leads, working day, away days, clock, seen for a manager): none. G3. `/workspace/channels/{id}/members/{user_id}` has only DELETE.
- `GET /workspace/channels/{id}/members` gives the members, not these facts.
