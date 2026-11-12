---
name: mail-overview
description: Workspace e-mail as a whole: mailboxes, the card or a box's room, the E-mails screen, sending, answers, routing, status, the API; which file holds each part, which PR built it
---

## What is it?
- Mail to a Workspace mailbox (`ws_mail_boxes`) becomes a conversation: a card in the box's channel, or in its own room when it is kept in no channel. The team reads it on the E-mails screen or in the channel and answers, forwards or writes new mail from the box's address through the mail queue.
- Built as slices G1-G8 (incoming e-mail, #2444) and steps WM1-WM14 (the E-mails screen, owner 2026-11-10 and 2026-11-11, #8604 and #8765).

## Which decisions bind it?
- The name rule, IMAP, the eleven answers, what the screen is and what is left: `mail-decisions.md`. The records: `workflow/decisions-made-product-10.md`, `decisions-made-workspace-13.md`, `-17.md`, `-21.md` (the list moves into the messages area, MA10).

## Which parts are there?
- Reading a box: `mail-fetch.md`, `mail-imap-client.md`, `mail-microsoft.md`; the parse and the store: `mail-parse.md`, `mail-ingest.md`, `mail-html.md`; routes and the customer's line: `mail-routing.md`.
- The card in a channel and the Mailboxes card of the settings: `mail-card.md`, `workspace-mail-settings-dialog.md`; a box kept in no channel: `mail-room.md`; the "Support box" switch: `mail-support-box.md`.
- The E-mails screen (Incoming, Sent, Not sent, filters, the row, the menu): `mail-list.md`; the reading pane and unread marks: `mail-screen-pane.md`, `mail-html-preview.md`.
- Sending: `mail-send.md`, `mail-send-outcome.md`, `core/mail-queue-outcome.md`, `mail-email-fields.md`, `mail-sent-and-bounces.md`; New e-mail: `mail-compose.md`; Forward as e-mail: `mail-forward.md`; Reply by e-mail: `mail-reply.md`.
- Status, holder, first-answer time, automatic answer: `mail-status.md`; who is told and the event: `mail-notices-and-event.md`; Pinegrap AI's suggestion: `mail-triage.md`; the API and MCP: `mail-api.md`.
- Tables: `mail-tables.md`, `mail-tables-sending.md`.

## Where is each part?
- Server: `includes/workspace/mail*.php` (`mail.php` ingest and card, `mail_fetch`, `mail_route`, `mail_send`, `mail_reply`, `mail_compose`, `mail_forward`, `mail_status`, `mail_list`, `mail_pane`, `mail_reads`, `mail_room`, `mail_settings`, `mail_triage`), `api_mail.php`; the screen `workspace_mail.php`.
- Screen script: `assets/js/workspace_mail.js` in the `workspace.min.js` bundle; the guarded frame `workspace_mail_frame.php`.

## Which PR built each slice?
- G1 core #2586, #2676, #2710, #2738; G2 IMAP #2764, #2788, #2806, #2828; G3 answer #2846, #2872, #2885; G4 Microsoft 365 #2961; G5 #2923, #2942; G6 routing #2904; G7 API #2952; G8 triage #3787.
- WM1 queue hook #8607; WM2 schema #8634; WM3 send #8646; WM4 screen #8652; WM5 New e-mail #8719; WM6 forward #8748; WM7 answer #8779; WM8 API #8737; WM10 a row opens the mail #8796; WM11 HTML kept #8821; WM12 HTML drawn #8861; WM13 kept in no channel #8985; WM14 support box #PRNUM.

## How is a part added?
- Who may answer asks `ws_mail_can_answer()`, never `ws_can_post_channel()` alone: a box's room is no channel to post in.
- A new reader of `ws_channels` is classed in `tests/workspace_mail_room_hidden_test.php`; a screen word for the support half asks `ws_mail_box_is_support()`.
- A new topic is a `mail-<name>.md`, named in the parts above.
