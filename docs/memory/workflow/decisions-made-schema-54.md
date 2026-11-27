---
name: decisions-made-schema-54
description: Decisions made, schema part 54 (2026-11-27 on): a support request's priority and source on ws_mail_conversations, and kb_votes for an article's "Was this helpful?"
---

## Where are the rules and the earlier parts?
- `decisions-made.md`; the generated `DECISIONS.md` lists every part's entries. The part before: `decisions-made-schema-53.md`.

## What does the help centre add to the database? (owner, 2026-11-27, agent channel poll 252, message 14929)
- Asked after the count, with what the architect had settled told first: a support request is no thing of its own but the mail conversation of a support box (its state, holder, first-answer target and rules are there); a form, the chat window and My Account open one; articles stay a collection. All of that needs no addition.
- Owner: "Hepsi". Offered beside it and not chosen: only the request's columns; only the priority; nothing.
- So, each by a step of the open version with the idempotent helpers:
  - `ws_mail_conversations.priority` TINYINT (Low, Normal, High, Urgent), on the row because every list, filter, count and the API read it.
  - `ws_mail_conversations.source` VARCHAR(10) (mail, whatsapp, sms, form, chat, account) and `source_id` INT (the `forms` or `chat_conversations` row): the list's label and the link back.
  - The table `kb_votes` (id, form_id, record_id, the answer yes or no, created_at) for "Was this helpful?": no address and no IP; a cookie stops a second vote.
- His yes covers these and nothing else: the resolve-by time stays computed, and a clock that stops while waiting would be a further column, not asked.
- Steps: HC7 (priority and source) and HC8 (`kb_votes`) of `plans/help-centre.md`.
- Running sites: a conversation stored before reads as Normal with the source mail; nothing is rewritten.
