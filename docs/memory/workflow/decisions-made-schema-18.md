---
name: decisions-made-schema-18
description: Decisions list, schema part 18 (2026-11-13 on): ws_mail_boxes.transport gains whatsapp and sms, ws_mail_conversations.customer_phone (#9286); ws_mail_items.draft_files for a draft's files
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. Entries name the issue, the date and the migration shape.

## Which tables and columns were approved?
- A WhatsApp or SMS conversation in the mail conversation tables (owner, 2026-11-13, #9286), as step WA12 of the plan `whatsapp-channel` proposed (PR #9281): `ws_mail_boxes.transport` gains the values `whatsapp` and `sms`; `ws_mail_conversations` gains `customer_phone` VARCHAR(20) with an index on (box_id, customer_phone). By a 2026.11.0 step with the idempotent helpers; nothing changes for a mailbox.
  - Owner (2026-11-13), asked "WhatsApp ve SMS konuşmaları (kanalda, e-posta konuşması gibi) nerede tutulsun?": "E-posta tablolarında (Önerilen)". Not chosen: tables of their own in the same shape; no conversation, the inbox rows read as threads by number.
  - The architect's, not the owner's words: an incoming SMS is kept as a `ws_mail_items` row, no table of its own; two years and the contact's erasure, as #9085 set for `whatsapp_inbox` (`decisions-made-schema-17.md`).

## May a mail draft keep its files in a column of ws_mail_items? (owner, 2026-11-14, in the architect's window)
- Asked by the census of `plans/mail-chat-tools.md` (MT1, #9463, channel message 2782): a draft kept on the server is a `ws_mail_items` row with `send_state` `draft`, but a sent mail's files are messages under its card and a draft has neither, so a file a chat adds to a draft would be lost before Send.
- The owner, asked "Taslağın dosyaları için ws_mail_items tablosuna draft_files sütunu eklensin mi?", answered: "Evet, eklensin".
- Shape: `ws_mail_items.draft_files` TEXT NULL, the ids of the draft's files (`files.id`, not message ids: a file taken from the File Manager has no message), added by MT5's step of the open version with `install_add_column()`. NULL on every row that is not a draft with files; at Send the sender's right to each file is checked again and the column is emptied.
- Running sites: nothing changes until a draft with a file is written.
