---
name: workspace-channel-agent-wake-api
description: POST /workspace/channels/{id}/agents/{agent_id}/wake (PR #9798): a person sets an application agent's scheduled wakes over the API as /wake does, person only, off MCP, refusals
---

## What does the channel wake route do? (PR #9798)
- `POST /workspace/channels/{id}/agents/{agent_id}/wake` (`workspace.channels.agents.wake`, `workspace:write`, dry run) is `/wake @agent` typed in the channel: exactly one of `every` (1-1440 minutes), `at` (a moment to come) or `off`, and `note` (at most 250).
- It reuses `/wake`'s check `ws_agent_wake_problem()`, writer `ws_agent_wake_save()` and system line `ws_agent_wake_system_line()`; the input is read by `ws_api_agent_wake_parsed()`, shared with the agent's own `POST /workspace/agent-wake`.
- The answer is the schedule (`handle`, `every`, `next_at`, `note`) with `channel_id`, `agent_id` and `message_id`, the system line's id (null on a dry run).

## Who may call it?
- A person signed in on a device only: `ws_api_person_only()` runs first, so an application key sets no agent's wakes. `'mcp' => false`: no tool. An agent keeps its own `POST /workspace/agent-wake`.
- As with `/wake`: the person may write in the channel, the agent is allowed in it and active.

## What are its refusals?
- 422 not exactly one of `every`, `at`, `off`, or `at` gone; 404 a channel the person may not read, no such agent, or not an application agent (a model agent has no scheduled wakes); 403 an application key, a person who may not write there, an agent not in the channel or paused; 503 the database not updated.
- Tests: `tests/workspace_channel_agents_api_test.php`. The agent's own schedule: `workspace/agent-wakes.md`.
