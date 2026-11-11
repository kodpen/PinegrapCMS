---
name: decisions-made-workspace-13
description: Decisions list, workspace part 13 (2026-11-10 on): the Workspace mail screen (one list of every e-mail, New e-mail, Forward as e-mail), its send path through the mail queue; schema approved (#8604)
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. Entries name the issue and the date.

## What is the Workspace's mail screen, and how does it send? (#8604, owner 2026-11-10; the shape: architect)
- Owner (2026-11-10): mail written into channels lands in no one list. Wanted: one list of every e-mail, writing and sending a new e-mail in the Workspace, forwarding a chosen channel message as an e-mail, sent through the systems there are (the mail queue), for the mailboxes entered for channels and not the site's own; plain text now, HTML later. The decisions were left to the architect ("kararlar sende"); the words are quoted in #8604.
- Architect (2026-11-10, under the owner's delegation): the E-mails screen grows into it (views Incoming, Sent, Not sent; a mailbox filter and a search; a reading pane; unread per person), named "E-mails" since "Inbox" stays the notifications' word (`workspace/mail-decisions.md`).
- A sent mail is a conversation with a card in a channel of its mailbox, so the channel's readers see it; who may post in that channel may send from the mailbox; team accounts only. Assistants and applications never send (the owner's earlier answer stays): a draft is theirs to offer, Send is the person's.
- One send path through the mail queue with an outcome hook, for a new mail, a forward and the answer: a mail that could not leave shows Not sent, tells its writer and offers Send again.
- Owner (2026-11-11, #8604): the schema as the plan proposed it, "A: dört sütun + tablo.": four `ws_mail_items` columns (`bcc_list`, `send_state`, `send_error`, `outbox_id`) and table `ws_mail_reads`, additions only, an earlier mail reads as sent. The entry: `decisions-made-schema-15.md`.
- Limits: 20 addresses a mail, 30 mails an hour a person, 10 files and 20 MB a mail.
- Not chosen: a second screen beside E-mails (two lists of the same conversations); sending at once as the answer does today (no retry, a failure nobody sees); the site's own mailbox in the list (the owner's words); a mailbox with rights of its own (a channel decides, as for reading).
- For later: writing and showing HTML, recipient suggestions, drafts kept on the server, reading a box's Sent folder.
- The plan: `plans/workspace-mail-screen.md` and its two files.
