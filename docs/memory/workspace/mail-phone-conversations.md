---
name: mail-phone-conversations
description: A customer's WhatsApp message as a Workspace conversation (WA13, #9389, step 11.0.199): the WhatsApp box and its Channel field, threading by number, the card, what stays mail-only
---

## Where does a WhatsApp conversation live?
- In the mail tables (owner, #9286): a `ws_mail_boxes` row with `transport` whatsapp (step 11.0.199 widened the ENUM by `whatsapp`, `sms`), `ws_mail_conversations.customer_phone` (E.164 digits, `idx_box_phone (box_id, customer_phone)`), `ws_mail_items` rows with `rfc_message_hash` = SHA-256 of `whatsapp:<wamid>`, `from_email` the number with `+`, `auth` pass (Meta vouches for the number). Code: `includes/workspace/mail_phone.php`.
- Subject and `customer_email` stay empty; a known contact is the one WA4 matched by number (`whatsapp-webhook-events.md`).

## How is the box set?
- Communication › WhatsApp, step 6 "Conversations": a Channel field (`whatsapp_channel`) offering the open and private channels the person reads (`ws_mail_box_channels()`), "No channel" first; a kept channel the person does not see stays choosable as "A channel you do not see". Drawn only with the Workspace on and upgraded.
- `pg_whatsapp_channel_check()` runs before the pane writes (a channel the person may not choose is marked "Choose a channel you can see."); `pg_whatsapp_channel_save()` after the account, so the box's name (`WhatsApp <number>`) and address follow the card. No channel: the box stays, switched off, its conversations kept; no box is added until a channel is chosen.
- The box is not a mailbox: `ws_mail_box_is_phone()` keeps it off Workspace › Mailboxes, the `.eml` import and the box save by id; the IMAP fetch and New e-mail never reach it (no host, no sending route).

## How does a message become a conversation?
- `pg_whatsapp_inbox_to_channel()` after WA4 kept the `whatsapp_inbox` row (bell and history stay): only with the Workspace on and the box on. `ws_mail_phone_ingest()`: duplicate by hash; `WS_MAIL_SENDER_HOURLY` (20) an hour per number (`held`); a user known only by a username (no number) is not threaded.
- It joins the newest conversation of that number in the box that is not closed (status back to open, `mail_customer` to the holder), else opens one in the box's channel with the card as its first message. An archived channel refuses (`no_channel`).
- A new conversation of a known contact writes "A WhatsApp message from {name} came into <#channel>: {first words}" into their channels (`ws_mail_customer_lines(..., 'whatsapp')`).
- A message without text shows its kind ("[A picture]"); media is not fetched yet.

## What does the card show and offer?
- `ws_mail_items_map()` gives each item its box's `transport`; `ws_mail_card_html()` draws the WhatsApp badge and the name and number in place of the address. Status and holder as a mail's.
- No "Reply by e-mail" (`mail_reply` 0 in the payload; the reply endpoint refuses a conversation without a customer address anyway) and no "Forward as e-mail" (`ws_mail_forward_offer()`). Answering by WhatsApp is WA14; the list's transport filter, events and API are WA15; no triage or automatic answer is started for it.

## How was it verified?
- `tests/workspace_mail_phone_test.php` (the step run twice in memory, box values, the Channel field, the card, offers, the customer line, wiring). No database write was run in the cloud session apart from the sandbox pictures.

Related: docs/memory/workspace/mail-tables.md, docs/memory/platform/whatsapp-webhook-events.md, docs/memory/plans/whatsapp-channel-conversation-storage.md
