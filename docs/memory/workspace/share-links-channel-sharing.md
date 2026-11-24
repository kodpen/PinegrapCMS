---
name: share-links-channel-sharing
description: The channel head's share mark and menu and Channel info's Sharing part (SL4): what the server sends, the two parts, who sees Stop sharing, the phone entry, what redraws them
---

## What does the server send?
- `ws_channel_detail()` adds `share_links` (`ws_channel_share_links()` in `share_links.php`): null outside an agent channel and in a discussion; else the open links of the channel, newest first, only those `ws_share_link_state()` reads open (messages, files and floors read once for the list, not per link).
- One row is `ws_share_link_row()` (pure): id, message_id, kind, icon (`ws_share_link_kind_icon()`), name, line ("<who> · until <date> · opened <n> times" / "opened once" / "not opened yet", `ws_share_link_opened_words()`), until, can_revoke. Never the address.
- Who: a person's name, an application by its agent's `@handle` (`ws_share_link_maker_name()`).

## What is the head's share mark?
- `sharingButton()` in workspace.js: a `pg-menu-wrap` with a `pg-icon-button pg-icon-button-sm` (`bi-share`, tooltip "Sharing"), placed in `.ws-head-actions` after the closed-share notice and before the faces; drawn only while `sharingShown()`: a guest with an open share (`channel.guests`) or an open link. A room a guest reads (`channel.guest`) never has it.
- Its menu (`.ws-sharing-menu`): "This channel · n" with a read row per guest (eye or chat icon, name, its access line), or "Not shared outside"; then in an agent channel "Shared by link · n", a read row per link (kind icon, name, line) and under it a "Stop sharing" `menu-item` for who may.
- Under 40rem it is `.ws-head-wide` and hides; the ⋯ menu gets the entry "Sharing" (`.ws-head-narrow`), which opens Channel info scrolled to its Sharing (`showSharing()`).

## What is Channel info's Sharing?
- In `drawInfo()` the heading "Sharing" (`data-info-key="sharing"`) took the place of "Guests · n": the same two parts, the guest rows as before (last seen only for a manager), the link rows with a kind tile and "Stop sharing" (`pg-button-quiet pg-button-sm`) at the right. With nothing shared: "Nothing is shared outside this channel."; "Shared by link" only in an agent channel.

## What redraws them?
- Stop sharing anywhere goes through `shareStopLink(id)` (asked once, then `ws_share_link_revoke`). `shareChanged()` (after Share by link… or Stop sharing) calls `app.refreshInfo()`, which reads the channel again: `refreshHead()` puts, replaces or removes the mark, and Channel info redraws.
- A link made by someone else shows at the next channel read (opening it, a channel change); the sync does not carry links.
- Tests: `tests/workspace_share_links_test.php`, `tests/workspace_channel_menu_test.php` (`sl_sharing` is a header entry).
