---
name: sms-and-iys-rules
description: The lasting rules of SMS and İYS (approved 2026-10-19, built PR #600 to #3846): why İYS, providers, message kinds, consent and İYS rules, marketing SMS, where each part is described, what is left
---

## Why İYS?
- A commercial consent not recorded in İYS (İleti Yönetim Sistemi) counts as invalid, e-mail included; 2026 fines run 2,859–42,930 TL per message.

## What holds for every SMS?
- Drivers under `includes/sms/<provider>.php`, credentials in `connect_apps`, no schema per provider (`sms-provider-layer.md`): Netgsm, İleti Merkezi, Twilio. Netgsm abroad is unverified (Turkish mobiles only); sites abroad use Twilio.
- Kinds: order received, shipped, delivered, COD code, member code, ERP overdue reminder, stock: transactional, exempt from İYS, no promotion text. `marketing` is the only commercial kind (`sms-marketing-kind.md`).
- Requests only queue (`sms-outbox-queue.md`); the general job sends.

## What holds for consent and İYS?
- The direct İYS API, not a provider's module, so the consent log stays provider-independent (`iys-client.md`). Consent rows (`consent_log`, kept 3 years) are pushed within 3 business days; refusals pulled from İYS stop sending in the same window. DEFAULT-1 contacts (forms, imports, API, profile) have no consent row and are never pushed.
- E-mail and SMS: every opted-in address or number İYS has not refused gets marketing (#2430, owner 2026-10-28); İYS never narrows or widens it, since a non-company site owner cannot register with İYS. The local `opt_in` stays and is kept in sync.
- Marketing boxes stay ticked as today and box-less paths write `opt_in` as today (#2431); the SMS box sits beside the e-mail box (`core/sms-consent-box.md`), İYS `MESAJ` channel in `contacts.iys_sms_status` (`iys-sms-channel.md`).
- Legacy behaviour: e-mail-only sites work as before with İYS off; nothing marketing goes by SMS until SMS is set up and a campaign is made.

## Where is each part?
- Orders and shipping `sms-order-messages.md`, codes `sms-codes.md`, ERP reminder `sms-erp-overdue.md`, e-mail consent writers `core/consent-log-writers.md`, consents API `api/api-customer-consents.md`, identity and label `sms-marketing-kind.md`, campaigns `sms-campaigns.md`.

## What is left?
- The first İYS live run with the owner's test credentials (`iys-settings-card.md`). A Flows action that sends a marketing SMS (#3570, later).

Related: docs/memory/platform/sms-campaigns.md, docs/memory/platform/iys-settings-card.md
