---
name: decisions-made-schema-18
description: Decisions list, schema part 18 (2026-11-13 on): phone conversations in the mail conversation tables: ws_mail_boxes.transport gains whatsapp and sms, ws_mail_conversations.customer_phone (#9286)
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- A WhatsApp or SMS conversation in the mail conversation tables (owner, 2026-11-13, #9286), as step WA12 of the plan `whatsapp-channel` proposed (PR #9281): `ws_mail_boxes.transport` gains the values `whatsapp` and `sms`; `ws_mail_conversations` gains `customer_phone` VARCHAR(20) with an index on (box_id, customer_phone). By a 2026.11.0 step with the idempotent helpers; nothing changes for a mailbox.
  - Owner (2026-11-13), asked "WhatsApp ve SMS konuşmaları (kanalda, e-posta konuşması gibi) nerede tutulsun?": "E-posta tablolarında (Önerilen)". Not chosen: tables of their own in the same shape; no conversation, the inbox rows read as threads by number.
  - The architect's, not the owner's words: an incoming SMS is kept as a `ws_mail_items` row, no table of its own; two years and the contact's erasure, as #9085 set for `whatsapp_inbox` (`decisions-made-schema-17.md`).
