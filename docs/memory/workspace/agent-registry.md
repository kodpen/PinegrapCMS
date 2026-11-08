---
name: agent-registry
description: The agent registry (ws_agents) and the channel-agent row (ws_channel_agents): fields, Claude's routine as the first push agent, the membership routes, the depth and hourly brakes
---

## What holds the registry?
- `ws_agents` (step 11.0.172, `upgrade_2026_11_0_ws_agents()`): one row per agent with a door, any number per kind (`claude`, `app`, `model`), each with its own `handle` (unique, alias shape `ws_mcp_alias_shape_problem()`, never a user name or a reserved word: `ws_agent_handle_problem()`). Door: `door_kind` push or poll, `door_url` (push only, https, never a session's `webhook-triggers` address: `ws_agent_door_problem()`), `door_secret_sealed`, failures, last knock, last error. Schedule: `wake_every` minutes, `wake_next_at`, `wake_note` (A4 makes the wakes).
- `ws_channel_agents` (PK channel_id, agent_id): `allowed`, `wake_rule` (`mention`, `all`, `prefix`, `replies`), `wake_prefix`, `tier` 1-3 (default 1), `loop_guard`. It decides waking and the tier only: an application keeps reading with its owner's reach (`ws_app_reads_channel()`; decided in #8039). No row: the agent's defaults, Claude by `ws_channels.claude_access`.
- Pinegrap AI and the agent-mode MCP servers are not rows; `ws_assistants()` lists all three sources (the registry joins it in A4, with the request queue). An MCP alias may not take an agent's handle (`ws_mcp_alias_problem()`).
- Read with `ws_agents_ready()` first: files land before the upgrade. `ws_agent_shape()` never carries a sealed value, only `key_set` / `door_secret_set`. Secrets: `ws_agent_seal()` / `ws_agent_unseal()` (cipher:iv), both columns in the reseal registry.

## How is Claude's routine a push agent?
- The step copies `config.ws_claude_routine_url` and `ws_claude_token` into a `kind = 'claude'`, handle `claude` row, once (a second run finds the row and leaves it). A site with no routine gets no row; the card's first save makes it (`ws_agent_claude_save()`).
- `ws_claude_config()` and `ws_claude_token()` read the row when it is there, config otherwise; the card writes the row once the tables exist. Enabled, the application, the hold and the error stay in config. The two config columns stay unread for one release; their DROP is its own question later.

## Where are agents managed?
- The Agents card of Workspace settings (`includes/workspace/agents_settings.php`), administrators only: `ws_agent_save_check()` (pure) then `ws_agent_save()`. An `app` agent needs an application and a door; a `model` agent a provider, model and key, no door (the site runs it). Claude's row keeps kind, handle and door. Deleting an agent deletes its channel rows; Claude's row is never deleted. A changed schedule restarts `wake_next_at` from now.

## Who may change a channel's agents?
- Routes `workspace.channels.agents.list|set|remove` (`api_agents.php`), MCP tools as every route. Add, change or remove: whoever may post in the channel, in a private one only a member (`ws_channel_agent_add_problem()`). The tier, raised or lowered, only a channel manager (`ws_channel_agent_check()`, 403 forbidden). Fields left out keep their value; a new row is mention, tier 1, loop guard on.

## What are the brakes?
- Code, not prompt: `ws_agent_brake($depth, $last_hour)` refuses a wake when the chain of agent messages it would answer is `WS_AGENT_DEPTH_MAX` (6) deep (`ws_agent_chain_depth()`, a person's message ends the count) or the agent was woken `WS_AGENT_HOURLY_MAX` (30) times in the last hour. They hold for every tier; A4 calls them where it makes a request.
