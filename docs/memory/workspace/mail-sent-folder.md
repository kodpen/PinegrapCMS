---
name: mail-sent-folder
description: A mailbox's Sent folder read by the mail job (IB7, #9492, step 11.0.200): the switch, a position from the present, which mails join, send_state external read as sent
---

## What does it do?
- An answer written in a mail program (Outlook, Gmail's web page) to a customer of a Workspace conversation shows under the card as a sent mail. Owner's yes to the columns: channel message 2955 (2026-11-14); the shape is the architect's (2863).

## Where is it switched on?
- Box form (Workspace settings, Mailboxes), under "Sent folder": "Read the Sent folder", off by default, kept in `settings.read_sent` (`ws_mail_box_input()`). The folder is the one typed in "Sent folder", else `pg_imap_sent_folder()` (\Sent mark, then usual names).
- `ws_mail_sent_position_resets()`: a save with the switch off, or with another Sent folder while on, writes `sent_uidvalidity` and `sent_last_uid` back to 0.

## When is it read?
- In `ws_mail_fetch_box()` after the box's own folder is finished and marked, before logout, in the same session and lock: only when `ws_mail_sent_due()` (switched on, step 11.0.200 ran, at least `WS_MAIL_SENT_LEFT` (5) s of the 15 s IMAP budget left). Check e-mail and the job both go through `ws_mail_fetch()`.
- The folder is opened with EXAMINE: nothing there is marked or moved. A failure keeps the position and never fails the box; `last_error` speaks only of the box's own folder.

## Where does a read start?
- `ws_mail_sent_plan()`: position 0 or a changed UIDVALIDITY reads nothing and stores UIDNEXT − 1, so nothing older than the switch comes in; a server without UIDNEXT stores nothing. Then `UID <sent_last_uid+1>:*`, at most `WS_MAIL_SENT_MAX` (10) downloads a run, the position moved by `ws_mail_fetch_cursor()`.
- A Message-ID already in `ws_mail_items` of the box is skipped without download: the Workspace's own answers, copied there or kept by Gmail and Microsoft 365.

## Which mail joins a conversation?
- `ws_mail_sent_conversation()`: a mail its In-Reply-To or References name, kept in the box with a conversation; else the conversation of the subject's `[#T<id>]` tag when its customer is in To, Cc or Bcc (`ws_mail_sent_to_customer()`). Anything else is passed over, its UID still counted as done.
- `ws_mail_sent_store()`: an `out` item with `send_state = 'external'`, `user_id` 0, `auth` pass, To/Cc/Bcc, text, HTML when kept, the `.eml` for 30 days; its files are not taken in (only the `.eml` keeps them). A message under the card, as an answer's (`ws_mail_message_write()`), and the card touched. No queue, event, notice or automatic answer.
- `ws_mail_sent_conversation_changes()`: `last_out_at` (the mail's Date, or now when none or in the future), `first_reply_at` when 0, `waiting` when it is newer than the customer's last mail and the conversation is open; closed stays closed.

## How is 'external' read?
- `ws_mail_send_state()` answers `external`; nothing compares with `sent`, so the "Sent by e-mail" badge, no "Send again" (only `failed`). The Sent view of the E-mails screen and the API take `('', 'sent', 'external')`.
- `ws_mail_item_is_auto_reply()` excludes it: an out item with no writer is otherwise the automatic answer.
- API: the conversation's mails carry `send_state` external, `user_id` null; route texts in `api_mail.php`.

## Tests?
- `tests/workspace_mail_sent_folder_test.php`: the step twice in memory, due, plan, conversation changes, recipients, the reset on save, external read as sent, wiring. No IMAP server was reached from the cloud session.
