---
name: mail-not-delivered-rows
description: A sent Workspace mail whose delivery failure came back reads "Not delivered" with the server's words on its Sends row and mail queue row, read at list time for the page's rows only (MW2)
---

## What does a row show?
- Communication › Sends (`pg_messages_sends_row()`) and the mail queue list (`pg_mail_queue_list_row()`): a `sent` row of type `workspace` whose mail got a delivery failure reads badge "Not delivered" (danger) and shows the failure's words where the error goes. The stored status stays `sent`, so the State filter and the tiles still count it as sent.
- Architect (2026-11-13), channel 25: read at list time for the page's rows only; no new table or column (MW2 of the mail domain warning; MW1: `mail-domain-warning.md`).

## How is a row tied to its bounce?
- `pg_mail_queue_bounced($row_ids)` in `includes/fn/mail_queue_list.php`, for the ids `pg_mail_queue_bounce_candidates()` picks (sent, type `workspace`): the rows' `properties` by primary key, `on_outcome` `workspace_mail` and `outcome_ref` `<item>:<previous row>` (`core/mail-queue-outcome.md`); the items by primary key; bounces `conversation_id = 0`, `direction = 'in'`, `user_id > 0`, `in_reply_to` the item's Message-ID (idx_conversation; `mail-sent-and-bounces.md`).
- `pg_mail_queue_bounce_ties()` (pure) keeps a row only while the item's `outbox_id` is that row (Send again moves it) and the bounce is in the item's own box; the first notice's words win.
- A site without `ws_mail_items` reads nothing (the failed query returns false). Three reads per page at most; nothing is stored.

## What is not covered?
- A mail sent at once without the queue has no row. A failure notice that reached the site's bounce mailbox rather than the Workspace box is the queue's `mail_bounces` matter (`mail-bounce-dev-check.md`).

Tests: `tests/mail_queue_bounce_test.php`. Not run against a database.
