---
name: whatsapp-overview
description: Start file for the whatsapp-* files: WhatsApp beside e-mail for orders, forms and flows, the decisions that bind it, the rules every part keeps, its screens, which file holds what, adding a sender
module: whatsapp
---

## What is it?
- A site sends WhatsApp template messages through its own Meta app and number, beside its e-mails: order moments, a form's message, a flow's step. Templates are designed in the panel and approved by Meta; one can carry an offer code. Answers come back by webhook.
- Built in 2026.11.0, WA1–WA10 (#9067 to #9414). Customers' messages in a Workspace channel: `workspace/mail-phone-conversations.md`.

## Which decisions bind it?
- WhatsApp as a channel, its first scope, templates and offer codes (owner, #8950): `workflow/decisions-made-product-47.md`. The site's own Meta app, no Kodpen app between (W1a); one message to one person first, a campaign to many later with a consent decision of its own (W3a).
- Marketing consent is the number's SMS marketing consent (#9087): `decisions-made-product-48.md`. The three tables (#9085): `decisions-made-schema-17.md`. Variables as chips, never a bare `{{1}}` (#2222): `whatsapp-template-designer.md`.

## Which rules does every part keep?
- A request only queues (`pg_whatsapp_enqueue()`); the general job sends. A send whose outcome is unknown is never repeated. Every sending event is off until the site turns it on.
- Outside the 24 hours after a customer's own message only an approved template goes, its variables filled; never free text.
- Meta's category at the send decides: utility and authentication go as a transactional SMS does; marketing, and any message with an offer code, only to a number with SMS marketing consent, asked at enqueue and again at the send.
- An e-mail that went before keeps going; WhatsApp never takes its place.
- Numbers through `pg_sms_number()`; no second normaliser.
- A demo site sends and receives nothing. The token and secrets are sealed in `connect_apps`, never shown again, logged or exported.
- No table or column without the owner's yes (`plans/whatsapp-channel-storage.md` while the plan lives).

## What does a person see?
- Communication › WhatsApp card: the account in five steps and a test message (`whatsapp-connection.md`).
- Communication › Templates, card "WhatsApp templates", and the designer `whatsapp_template.php`: header, message with chips, footer, buttons, phone preview, Send for approval, Send myself a test, the Offer code card (`whatsapp-templates.md`, `whatsapp-template-designer.md`, `whatsapp-template-approval.md`, `whatsapp-offer-codes.md`).
- The WhatsApp Messages card for order moments (`whatsapp-order-messages.md`), a custom form's WhatsApp message (`whatsapp-form-messages.md`), the flow box "Send a WhatsApp message" and event "A WhatsApp message arrived" (`workspace/workspace-flows-whatsapp-box.md`).
- A customer's message: bell and customer history (`whatsapp-webhook-events.md`).

## Where is each part?
- Meta's API as of 2026-11-12: `whatsapp-cloud-api-facts.md`, `whatsapp-cloud-api-webhooks.md`.
- `includes/fn/whatsapp.php` (account, webhook check, queue, templates), driver `includes/whatsapp/cloud_api.php` and `cloud_templates.php`; `whatsapp_webhook.php` and `includes/fn/whatsapp_webhook.php` (events); `whatsapp_design.php` (designer); `whatsapp_offer.php` (offer codes); `whatsapp_messages.php` (slots, filling, order moments); `whatsapp_forms.php`.
- Tables: `whatsapp_outbox` (`whatsapp-outbox-queue.md`), `whatsapp_templates`, `whatsapp_inbox`.

## How is a new sender added?
- Find the approved template (`pg_whatsapp_template_find()`), let the person choose a value per slot (`pg_whatsapp_message_slots()` with the template's offer), fill with `pg_whatsapp_message_components()` and queue with `pg_whatsapp_enqueue()`: kind, a UNIQUE reference, an expiry, the contact. Never call `pg_whatsapp_cloud_send()` from a request; never fill the offer code yourself.
- Its switch off by default, its tests beside `tests/whatsapp_messages_test.php`, its file listed here.
