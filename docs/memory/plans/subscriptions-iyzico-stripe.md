---
name: subscriptions-iyzico-stripe
description: Plan (approved 2026-10-19, watch list, after payment-driver-layer): recurring billing for iyzico and Stripe through drivers, membership renewals, customer pause/cancel; legacy profiles untouched
---

## Why?
- Recurring products create gateway profiles only for the legacy US gateways (`submit_order.php:4580-5227`); iyzico only flags the line and registers the card (`:5211-5224`), nothing charges it later; Stripe has no recurring path; `recurring_payment_job.php` polls PayPal Payments Pro only. Membership renewal and recurring donations therefore work only on gateways Turkish sites do not use. There is no customer self-service to pause, skip or cancel.

## What is decided?
- Built on the payment driver layer (`commerce/payment-drivers.md`): drivers declare `recurring` with a `mode` (`store` | `psp`) in `capabilities()`. Decided (#3183, architect, `workflow/decisions-made-product-5.md`): store-run first on both drivers: the first payment registers the card (iyzico `setRegisterCard`, Stripe `setup_future_usage: off_session`), the token kept as the PSP's token only, `charge_renewal(subscription)` from the cron with the 1/3/7 ladder, `update_card(subscription)` through the PSP's hosted page, `subscription_cancel(subscription)` store-side (`cancel()` is the order operation). Stripe `authentication_required` → `past_due` and a confirmation link; iyzico needs the merchant's stored-card non-3DS permission (a driver setting; subscriptions through iyzico off until it is on). PSP-run (iyzico Subscription API, Stripe Subscriptions with `invoice.*` webhooks) is a later slice only if that permission is not granted to ordinary merchants.
- Tables `subscriptions` and `subscription_events` as approved in `workflow/decisions-made-schema-5.md` (#3183). The legacy gateway profiles are not migrated; they stay in their current columns and job. The `recurring_payment_job.php` dispatcher entry gains a second branch for driver subscriptions.
- Renewal creates a normal order (type online, marked only through `subscription_events.order_id`) through the existing order writer so stock, ERP invoice (`erp-auto-invoice-earchive.md`) and e-mails behave as for any order; membership days and private-folder access extend through the existing functions.
- Dunning: retry ladder on failure (1, 3, 7 days), e-mails from campaign profiles `subscription_renewed`, `subscription_failed`, `subscription_cancelled`; past-due memberships expire through the current membership job.
- Self-service: My Account › Subscriptions widget (pause, resume, cancel at period end, update card via the PSP's hosted page); API `/customers/{id}/subscriptions`; webhooks `subscription.*`.
- Express-order totals: the first charge is a normal line; renewal orders reuse the stored line price. Nothing in the totals code changes.

## What remains?
- Design note: #3183 (store-run; no proration, period end only).
- Slice 1 done (#3236): tables, the `recurring` mode and the store-mode operations in the contract, `includes/fn/subscriptions.php` (`commerce/subscriptions.md`).
- Slice 2 done (#3249): iyzico store mode behind the stored-card renewals switch, `card_ref`, `charge_renewal`, `update_card`, `subscription_cancel`, the row from checkout (`commerce/subscriptions-iyzico.md`). Future-start lines get no row yet.
- Slice 3 done (#3270): Stripe store mode (`commerce/subscriptions-stripe.md`).
- Slice 4a (#3290): renewal writer + ladder (`commerce/subscriptions-renewal.md`). 4b: the three e-mails + Stripe's confirmation link mail (`email_campaigns.action`: #3288); then account widget + API (M).

## Open questions?
- Price changes on existing subscriptions: apply at the next period only, with a notice e-mail 14 days before.

## Where to start?
- Owner (2026-10-29, #3183): start now. Slice 1: contract operations and the tables (`workflow/decisions-made-schema-5.md`), then iyzico, Stripe, renewal writer + dunning + e-mails, account widget + API. Read `docs/memory/commerce/` recurring and membership files and `recurring_payment_job.php`.
