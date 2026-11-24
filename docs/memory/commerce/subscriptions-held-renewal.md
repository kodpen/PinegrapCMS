---
name: subscriptions-held-renewal
description: How a held subscription renewal (confirmation, pending or unclear charge) ends: Stripe's payment_intent.payment_failed puts it on the 1/3/7 ladder, no outcome in 7 days ends it (#11427, PR #11448)
module: subscription_renewal
---

## What is a held renewal?
- A row the cron left with `next_charge_at` 0 while it waits for the service: `confirm_needed` (Stripe `authentication_required`, past_due), `renewal_pending`, `renewal_unclear` (status unchanged). `pg_subscription_renewal_is_held()`. Paid report: `subscriptions-renewal.md`.

## What does a failure report do?
- Stripe's `payment_intent.payment_failed` with metadata `subscription_id` (no `order_id`) is answered by `notify()` as a failure with `raw.renewal_failed`, `raw.ignored` (acknowledged 200).
- `pg_payment_callback_renewal_failed()` → `pg_subscription_renewal_held_failed()`: lock (10 s; busy → 503, Stripe resends), only a held row of that driver, then the ladder's failed plan: `retry_count` + 1, retry 1/3/7 days later, active → past_due, event `renewal_failed` (Stripe's message), failed mail; after the last retry the row ends with the cancelled mail. A row not held is left alone.
- `last_payment_error.code` `authentication_required` is ignored: it is the cron's off-session refusal that started the confirmation, and may arrive after the row was held.
- The Stripe webhook endpoint must send `payment_intent.payment_failed` too; one that does not still ends by the 7-day rule.

## What if no outcome ever comes?
- `pg_subscription_renewals_run()` calls `pg_subscription_renewal_holds_run()` after the due rows: held rows whose latest hold event (`confirm_needed`, `renewal_pending`, `renewal_unclear` in `subscription_events.created_at`) is `pg_subscription_renewal_hold_days()` (7) days old or more (`pg_subscription_renewal_held_sql()`; no column, no schema change).
- `pg_subscription_renewal_hold_end_row()` under the renewal lock: `ended`, `next_charge_at` 0, event `renewal_failed` ("No outcome of the renewal payment came within 7 days."), so the cancelled mail and the `subscription.failed` webhook go out as at the ladder's last step. Held rows with no hold event are not touched.
- Running sites: rows held over 7 days end on the first run after the update.

## What is not covered?
- A payment Stripe reports as succeeded after the row was put on the ladder or ended is left alone (`pg_subscription_renewal_confirmed()` takes only a held row): staff refund it on Stripe's screen.

## Who decided?
- Owner (2026-11-24): options 1 + 3 of #11427 — payment_failed puts a held row on the ladder at once; no outcome in 7 days ends it as the ladder's last step does. No schema change.

## Tested?
- `tests/subscriptions_renewal_test.php` (held failure, 7-day end), `tests/payment_stripe_webhook_test.php` (the failed event). No DB or Stripe test-mode run.

Related: commerce/subscriptions-renewal.md, commerce/subscriptions-stripe.md, commerce/subscriptions-mails.md
