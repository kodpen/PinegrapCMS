---
name: whatsapp-channel-steps
description: Steps WA0-WA11 of the WhatsApp channel plan (#8950): facts and storage first, connection, queue, templates, webhook, orders, forms, the Flows box, the template designer, offer codes
---

## Which steps? (rules: `whatsapp-channel.md`)
- WA0 (S, no product code) Facts and storage. Done in #9067: `platform/whatsapp-cloud-api-facts.md`, `platform/whatsapp-cloud-api-webhooks.md`.
- WA0's proposals: `whatsapp-channel-storage.md`, asked in #9085 (queue, templates, customers' messages) and #9087 (consent, per-recipient key). The card drawn: `plans/whatsapp-channel/settings-card.html`.
- WA1 (M) Connection. Done in #9067: `platform/whatsapp-connection.md`. The webhook's verify call and signature check are in it, so step 5 of the card can be completed; WA4 reads the events.
- WA2 (M) Queue and sender: the enqueue function and the general job's pass with the SMS queue's rules (reference, expiry, retry only when nothing reached Meta, uncertain never resent); the queue screen's WhatsApp tab.
- WA3 (M) Templates of the account: read, keep, refresh; a list screen with type, language and approval state; a rejected template's reason shown.
- WA4 (M) Webhook receiver: the verify call, the signature checked with the app secret, delivery states onto queued rows, a template's approval change, a customer's message as a bell notice and a line in the contact's history (W2a).
- WA5 (M) Order moments: received, shipped, delivered, back in stock, beside the SMS events of `platform/sms-order-messages.md`; per event a template and what fills each variable; off by default.
- WA6 (M) Forms: a custom form's message to its sender: which field holds the number, which template, which fields fill it.
- WA7 (M) Flows: the box "Send a WhatsApp message" (recipient from a value, template, variables, "send an e-mail when there is no number or it fails") and the event "A WhatsApp message arrived" in the event start's catalogue; both general, by `workspace/workspace-flows-adding.md`.
- WA8 (M) Template designer, first half: the screen as drawn with the phone preview, name, type, language, the message with variables and sample values, footer; a draft saved; "Send myself a test" for an approved template.
- WA9 (M) Template designer, second half: header image, video or document from Files, the buttons, "Send for approval", the state shown, editing a rejected template.
- WA10 (M) Offer codes: the variable "Offer code" as the offer's shared code or a key made for the recipient at send time with the validity set on the template; the marketing consent gate of Ka on every path that sends.
- WA11 (S) Close-out: the lasting rules into `platform/`, the overview file, this plan deleted.

## In which order?
- WA0 first; its questions (#9085) answered before WA2, WA3 and WA4 start, #9087 before WA10. WA1, then WA2, WA3 and WA4 one after the other (they share the module).
- WA5, WA6 and WA7 after WA3, in any order. WA8 after WA3, WA9 after WA8, WA10 after WA9 and WA5.
- `flows-quick-setup.md` shows the WhatsApp step once WA7 has landed. The card, the template list and the queue tab move into the messages area when its shell is there (`messages-area-steps.md`).
- Claim as `[plan:whatsapp-channel] <step> — <topic>`.

## What is asked of the owner along the way?
- WA0's storage and consent questions, through the architect (#9085, #9087). The first live send with the owner's own Meta app and number, before WA5 is switched on anywhere.
