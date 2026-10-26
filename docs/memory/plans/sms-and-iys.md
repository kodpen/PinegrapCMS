---
name: sms-and-iys
description: Plan (approved 2026-10-19): SMS layer, order messages, consent writers, İYS client and card done; left: first İYS live run, COD code and member OTP, consent API
---

## Why?
- A consent not recorded in İYS (İleti Yönetim Sistemi) counts as invalid, e-mail included; 2026 fines run 2,859–42,930 TL per message.

## What is decided?
- SMS drivers under `includes/sms/<provider>.php`: Netgsm first, then İleti Merkezi and Twilio; credentials in `connect_apps`, no schema per provider (`platform/sms-provider-layer.md`).
- Message kinds and templates (Settings › Communication): order received, order shipped (carrier + tracking URL), order delivered, cash-on-delivery confirmation code, password/OTP for members, ERP overdue reminder. Transactional messages are exempt from İYS and never carry promotion text; the template editor says so.
- İYS: direct İYS API (not a provider's module) so the consent log stays provider-independent. Consent rows (`consent_log`, kept 3 years) are pushed to İYS within 3 business days by a job; refusals pulled from İYS stop sending in the same window.
- E-mail campaigns check the İYS status for Turkish sites before sending; the existing `opt_in` stays as the local flag and is kept in sync.
- Sender identity (MERSİS/trade name) and the type label ("tanıtım, kampanya ve bilgilendirme") come from settings and are appended to every marketing SMS.
- Legacy behaviour is untouched: e-mail-only sites work as before with İYS off (a switch per channel).

## What remains?
- Done: provider interface + Netgsm (PR #600), `sms_outbox` queue (PR #610), order and shipping messages (PR #1839, `platform/sms-order-messages.md`), e-mail consent writers (PR #1581, `core/consent-log-writers.md`). Commercial SMS kinds stay refused by `pg_sms_send()` until SMS consent exists.
3b. COD code check at checkout (M), and member OTP: Netgsm's OTP route (`/sms/rest/v2/otp`: OTP package, 160 characters, no Turkish letters) for `otp` or not: owner question in PR #1839. Codes use `expires_at` (`platform/sms-outbox-queue.md`).
5. ~~İYS client, daily push/pull job, contact status column, campaign filter~~ (done, PR #1895; `platform/iys-client.md`; dry run by default, inert until 5b).
5b. ~~İYS settings card~~ (done, PR #1940; `platform/iys-settings-card.md`). Left: the first live run against the sandbox once the owner gives İYS test credentials.
6. API: `/contacts/{id}/consents` read, webhook `customer.consent_changed`.

## Open questions?
- Member OTP by SMS replaces nothing: TOTP stays; SMS OTP is an extra sign-in option only when a provider is configured.
- İYS test credentials: owner provides; until then the client runs dry (logs only). Answered 2026-10-26: the contact status column is approved (`contacts.iys_email_status`, 11.0.39).
- Owner: with İYS on, should a commercial e-mail go only to addresses İYS shows as approved, rather than to every opted-in address İYS has not refused (today)? Asked in PR #1895.
- Netgsm to numbers abroad: the format was not verified, so the driver takes Turkish mobiles only.
- Owner: marketing boxes start ticked (registration, membership, widgets; checkout from `orders.opt_in` DEFAULT 1); KVKK/GDPR and İYS expect them unticked. Untick?
- Owner: a designed express order without the box stores `opt_in = 0` (order, updated contact); social sign-up 0 unasked. Keep?
- Contacts opted in by DEFAULT 1 (forms, imports, API, profile) have no consent row and are never pushed (step 5 pushes consent_log rows only).

## Where to start?
- First İYS live run: once credentials exist, fill the card in test environment and live mode (`platform/iys-settings-card.md`), run `iys_job.php`.
- Step 3b once the owner has answered the OTP question; read `platform/sms-order-messages.md` and `sms-outbox-queue.md` (`expires_at` for codes).
