---
name: workspace-mail-screen
description: Plan (owner 2026-11-10): the Workspace's E-mails screen becomes a mail screen for the Workspace mailboxes: Incoming, Sent, Not sent, a reading pane, New e-mail, Forward as e-mail, the mail queue
---

## What is it for?
- Owner (2026-11-10): mail is written into channels but lands in no one list. Wanted: one list of every e-mail, writing and sending a new e-mail in the Workspace, forwarding a chosen channel message as an e-mail; sent through the systems there are (the mail queue), for the mailboxes entered for channels (`ws_mail_boxes`), not the site's own. Plain text now, HTML later. The decisions were left to the architect (#8604).
- Sending and the schema: `workspace-mail-screen-sending.md`. Steps: `workspace-mail-screen-steps.md`. The record: `workflow/decisions-made-workspace-13.md`.

## What is there today?
- Mail to a box is a card in the box's channel, its answers the card's replies (`workspace/mail-decisions.md`, `mail-card.md`). The E-mails screen (`workspace_mail.php`, `mail-list.md`) lists the conversations of the channels the reader may read, by status and holder; a row only links into its channel.
- "Reply by e-mail" sends plain text to the conversation's customer at once, not through the queue, with no Cc and no file (`mail-reply.md`). Nothing writes a new e-mail, and a channel message cannot leave as one.

## What does the screen become?
- Architect (2026-11-10, for all that follows): the E-mails screen grows; no second screen. The name rule stays (`mail-decisions.md`): "Inbox" is the notifications' word, so the screen is "E-mails" and its first view "Incoming".
- Views: Incoming (conversations with mail that came in, newest first), Sent (with mail that went out), Not sent (an outgoing mail waiting in the queue or given up on). The status filter stays, Open by default in Incoming, All in the other two. Added: a mailbox filter, a search (subject, names, addresses, the mail's text, folded like the Workspace search), Load more.
- A row is a conversation: who, subject, the newest mail's first words, time, mailbox, channel, status, holder, the number of mails; bold while unread.
- Reading pane: a row opens beside the list (a full view on a phone): every mail of the conversation in order with From, To, Cc, time, text (`body_text`, plain) and files, the marks Not delivered and Not sent, the actions Reply, Reply to all, Forward, status, holder, Open in channel.
- Unread is per person (`ws_mail_reads`): a conversation is unread while its newest incoming mail is past the person's mark. Opening it in the pane, or its card's replies in the channel, marks it; Mark all as read moves the person's floor. The menu entry shows the number of open unread conversations.
- New e-mail: a button on the screen and in the menu of a channel a mailbox writes into. Form: From (a mailbox), To, Cc, Bcc, Subject, text, files; the unsent form is kept in the browser like the composer's (`workspace-unsent-draft.md`).
- Forward as e-mail: on the bar of messages chosen together and in a message's menu, beside Forward; a mail's own Forward on its card and in the pane. Both open the New e-mail form filled in.

## Who may send, and what is kept?
- A channel decides, not the box (`mail-api.md`): a person sends from a mailbox when they may post in its channel or in a channel one of its routes writes to (`ws_can_post_channel()`) and the box has a working route. Team accounts only, never a guest.
- Every sent mail is a conversation with a card in that channel, so the channel's readers see it; the form names the channel. A personal mailbox is a box whose channel is a private channel of one person: no new concept.
- Assistants and applications never send (owner's answer 7, `mail-decisions.md`): the new route is person-only and no MCP tool. A draft is theirs to offer, Send is the person's.

## What stays as it is?
- Ingest, routing, cards, statuses, holders, the first-reply time, auto-replies, the triage suggestion, the notices and the event. A site with mailboxes keeps every conversation where it is.
