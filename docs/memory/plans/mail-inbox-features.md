---
name: mail-inbox-features
description: Plan (owner 2026-11-14): the E-mails screen gains Check e-mail, a person's own signature, a block list, recipient suggestions, Send later and the Sent folder; steps IB1 to IB8
---

## What is asked?
- Owner (2026-11-14, in the architect's window): a button that checks for new e-mail and downloads it, then a signature per person, a block list and the other features of a mailbox. His words: `workflow/decisions-made-workspace-27.md`. The calendar half: `plans/personal-calendar-plans.md`.

## What is there today?
- The job reads the boxes every minute, 25 mails a run, one reader per box by a lock (`workspace/mail-fetch.md`). Nobody can ask for a read from the screen.
- One Signature per box, plain text, under the writer's name (`workspace/mail-compose.md`).
- Only the loop gate and the hold at 20 mails an hour stop a sender (`workspace/mail-ingest.md`).
- `workspace/mail-decisions.md` names as later: recipient suggestions, sending at a chosen time, a box's Sent folder. Drafts on the server are MT3 of `plans/mail-chat-tools.md`.

## Which steps?
- IB1 (S, no product code) Census, written into this file: what a read on request may call and how long it holds a request; where a person's own setting is kept today; whether a blocked mail is a conversation in a state of its own or a row of its own; what the mail queue knows of a later time; how a box's Sent folder is found. Each column or table a step needs is asked first.
- IB2 (S) "Check e-mail" in the E-mails toolbar and a box's room: reads now the IMAP boxes the person reads, through the fetch's own lock and time budget, never one box twice within 15 seconds, a few presses a minute per person. The list then says "3 new e-mails" or "No new e-mail", a box's failure in the words of its card. No schema.
- IB3 (M) A person's own signature: "My e-mail settings" on the E-mails screen, plain text within the box Signature's limits. In a mail it stands where the writer's name stands today, the box's Signature under it; the forms show the same lines. In HTML once MA10b's writing has landed.
- IB4 (M) Block list: "Block sender" in a mail's menu (the address or its whole domain, for this box or every box) and the list with "Allow again" on the Mailboxes card. A blocked sender's mail opens no card, tells nobody, starts no flow and gets no automatic answer; it waits 30 days in a "Blocked" view, where "Not blocked" brings it back as a conversation. Who may answer in the box blocks for that box; the list of every box is the Workspace settings right's. Each block is a Site Log line.
- IB5 (S) Recipient suggestions in To, Cc and Bcc: the contacts the person may see and the addresses the box has heard from or written to; an address on the bounce list is marked.
- IB6 (M, after MT3) Send later: a time on the New e-mail form and the reply drawer, in the person's time zone; the mail waits under "Not sent" with "Send now" and "Cancel".
- IB7 (M) A box's Sent folder: an answer written from a mail program shows in its conversation as a sent mail, read by the same job and limits, never sent again.
- IB8 (S) Close-out: `workspace/mail-overview.md`, `mail-decisions.md`'s later list and the files of the steps brought up to date, this plan deleted.

## What does every step keep?
- Team accounts only; the channel decides who reaches a conversation, as today. A guest sees none of this.
- A running site reads and sends as before until a new button is pressed or a setting written.
- The block list holds addresses and domains. A phone number of a WhatsApp or SMS conversation is outside it unless IB1 shows it fits.
- The API and MCP get a route or tool only where a step names it; `php tools/check_api_schema.php` passes.
- Look A (`ui/look-design-examples.md`), the whole picture set in the agent channel at once. Texts through `lang()`, Turkish by `tools/tr_add.php`.
- No table or column without asking the architect.
- IB2 beside IB1; IB3, IB4, IB5 and IB7 after IB1; IB6 after MT3. Claim as `[plan:mail-inbox-features] <step> — <topic>`.
