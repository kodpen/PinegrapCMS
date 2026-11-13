---
name: whatsapp-channel-conversation-storage
description: WhatsApp and SMS conversations in the mail tables with a transport, an incoming SMS kept as a mail item, the answer's SMS kind, stop words (owner 2026-11-13, #9286)
---

## Where does a WhatsApp or SMS conversation live?
- Owner (2026-11-13, #9286): option a, the mail conversation tables with a transport, by a 2026.11.0 step with the idempotent helpers: a `ws_mail_boxes` row per WhatsApp account or SMS number, `transport` widened from `ENUM('imap','graph','gmail','push')` by `whatsapp` and `sms`; its `channel_id`, room, holder, first-answer target and automatic answer as a mailbox's. Its `settings` name the `connect_apps` row and the number; no password of its own.
  - `ws_mail_conversations`: new `customer_phone` VARCHAR(20) (E.164 digits) with an index on (box_id, customer_phone); `subject` and `customer_email` stay empty.
  - `ws_mail_items`: no new column. `rfc_message_hash` is the SHA-256 of the wamid or the provider's message id, so the UNIQUE de-duplication holds; `body_text` the text; `outbox_id` points into `whatsapp_outbox` or `sms_outbox` by the box's transport; mail-only columns stay empty.
  - Why: the card, list, status, holder, notices, rights, API and reads are written once and work by the conversation and its box; the plan says no second conversation engine.
- Not chosen: b) tables of their own (`ws_chat_conversations`, `ws_chat_items`) in the same shape: no ENUM change, but every screen and gate twice or behind a union.
- Not chosen: c) no new conversation: `whatsapp_inbox` and a new `sms_inbox` read as threads by number: no status, holder or list rows, so not "as an e-mail".

## Where is an incoming SMS kept, and how long?
- Owner (2026-11-13, #9286): in `ws_mail_items` like a mail, no `sms_inbox`. Kept with its conversation; removed after two years and with the contact's erasure, as `whatsapp_inbox` (#9085). A WhatsApp message keeps its `whatsapp_inbox` row as well (the history line and W2a).
- An SMS from a number on a box kept in no channel or with no box is not kept at all: only an SMS number given a channel receives.

## Which SMS kind does an answer have, and what do stop words do?
- Owner (2026-11-13, #9286): a transactional kind `reply`, only to the conversation's number, within 7 days of its last SMS, no consent asked (`whatsapp-channel-incoming-sms.md`). Not chosen: only with SMS marketing consent; no SMS answer.
- Owner (2026-11-13, #9286): a stop word by SMS or WhatsApp writes an SMS (and so WhatsApp) marketing refusal to the consent log and İYS. Not chosen: only shown in the conversation.

Related: docs/memory/plans/whatsapp-channel-storage.md, docs/memory/workspace/mail-tables.md
