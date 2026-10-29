---
name: subscriptions-renewal
description: Renewals of store-run subscriptions (PR #3290, decided in #3183): the job's driver branch, the per-row lock, the renewal order, next date from the series, the 1/3/7 ladder and ended, held rows
module: subscription_renewal
---

## Where does it run?
- `recurring_payment_job.php` calls `pg_subscription_renewals_run(time())` first, before the legacy PayPal Payments Pro branch (left as it was, so its `exit()` never holds renewals up). Module `includes/fn/subscription_renewal.php`.
- Due: `status` active or past_due and `0 < next_charge_at <= now` (`pg_subscription_renewal_due_sql()`), oldest first, batches of 25, at most 200 a run; the job runs daily. `next_charge_at` 0 = held, never due.

## How is a double charge kept out?
- `pg_subscription_renewal_run_row()`: `GET_LOCK('pg_subscription_renewal_<id>', 0)`, the row read again inside the lock and charged only while still due; the update is conditional on the status and `next_charge_at` read. The row moves before the order is written, so a run that dies in the order's completion leaves a row that is not due. A run cut short before the update keeps `next_charge_at`; Stripe's key repeats the same attempt.
- All database and service calls go through `$io` (`pg_subscription_renewal_io()`) so tests can stand in for them.

## What does each answer do?
- `pg_subscription_renewal_outcome()`: paid, pending, confirm (redirect with a link), unclear, failed; `pg_subscription_renewal_plan()` (pure) gives the columns and the event.
- paid: `next_charge_at` = the first date of the series (first charge, then one period after another) still to come (`pg_subscription_renewal_next_at()`): a late run or a paid retry keeps the series, a long hold skips missed periods. `retry_count` 0, past_due → active. Event `renewed` with `order_id` and amount. A `card_ref` the answer carries that differs from the stored one is sealed again with `card_last4`.
- failed: `retry_count` + 1, retry 1/3/7 days later (`pg_subscription_retry_at()`), active → past_due; after the third retry fails: `ended`, `next_charge_at` 0. Event `renewal_failed` with the service's words. Memberships of an unpaid row simply lapse through `membership_job.php`.
- confirm (Stripe `authentication_required`): past_due, held (`next_charge_at` 0, no ladder charge while the customer may still confirm), event `confirm_needed` with the link (left empty when longer than 255).
- pending / unclear: held, status unchanged, event `renewal_pending` / `renewal_unclear`. Nothing resolves them yet (the account widget/panel slice).
- First order or its line gone: not charged, a ladder step as for a refusal.

## What is the renewal order?
- `pg_subscription_renewal_order_write()`: an `incomplete` orders row (type online, new `reference_code`, the first order's customer, billing columns and currency), the line's recipient copied when it had one, one `order_items` line at the stored price and quantity with the rest of the amount as `tax_total` (`pg_subscription_renewal_figures()`), then `pg_order_complete()` with the first order's checkout page receipt (`pg_order_payment_page_settings()`) and `pg_erp_auto_invoice($id, 'order_paid')`. So stock, membership days, private-folder access, receipt, campaigns, webhooks and the invoice run as for any order. No shipping, discount or commission. Marked only through `subscription_events.order_id`.
- `charge_renewal` gets the first order's orders row as `order` (iyzico needs buyer and billing address).

## What is not here yet?
- The three campaign e-mails and the confirmation link mail (slice 4b, `email_campaigns.action` widening asked in #3288); Stripe's confirmation `payment_intent.succeeded` (carries `subscription_id`) is not taken yet.

## Tested?
- `tests/subscriptions_renewal_test.php` (pure rules, stand-in io: lock, order of writes, one charge). Not run against a database or a live service.

Related: commerce/subscriptions.md, commerce/subscriptions-stripe.md, plans/subscriptions-iyzico-stripe.md
