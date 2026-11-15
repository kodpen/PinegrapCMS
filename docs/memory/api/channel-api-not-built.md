---
name: channel-api-not-built
description: What of channels the API does not do on purpose after the 2026-11-15 censuses: an administrator opening a private channel, a channel saved as a template, a guest's links, the Workspace Settings card
---

## What stays without a route? (architect, 2026-11-15, after the census CA1a #9764)
- An administrator opening a private channel (`ws_channel_audit_open`, the census's G25). Why: it grants reading of what the members wrote in private and tells the channel so; the person does it on the screen under their own sign-in. A key or a connected app never opens a private channel for itself.
- A channel saved as a template (`ws_template_from_channel`, G21). Why: channel templates are kept and edited in Site Settings, which has no route (`api-settings-no-endpoint.md`); a route would make rows the API can neither list nor change.
- A guest's reach: a channel's share link (`ws_channel_share`, `ws_channel_share_change`), a guest's room (`ws_guest_start`, `ws_guest_relink`, `ws_guest_end`), an approval link sent again (`ws_approval_guest_resend`). Why: a link that lets people outside the team in is made by a person on the screen, the same rule as no AI app minting a public link (`api-mcp-no-tool-routes.md`).
- The Workspace Settings card (channel templates, voice transcripts, the GitHub repository links): the same form as Site Settings, so the same rule (`api-settings-no-endpoint.md`, `api-github-channel-links.md`).

## Whose call is this?
- The architect's reading of how far the API reaches, not an owner decision: none of it changes what a person does in the panel. The owner may open any of them; that is then a step of its own with his reason.

## What is built for the person alone?
- Access and destructive writes get a route for the person alone (`ws_api_person_only()`, no MCP tool): make private or public, join, clear the conversation, the Claude, Pinegrap AI and voice switches, undo of an agent's canvas edit, a binned table row deleted for good, an agent's wake set by a person. The steps: `plans/channel-api-gaps-steps.md`.

Related: docs/memory/api/channel-api-census-channel-no-route.md, docs/memory/workspace/workspace-api-callers.md
