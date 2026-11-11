---
name: mail-list
description: The Workspace E-mails list (workspace_mail.php, mail_list.php): which conversations, the views Incoming, Sent and Not sent, the filters, mailbox, search, Load more, the row, the menu entry
---

## What does it show?
- `workspace_mail.php` (behind `ws_screen_gate()`, and `ws_mail_reads_ready()`): the e-mail conversations of every channel the reader may read (`ws_mail_list_channels()`: distinct `ws_mail_conversations.channel_id`, each through `ws_can_read_channel()`), never decided by the box. Newest mail first (`GREATEST(last_in_at, last_out_at)`, then id).
- Beside the list the reading pane opens a conversation (`workspace/mail-screen-pane.md`); without the script a row links to the card in the channel (`workspace.php?channel=&message=`).

## Which views and filters?
- Address words, `ws_mail_list_filters()`: `view` incoming (default; `last_in_at > 0`) | sent (an outgoing item with `send_state` '' or sent) | unsent (an outgoing item queued or failed) | all (the API only); `status` open (default in Incoming, else all) | waiting | closed | all; `holder` all | me | none; `overdue=1`; `box` (a mailbox id); `q`; `before`. Unknown words fall back.
- The toolbar (`ws_mail_list_filters_html()`): Incoming / Sent / Not sent buttons, status and holder selects, a Mailbox select when more than one box has conversations the reader sees (`ws_mail_list_boxes()`), the overdue toggle, the search field, Mark all as read and New e-mail for whoever can send (`mail-compose.md`). `ws_mail_list_query()` builds each link's address and drops the defaults.
- Search (`q`, at most `WS_MAIL_LIST_QUERY_MAX` 100 characters, folded through `ws_search_like_sql()`): the conversation's subject, customer name and address, or any item's sender, To, Cc or text.
- `ws_mail_list_where()` is the pure SQL part. Overdue depends on each box's target, so the query takes five pages of unanswered, not closed rows and `ws_mail_first_reply_overdue()` keeps the late ones.

## How does Load more work?
- A page is `WS_MAIL_LIST_PAGE` (50) rows. The cursor `before=<time>-<id>` (`ws_mail_list_cursor()`) is the last row's newest mail time and id; `ws_mail_list()` returns `rows` and `next` ('' at the end). The link works without the script; with it the panel action `ws_mail_rows` adds the rows and the next link under the list.

## What does a row show?
- `ws_mail_list_rows_html()`: the customer (bold and a hidden "Unread" while unread), the number of mails when more than one, the time (red when the first answer is late), the subject, the newest mail's first words (240 characters, `ws_mail_list_mails()`), a Not sent or Waiting to be sent badge, box name, channel and `ws_mail_status_html()`. The list itself writes nothing.

## Where is it in the menu?
- `ws_nav_groups()` key `mail` ("E-mails", group work), flag `ws_mail_list_visible()`: a box (or one of its routes) writes into a channel the person reads; checked on every panel page that draws the list, at most 50 boxes. Its tile shows the unread count (`mail_unread`, `workspace/mail-screen-pane.md`). `ws_side_screen('workspace_mail.php')` = `mail`; `output.php` lights menu 23.
- The API's list (`api_mail.php`) asks view all, so it still lists every conversation; its own `view`, `box_id`, `q` and `unread` are step WM8.

## Tests?
- `tests/workspace_mail_list_test.php` (filters, cursor, query, where, rows, toolbar, unread rule, pane, menu count), `tests/workspace_side_test.php`. The screen against a database and in a browser was not run for PR #8652.
