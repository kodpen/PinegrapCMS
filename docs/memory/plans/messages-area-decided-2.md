---
name: messages-area-decided-2
description: The architect's decisions on the proposals of MA9a and MA10a (#9077): campaign profiles into flows (boxes first, what stays, one flow per chain, the switch, the owner), incoming e-mail in the area
---

## What is decided after MA9a? (architect, 2026-11-13, #9077)
- All five proposals are taken (`core/campaign-profile-flow-gaps.md`, `-switch.md`, `-dry-run.md`); MA9 is cut anew as MA9b to MA9f (`messages-area-steps-2.md`).
- The boxes first: the e-mail box gains a sender, a page body filled as a campaign fills it and, for marketing, the codes, the commercial footer and a group's opt-out; the Wait box a time of day and "if the moment has passed"; the events their mail values. Each is general, none needs a column, and a flow drawn before sends and waits as before.
- Conditions: a sender is checked as a campaign's sender is; sender and BCC are never offered in a member's personal flow; what the events gain is added only, with the API's schema.
- What stays a profile: an action with no start (order abandoned, review request, product ordered, booking reminder, credit expiring, subscription price changed), a wait over `WS_FLOW_DELAY_MAX`, a field no value fills, a profile near the flow's limits, and their chain partners. It is the plan's own rule (`messages-area.md`: never dropped silently); the profile screens stay for them.
- The six starts are a later plan, written once MA9d's report on running sites shows which are in use; M2b is complete on a site when no profile is left there.
- One flow per chain, whole or not at all. The switch: the head turned off by one conditional update at second S; an event 60 s before S never starts the flow, 60 s after it always does, in between only when the profile made no campaign to that person. The check is one pure function with tests.
- The flow's owner: the profile's creator while on the staff with the start's right, else the first active administrator, named in the report.
- Accepted differences: marketing reaches only an address a contact with consent holds (the report counts, per chain, the last 30 days' recipients no contact holds); "View this email at our site" goes; flow mails show in Sends, not in the campaign history; a follow-up goes after a first mail that was sent.

## What is decided after MA10a? (architect, 2026-11-13, #9077; the owner's part #9203)
- Mail works where the Workspace is off: the owner's (`workflow/decisions-made-workspace-22.md`). A gate of its own, `ws_mail_on()`, in a step of its own, MA10c.
- The tab asks `ws_mail_list_visible()`, as the menu entry does today, not the campaigns' right. Who reads stays the members of the box's channel or room; no mailbox right of its own.
- The card keeps the sender with its badge, the subject, the text's first lines (8 lines or 600 characters), the attachments, a support box's status and holder; it gains "Answered · who · time" (no column); "Show all" and "Not taken in" leave it; "Open e-mail" is a plain link (`workspace/mail-area-proposals.md`). MA10b's PR shows the card before and after.
- The Mailboxes card moves to a place in Site Settings that the Workspace's switch does not hide, for holders of the Workspace settings right.
