---
name: channel-api-census-channel-no-route
description: CA1a list 2: what of a channel's own side has no route on purpose or by its nature, with the memory file that says why, and the guest links whose reason no memory file records
---

## What has no route on purpose?
- Site Settings parts of channels: channel templates and voice transcripts in Site Settings (Channels, mail and voice) and the GitHub repository links (`ws_github_link_save`, `ws_github_link_remove`), drawn there and on the Workspace Settings card: `api-settings-no-endpoint.md`, `workspace/workspace-channel-settings.md`, `api-github-channel-links.md`. The Workspace Settings card is the same form; whether it stays without a route is the architect's.
- The person's decisions on a card stay REST only, no tool: `workspace.approvals.decide`, `workspace.changes.apply` / `dismiss` (`api-mcp-no-tool-routes.md`). Own-words routes are routes with no tool, not gaps (`workspace/workspace-api-callers.md`).
- A guest's reach: a channel's share link (`ws_channel_share`, `ws_channel_share_change`), a guest's room (`ws_guest_start`, `ws_guest_relink`, `ws_guest_end`) and an approval link sent again (`ws_approval_guest_resend`). Not built on purpose; the reason is in `channel-api-not-built.md` (architect, 2026-11-15). The nearest rule: no AI app mints a public link (`api-mcp-no-tool-routes.md`, prototype share links).

## What has no route by its nature?
- The reader's application-lines choice and sound: kept in the browser (`localStorage`), no server state.
- The edit box's beat `ws_edit_hold` (`ws_edit_hold_beat`): it holds an agent while a person edits in the panel (`workspace/agent-edit-hold.md`); an API edit is one call.
- A live page's calls and consent `ws_page_call` / `ws_page_consent`: the guarded frame's bridge, answered with the viewer's rights (`workspace/canvas-bridge.md`).
- A table import over several requests (`import_read` … `import_cancel`): the API's `workspace.tables.import` is one call (`api-panel-coverage.md`, why an import is a run).
- A / command typed in the composer: `workspace.commands.run` (no tool) runs it.

Related: `channel-api-census-channel.md`.
