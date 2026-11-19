---
name: mail-screen-pane
description: The Workspace E-mails reading pane (mail_pane.php) and the per-person unread marks (mail_reads.php): the unread rule, the floor, where marks are written, the menu count
---

## What does the pane show?
- A row opens its conversation beside the list (panel action `ws_mail_pane`, `ws_mail_pane()`): "This conversation cannot be opened." unless it has its card (`message_id > 0`) and the reader may read its channel (`ws_can_read_channel()`). Long names wrap: `mail-screen-pane-wrap.md`.
- `ws_mail_pane_html()`: the subject, customer, box and channel, Open in channel (neither for a box kept in no channel, `mail-room.md`), then the newest `WS_MAIL_PANE_MAX` (200) mails oldest first with a notice that the channel has them all. Each mail (`ws_mail_pane_item_html()`): sender, time, To and Cc, the text escaped with `nl2br` or its kept HTML in the guarded frame (`mail-html-preview.md`), its files (`ws_messages` rows of the mail with `file_id`), "Not taken in" for refused files.
- Marks per mail: Not sent with `send_error` and Waiting to be sent, the card's badge (`ws_mail_send_state()`, `ws_mail_send_badge_html()`, `mail-send.md`; a mail that left gets none), Automatic answer (`ws_mail_item_is_auto_reply()`), and Not delivered under an outgoing mail whose bounce came back (`in_reply_to` = its `rfc_message_id`, `mail-sent-and-bounces.md`).
- The open conversation stays in the address (`?conversation=`): a reload or shared link opens it. Below 768 px the pane takes the list's place.
- For that address the server draws the pane (WM10, #8796): `ws_mail_pane_open()` (read mark, Forward offers) before the list and the menu count, `ws_mail_pane_page_html()` with a Close link back to the list (`data-ws-mail-close`), or "This conversation cannot be opened.". The script reads it again for its tools without a loading line.

## What can be done in it?
- Close, and for whoever may answer (`ws_mail_can_answer()`, `ws_mail_pane_tools()`): Reply, which opens the same drawer as the card's Reply by e-mail (`replyForm()`, `mail-reply.md`), Reply to all when the last mail named others (WM7), and the status and holder controls (`ws_mail_state_offer()`, `mail-status.md`). After a change the pane is drawn again; focus that was in it (or fell to the page) goes to its heading (#8779).
- A status or holder change, or Cancel reminder, shows a notice and asks the list again (`refresh()`, `ws_mail_rows` with `whole=1`, `ws_mail_rows_answer()`): page one as the address filters it, so a Closed row leaves Open (#10084). Blocked and Drafts keep theirs.
- Forward under each mail (WM6, `mail-forward.md`). New e-mail is the toolbar's (`mail-compose.md`). Send again (#8646) is on the card only.

## When is a conversation unread?
- Per person, in `ws_mail_reads` (`mail-tables-sending.md`): unread while its newest incoming mail id is greater than both the person's mark on it and their floor (`ws_mail_unread()`, in SQL `ws_mail_unread_sql()`). A mark never moves back.
- The SQL puts the floor in `GREATEST()` unquoted (#9729): MySQL 8 compared them as text.
- The floor is the row with conversation 0. `ws_mail_read_floor()` writes it at the newest mail the first time a person's count is asked for, so nobody starts with a backlog of old mail.

## Where are marks written?
- Opening it in the pane marks it up to its newest mail.
- Opening a channel (`ws_channel_open`) or a sync that reads (`read`) marks each conversation whose card or replies were drawn, up to the newest of its mails among them (`ws_mail_read_rows()`, `ws_mail_read_item_ids()`).
- Mark all as read (`ws_mail_read_all`): the floor moves to the newest mail and the marks below it are deleted.

## What does the menu count?
- `ws_mail_unread_count()`: open conversations of the channels the person reads that are unread for them; the badge of the Communication menu row (`pg_messages_mail_unread()`, MA10b #9453), counted again on each page.

## Tests?
- `tests/workspace_mail_list_test.php` (pane HTML and marks, unread rule and its SQL, item ids, menu count, wiring).
