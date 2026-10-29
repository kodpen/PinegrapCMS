---
name: subscriptions-iyzico-stripe
description: Plan (approved 2026-10-19, watch list, after payment-driver-layer): recurring billing for iyzico and Stripe through drivers, membership renewals, customer pause/cancel; legacy profiles untouched
---

## Why?
- Recurring products create gateway profiles only for the legacy US gateways (`submit_order.php:4580-5227`); iyzico only flags the line and registers the card (`:5211-5224`), nothing charges it later; Stripe has no recurring path; `recurring_payment_job.php` polls PayPal Payments Pro only. Membership renewal and recurring donations therefore work only on gateways Turkish sites do not use. There is no customer self-service to pause, skip or cancel.

## What is decided?
- Built on the payment driver layer (`commerce/payment-drivers.md`): drivers declare `recurring` in `capabilities()` and implement `subscribe(order_line, schedule)`, `charge_renewal(subscription)`, `cancel(subscription)`, `update_card(subscription)`. iyzico uses its subscription API (plans, customers, card update link); Stripe uses Subscriptions with Checkout/Elements and webhooks (`invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted`).
- Table `subscriptions` (user_id, product_id, order_id, driver, remote_id, status `active|past_due|paused|cancelled|ended`, period, next_charge_at, card_last4, created_at), with `subscription_events`. The legacy gateway profiles are not migrated; they stay in their current columns and job. The `recurring_payment_job.php` dispatcher entry gains a second branch for driver subscriptions.
- Renewal creates a normal order (type online, source `renewal`) through the existing order writer so stock, ERP invoice (`erp-auto-invoice-earchive.md`) and e-mails behave as for any order; membership days and private-folder access extend through the existing functions.
- Dunning: retry ladder on failure (1, 3, 7 days), e-mails from campaign profiles `subscription_renewed`, `subscription_failed`, `subscription_cancelled`; past-due memberships expire through the current membership job.
- Self-service: My Account › Subscriptions widget (pause, resume, cancel at period end, update card via the PSP's hosted page); API `/customers/{id}/subscriptions`; webhooks `subscription.*`.
- Express-order totals: the first charge is a normal line; renewal orders reuse the stored line price. Nothing in the totals code changes.

## What remains?
- Design note after the driver layer ships (iyzico plan model vs Stripe prices; proration policy: none, period end only).
- Slices: tables + driver methods + iyzico (L), Stripe (M), renewal order writer + dunning + e-mails (M), account widget + API (M).

## Open questions?
- Price changes on existing subscriptions: apply at the next period only, with a notice e-mail 14 days before.

## Where to start?
- The payment driver layer is done (`commerce/payment-driver-layer-decisions.md`); read `docs/memory/commerce/` recurring and membership files and `recurring_payment_job.php`.
