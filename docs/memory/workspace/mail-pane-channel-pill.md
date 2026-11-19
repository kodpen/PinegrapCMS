---
name: mail-pane-channel-pill
description: The channel as a pill "In #name" in the open conversation's head on Communication › Incoming e-mail (IM3, #10435): where it leads, when none is drawn, its words, the list row unchanged
---

## What does the head show?
- Under the muted line "customer · box", the channel the conversation is written into as a blue `pg-pill` reading "In #name" (`ws_mail_pane_channel_pill()`, `includes/workspace/mail_pane.php`).
- Owner (2026-11-19): "epostanın başlık alanında yazıldığı kanal adı yazsın" (`workflow/decisions-made-product-78.md`).
- Architect (2026-11-19): a pill in the head, a link to the mail's card, and the words "In #name" are the reading of his words; Open in channel stays.

## Where does it lead?
- To the conversation's card in its channel: `workspace.php?channel=<channel_id>&message=<conversation message_id>`, the address Open in channel uses too, which stays among the head's tools.
- No new read on the server: `ws_mail_pane()` reads the channel, and only a reader of the channel opens the pane.

## When is there none?
- A mailbox kept in no channel (`mail-room.md`) and the Workspace off (`ws_mail_pane_html(…, $channels)` false, `mail-workspace-off.md`): no pill.
- The list row keeps its channel after the box's name (`ws_mail_list_rows_html()`).

## How is it drawn?
- An `<a>` with `pg-pill pg-tone-blue` and the screen class `ws-mail-pane-channel`, which only drops the link's underline (shown again on hover and focus) and cuts a long name with an ellipsis inside the pane's width; the whole words are in its `title`. No colour, size or weight of its own (`ui/look-check.md`).
- Words: `lang('In #{var:1}')`, Turkish "#{var:1} kanalında", a key the flows' post line shares (`fl_post_meta`).
- The server draws the head for the address and for the script's `ws_mail_pane` action, so the pill needs no JavaScript.

## Tests?
- `tests/workspace_mail_list_test.php` (`test_workspace_mail_pane_html`, `test_workspace_mail_pane_channel_pill`), `tests/workspace_mail_room_test.php`.
