---
name: channel-api-not-built
description: What of channels and their agents has no API route, on purpose or by its nature (comparison 2026-11-15): private channel opened by an admin, templates, guest links, settings cards
---

## What stays without a route on purpose? (architect, 2026-11-15, after the comparison #9764, #9761)
- An administrator opening a private channel (`ws_channel_audit_open`). Why: it grants reading of what the members wrote in private and tells the channel so; the person does it on the screen under their own sign-in. A key or a connected app never opens a private channel for itself.
- A channel saved as a template (`ws_template_from_channel`). Why: channel templates are kept and edited in Site Settings, which has no route (`api-settings-no-endpoint.md`); a route would make rows the API can neither list nor change.
- A guest's reach: a channel's share link (`ws_channel_share`, `ws_channel_share_change`), a guest's room (`ws_guest_start`, `ws_guest_relink`, `ws_guest_end`), an approval link sent again (`ws_approval_guest_resend`). Why: a link that lets people outside the team in is made by a person on the screen, the same rule as no AI app minting a public link (`api-mcp-no-tool-routes.md`).
- Site Settings and the Workspace Settings card (one form): channel templates, voice transcripts, the GitHub repository links (`api-settings-no-endpoint.md`, `api-github-channel-links.md`), and the Agents card: the registry, a model assistant's provider, key and Try, the activity buttons, the start text (`workspace/workspace-agent-settings.md`).
- The person's own decisions on a card stay REST only, no tool: `workspace.approvals.decide`, `workspace.changes.apply` / `dismiss` (`api-mcp-no-tool-routes.md`); a vote on an agent question (`workspace/workspace-api-callers.md`); undo of a change an agent applied within its tier (`workspace/agent-risk-tiers.md`); naming agents on an approval from an application key (`workspace/approval-agents.md`).
- Claude as a channel agent: `workspace/claude-not-channel-agent.md`. A waiting read of the queue, and the instructions as an MCP resource: `workspace/agent-instructions.md`.

## What has no route by its nature?
- The reader's application-lines choice and sound: kept in the browser, no server state.
- The edit box's beat (`ws_edit_hold_beat`): it holds an agent while a person edits in the panel (`workspace/agent-edit-hold.md`); an API edit is one call.
- A live page's calls and consent (`ws_page_call`, `ws_page_consent`): the guarded frame's bridge (`workspace/canvas-bridge.md`).
- A table import over several requests: `workspace.tables.import` is one call (`api-panel-coverage.md`). A / command: `workspace.commands.run` (no tool) runs it.

## Whose call is this?
- The architect's reading of how far the API reaches, not an owner decision: none of it changes what a person does in the panel. The owner may open any of them; that is then a step of its own with his reason.

## What is built for the person alone?
- Access and destructive writes have a route for the person alone (`ws_api_person_only()`, no MCP tool): make private or public, join, clear the conversation, the Claude, Pinegrap AI and voice switches, undo of an agent's canvas edit, a binned table row deleted for good, an agent's wake set by a person. Each is in its topic file: [[api-panel-coverage-channels]].

Related: docs/memory/api/channel-api-differences.md, docs/memory/workspace/workspace-api-callers.md
