---
name: mail-area-decisions
description: Choices for incoming e-mail in the messages area (MA10a, #NNNN): mail with the Workspace off, the tab's gate, who reads, what the mail card keeps beside its link, the steps that build it
---

## Is mail read and answered where the Workspace is off? (worker, 2026-11-13, #NNNN)
- Yes, from MA10c on. Mail gets a gate of its own (`ws_mail_on()`: the mail tables ready, never `WORKSPACE_ENABLED`) asked by the Incoming e-mail tab, its panel actions, the job's IMAP and orphan passes, `workspace_mail_job.php`, the frame and the Mailboxes card. The Workspace's gate stays for all else, as flows do (`plans/automation-flows-menu.md`, M1a).
- Why: the owner moved the list out of the Workspace into the messages area, beside campaigns a shop runs without the Workspace; stored mail, rooms and the queue need none of the Workspace's screens ([[mail-area-shell]]).
- Who reads stays the rule it is: team accounts (`ws_viewer()['member']`) who are members of the box's channel or room; who answers, `ws_mail_can_answer()`. No mailbox right of its own: it would be a second rule beside the room's people.
- With the Workspace off: a new box is kept in "The mailbox itself" only (no channel to pick); a box kept in a channel goes on writing its cards (they anchor the conversations) and that channel's members read it in the area; no "Open in channel", no AI suggestion (a channel message); the notices reach the panel's bell (MA10c checks that `pg_panel_get_notifications()` shows those `ws_inbox` rows then).
- The Mailboxes card moves to the messages area's settings (Communication) in MA10b, shown to holders of the Workspace settings right as read from the account (`ws_rights()['settings']`), whatever the switch.

## Which gate does the tab ask?
- `ws_mail_list_visible()`, as the menu entry does today. Not the campaigns' right: somebody who answers mail without campaign rights sees the area with Incoming e-mail alone; the area's row shows to whoever may open one of its tabs.

## What does the card keep beside its link? (MA10b)
- Kept: the sender's name and address with its E-mail or Sender not verified badge, the subject, the text's first lines (at most 8 lines or 600 characters, cut on a word), the attachments as files (the channel's people work with them), status and holder for a support box (`mail-support-box.md`).
- Added: "Answered · <who> · <time>" once the conversation's newest answer is later than its newest mail (`last_out_at` > `last_in_at`, no column), for a plain box too: the owner's "yanıtlandı olarak işaretlenir" (`workflow/decisions-made-workspace-21.md`).
- Gone from the card: "Show all" with the whole text and "Not taken in": they are read in the area. The AI suggestion under the card stays a channel message.
- Its "Open e-mail" button is a plain link to the area's conversation address, so it works without the script.

## In which steps?
- MA10b (Workspace on): the tab, list and conversation in the area; the Mailboxes card's move; the card as above; the Workspace menu's E-mails entry leaving, its redirects pointing at the area (`ws_mail_room_redirect()`, `ws_mail_room_url()`, `ws_mail_notice_url()`); an answer marking the card; an HTML body for a written e-mail.
- MA10c (after MA10b and AF1, which sets the flows' gate the same way): `ws_mail_on()` and its callers, the job's passes, the room-only box form, the bell with the Workspace off, a test that every `ws_mail_*` entry asks `ws_mail_on()`.
