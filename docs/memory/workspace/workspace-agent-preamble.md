---
name: workspace-agent-preamble
description: The site preamble an MCP agent (switch ws_mcp_servers.agent_preamble, 11.0.111, off by default) and an outside app (GET /workspace/messages/{id}/preamble) get, and what stays out
---

## What is the preamble?
- `ws_ai_agent_preamble($head, $place, $request, $language)` (includes/workspace/ai.php): the head of the shared briefing (`ws_ai_briefing_head($asker, 'agent', $name)`: "You are <name>, an agent asked from the Workspace of the Pinegrap site …", now, who asked by name, no `<@user:N>` tag), the place (`ws_ai_agent_place()`: the channel, a discussion by its channel, a task by number), the request inside a `request` data block when given, then `ws_ai_briefing_text()`: "Answer in …" and the assistant rules last.
- Never in it: the channel's other messages, the people list, a task's notes. A task's title and description stay in the request text the agent already got (`ws_mcp_agent_task_compose()`).

## When does an MCP agent get it?
- Only when its server's switch is on: `ws_mcp_servers.agent_preamble` TINYINT(1) DEFAULT 0, step `upgrade_2026_11_0_workspace_mcp_preamble()` (11.0.111, schema decision #2765 (a); behaviour #2443 answer 2a: off by default, per server). Existing servers start off, so nothing an agent is sent changes until an administrator turns it on.
- `ws_mcp_agent_preamble_on()` trusts the column only after `ws_mcp_agent_preamble_ready()` (`waf_table_has_column()`), as `agent_tool` does: a site whose upgrade has not run sends the message alone.
- `ws_mcp_agent_run()` wraps `ws_mcp_agent_request_text()` with `ws_mcp_agent_preamble()` before the arguments are built, so a call waiting for approval keeps the same text. A carried conversation still gets it: it is short.
- The switch sits under "Who answers @alias" on the server card (`ws_mcp_agent_preamble_field()`), saved with the tools form (`ws_mcp_agent_preamble_save()`, Site Log line); the server's details say "told about the site before each message".

## How does an outside app read it?
- `GET /workspace/messages/{id}/preamble` (`workspace.messages.preamble`, `workspace:read`, `'mcp' => false`, type `WorkspaceAgentPreamble` `{message_id, text}`): the calling application's name, the writer of the message by name, the channel or discussion, the language of the message. No request block: the app has the text from `workspace.app.mentioned` and frames it itself. 404 for a message the owner may not read (`ws_api_message_or_404()`).

## Tests
- tests/ai_agent_preamble_test.php: place lines, the text's order, a forged mark harmless, no channel context or people in the MCP builder, the run asking the switch and the column probe.

Related: core/ai-prompt-data-blocks.md, workspace/workspace-claude-bootstrap.md, plans/ai-agent-prompts.md
