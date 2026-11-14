---
name: whatsapp-channel-steps-2
description: Steps WA12-WA18 of plans/whatsapp-channel.md (owner 2026-11-13, #9151): a customer's WhatsApp message and SMS land in a channel as an e-mail does and are answered the same way
---

## What is asked?
- Owner (2026-11-13, #9151): "whatsapp ve sms de kanallara eposta gibi düşüp aynı yolla ynıtlama içinde bir plana ekleme yapalım". Record: `workflow/decisions-made-product-49.md`. It takes the place of W2a's "later".
- The model is incoming e-mail in the Workspace: `workspace/mail-routing.md`, `workspace/mail-reply.md`, `workspace/mail-list.md`, `workspace/mail-notices-and-event.md`.

## What does every step keep? (beside `whatsapp-channel.md`)
- One way for the person: the e-mail conversation's card, answer drawer, list, rights (`ws_mail_can_answer()`), notices and saved replies. A transport brings its receiver, its send and its limits: no second conversation engine, no copy of the mail screens.
- Assistants draft, never send (owner answer 7, #2444). W2a's bell notice and history line stay.
- WhatsApp takes free text only inside the 24 hours after the customer's last message, after it only approved templates. An answer leaves through `whatsapp_outbox` or `sms_outbox`, never a send of its own.
- An answer to a customer's own message carries no promotion. It goes as the transactional SMS kind `reply` (#9286, `whatsapp-channel-conversation-storage.md`).
- No table or column without the owner's yes; the architect asks. WA12's storage is decided (#9286). A demo site receives and sends nothing.
- A site with the Workspace off follows what MA10 settles for incoming e-mail (`workspace/mail-area-proposals.md`).

## Which steps?
- WA12 (S, no product code) Census and proposal. Done in #9281: `whatsapp-channel-census.md`, `-incoming-sms.md`, `-conversation-storage.md` (decided #9286, all as proposed).
- WA13 (M) WhatsApp into a channel. Done in #9389: `workspace/mail-phone-conversations.md`. Media fetching into the card's files is left for WA14.
- WA14 (M) Answering by WhatsApp: the card's "Reply by WhatsApp" and the drawer: text and a file from Files inside the 24 hours, the time left shown; after it the approved templates with their variables. Waiting, Not sent and Send again as e-mail's.
- WA15 (M) The list, the event and the API: WhatsApp conversations in the conversations list beside e-mails, with a filter by transport; the flow event carries the conversation; reading and answering over the API as `workspace/mail-api.md`.
- WA16 (M) WA16a done in #9412 (Twilio webhook, Channel field, stop words), WA16b in progress (the Netgsm and İleti Merkezi polls): `platform/sms-incoming.md`. SMS into a channel, for the providers WA12 found able: the receiver (webhook or the general job's poll), the number's channel in the SMS settings, the conversation by number, an opt-out word written to the consent log.
- WA17 (S) Answering by SMS: the drawer counts the length and the messages it makes, no files; a provider without incoming SMS offers nothing.
- WA18 (S) Close-out: the lasting rules into `workspace/` and `platform/`, `workspace/mail-decisions.md` brought up to date, the plan deleted.

## In which order?
- WA12 first, its questions answered before WA13 and WA16. WA13 after WA4; WA14 after WA13, WA2 and WA3; WA15 after WA13.
- WA16 after WA13 has landed (they share the conversation code), WA17 after WA16. Claim as `[plan:whatsapp-channel] <step> — <topic>`.

## What is asked of the owner along the way?
- WA12's storage: decided (#9286). WA14's drawer on a real panel, shown by the architect before the step lands.
