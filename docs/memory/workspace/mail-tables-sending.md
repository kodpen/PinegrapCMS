---
name: mail-tables-sending
description: What step 11.0.185 (WM2, #NNNN) adds for sending and reading Workspace mail: ws_mail_items bcc_list, send_state, send_error, outbox_id and table ws_mail_reads; types and why
---

## Which step adds them?
- `upgrade_2026_11_0_ws_mail_send()` (label 11.0.185) in `includes/migrations/2026.11.0.php`, PR #NNNN; `ws_mail_reads` is in `get_tables()`. Skipped when `ws_mail_items` is missing.
- The schema is the owner's (2026-11-11, #8604, "A: dört sütun + tablo."; `workflow/decisions-made-schema-15.md`). The plan wants a send path (WM3) and an unread mark per person (WM4): `plans/workspace-mail-screen-sending.md`.
- Run twice on a sandbox DB (MariaDB, CLI `install/index.php automated_upgrade` from 2026.4.10): the first run executed the five statements, the second found all 327 of the version in place. `tests/workspace_mail_send_schema_test.php` runs it twice against an in-memory schema.

## What does each new ws_mail_items column hold?
- `bcc_list` TEXT NULL, as `to_list` / `cc_list`: the Bcc of a mail the Workspace sends.
- `send_state` VARCHAR(8) NOT NULL DEFAULT '': '' for mail that came in and for answers written before the step; else `queued`, `sent`, `failed`. Reading code takes an out item with '' as sent. A VARCHAR, not an ENUM: a later state needs no MODIFY of a table that grows with every mail.
- `send_error` VARCHAR(500) NOT NULL DEFAULT '': the reason a send failed; the mail queue clips its `last_error` to 500 (`pg_mail_clip()`), so the reason fits whole.
- `outbox_id` INT UNSIGNED NOT NULL DEFAULT 0: the `mail_outbox` row while the mail waits; the queue's outcome finds the item by it (WM3, `core/mail-queue-outcome.md`).

## What does ws_mail_reads hold?
- `conversation_id`, `user_id`, `item_id` (INT UNSIGNED, 0 by default), PRIMARY KEY (`conversation_id`, `user_id`), InnoDB utf8mb4_unicode_ci; no other index.
- The newest item of a conversation the person has read. A row with conversation 0 is the person's floor: everything up to that item is read. The screen (WM4) writes it at the newest item when the person's count is first asked for and on Mark all as read, so nobody starts with a backlog.
- Why a table: the only read mark is one position per channel (`ws_channel_members.last_read_id`); opening one mail would mark the whole channel read.

## What do running sites see?
- Additions only, nothing rewritten; existing mail is unchanged and earlier answers read as sent (the step's `install_note()`).
- Not in `install_heavy_tables()`: `ws_mail_items` is created by 11.0.82 of the same unreleased version, so no released site has a big one, and an ADD of a column with a constant default does not rewrite rows.
