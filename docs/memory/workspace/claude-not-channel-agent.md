---
name: claude-not-channel-agent
description: Claude is no channel agent: never offered in a channel's Agents list, refused with 422 on the add route, its old ws_channel_agents rows unread but removable
---

## Why is Claude no channel agent?
- Owner (2026-11-10), #8445: Claude is asked where `ws_channels.claude_access` allows; no reader takes a `ws_channel_agents` row of `kind = 'claude'` (the tiers read `app` rows only, `agent-risk-tiers.md`). A row that would decide for Claude is a feature of its own, built only if the owner asks.
- Seen on the dev trial (`plans/agent-channel-trial.md` finding 6): the drawer offered Claude, and the row made changed nothing.

## What does the code do?
- `ws_channel_agent_kind_problem($kind)` (pure, `includes/workspace/agents.php`) answers `claude_not_channel_agent` for `claude`; its sentence is in `ws_channel_agent_error_text()`.
- "Add an agent" in Channel settings → "Agents in this channel" never lists Claude (`ws_channel_agents_addable()`); the panel action `ws_channel_agents_set` refuses it after the removal branch, before the row is written.
- `POST /workspace/channels/{id}/agents` (`workspace.channels.agents.set`, its MCP tool too) answers 422 `validation_failed`, field `agent_id`, before `api_dry_run_stop()`, so a rehearsal is refused as well.
- `ws_channel_agents()` leaves a claude row out (`ws_channel_agents_readable()`), so the GET route, the drawer's rows and every reader skip it.

## What happens to rows made before?
- They stay in the table, unread; no data step. The remove route and the panel's removal still take one out (they do not ask the kind).
- Tests: `tests/workspace_channel_agents_claude_test.php`, the Add list in `tests/workspace_channel_agents_panel_test.php`.
