---
name: mail-overview
description: Workspace e-mail as a whole: mailboxes, the card or a box's room, the E-mails screen, sending, answers, routing, status, the API; which file holds each part, which PR built it
---

## What is it?
- Mail to a Workspace mailbox (`ws_mail_boxes`) becomes a conversation: a card in the box's channel, or in its own room when it is kept in no channel. The team reads it on the E-mails screen or in the channel and answers, forwards or writes new mail from the box's address through the mail queue.
- Built as slices G1-G8 (incoming e-mail, #2444) and steps WM1-WM14 (the E-mails screen, owner 2026-11-10 and 2026-11-11, #8604 and #8765).

## Which decisions bind it?
- The name rule, IMAP, the eleven answers, what the screen is and what is left: `mail-decisions.md`. The records: `workflow/decisions-made-product-10.md`, `decisions-made-workspace-13.md`, `-17.md`, `-21.md` (the list moves into the messages area, MA10), `-27.md` (a mailbox's features, IB1-IB8).

## Which parts are there?
- Reading a box: `mail-fetch.md`, Check e-mail on the list `mail-check.md`, Block sender and the Blocked view `mail-blocks.md`, `mail-imap-client.md`, `mail-microsoft.md`; the parse and the store: `mail-parse.md`, `mail-ingest.md`, `mail-html.md`; routes and the customer's line: `mail-routing.md`.
- The card in a channel and the Mailboxes card of the settings: `mail-card.md`, `workspace-mail-settings-dialog.md`; a box kept in no channel: `mail-room.md`; the "Support box" switch: `mail-support-box.md`.
- The E-mails screen (Incoming, Sent, Not sent, filters, the row, the menu): `mail-list.md`; the reading pane and unread marks: `mail-screen-pane.md`, `mail-html-preview.md`.
- Sending: `mail-send.md`, `mail-send-outcome.md`, `core/mail-queue-outcome.md`, `mail-email-fields.md`, `mail-sent-and-bounces.md`; a box's Sent folder read back: `mail-sent-folder.md`; New e-mail: `mail-compose.md`, its address suggestions `mail-suggest.md`; a person's own signature: `mail-own-signature.md`; Send later: `mail-send-later.md`; Forward as e-mail: `mail-forward.md`; Reply by e-mail: `mail-reply.md`.
- Status, holder, first-answer time, automatic answer: `mail-status.md`; who is told and the event: `mail-notices-and-event.md`; Pinegrap AI's suggestion: `mail-triage.md`; the API and MCP: `mail-api.md`.
- Tables: `mail-tables.md`, `mail-tables-sending.md`.

## Where is each part?
- Server: `includes/workspace/mail*.php` (`mail.php` ingest and card, `mail_fetch`, `mail_route`, `mail_send`, `mail_reply`, `mail_compose`, `mail_forward`, `mail_status`, `mail_list`, `mail_pane`, `mail_reads`, `mail_room`, `mail_settings`, `mail_triage`), `api_mail.php`; the screen `workspace_mail.php`.
- Screen script: `assets/js/workspace_mail.js` in the `workspace.min.js` bundle; the guarded frame `workspace_mail_frame.php`.
- A mailbox's features, one module each: `mail_check`, `mail_signature`, `mail_blocks`, `mail_suggest`, `mail_later`, `mail_sent_folder`.

## Which PR built each slice?
- `mail-prs.md`: the slices G1-G8, WM1-WM14 and IB1-IB8 with their PRs.

## How is a part added?
- Who may answer asks `ws_mail_can_answer()`, never `ws_can_post_channel()` alone: a box's room is no channel to post in.
- A new reader of `ws_channels` is classed in `tests/workspace_mail_room_hidden_test.php`; a screen word for the support half asks `ws_mail_box_is_support()`.
- A new topic is a `mail-<name>.md`, named in the parts above.
