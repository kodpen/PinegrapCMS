---
name: whatsapp-channel-census
description: WA12's census (2026-11-13): what a WhatsApp or SMS conversation shares with the e-mail one (card, state, holder, list, notices, event, AI draft, rights) and what stays mail-only
---

## Which parts does a phone conversation share with e-mail?
- Card: `ws_mail_card_html()` with a badge "WhatsApp" or "SMS" and the number in place of the address; the text escaped as mail's (untrusted, never Workspace markup). A WhatsApp sender is verified by Meta; an SMS sender's number is taken as the provider gives it.
- State and holder: `status` open, waiting, closed and `assignee_id` as `mail-status.md`; an answer sets waiting, the customer writing again sets open; the first-answer target per box.
- Rights: `ws_mail_can_answer()` and the room of a box kept in no channel (`workspace/mail-room.md`), unchanged.
- List: the same rows, a transport filter (WA15); the subject column shows the first words.
- Notices: `mail_assigned`, `mail_customer`, `mail_unsent` as they are. W2a's bell notice and the contact's history line stay beside them for WhatsApp.
- A known customer's channels: the line of `ws_mail_customer_lines()`, the contact found by number (`pg_sms_number()` digits), "A WhatsApp message from {name} came into …".
- AI draft: the triage of `mail-triage.md` on a new conversation; "Open the reply draft" fills the WhatsApp or SMS drawer. Assistants never send.
- Event: each transport keeps its own (`workspace.mail.received`; WA7's "A WhatsApp message arrived"; an SMS one in WA16), each payload with conversation_id, channel_id, message_id and the transport.
- Saved replies, the unread marks (`ws_mail_reads`), the API's list and reply (`mail-api.md`), Waiting / Not sent / Send again.

## What stays mail-only?
- Subject and its `[#T<id>]` tag; Message-ID, In-Reply-To, References and threading by them; Cc, Bcc, Reply to all; the quoted mail and the box's Signature; HTML body and preview; the `.eml` file; DMARC `auth`; routes by address or `+tag`; Forward as e-mail; the sent-folder copy and bounces; the loop gate on `Auto-Submitted`.

## How does a phone conversation thread?
- By number: a message joins the newest conversation of that number in the box that is not closed; a closed one gets a new conversation, as a new mail would. No subject, so nothing else to match.

## What is new for phone conversations?
- WhatsApp: the 24-hour window shown with the time left; after it only approved templates (WA14). Media (image, video, audio, document, sticker) fetched by its id into the card's files, as a mail's attachments are.
- SMS: no files; the drawer counts characters and parts (WA17).
- Automatic answer: WhatsApp may have one (free text inside the window it just opened); SMS has none (each costs, and it can loop with another machine).
- A loop guard by number instead of `Auto-Submitted`: `WS_MAIL_SENDER_HOURLY` (20) messages an hour from one number, as mail counts by sender.

Related: docs/memory/plans/whatsapp-channel-steps-2.md, docs/memory/plans/whatsapp-channel-conversation-storage.md, docs/memory/plans/whatsapp-channel-incoming-sms.md
