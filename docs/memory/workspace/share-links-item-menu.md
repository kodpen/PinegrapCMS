---
name: share-links-item-menu
description: Share links on the screen (SL3): the item's mark, Share by link… / Stop sharing in its menu, the dialog that shows the address once, the conversation's system line
---

## What does the message payload carry?
- `file.share` (`ws_share_links_map()`, one query a page, in `ws_message_payloads()`): null outside an agent channel; else `open` (`ws_share_link_item_mark()`: id, until_at, until as `d.m.Y H:i`, can_revoke) or null, and `can_make` (no open link, the reader may post, not an earlier version). The address is never in it.
- The Files tab reads the same payload, so its tiles and rows get the mark too.

## Where is the mark?
- `shareMark()` in workspace.js: `.ws-share-mark` with `bi-link-45deg`, title and aria-label "Shared by link until <date>", on the picture, the file card (a badge over its top-right corner), the media box and the Files gallery tiles; inline after the name in a Files tab row.

## Where are Share by link… and Stop sharing?
- In the message's More / context menu among the file entries (`shareItems()`), never on the hover bar; and in the Files tab's item menu. Stop sharing only for who may take the link back (its maker, the channel's managers).
- Share by link… opens the workspace's question dialog (`ask()`): the file name, "Ends after" as a `pg-seg` of `WS_SHARE_LINK_DAYS` (7 pressed), Create. Then the same dialog shows the address in a read-only `pg-input` with Copy and "Shown once. Anyone with this address can read this item until <date>."; Done closes it.
- Panel actions `ws_share_link_make` (message_id, days) and `ws_share_link_revoke` (link_id) share `ws_share_link_make_check()` / `ws_share_link_can_revoke()` with the API.

## What does the conversation say?
- `ws_share_link_note()` writes a `ws_message_system()` line in the message's own channel from `ws_share_link_make()` and from `ws_share_link_revoke($link, $now, $by)` when `$by` is given (the panel and the API pass it): "<who> shared <item> by link until <date>" / "<who> stopped sharing <item>" (`ws_share_link_line()`, pure).
- Only a link still open when taken back gets the line; one that had expired or closed writes none (`ws_share_link_revoke_line()`, `workflow/bug-hunt-areas-channel-notes-share.md`).
- Who: a person as `<@user:N>`, an application as the `@handle` of its agent in the channel (`ws_share_link_who()`). No agent is woken, no bell line. An end that passes writes nothing. The message is touched so open screens redraw its mark.
