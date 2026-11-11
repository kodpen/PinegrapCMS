---
name: mail-send
description: Sending Workspace mail through the mail queue (mail_send.php, WM3 #8646): functions, the flow, send states and the card, Send again, limits, mail_unsent, the outcome handler's rules
---

## Which functions?
- `includes/workspace/mail_send.php` (PR #8646). `ws_mail_send_boxes($viewer)`: open boxes with a working route (`ws_mail_reply_route()`) and the channels a mail may be kept in (the box's own and its routes' channels the person may post in); team accounts only (`member`), never a guest.
- `ws_mail_send_check($viewer, $mail)` checks everything before a write; `$mail` = box_id, channel_id, to, cc, bcc (text or list), subject, text, files (each `name` and decoded `bytes`). Pure parts: `ws_mail_send_input()`, `ws_mail_send_limits()`. Words: `ws_mail_send_error_text($error, $detail)`.
- `ws_mail_send($viewer, $mail)` → ok, error, detail, conversation_id, item_id, message_id, state. Called by New e-mail (`ws_mail_new`, `mail-compose.md`); the answer comes to it in WM7, until then `ws_mail_reply_send()` sends at once.

## How does a mail leave?
- Writes the conversation (customer = first To, status `waiting`, holder the writer, `last_out_at`), the out item (`send_state` `queued`, to/cc/bcc lists, subject with ` [#T<id>]`, Message-ID `pg.c<conversation>…`), the card and one message per file under it (`ws_store_file_bytes()`).
- `ws_mail_send_dispatch($item, $previous)` builds the mail from the item (`ws_mail_send_properties()`: From the box, its route, text plus writer and the box's Signature or name (`ws_mail_send_body()`, `mail-compose.md`), files by path through `pg_file_local_copy()`, type `workspace`, `secret`, `notify_sender` off, `keep_mime`). While `pg_mail_job_alive()`: `pg_mail_enqueue()` with `on_outcome` `workspace_mail` and `outcome_ref` `<item>:<previous row>`, then `outbox_id` written. Else, or when enqueue gives 0: `email()` at once and the outcome handled in the request. Never `email()`'s `queue`: it calls no handler.
- An answer from any recipient finds the conversation by References; the subject tag only from the first To (`ws_mail_find_conversation()`).

## What does the outcome handler do?
- `ws_mail_send_outcome()`: `mail-send-outcome.md`.

## What does the card show?
- `ws_mail_send_badge_html()`: '' (no column, earlier answers) and `sent` "Sent by e-mail"; `queued` "Waiting to be sent" (also while the queue retries); `failed` "Not sent" and the reason, escaped.
- Payload `mail_resend` (`ws_mail_resend_offer()`): the item id on a failed out item for a team member who may post. `workspace_mail.js` draws Send again in `node()`, which workspace.js calls only where `mail_reply` is set (a conversation's card, so a new mail's own card); an answer under the card needs that gate widened (WM7).

## Who may press Send again?
- Action `ws_mail_resend`: anyone who may send from the item's channel (`ws_can_post_channel()`, team only), the same rule as sending; the conversation is the channel's. Hourly limit and the bounce list again. One press counts: `failed` → `queued` only where the row still reads failed.

## Which limits?
- 20 addresses (To, Cc, Bcc), each valid; one on the bounce list refused by name (the queue gates only To); subject required; text 1 to `WS_MAIL_REPLY_MAX`; 10 files, 20 MB together, the channel's upload rules; `WS_MAIL_SEND_HOURLY` 30 out items a person an hour (answers count; Send again adds none).

## Where does mail_unsent go?
- `ws_mail_unsent_describe()` (title "Your e-mail in … could not be sent", body the subject), the API inbox list. Not read with the channel (`ws_channel_mark_read()`): a failure stays until opened.

## Why?
- Owner (#8604): a mail that could not leave is seen, its writer hears and it can be sent again; the queue tells an outcome only through a handler (`core/mail-queue-outcome.md`).

Tests: `tests/workspace_mail_send_test.php`. Not run against a database or a mail server.
