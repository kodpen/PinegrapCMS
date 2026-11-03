---
name: subscriptions-decisions
description: Why driver subscriptions are built as they are (approved 2026-10-19, #3183): store-run renewals first, legacy profiles untouched, renewals as normal orders, no proration; the PRs of each slice
---

## Why were subscriptions added?
- Recurring products made gateway profiles only for the legacy US gateways; iyzico flagged the line and registered the card but nothing charged it later; Stripe had no recurring path; `recurring_payment_job.php` polled PayPal Payments Pro only; customers could not pause or cancel.

## Who runs the renewals?
- The store (decided #3183, `workflow/decisions-made-product-5.md`): the first payment registers the card, the PSP's token is kept sealed (`subscriptions.md`), the cron charges each renewal on the 1/3/7 ladder, the card is changed on the PSP's hosted page, cancel is store-side. Drivers declare `recurring` with a `mode`; only `store` is built.
- PSP-run (iyzico Subscription API, Stripe Subscriptions with `invoice.*` webhooks) is a later slice only if iyzico's stored-card non-3DS permission is not granted to ordinary merchants; until a merchant has it, subscriptions through iyzico stay off (a driver setting).
- Stripe `authentication_required` makes the row `past_due` and sends a confirmation link.

## What stays as it was?
- Legacy gateway profiles are not migrated: their columns and job stay; `recurring_payment_job.php` gained a second branch for driver subscriptions.
- Express-order totals: the first charge is a normal line, a renewal reuses the stored line price; nothing in the totals code changed.

## How does a renewal behave?
- It is a normal order (type online, marked only through `subscription_events.order_id`) written by the existing order writer, so stock, the ERP invoice and e-mails act as for any order; membership days and private-folder access extend through the existing functions; past-due memberships expire through the membership job.
- No proration: a change takes effect at the period end only. Tables as approved in `workflow/decisions-made-schema-5.md`.

## Which PRs built it?
- Slice 1 #3236 (tables, contract, `subscriptions.md`); 2 #3249 iyzico (`subscriptions-iyzico.md`, later lines #3378); 3 #3270 Stripe (`subscriptions-stripe.md`); 4a #3290 renewal writer (`subscriptions-renewal.md`); 4b #3318 mails (`subscriptions-mails.md`); 5a #3347 My Account (`subscriptions-account.md`); operator screen #3405 (`subscriptions-operator-screen.md`); 5b #4399 API and `subscription.*` webhooks (`api/api-customer-subscriptions.md`); price changes #3495 and the profile form's action #3520 (`subscriptions-price-notice.md`).
