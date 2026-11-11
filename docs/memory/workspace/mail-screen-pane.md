---
name: mail-screen-pane
description: The Workspace E-mails reading pane (mail_pane.php) and the per-person unread marks (mail_reads.php): the unread rule, the floor, where marks are written, the menu count
---

## What does the pane show?
- A row of the list opens its conversation beside the list (panel action `ws_mail_pane`, `ws_mail_pane()`): null, so "This conversation cannot be opened.", unless the conversation has its card (`message_id > 0`) and the reader may read its channel (`ws_can_read_channel()`).
- `ws_mail_pane_html()`: the subject, customer, box and channel, Open in channel, then the newest `WS_MAIL_PANE_MAX` (200) mails oldest first with a notice that the channel has them all. Each mail (`ws_mail_pane_item_html()`): sender, time, To and Cc, the text escaped with `nl2br`, its files (`ws_messages` rows of the mail with `file_id`), "Not taken in" for refused files.
- Marks per mail: Not sent with `send_error` and Waiting to be sent, the card's badge (`ws_mail_send_state()`, `ws_mail_send_badge_html()`, `workspace/mail-send.md`; a mail that left gets none), Automatic answer (`ws_mail_item_is_auto_reply()`), and Not delivered under an outgoing mail whose bounce came back (`in_reply_to` = its `rfc_message_id`, `workspace/mail-sent-and-bounces.md`).
- The open conversation stays in the address (`?conversation=`), so a reload or a shared link opens it again. Below 768 px the pane takes the list's place.

## What can be done in it?
- Close, and for whoever may write in the channel (`ws_can_post_channel()`, `ws_mail_pane_tools()`): Reply, which opens the same drawer as the card's Reply by e-mail (`compose()`, `workspace/mail-reply.md`), and the status and holder controls (`ws_mail_state_offer()`, `workspace/mail-status.md`). After a change the pane is drawn again.
- Not yet there: Reply to all and Forward (WM6, WM7), New e-mail (WM5). Send again (#8646) is on the card only; no step brings it to the pane yet.

## When is a conversation unread?
- Per person, in `ws_mail_reads` (`workspace/mail-tables-sending.md`): unread while its newest incoming mail id is greater than both the person's mark on it and their floor (`ws_mail_unread()`, in SQL `ws_mail_unread_sql()`). A mark never moves back (`GREATEST` on upsert).
- The floor is the row with conversation 0. `ws_mail_read_floor()` writes it at the newest mail the first time a person's count is asked for, so nobody starts with a backlog of old mail.

## Where are marks written?
- Opening it in the pane marks it up to its newest mail.
- Opening a channel (`ws_channel_open`) or a sync that reads (`read`) marks each conversation whose card or replies were drawn, up to the newest of its mails among them (`ws_mail_read_rows()`, `ws_mail_read_item_ids()`).
- Mark all as read (`ws_mail_read_all`): the floor moves to the newest mail and the marks below it are deleted.

## What does the menu count?
- `ws_mail_unread_count()`: open conversations of the channels the person reads that are unread for them; on the E-mails tile (`mail_unread` flag of `ws_nav_groups()`, `count` of `ws_side_nav()`, `countBadge()` in `workspace_side.js`). The screen's script updates the tile after a pane opens or Mark all as read.

## Tests?
- `tests/workspace_mail_list_test.php` (pane HTML and marks, unread rule, item ids, menu count, wiring). Not run against a database or in a browser for PR #8652.
