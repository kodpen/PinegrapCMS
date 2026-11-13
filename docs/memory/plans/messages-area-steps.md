---
name: messages-area-steps
description: Steps MA0-MA11 of the messages area plan (#8961): census, shell and tabs, one sends list, campaigns, e-mail bodies as pages, templates, channels, automatic sends, profiles into flows, incoming e-mail
---

## Which steps? (rules: `messages-area.md`)
- MA0 (S, done #9074) The census: today's screens and their rights, the addresses that keep leading somewhere, one Sends list without a new table (`platform/messages-area-census-*.md`). Its proposals (`-tabs`, `-addresses`, `-sends`) wait for the architect on #9081; MA1 and MA2 start from the answer.
- MA1 (M) The shell: the menu row, the five tabs with their gates, old addresses leading in.
- MA2 (M) Sends: the tiles, the channel, source, state and date filters, single messages and campaign rows that open, retry and delete under the queues' own rules.
- MA3 (M) Campaigns: one list for e-mail and SMS campaigns; "New campaign" asks the channel first, then recipients, content, time.
- MA4 (S, done #9074) E-mail body as a page: how e-mail pages are made today, what mail programs need, a proposed way for MA5 (`designer/campaign-email-body-*.md`), waiting for the architect on #9083.
- MA5 (M to L, shaped after MA4) The campaign's content step: edit the design, ready designs, paste HTML, phone preview, a test to oneself.
- MA6 (M) Templates: e-mail designs, SMS texts and, once `whatsapp-channel-steps.md` reaches WA3, WhatsApp templates in one tab.
- MA7 (S) Channels: e-mail sender, SMS, WhatsApp and İYS as cards with their state, each leading to its settings.
- MA8 (M) Automatic sends: the flows that send a message, their recipes, the way into the quick setup; shown once AF2 of `automation-flows-menu.md` has landed.
- MA9 Profiles into flows (M2b), four parts, and MA10 Incoming e-mail, two parts: `messages-area-steps-2.md`.
- MA11 (S) Close-out: the lasting rules into `platform/` and `ui/`, an overview file, this plan deleted.

## In which order?
- MA0 first, then MA1; MA2, MA3, MA6, MA7 after MA1 in any order. MA4 may run beside MA1; MA5 after MA4 and MA3.
- MA8 after AF2; MA10a any time, MA10b after MA1; MA9a any time; MA9b to MA9d only after AF3 has landed and the architect has decided MA9a's proposals; MA9c is shown to the owner on a copy of a running site before it lands.
- Where WhatsApp's card, template list and queue sit follows this area once MA1 has landed; before that they sit beside the SMS ones.
- Claim as `[plan:messages-area] <step> — <topic>`.

## What is asked of the owner along the way?
- MA9c's dry-run report on a real site's profiles before the update step lands.
