---
name: workspace-api-link-preview
description: link_preview on a Workspace message read over the external API and MCP (LP3): the card's plain fields, the states and the switch that leave it null, which routes carry it
---

## What does a message read over the API carry?
- `link_preview` on every `WorkspaceMessage` (`ws_api_message_present()`): `url`, `title`, `description`, `site_name` (object `WorkspaceMessageLinkPreview`), the plain texts as stored, never HTML; a client escapes them where it draws them. Empty strings for a description or site name the page did not give.
- It is null for a deleted message, for a row `waiting`, `none` or `removed`, for a row with no title or no web address, for a message with no row, and on every message while the Workspace Settings switch is off or on a demonstration site.
- The rule is the screen's own: `ws_link_preview_card_rows_map()` and `ws_link_preview_card_fields()` (`includes/workspace/link_preview.php`), never written again in the API.

## Which routes carry it, and which do not?
- Every route that answers a `WorkspaceMessage` carries it: the channel's or a discussion's message list (`GET /workspace/channels/{id}/messages`, one query for the page), the channel's files, sync, and every route that answers one message (post, edit, reactions, pin and the rest).
- `GET /workspace/search` answers `WorkspaceSearchHit`, not a message: no card, as the screen's search hits show text only.
- An agent's answer drawn in the panel as an agent view shows no card there; over the API it is an ordinary message and carries its card.

## How does a client learn of a card read later?
- The job touches the message once it is read and Remove preview touches it again, so `changed_since` on the message list (and sync) brings the message with its card set or null.

## Can a client write a preview?
- No: the API only reads it. Removing a card is the writer's "Remove preview" in the panel (`workspace/workspace-link-preview-card.md`).

Tests: `tests/workspace_link_preview_test.php`.
