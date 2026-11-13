---
name: messages-area-steps
description: Steps MA0-MA11 of the messages area plan (#8961): census, shell and tabs, one sends list, campaigns, e-mail bodies as pages, templates, channels, automatic sends, profiles into flows, incoming e-mail
---

## Which steps? (rules: `messages-area.md`)
- MA0 (S, no product code) The census: today's screens (campaigns, campaign profiles, SMS campaigns, the mail queue with its SMS tab, the Communication settings' cards), the right each asks, the addresses that must keep leading somewhere, and how one Sends list reads the mail queue, the SMS queue and the campaigns' history without a new table (or what it would need, as a question). Proposals go to the architect.
- MA1 (M) The shell: the menu row, the five tabs with their gates, old addresses leading in.
- MA2 (M) Sends: the tiles, the channel, source, state and date filters, single messages and campaign rows that open, retry and delete under the queues' own rules.
- MA3 (M) Campaigns: one list for e-mail and SMS campaigns; "New campaign" asks the channel first, then recipients, content, time.
- MA4 (S, no product code) E-mail body as a page, the look first: how the account e-mails are designed today (`designer/account-email-pages.md`), what a body must be for mail programs (width, inline styles, pictures), ready designs, pasted HTML; the way proposed to the architect.
- MA5 (M to L, shaped after MA4) The campaign's content step: edit the design, ready designs, paste HTML, phone preview, a test to oneself.
- MA6 (M) Templates: e-mail designs, SMS texts and, once `whatsapp-channel-steps.md` reaches WA3, WhatsApp templates in one tab.
- MA7 (S) Channels: e-mail sender, SMS, WhatsApp and İYS as cards with their state, each leading to its settings.
- MA8 (M) Automatic sends: the flows that send a message, their recipes, the way into the quick setup; shown once AF2 of `automation-flows-menu.md` has landed.
- MA9 Profiles into flows (M2b), six parts, and MA10 Incoming e-mail, three parts: `messages-area-steps-2.md`.
- MA11 (S) Close-out: the lasting rules into `platform/` and `ui/`, an overview file, this plan deleted.

## In which order?
- MA0 first, then MA1; MA2, MA3, MA6, MA7 after MA1 in any order. MA4 may run beside MA1; MA5 after MA4 and MA3.
- MA8 after AF2; MA10b after MA1; MA10c after MA10b and AF1. MA9b and MA9c one after the other, in either order (both edit `workspace_flow.js`), never beside AF3; MA9d after both and after AF3; MA9e after MA9d, shown to the owner on a copy of a running site before it lands; MA9f after MA9e.
- Where WhatsApp's card, template list and queue sit follows this area once MA1 has landed; before that they sit beside the SMS ones.
- Claim as `[plan:messages-area] <step> — <topic>`.

## What is asked of the owner along the way?
- MA9e's dry-run report on a real site's profiles before the update step lands.
