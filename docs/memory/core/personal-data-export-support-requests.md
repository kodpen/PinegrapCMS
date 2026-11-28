---
name: personal-data-export-support-requests
description: The personal data export's "Support requests" section (help centre HC6): which conversations are a person's requests, what each row carries, the mails in and out, what is never read
module: privacy_export
---

## What does the section hold?
- Key `support_requests`, label "Support requests", after `chats` in `pg_privacy_export_sections()` (the file's format, pinned by `tests/privacy_export_test.php`).
- Read by `_pg_privacy_export_support_requests()` in `includes/fn/privacy_export.php` when `ws_mail_conversations` and `ws_mail_boxes.settings` exist.
- The architect settled (2026-11-27, `workspace/support-requests.md`): a request is a conversation of a support box, and a person's requests join the export. Erasure is unchanged: a request stays as mail does (`workflow/decisions-made-workspace-28.md`).

## Which conversations are the person's?
- Those of a support box (`ws_mail_box_is_support()`, so `mail_status.php` is required there) whose `customer_email` is one of the person's addresses, or whose `user_id` or `contact_id` is one of theirs. A plain box's conversations are not requests and stay out.
- A WhatsApp or SMS conversation is found only by its `user_id` or `contact_id`; `customer_phone` is not matched.

## What does a row carry?
- `pg_privacy_export_request_row()` (pure): `number` as the customer sees it in a subject (`#T<id>`), `box` (the box's name), then id, box_id, subject, status, customer_email, customer_name, contact_id, user_id and the times `first_in_at`, `first_reply_at`, `last_in_at`, `last_out_at`, `closed_at`, then `mails`.
- `mails`: the conversation's `ws_mail_items` in and out (an automatic answer included), drafts (`send_state` draft) left out: id, direction, from, to, cc, subject, `body_text`, sent_at, created_at. Not read: the kept HTML, raw file, headers, `auth`.
- The team's notes under a request are channel messages, never mails, and are never read. The section is cut at the row limit like the others; mails at 20 times it.
