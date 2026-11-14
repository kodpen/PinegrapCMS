---
name: mail-inbox-features-census
description: IB1 census (2026-11-14) of plans/mail-inbox-features.md: a read on request, a person's own setting, a blocked mail, a later send, a box's Sent folder, phone numbers; the questions asked
---

## What may Check e-mail call, and how long does it hold a request?
- `ws_mail_fetch($box)` (`includes/workspace/mail_fetch.php`), never `ws_mail_fetch_run()`: one box under `GET_LOCK('pg_ws_mail_<id>', 0)`, so a job tick reading it answers `locked` and the button never reads it twice at once. It writes `checked_at`, so the job skips the box for `WS_MAIL_FETCH_EVERY` (60) s after.
- One box holds the request up to `WS_MAIL_FETCH_BUDGET` (15) s of IMAP plus up to 25 ingests; N boxes in one request is N × that. IB2 bounds the whole press, leaving the rest to the job. Microsoft 365 boxes are `imap` too (XOAUTH2), same call.

## Where is a person's own setting kept?
- `ws_profiles` (PRIMARY KEY `user_id`): `title`, `day_minutes`, `workdays`, `excluded` (team settings) and `notes_pinned`, the person's own (2026.4.8, written by `INSERT … ON DUPLICATE KEY UPDATE` in `notes.php`). Time zone: `user.timezone` (`USER_TIMEZONE`). No mail column then; IB3 added `ws_profiles.mail_signature` (11.0.203, `workspace/mail-own-signature.md`).

## Is a blocked mail a conversation or a row of its own?
- `ws_mail_conversations.status` is an ENUM (open, waiting, closed) and every conversation has a card; every list, count, the API and the customer line read it. A blocked mail as a conversation would need a MODIFY and a filter in each reader.
- An item with `conversation_id = 0` already exists: a delivery failure (`ws_mail_bounce_store()`, marked by `user_id > 0`). A blocked mail fits beside it: kept, no card, no conversation, swept after 30 days like the `.eml`; "Not blocked" makes the conversation from the item.

## What does the mail queue know of a later time?
- `mail_outbox.send_after` exists (idx_due), but `pg_mail_enqueue()` writes 0 and uses it only for retry backoff; the Mail queue screen's Retry sets it back to 0. `email()` with `'queue' => true` sends at once when the job has not run lately (`pg_mail_job_alive()`).
- So IB6 cannot hand a later time to the queue as it is: either the item waits on the Workspace side (`send_state` is VARCHAR(8): `later` fits) and a job queues it when due, or the queue learns a hold. IB6 waits for MT3.

## How is a box's Sent folder found?
- `pg_imap_sent_folder()` (`imap_client.php`): `LIST (SPECIAL-USE)`, the `\Sent` mark, then usual names; or the box's `settings.sent_folder`. `ws_mail_reply_copy()` already APPENDs our answers there (`sent_copy`), so a read finds our own copies: their Message-ID hash is already in `ws_mail_items` and is skipped.
- The box has one position (`uidvalidity`, `last_uid`) for its folder; a second folder needs its own. An item read from Sent has no writer, and `user_id = 0` on an out item means "Automatic answer" (`ws_mail_item_is_auto_reply()`): it needs another mark.

## Does a WhatsApp or SMS number fit the block list?
- It fits the data: phone boxes are `ws_mail_boxes` rows, `from_email` holds `+E.164`. But they never show on the Mailboxes card, come in through `ws_mail_phone_ingest()`, not `ws_mail_ingest()`, and a domain means nothing for them. IB4 keeps numbers out, as the plan says.

## What do IB5's suggestions read?
- No schema: contacts by `validate_contacts_access()` / `users_contact_groups_xref` (role 3), `ws_mail_items.from_email` by `idx_sender (box_id, from_email(191), …)` for the boxes of `ws_mail_send_boxes()`, recent `to_list` / `cc_list` (no index: bounded rows), the mark by `pg_mail_bounce_blocked_among()`.

## Which questions went to the architect?
- Channel message 2860, answered in 2863: the shapes are in `plans/mail-inbox-features-scopes.md`; each column or table waits for the owner's yes.
