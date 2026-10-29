---
name: mail-list
description: The Workspace E-mails list (workspace_mail.php, mail_list.php, G5b): who sees it, which conversations, the filters, the overdue filter, the menu entry
---

## What does it show?
- `workspace_mail.php` (behind `ws_screen_gate()`): the e-mail conversations of every channel the reader may read (`ws_mail_list_channels()`: distinct `ws_mail_conversations.channel_id`, each through `ws_can_read_channel()`), never decided by the box. Newest mail first (`GREATEST(last_in_at, last_out_at)`), at most `WS_MAIL_LIST_MAX` (100) rows, then a line asks to narrow the list.
- A row: subject (link `workspace.php?channel=&message=` the card), box name, customer, `ws_mail_status_html()` (status, holder, first-answer word), channel, last mail time. Changes happen on the card, not in the list (no writes on this screen).

## Which filters?
- Address words, `ws_mail_list_filters()`: `status` open (default) | waiting | closed | all, `holder` all | me | none, `overdue=1`. Unknown words fall back. Drawn as links (`ws_mail_list_filters_html()`).
- `ws_mail_list_where()` is the pure SQL part. Overdue depends on each box's target, so the query takes unanswered, not closed rows (five times the page) and `ws_mail_first_reply_overdue()` keeps the late ones.

## Where is it in the menu?
- `ws_nav_groups()` key `mail` ("E-mails", group work), flag `ws_mail_list_visible()`: a box (or one of its routes) writes into a channel the person reads; checked on every panel page that draws the list, at most 50 boxes. `ws_side_screen('workspace_mail.php')` = `mail`; `output.php` lights menu 23.

## Tests?
- `tests/workspace_mail_list_test.php`, `tests/workspace_side_test.php`. The page against a database and in a browser was not run in the cloud session.
