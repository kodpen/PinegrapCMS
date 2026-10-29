---
name: subscriptions-renewal
description: Renewals of store-run subscriptions (PR #3290, decided in #3183): job branch, per-row lock, renewal order, next date, 1/3/7 ladder, held rows and Stripe's paid report
module: subscription_renewal
---

## Where does it run?
- `recurring_payment_job.php` calls `pg_subscription_renewals_run(time())` first, before the legacy PayPal Payments Pro branch (unchanged; its `exit()` cannot hold renewals up). Module `includes/fn/subscription_renewal.php`.
- Due: `status` active or past_due and `0 < next_charge_at <= now` (`pg_subscription_renewal_due_sql()`), oldest first, batches of 25, at most 200 a run; the job runs daily. `next_charge_at` 0 = held, never due.

## How is a double charge kept out?
- `pg_subscription_renewal_run_row()`: `GET_LOCK('pg_subscription_renewal_<id>', 0)`, the row read again and charged only while due; the update is conditional on the status and `next_charge_at` read. The row moves before the order is written, so a run dying there leaves it not due. A run cut short before the update keeps `next_charge_at`: Stripe's key repeats the attempt.
- Database and service calls go through `$io` (`pg_subscription_renewal_io()`).

## What does each answer do?
- `pg_subscription_renewal_outcome()`: paid, pending, confirm (redirect with a link), unclear, failed; `pg_subscription_renewal_plan()` (pure) gives the columns and the event.
- paid: `next_charge_at` = the first date of the series (first charge, then one period after another) still to come (`pg_subscription_renewal_next_at()`): late runs and paid retries keep it, a long hold skips missed periods. `retry_count` 0, past_due → active. Event `renewed` with `order_id` and amount. A `card_ref` the answer carries that differs from the stored one is sealed again with `card_last4`.
- failed: `retry_count` + 1, retry 1/3/7 days later (`pg_subscription_retry_at()`), active → past_due; after the third retry fails: `ended`, `next_charge_at` 0. Event `renewal_failed` with the service's words. Unpaid memberships lapse through `membership_job.php`.
- confirm (Stripe `authentication_required`): past_due, held (`next_charge_at` 0, no ladder charge while the customer may still confirm), event `confirm_needed` with the link (left empty when longer than 255).
- pending / unclear: held, status unchanged, event `renewal_pending` / `renewal_unclear`.
- A held row is resolved by the service's paid report: Stripe's `payment_intent.succeeded` with metadata `subscription_id` (`raw.subscription_id`): `pg_payment_callback_renewal()` → `pg_subscription_renewal_confirmed()`: same lock (10 s wait; busy → 503 so Stripe resends), only a held row of that driver with the same amount, then the paid path; event `renewed` with the pi id as `remote_event_id`. A row not held (the cron's own off-session payment) is left alone.
- First order or its line gone: not charged, a ladder step as for a refusal.

## What is the renewal order?
- `pg_subscription_renewal_order_write()`: an `incomplete` online order copying the first order's customer, billing and currency (new `reference_code`), its recipient, one line at the stored price, the rest of the amount as `tax_total` (`pg_subscription_renewal_figures()`); then `pg_order_complete()` with the checkout page's receipt and `pg_erp_auto_invoice()`. Stock, membership days, folder access, receipt, campaigns, webhooks and invoice run as for any order; no shipping, discount or commission. Marked only through `subscription_events.order_id`.
- `charge_renewal` gets the first order as `order` (iyzico's buyer and address).

## What is not here yet?
- The three campaign e-mails and the confirmation link mail (slice 4b; `email_campaigns.action` asked in #3288).

## Tested?
- `tests/subscriptions_renewal_test.php` (pure rules, stand-in io: lock, order of writes, one charge). Not run against a database or a live service.
- `tests/payment_stripe_webhook_test.php`: the renewal's `payment_intent.succeeded`.

Related: commerce/subscriptions.md, commerce/subscriptions-stripe.md, plans/subscriptions-iyzico-stripe.md
