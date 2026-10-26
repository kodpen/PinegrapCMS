---
name: sms-and-iys
description: Plan (approved 2026-10-19; SMS layer, Netgsm, queue, order messages, e-mail consent writers done): order/shipping/OTP messages, İYS consent sync for e-mail and SMS marketing, 3-year consent log
---

## Why?
- Nothing reaches İYS (İleti Yönetim Sistemi); e-mail consent is in `contacts.opt_in` and `consent_log`. A consent not recorded in İYS counts as invalid, e-mail included; 2026 fines run 2,859–42,930 TL per message. Turkish e-commerce SaaS ship Netgsm and İYS as standard.

## What is decided?
- Provider interface `includes/fn/sms.php` (`pg_sms_send($to, $text, $kind)`, `pg_sms_provider()`), drivers under `includes/sms/<provider>.php`: Netgsm first, then İleti Merkezi and Twilio for sites abroad; credentials sealed in `connect_apps`, no schema step per provider (built: `platform/sms-provider-layer.md`, `sms-netgsm-driver.md`).
- Message kinds and templates (Settings › Communication): order received, order shipped (carrier + tracking URL), order delivered, cash-on-delivery confirmation code, password/OTP for members, ERP overdue reminder. Transactional messages are exempt from İYS and never carry promotion text; the template editor says so.
- Queue: `sms_outbox` on the mail queue's ladder, sent by the general job, Mail Queue's SMS tab (built: `platform/sms-outbox-queue.md`).
- İYS: direct İYS API (not a provider's module) so the consent log stays provider-independent. Consent rows (`consent_log`, kept 3 years) are pushed to İYS within 3 business days by a job; refusals pulled from İYS stop sending in the same window.
- E-mail campaigns check the İYS status for Turkish sites before sending; the existing `opt_in` stays as the local flag and is kept in sync.
- Sender identity (MERSİS/trade name) and the type label ("tanıtım, kampanya ve bilgilendirme") come from settings and are appended to every marketing SMS.
- Legacy behaviour is untouched: e-mail-only sites work as before with İYS off (a switch per channel).

## What remains?
1. ~~Provider interface + Netgsm driver + settings card + test send~~ (done, PR #600). Commercial kinds are refused by `pg_sms_send()` until step 5.
2. ~~`sms_outbox` step, queue sending in `job.php`, Mail Queue tab~~ (done, PR #610).
3. ~~Order and shipping templates, hooks at `submit_order.php`, `update_order()` and `pg_shipment_delivered()` through `pg_sms_enqueue()` with a reference per order and event~~ (done, PR #1839; `platform/sms-order-messages.md`).
3b. COD code check at checkout (M), and member OTP: Netgsm's OTP route (`/sms/rest/v2/otp`: OTP package, 160 characters, no Turkish letters) for `otp` or not: owner question in PR #1839. Codes use `expires_at` (`platform/sms-outbox-queue.md`).
4. ~~Consent writers at sign-up, account page, checkout, unsubscribe~~ (done, PR #1581; e-mail only, no screen asks for SMS: `core/consent-log-writers.md`).
5. İYS client (`includes/iys/client.php`): brand/consent endpoints, push and pull jobs in the dispatcher (daily), status column on contacts, filter in campaign sending (M).
6. API: `/contacts/{id}/consents` read, webhook `customer.consent_changed`.

## Open questions?
- Member OTP by SMS replaces nothing: TOTP stays; SMS OTP is an extra sign-in option only when a provider is configured.
- İYS test environment credentials: owner provides; until then the client ships with a dry-run mode that only logs.
- Netgsm to numbers abroad: the format was not verified, so the driver takes Turkish mobiles only.
- Owner: marketing boxes start ticked (registration, membership, widgets; checkout from `orders.opt_in` DEFAULT 1); KVKK/GDPR and İYS expect them unticked. Untick?
- Owner: a designed express order without the box stores `opt_in = 0` (order, updated contact); social sign-up 0 unasked. Keep?
- Contacts opted in by DEFAULT 1 (forms, imports, API, profile) have no consent row; step 5 must not push them to İYS.

## Where to start?
- Step 3b once the owner has answered the OTP question; read `platform/sms-order-messages.md` and `sms-outbox-queue.md` (`expires_at` for codes).
