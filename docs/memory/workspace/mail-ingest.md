---
name: mail-ingest
description: ws_mail_ingest() (includes/workspace/mail.php): de-duplication, loop gate, DMARC from the box's server, threading, card and answers, attachments, .eml for 30 days
---

## What does ws_mail_ingest($raw, $box) do?
- Parses with `pg_mail_parse(..., with_content)` (`mail-parse.md`), then answers `status`: `stored` (item, conversation, message ids), `duplicate`, `loop` (reason own | auto | bulk), `held`, `refused` (reason too_large | no_sender | no_channel | not_saved). Only `stored` keeps anything.
- Callers: the `.eml` upload (`mail-card.md`) and `ws_mail_fetch()` (`mail-fetch.md`).

## How is a mail kept once?
- `rfc_message_hash` = SHA-256 of `id:<Message-ID>`, else of `raw:<whole mail>` (`ws_mail_hash()`); `UNIQUE (box_id, rfc_message_hash)`. A failed item INSERT is read back as `duplicate` (two readers).

## What is the loop gate?
- A delivery failure of a box answer is read first (`mail-sent-and-bounces.md`).
- `ws_mail_loop_reason()`: this site's mark in `X-Pinegrap-Mail` (`mail-reply.md`), From = the box address, `Auto-Submitted` other than `no`, `Precedence: bulk | junk | list`. Then more than `WS_MAIL_SENDER_HOURLY` (20) items from one sender in one box in an hour is `held` (`idx_sender`).

## When is a sender verified?
- `ws_mail_auth()` reads `dmarc=pass | fail` only from an `Authentication-Results` header whose authserv-id equals the box's `settings.auth_server` (RFC 8601: a server strips forged headers with its own id). A box naming no server stays `none`. G2 should fill `auth_server` per provider (e.g. `mx.google.com`).
- Only `pass` ties a new conversation to `contacts.email_address` (`contact_id`) or `user.user_email` (`user_id`).

## How is the conversation found?
- `ws_mail_find_conversation()`: In-Reply-To, then References newest first (at most 30), hashed and looked up in the box's items, ours or the customer's; then a `[#T<id>]` subject tag, only when that conversation's `customer_email` is the sender. Never by subject. Outgoing answers are items too (`mail-reply.md`), so our own Message-IDs are found by hash.

## What is written in the channel?
- Channel: the conversation's, else routed (`mail-routing.md`); missing or archived: `refused` / `no_channel`.
- New conversation: a top-level message, `sender_kind 'mail'`, `sender_id` the item, kind `message`; its id goes into `ws_mail_conversations.message_id` (the card). An answer: a message with `parent_id` = the card (the card touched), or a new card when the old one was deleted. A new mail sets the conversation `open` again (`closed_at` 0).
- Message body: the text (plain body, else HTML as text by `ws_mail_html_text()`), `<@user:1>`-style tags taken out of their brackets so they name and notify nobody, cut to `WS_MESSAGE_MAX` with `…`. The whole text (up to 1 MB) is `ws_mail_items.body_text`. Written by `ws_mail_message_write()`, not `ws_message_send()`: no mentions, webhooks or assistants. Drawn by `ws_mail_card_html()` (`mail-card.md`).
- No Workspace event or bell yet: the "E-mail received" event and the assignee's notice come with G5.

## What happens to attachments?
- `ws_mail_attachments_sort()`: a nameless inline part is dropped silently; a nameless part, a name `ws_upload_refused()` refuses, one over `ws_upload_max_bytes()`, and those past `WS_MAIL_ATTACHMENTS_MAX` (20) go to `refused_files` (names, one per line). The rest pass `ws_upload_inspect()` and are kept by `ws_store_file_bytes()` (split out of `ws_store_upload_bytes()` in `files.php`, owner the box's `created_by`), each as an answer to the card carrying the file.

## Where is the .eml kept and for how long?
- `data/mail/<box>/<YYYYmm>/<hash>.eml` (`raw_path` relative to `data/`; `data/` is closed to the web server, `data/mail/.htaccess` too). Write failure leaves `raw_path` empty; the mail still comes in.
- `ws_mail_eml_sweep()` from `workspace_recurring_job.php` deletes files older than `WS_MAIL_EML_DAYS` (30), 500 a run, and empties `raw_path`; the item and messages stay.

## Tests?
- `tests/workspace_mail_ingest_test.php`: pure parts.
