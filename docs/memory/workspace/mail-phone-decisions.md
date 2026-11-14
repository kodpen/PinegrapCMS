---
name: mail-phone-decisions
description: Why a customer's WhatsApp message and SMS are Workspace conversations like e-mail (owner #9151), where they are kept (#9286), the rules every part keeps, the drawer's owner rules, what was left out
---

## What did the owner ask, and what follows?
- Owner (2026-11-13, #9151): a customer's WhatsApp message and SMS land in a channel as an e-mail does and are answered the same way. Record: `workflow/decisions-made-product-49.md`.
- One way for the person: the e-mail conversation's card, answer drawer, list, rights (`ws_mail_can_answer()`), notices and saved replies. A transport brings its receiver, its send and its limits; there is no second conversation engine and no copy of the mail screens.

## Where are they kept?
- Owner (2026-11-13, #9286), option a: the mail tables with a transport (`ws_mail_boxes.transport` whatsapp | sms, `ws_mail_conversations.customer_phone`); an incoming SMS is a `ws_mail_items` row, no table of its own, kept two years and erased with the contact (`workflow/decisions-made-schema-18.md`). Not chosen: tables of their own; threads read from `whatsapp_inbox` without status or holder.
- An SMS answer is the transactional kind `reply`, to the conversation's number within 7 days of its last SMS; a stop word writes an SMS (and WhatsApp) marketing refusal (`workflow/decisions-made-workspace-23.md`).

## Which rules does every part keep?
- WhatsApp takes free text and files only inside the 24 hours after the customer's last message; after them only an approved template. An answer leaves through `whatsapp_outbox` or `sms_outbox`, never a send of its own, and carries no promotion.
- Assistants draft, never send (#2444 answer 7). A demo site receives and sends nothing. A site with the Workspace off follows incoming e-mail's rule (`mail-area-proposals.md`).

## What did the owner set for the drawer?
- Owner (2026-11-14, channel 25 #2709, architect 2711): wherever a template or design is chosen, the choice shows a preview (`mail-phone-reply-files.md`); inside a channel every new part is built from look A's components, old parts converted by the steps that touch them.

## What is where?
- Conversations: `mail-phone-conversations.md`. WhatsApp answers: `mail-phone-reply.md`, `mail-phone-reply-files.md`; received files: `mail-phone-media.md`. SMS answers: `mail-sms-reply.md`; incoming SMS: `platform/sms-incoming.md`, `platform/sms-incoming-providers.md`. List, event, API: `mail-phone-list-event-api.md`, `mail-api.md`. WhatsApp sending in general: `platform/whatsapp-overview.md`.

## What was left out?
- WhatsApp campaigns to many (a consent decision of their own), a chat button widget, Meta's catalog and other products, a Kodpen verified app between sites and Meta.

Related: docs/memory/workspace/mail-decisions.md, docs/memory/platform/whatsapp-overview.md
