---
name: whatsapp-channel-conversation-storage
description: WA12's storage proposal (2026-11-13), open for the architect before WA13 and WA16: phone conversations in the mail conversation tables, a box per transport, where an incoming SMS is kept and how long
---

## Who decides?
- The architect with the owner (Fixed rules 5): nothing below is decided until answered. Census: `whatsapp-channel-census.md`, `whatsapp-channel-incoming-sms.md`.

## Where would a WhatsApp or SMS conversation live? (proposed: a)
- a) The mail conversation tables with a transport: a `ws_mail_boxes` row per WhatsApp account or SMS number, `transport` widened from `ENUM('imap','graph','gmail','push')` by `whatsapp` and `sms`; its `channel_id`, room, holder, first-answer target and automatic answer as a mailbox's. Its `settings` name the `connect_apps` row and the number; no password of its own.
  - `ws_mail_conversations`: new `customer_phone` VARCHAR(20) (E.164 digits) with an index on (box_id, customer_phone); `subject` and `customer_email` stay empty.
  - `ws_mail_items`: no new column. `rfc_message_hash` is the SHA-256 of the wamid or the provider's message id, so the UNIQUE de-duplication holds; `body_text` the text; `outbox_id` points into `whatsapp_outbox` or `sms_outbox` by the box's transport; mail-only columns stay empty.
  - Why: the card, list, status, holder, notices, rights, API and reads are written once and work by the conversation and its box; the plan says no second conversation engine.
- b) Tables of their own (`ws_chat_conversations`, `ws_chat_items`) in the same shape: no ENUM change, but every screen and gate twice or behind a union.
- c) No new conversation: `whatsapp_inbox` and a new `sms_inbox` read as threads by number: no status, holder or list rows, so not "as an e-mail".

## Where is an incoming SMS kept, and how long?
- In `ws_mail_items` like a mail (option a), no `sms_inbox`. Kept with its conversation; removed after two years and with the contact's erasure, as `whatsapp_inbox` (#9085). A WhatsApp message keeps its `whatsapp_inbox` row as well (the history line and W2a).
- An SMS from a number on a box kept in no channel or with no box is not kept at all: only an SMS number given a channel receives.

## What is asked of the owner? (through the architect)
- Q1 storage: a, b or c above (proposed a: two new values of `ws_mail_boxes.transport`, one column and one index on `ws_mail_conversations`).
- Q2 the answer's SMS kind: a transactional `reply`, only to the conversation's number, within 7 days of its last SMS, no consent asked; or only to a number with SMS marketing consent; or no SMS answer at all.
- Q3 stop words: STOP, IPTAL, RET, DUR and the others write an SMS (and so WhatsApp) marketing refusal; or they only show in the conversation.

Related: docs/memory/plans/whatsapp-channel-storage.md, docs/memory/workspace/mail-tables.md
