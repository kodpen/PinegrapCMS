---
name: whatsapp-channel
description: Plan (owner 2026-11-12, #8950): WhatsApp as a message channel beside e-mail for orders, forms and flows; the site's own Meta app, templates designed in the panel, offer codes; what binds every step
---

## What is asked?
- Owner (2026-11-12): "sms türkiye için zorlayıcı oldu artık ilerişim eposta + whatsapp oldu o yüzden whatsapp entegrasyonunu geliştirelim sipariş ve formlar için olanak açalım". Record: `workflow/decisions-made-product-47.md`. Steps: `whatsapp-channel-steps.md`.
- Today WhatsApp is only a share button. This plan replaces the WhatsApp line of `reverse-oauth-leftovers.md`.

## What did the owner decide?
- W1a: a site connects with its own Meta app and a permanent token; the settings card walks through the steps. No Kodpen app in between.
- W2a: a customer's reply shows as a bell notice and in the contact's history. Answering from the panel comes later; so does a shared inbox (`workspace/mail-decisions.md` keeps WhatsApp out of the mailbox).
- W3a: the first scope is one message to one person: order moments, a form's message to its sender, a flow's step. A campaign to many at once comes later, with its own consent decision.
- Şa: templates are designed in the panel as drawn (`plans/whatsapp-channel/template-designer.html`, pictures beside it; the phone part is `plans/whatsapp-channel/wa-phone.css`): a preview as on a phone, a header (text, image, video, document), the message with variables, a footer, up to three buttons (link, copy code, quick reply), then sent to WhatsApp for approval.
- Ka: an offer code can go in a message, as the offer's shared code or as a key for each recipient. A marketing-type template goes only to a contact who gave marketing consent, and only one by one.

## What does every step keep?
- The SMS layer's shape (`platform/sms-provider-layer.md`, `platform/sms-outbox-queue.md`): a request only queues, the general job sends, credentials in `connect_apps`, a send never repeated when its outcome is unknown, every event off until the site turns it on. Functions in `includes/fn/whatsapp.php`.
- Outside the 24 hours after a customer's own message WhatsApp takes only approved templates: every message of this plan is a template with its variables filled, never free text.
- WhatsApp's template type decides the rule: an information or verification template goes as a transactional SMS does (`platform/sms-and-iys-rules.md`); a marketing template needs the contact's marketing consent. Which stored consent counts is proposed in WA0 and asked of the owner.
- An e-mail that goes today keeps going; WhatsApp is added beside it and never replaces it by itself.
- Numbers in E.164 through the SMS layer's own normalising; no second function.
- No table or column without the owner's yes: WA0 proposes the storage, the architect asks. A step that meets a need for one stops and asks.
- A demo site sends nothing. The token and the app secret are stored as the SMS credentials are and never shown again, logged or exported.
- Seen on a sandbox with a test number of Meta's; what could not be reached is named in the PR.

## What is not in this plan?
- Campaigns to many, answering from the panel, a shared inbox, a chat button's widget, the catalog and Meta's other products, Kodpen's own verified app.
