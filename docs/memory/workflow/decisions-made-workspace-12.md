---
name: decisions-made-workspace-12
description: Decisions list, workspace part 12 (2026-11-10 on): an agent's one-answer question is a one-choice poll the asking agent closes; the Workspace mail screen, its send path and schema
---

## Where are the rules and the earlier parts?
- `decisions-made.md` holds the rules; `DECISIONS.md` lists the entries of every part. Entries name the issue and the date.

## With which control does an agent ask a one-answer question in a channel? (owner, 2026-11-10, #8554; the shape: architect)
- Owner (2026-11-10): the checklist is the wrong control; the question takes one choice, the answer does not change afterwards, and a poll fits better. The owner was away and left the shape to the architect ("decide as fits best").
- Seen on the checklist question about the clip timeline: all three options could be ticked, each ticked line was struck through, and the boxes still took ticks after the decision reply. A checklist is the to-do list: every line its own box, nothing closes it.
- Architect (2026-11-10): a poll with one option per voter, voters named, the recommended option first. The asking agent closes it as soon as it reads the vote it waits for (the owner account's, for an owner question); the result is a decision line under the poll and a closed poll takes no vote. An answer given in the architect's window closes the poll too. No product change; the calls: `workspace/agent-questions.md`.
- The owner account's vote is the owner's answer, as its tick was (`decisions-made-workspace-8.md`); anybody else's is a channel decision.
- A checklist stays for a list where several lines are ticked and for a question one agent asks another (an application cannot vote): `workspace/agent-checklist-questions.md`.
- Left open on purpose: until the agent reads the vote it can be changed or taken back (usually under a minute, longer while the agent's session is down), which also corrects a wrong click. The vote read is the answer.
- Not chosen: a "votes are final" poll setting (a new `ws_polls` column, a schema change; asked of the owner as its own question only if the open time before the close turns out to matter); a one-choice mode of the checklist (the largest change, for what a poll already does).

## What is the Workspace's mail screen, and how does it send? (owner, 2026-11-10; the shape: architect)
- Owner (2026-11-10): mail written into channels lands in no one list. Wanted: one list of every e-mail, writing and sending a new e-mail in the Workspace, forwarding a chosen channel message as an e-mail, sent through the systems there are (the mail queue), for the mailboxes entered for channels and not the site's own; plain text now, HTML later. The decisions were left to the architect.
- Architect (2026-11-10, under the owner's delegation): the E-mails screen grows into it (views Incoming, Sent, Not sent; a reading pane; unread per person), named "E-mails" since "Inbox" stays the notifications' word. A sent mail is a conversation with a card in a channel of its mailbox; who may post there may send; assistants and applications never send. One send path through the mail queue with an outcome hook.
- Schema, decided with it: four `ws_mail_items` columns (`bcc_list`, `send_state`, `send_error`, `outbox_id`) and table `ws_mail_reads`; additions only. Limits: 20 addresses a mail, 30 mails an hour a person.
- The plan: `plans/workspace-mail-screen.md` and its two files.
