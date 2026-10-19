---
name: sms-and-iys
description: Plan (approved 2026-10-19, in progress: provider layer, Netgsm driver and settings card done): queue, order/shipping/OTP messages, İYS consent sync for e-mail and SMS marketing, 3-year consent log
---

## Why?
- No İYS (İleti Yönetim Sistemi) record is kept; marketing consent is only `contacts.opt_in`. A consent not recorded in İYS counts as invalid, e-mail included; 2026 fines run 2,859–42,930 TL per message. Turkish e-commerce SaaS ship Netgsm and İYS as standard.

## What is decided?
- Provider interface `includes/fn/sms.php`: `pg_sms_send($to, $text, $kind)`, `pg_sms_provider()`, with drivers under `includes/sms/<provider>.php`. Netgsm first, then İleti Merkezi and Twilio for sites abroad. Credentials sealed in `connect_apps` (`sms_<provider>`), not a config blob: no schema step per provider (built: `platform/sms-provider-layer.md`, `sms-netgsm-driver.md`).
- Message kinds and templates (Settings › Communication): order received, order shipped (carrier + tracking URL), order delivered, cash-on-delivery confirmation code, password/OTP for members, ERP overdue reminder. Transactional messages are exempt from İYS and never carry promotion text; the template editor says so.
- Queue: SMS goes through `mail_outbox` style rows in a new `sms_outbox` table with the same retry ladder, so the general job sends them; a Mail Queue tab shows SMS rows.
- İYS: direct İYS API (not a provider's module) so the consent log stays provider-independent. Consent events (sign-up, account page toggle, checkout checkbox, unsubscribe) are written to `consent_log` (person, channel e-mail/SMS/call, status, source, ip, timestamp; kept 3 years) and pushed to İYS within 3 business days by a job; refusals pulled from İYS stop sending in the same window.
- E-mail campaigns check the İYS status for Turkish sites before sending; the existing `opt_in` stays as the local flag and is kept in sync.
- Sender identity (MERSİS/trade name) and the type label ("tanıtım, kampanya ve bilgilendirme") come from settings and are appended to every marketing SMS.
- Legacy behaviour is untouched: e-mail-only sites keep working with İYS off; the feature is a Settings switch per channel.

## What remains?
1. ~~Provider interface + Netgsm driver + settings card + test send~~ (done, PR #600). Commercial kinds are refused by `pg_sms_send()` until step 5.
2. `sms_outbox` migration step, queue sending in `job.php`, Mail Queue tab (S–M). The queue goes behind `pg_sms_send()`; the settings' test stays immediate; a row is resent only when the result's `retry` is true.
3. Order and shipping templates, hooks at `submit_order.php` (after the order exists), `pg_shipment_record()` once `shipping-management.md` lands, COD code check at checkout (M). Netgsm's OTP route (`/sms/rest/v2/otp`: OTP package, 160 characters, no Turkish letters) for `otp` or not: decide. The changelog's `[NEW]` line comes with this step, when a message first goes out on its own.
4. `consent_log` migration, consent writers at sign-up, account page, checkout, unsubscribe (M).
5. İYS client (`includes/iys/client.php`): brand/consent endpoints, push and pull jobs in the dispatcher (daily), status column on contacts, filter in campaign sending (M).
6. API: `/contacts/{id}/consents` read, webhook `customer.consent_changed`.

## Open questions?
- Member OTP by SMS replaces nothing: TOTP stays; SMS OTP is an extra sign-in option only when a provider is configured.
- İYS test environment credentials: owner provides; until then the client ships with a dry-run mode that only logs.
- Netgsm to numbers abroad: the format was not verified, so the driver takes Turkish mobiles only.

## Where to start?
- Step 2; read `docs/memory/platform/sms-provider-layer.md` and `sms-netgsm-driver.md`, then `docs/memory/core/fn-module-errors-mail-backup-consent.md` (mail_queue) for the row and backoff pattern; the driver result's `retry` is the retry decision.
