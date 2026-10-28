---
name: mail-tables
description: Incoming e-mail tables (G1b, step 11.0.82): ws_mail_boxes, ws_mail_conversations, ws_mail_items, sender kind 'mail'; what each column holds and why, pending the architect's approval
---

## Which step adds them?
- `upgrade_2026_11_0_workspace_mail()` (label 11.0.82) in `includes/migrations/2026.11.0.php`; the three tables are in `get_tables()`. The parser module `includes/fn/mail_parse.php` is loaded by `functions.php` from the same PR.
- The owner's answers in #2444 approved the feature, not the tables: the schema waits on an `architect` issue (Fixed rules 5) named in the PR body.

## What does ws_mail_boxes hold?
- `name`, `address`, `transport` (imap | graph | gmail | push), `settings` TEXT (JSON: server, port, user, the password sealed with `pg_connect_seal()`, the move folder, a connection id), `folder` (default `INBOX`), `after_fetch` (seen | move), `channel_id`, `reply_via` (box | site | connection).
- `auto_reply` (0 by default) and `auto_reply_text`: answer 8 (per box, off, editable), here so G5 needs no step.
- IMAP position `uidvalidity` / `last_uid`; `checked_at`, `ok_at`, `last_error` for the box card and System status; `enabled`, `created_by`, `created_at`, `updated_at`.

## What does ws_mail_conversations hold?
- `box_id`, `channel_id`, `message_id` (the card in `ws_messages`; the thread is that message's replies by `parent_id`, so no thread column), `subject`, `customer_email` / `customer_name`, `contact_id` or `user_id` (only for a verified sender matching `contacts.email_address` / `user.user_email`).
- `contact_id` is a holder in `pg_contact_holders()` (merge moves it, erasure `none` like other Workspace records; `tests/contact_holders_test.php` checks every `contact_id` column).
- `status` (open | waiting | closed), `assignee_id`, `first_in_at`, `first_reply_at`, `last_in_at`, `last_out_at`, `closed_at`: G5's columns now, so G5 needs no step.

## What does ws_mail_items hold?
- Every mail in and out: `box_id`, `conversation_id`, `message_id` (its card or thread message), `direction` (in | out), `rfc_message_id`, `in_reply_to`, `reference_ids` (`references` is a reserved word), `from_email` / `from_name`, `to_list` / `cc_list`, `subject`, `body_text` MEDIUMTEXT, `auth` (pass | fail | none), `raw_path` (the `.eml` outside the web root, 30 days), `refused_files` (names only), `user_id` (the writer of an outgoing mail), `sent_at` (the mail's Date), `created_at`.
- `rfc_message_hash` CHAR(64): SHA-256 of the Message-ID, or of the raw mail when it has none. `UNIQUE (box_id, rfc_message_hash)` is the de-duplication; a 255-character utf8mb4 key would pass the 767-byte index limit of older InnoDB row formats.
- `idx_sender (box_id, from_email(191), created_at)` serves the loop gate's per-sender hourly count; `idx_created` the `.eml` cleanup.

## Which existing columns change?
- `ws_messages.sender_kind` and `ws_message_forwards.source_sender_kind` gain `'mail'` (sender_id the `ws_mail_items` row), widened only when the value is missing, as for `'guest'` in 2026.4.5. Forwards are included so a mail card forwarded to another channel keeps its kind.
