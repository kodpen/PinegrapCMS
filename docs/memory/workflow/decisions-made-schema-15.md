---
name: decisions-made-schema-15
description: Decisions list, schema part 15 (2026-11-10 on): ws_messages.via_client_id, ws_ai_requests.claimed_device_id, the Workspace mail screen's columns and ws_mail_reads, the image editor's largest upload
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- A connected application's message and per-person `@Claude` sessions (owner, 2026-11-10, #8490; step A8a of `plans/agent-channel-steps-2.md`; the behaviour: `decisions-made-workspace-11.md`): `ws_messages.via_client_id` and `ws_ai_requests.claimed_device_id`, each INT UNSIGNED NOT NULL DEFAULT 0 by `install_add_column()`, in a re-runnable sub-step of 2026.11.0.
  - `via_client_id` is the `oauth_clients` id of the connection that wrote the message, 0 for a message not written through one. The client row is named, not the `api_devices` row: a person's device rows give way past 20, the client stays.
  - `claimed_device_id` is the `api_devices` id of the connection that claimed the request, 0 when the site's routine takes it.
  - No backfill and no index. Both tables exist on released sites (created in 2026.4.4), so the step only adds the columns.
- The Workspace mail screen (owner, 2026-11-11, #8604: "A: dört sütun + tablo."; step WM2 of `plans/workspace-mail-screen-steps.md`; the behaviour: `decisions-made-workspace-13.md`): four `ws_mail_items` columns and table `ws_mail_reads`, in one re-runnable step of 2026.11.0 with the idempotent helpers.
  - `bcc_list` (text, as `cc_list`); `send_state` (empty for mail that came in and for earlier answers, else `queued`, `sent`, `failed`); `send_error` (the reason of a failure); `outbox_id` (the mail queue's row while the mail waits).
  - `ws_mail_reads` (`conversation_id`, `user_id`, `item_id`; primary key the first two): the newest mail of a conversation a person has read; a row with conversation 0 is the person's floor, so nobody starts with a backlog.
  - Additions only, nothing rewritten: an earlier outgoing mail (empty state) reads as sent.
  - Refused: the columns without the table (no unread per person; a channel's one read position would mark every mail read at once); no schema change (no Not sent mark, view or Send again; Bcc not kept).
- The image editor's largest upload (owner, 2026-11-11, #8587: "A: tek ayar, varsayılan 0."; step V8b of `plans/image-editor-video-steps-2.md`): one setting in megabytes, 0 keeps PHP's `upload_max_filesize`. Kept as the `config_text` row `image_editor_upload_max_mb`, no schema change (#8637, 2026-11-11): the column #8587 named would break the `config` row's room (`data/config-table-no-varchar.md`).
  - 0 is PHP's `upload_max_filesize`, the bound before the column: a running site does not change until an administrator raises it.
  - One bound for an uploaded video or sound and a saved animation, answered by `pg_image_editor_upload_limits()`. The field "Largest Video Or Sound In The Image Editor (MB)" stands in Settings, Features, beside "Shrink Pictures On Upload Above".
  - Refused: a second column for the saved animation (the export and the source land in the same Files and disk); no setting (a host with a small `upload_max_filesize` could not raise the bound).
