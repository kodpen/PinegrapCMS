---
name: help-centre-census-privacy
description: Help centre census: who reads a support request, and how it leaves with a customer's data (export, erasure, keep period) today and as proposed
---

## Who reads a request?
- Today a mail conversation is read by the members of its box's channel or room who may answer there (`ws_mail_can_answer()`, `includes/workspace/mail_room.php:101`); the support channel is private (`workspace/mail-decisions.md`).
- Proposed: the same in the panel; the customer reads their own on My Account when signed in (the conversation's `user_id`), the mails in and out only. A guest gets a signed link in the automatic answer, as event tickets have one (`commerce/event-tickets-key.md`), if the owner wants guest pages.

## How does it leave with the customer's data today?
- Export: Workspace channels about the person by reference only (`includes/fn/privacy_export.php:1374-1380`, `core/personal-data-export.md`); mail conversations and their mails are not in the export.
- Erasure: Workspace mail is not reached; the owner chose on 2026-11-14 that it stays (`workflow/decisions-made-workspace-28.md`). `pg_contact_holders()` lists `ws_mail_conversations` with erase none (`includes/fn/contacts.php:72`).
- Forms: exported (`privacy_export.php:996-1000`), deleted on erasure (`includes/fn/privacy.php:1016`), kept 2 years by default (`includes/fn/privacy_retention.php:269`).
- Site chats: exported (`privacy_export.php:1317-1323`), deleted on erasure (`privacy.php:1085`), kept 60 days by default (`privacy_retention.php:274`).

## What changes with requests?
- A form or a chat made a request copies its text into a mail conversation, which erasure leaves by the 2026-11-14 decision: the text would outlive an erasure that deletes the form or chat. Whether it stays is the owner's.
- Export: proposed that a person's requests join it (number, subject, state, dates, the mails in and out), as a request is the customer's own data; mail is not exported today, so this is asked.
- Keep period: none for mail conversations; one for closed requests is asked.
- `kb_votes` holds no personal data, so it needs no export or erasure.
