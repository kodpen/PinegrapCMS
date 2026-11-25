---
name: share-links
description: Share by link of an agent channel's item (SL1-SL6): a file, note, table or checklist by an unguessable address with an end; who makes one, when it closes, the address
---

## What is a share link?
- Owner (2026-11-23): an item of an agent channel can be handed on by a link, so a coordinator gives a worker the picture the owner posted; one item a link, read only, always with an end, taken back at any time (`workflow/decisions-made-workspace-49.md`).
- A `ws_share_links` row: one item of one message of an agent channel (`ws_channels.agent_channel`) or of a discussion of one. Kinds: `file`, `note` (the channel's own note whose card the message is, never a person's note shown there), `table` and `list` (a table or a checklist written in the message's text, `share-links-table-list.md`).
- The address is `workspace_share.php?t=<token>`, shown once when the link is made; only the token's SHA-256 is kept (`share-links-api.md`).

## How long does it last?
- 1, 7 or 30 days, 7 unless chosen; never without an end. Its maker and whoever manages the channel take it back.
- Closed while the channel is gone or archived, the message is deleted or lies before the conversation's clear floor, or the item is gone (`ws_share_link_state()`, one pure rule). An end that passes writes no line.
- At most 200 open links a channel; a second link to an item with an open one is refused with that link's end.

## Who makes one?
- A person who may post in the channel, from the item's menu (`share-links-screens.md`).
- An application with an allowed agent row in the channel, by `POST /workspace/messages/{id}/share-links` or its MCP tool, at once and without a card: making a link changes no record (`agent-risk-tiers.md`).

## What does the address answer?
- No sign-in, no cookie, nothing else listed. Every fault (bad shape, unknown, ended, taken back, closed) is one 404 line "This link is not valid."; a token no row has counts against the address in the firewall.
- A file: its bytes, a picture inline, anything a browser could run as a download. A note, a table, a checklist: a read-only page with tags as plain names; `&raw=1` gives a note's or a checklist's Markdown and a table's CSV.
- Headers `X-Robots-Tag: noindex, nofollow`, `Referrer-Policy: no-referrer`, `Cache-Control: private, no-store`. An open is counted once a minute a link at most.

## Where is each part?
- `includes/workspace/share_links.php` (links, state, make, take back, the map of marks, the channel's Sharing, the pages), `share_links_text.php` (tables and checklists), `api_share_links.php`, `workspace_share.php`; tests `tests/workspace_share_links_test.php`.
- Details: `share-links-api.md` (table, routes, address), `share-links-item-menu.md` (item menu and dialog), `share-links-channel-sharing.md` (head's mark, Channel info), `share-links-table-list.md`, `share-links-screens.md`.
- How this project's sessions use it: `workflow/share-links-for-workers.md`.
