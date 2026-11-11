---
name: mail-send-schema
description: Step 11.0.185 (PR #PRNUM): ws_mail_items bcc_list, send_state, send_error, outbox_id and table ws_mail_reads, the schema of sending mail and per-person read marks
---

## Which step adds them?
- `upgrade_2026_11_0_ws_mail_send()` (label 11.0.185) in `includes/migrations/2026.11.0.php`; `ws_mail_reads` is in `get_tables()`. Approved by the owner in #8604 (option A, 2026-11-11).
- Additions only: the four columns go in with `install_add_columns_instant()` (copy fallback `install_add_column()`), no row is rewritten.
- `tests/workspace_mail_send_schema_test.php` runs the step twice against an in-memory schema with the real runner helpers; the second run must write nothing.

## What do the new ws_mail_items columns hold?
- `bcc_list` TEXT: an outgoing mail's Bcc, kept apart from `to_list` / `cc_list` so it is shown only to the writer's side, never in the headers other recipients see.
- `send_state` ENUM('', 'queued', 'sent', 'failed'): empty for mail that came in and for answers written before the step; code reads an empty out item as sent. Queued shows Waiting to be sent, failed shows Not sent.
- `send_error` VARCHAR(255): why the queue gave up (set by `ws_mail_send_outcome()`).
- `outbox_id` INT: the `mail_outbox` row while the mail waits in the queue (`core/mail-queue-outcome.md`).

## What does ws_mail_reads hold?
- One row per person and conversation: `conversation_id`, `user_id`, `item_id` (the newest item the person has read). Primary key (`conversation_id`, `user_id`); `idx_user` serves Mark all as read.
- A row with `conversation_id` 0 is the person's floor: every item up to `item_id` is read. It is written at the newest item when the person's count is first asked for, and by Mark all as read, so nobody starts with a backlog.
- Why a table: the only other read mark is one position per channel (`ws_channel_members.last_read_id`); opening one mail would mark the whole channel read.
