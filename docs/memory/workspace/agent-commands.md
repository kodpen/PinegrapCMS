---
name: agent-commands
description: An agent's command (CC11, PR #10586): an application with an app agent on its queue has its / commands posted as the writer's line and asked of the agent, only where the agent is allowed
---

## Which commands are an agent's?
- Those of an application with an active `app` agent working through it (`ws_app_command_agent_of()`: the first in id order, as its key resolves to one agent). A poll or a push door alike: both wait on the request queue. An application without one: unchanged (`workspace-app-commands.md`).
- Owner (2026-11-16): typed, the line shows in the channel under the writer's name, with a mention's brakes (poll 96, `workflow/decisions-made-workspace-37.md`).

## Where is it offered and run?
- Only where the agent is allowed: `ws_app_command_route()` over `ws_agent_reach_rows()` (a discussion with no row of its own through its channel, loop guard on). Elsewhere it is not offered and, typed, answered as unknown.
- The "/" list: `ws_command_help()` gives `agent_channels` (the allowed channel ids, null for any other command); `commandPickerRows()` in workspace.js shows it only when the box's channel or a discussion's channel is among them. `/help` in a channel leaves it out server side. `GET /workspace/commands` is unchanged.
- The application must still read the channel (`ws_app_reads_channel()`), as for any application command.

## What happens when it is typed?
- `ws_app_command_send()` reads the values as for any command (a missing required one: the writer is told, nothing posted). Then `ws_app_command_agent_send()`:
- The line `/<name> <words>` is posted with `ws_message_send()` as the writer's message (an answer when typed as one).
- One request for that agent alone: `ws_agent_after_send()` in followed mode (`agent:<id>`), so its brakes, the braked system line and the push knock are a mention's. Other agents, Claude and Pinegrap AI are not asked by the line.
- `ai_state` carries `{"command":{"name":"/x","values":{…}|null}}` (`ws_app_command_request_state()`); `ws_agent_brief()` adds the command and the values as a data block (`ws_app_command_brief_lines()`). An edit that queues the request again clears `ai_state`: the brief then has the line only.
- `workspace.app.command` is sent once, its payload unchanged. The writer's answer is `done` with `message_id`; the screen syncs and shows the line.

## Tests?
- tests/workspace_app_commands_test.php (agent_of, route, entry_reach, agent_send with stubbed writes, brief lines); tests/workspace_command_picker_js_test.php (the list where allowed).
- Not run on a real database or on screen: the posted line with the agent's answer under it, the push knock.
