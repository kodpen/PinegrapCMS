---
name: sms-and-iys
description: Plan (approved 2026-10-19): SMS layer, order messages, consent writers, İYS client and card, consents API done; left: first İYS live run, marketing SMS 7-10
---

## Why?
- A consent not recorded in İYS (İleti Yönetim Sistemi) counts as invalid, e-mail included; 2026 fines run 2,859–42,930 TL per message.

## What is decided?
- SMS drivers under `includes/sms/<provider>.php`: Netgsm first, then İleti Merkezi and Twilio; credentials in `connect_apps`, no schema per provider (`platform/sms-provider-layer.md`).
- Message kinds and templates (Settings › Communication): order received, order shipped (carrier + tracking URL), order delivered, cash-on-delivery confirmation code, password/OTP for members, ERP overdue reminder (PR #3626, `platform/sms-erp-overdue.md`). Transactional messages are exempt from İYS and never carry promotion text; the template editor says so.
- İYS: direct İYS API (not a provider's module) so the consent log stays provider-independent. Consent rows (`consent_log`, kept 3 years) are pushed to İYS within 3 business days by a job; refusals pulled from İYS stop sending in the same window.
- E-mail campaigns check the İYS status for Turkish sites before sending; the existing `opt_in` stays as the local flag and is kept in sync.
- Sender identity (MERSİS/trade name) and the type label ("tanıtım, kampanya ve bilgilendirme") come from settings and are appended to every marketing SMS.
- Legacy behaviour is untouched: e-mail-only sites work as before with İYS off (a switch per channel).

## What remains?
- Done: provider interface + Netgsm (PR #600), `sms_outbox` queue (PR #610), order and shipping messages (PR #1839, `platform/sms-order-messages.md`), e-mail consent writers (PR #1581, `core/consent-log-writers.md`). Commercial kinds stay refused until SMS consent exists.
3b. ~~COD code and member sign-in code by the normal SMS route~~ (done, PR #2669 and #2720; #2670, #2672; `platform/sms-codes.md`).
5. ~~İYS client, daily push/pull job, contact status column, campaign filter~~ (done, PR #1895; `platform/iys-client.md`; dry run by default, inert until 5b).
5b. ~~İYS settings card~~ (done, PR #1940; `platform/iys-settings-card.md`). Left: the first live run against the sandbox with the owner's İYS test credentials.
6. ~~API: consents read, webhook `customer.consent_changed`~~ (done, PR #1940; `api/api-customer-consents.md`).
- Drivers done: İleti Merkezi (PR #3623), Twilio (PR #3647); `platform/sms-iletimerkezi-driver.md`, `sms-twilio-driver.md`.
7-10. Marketing SMS, decided #3570 (`workflow/decisions-made-schema-6.md`, `-product-8.md`), one PR each in order: 7 the SMS consent box beside the e-mail box (as #2431) and `consent_log` channel `sms`; 8 `contacts.iys_sms_status` and the İYS `MESAJ` channel; 9 the sender name and type label settings, the `marketing` kind opened; 10 an SMS campaign beside the e-mail campaigns.

## Open questions?
- Answered (#2672): the member code is an alternative to the app code at the second step, role 3 only; no passwordless sign-in.
- İYS test credentials: owner provides; until then the client runs dry (logs only). Answered 2026-10-26: the contact status column is approved (`contacts.iys_email_status`, 11.0.39).
- Answered (#2430, owner 2026-10-28): sending stays as today (every opted-in address İYS has not refused); İYS never narrows it further, since a non-company site owner cannot register with İYS at all.
- Netgsm abroad: format unverified, Turkish mobiles only; sites abroad use Twilio.
- Answered (#2431, owner 2026-10-28): the marketing boxes stay ticked as today, and the box-less paths keep writing `opt_in` as today: marketing is a feature of the software.
- Contacts opted in by DEFAULT 1 (forms, imports, API, profile) have no consent row and are never pushed (step 5 pushes consent_log rows only).

## Where to start?
- Steps 7-10 (#3570) in order, 7 first. First İYS live run: once credentials exist, fill the card in test environment and live mode (`platform/iys-settings-card.md`), run `iys_job.php`.
