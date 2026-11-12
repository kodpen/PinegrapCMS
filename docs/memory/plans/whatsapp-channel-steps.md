---
name: whatsapp-channel-steps
description: Steps WA0-WA11 of the WhatsApp channel plan (#8950): facts and storage first, connection, queue, templates, webhook, orders, forms, the Flows box, the template designer, offer codes
---

## Which steps? (rules: `whatsapp-channel.md`)
- WA0 (S, no product code) Facts and storage. Read Meta's Cloud API pages on the day (version, template parts and limits, media upload, webhook fields, error codes, what a send costs by type) into a facts file of its own in `platform/`.
- WA0 goes on: read the SMS queue and propose, as `architect` questions: where queued messages live (the SMS queue with a channel, or a table of its own), where template drafts and the account's template list live, where a customer's message is kept for the contact's history, which stored consent a marketing template asks for, how a key for one recipient is made from the key codes (`commerce/key-code-form-checks.md`). Draw the settings card in look A.
- WA1 (M) Connection: `includes/fn/whatsapp.php` and the Cloud API driver, the Communication › WhatsApp card with its numbered steps (app, permanent token, phone number id, account id, webhook address and verify token), a check call, a test message to the signed-in person.
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
- WA0 first; its questions answered before WA2, WA3 and WA4 start. WA1, then WA2, WA3 and WA4 one after the other (they share the module).
- WA5, WA6 and WA7 after WA3, in any order. WA8 after WA3, WA9 after WA8, WA10 after WA9 and WA5.
- `flows-quick-setup.md` shows the WhatsApp step once WA7 has landed.
- Claim as `[plan:whatsapp-channel] <step> — <topic>`.

## What is asked of the owner along the way?
- WA0's storage and consent questions, through the architect. The first live send with the owner's own Meta app and number, before WA5 is switched on anywhere.
