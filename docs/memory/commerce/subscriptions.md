---
name: subscriptions
description: Store-run subscriptions (11.0.128, PR #3236): the two tables, recurring mode and store-mode driver operations, status moves, period dates, 1/3/7 ladder, sealed card_ref
module: subscriptions
---

## What is in place?
- Tables (step 11.0.128 `upgrade_2026_11_0_subscriptions()`, both in `get_tables()`; schema as decided in #3183, `workflow/decisions-made-schema-5.md`): `subscriptions` (`user_id`, `product_id`, `order_id` = first order, `order_item_id`, `driver`, `remote_id` '' in store mode, `status`, `period`, `amount` minor units, `currency`, `next_charge_at`, `retry_count`, `card_last4`, `card_ref` sealed, `created_at`, `updated_at`, `cancelled_at`, `ended_at`; unix seconds) and `subscription_events` (`kind`, `order_id`, `amount`, `remote_event_id` NULL + `uk_subscription_event_remote`, `detail`, `created_at`). The same step appends `subscription_renewed`, `subscription_failed`, `subscription_cancelled` to `email_campaign_profiles.action`. `email_campaigns.action` is not widened yet: the mail slice needs it, as `order_delivered` needed 11.0.42 after 11.0.20.
- Module `includes/fn/subscriptions.php`, readiness `pg_subscriptions_ready()`. The checkout calls `pg_subscription_checkout_create()` (iyzico, `subscriptions-iyzico.md`); `pg_subscription_create_from_order_line()` returns the row an order line already has.

## How does a driver say it runs subscriptions?
- `capabilities()` returns `'recurring' => 'store'` (or `'psp'`, or `array('mode' => …)`); a bare `'recurring'` is dropped by `pg_payment_capabilities_normalize()`. `pg_payment_recurring_mode($key)` reads it.
- Store-mode operations in `pg_payment_operations()`, one argument each, the row from `pg_subscription_driver_row()` (card_ref opened in memory only): `charge_renewal` (paid, pending, or redirect = a confirmation link the customer must open), `update_card` (redirect to the PSP's hosted page), `subscription_cancel` (cancelled; forgets the card at the PSP). `cancel()` stays the order operation. Undefined = not supported (`pg_payment_call()` failure).
- The first payment answers the stored card's token as `card_ref` in `pg_payment_answer()`; at most 150 printable characters, so its sealed form fits VARCHAR(255) (150 → 241).

## Which statuses and moves?
- `active` → past_due, paused, cancelled, ended; `past_due` → active, paused, cancelled, ended; `paused` → active, cancelled, ended; `cancelled` → ended; `ended` final (`pg_subscription_transition_allowed()`).
- `pg_subscription_set_status()` updates `WHERE id AND status = <the status read>`, so a concurrent move wins; back to active resets `retry_count`; cancelled/ended stamp their time once; each move writes event `status_<to>`.

## How are dates and retries computed?
- `pg_subscription_next_charge_at($from, $period, $anchor_day)`: month periods keep the first charge's day and clamp to the month's end (Jan 31 → Feb 28/29 → Mar 31); day periods add calendar days (time of day kept across DST). `pg_subscription_periods()` (twice_monthly = 15 days, as the legacy gateways); `pg_subscription_period_from_legacy()` maps `recurring_payment_period` names.
- Ladder `pg_subscription_retry_delay_days()`: after failure 1, 2, 3 → 1, 3, 7 days; then 0 (ladder over; what follows is the dunning slice's).

## How are card tokens and events kept?
- `pg_subscription_card_ref_seal()` = `encrypt_string_with_iv()` "<ciphertext>:<iv>"; '' without `ENCRYPTION_KEY`. `pg_subscription_row_from_order_line()` refuses a value that is not in the sealed shape, so a plain token is never written.
- Store-side events keep `remote_event_id` NULL (never collide); a webhook id de-duplicates through the unique key (`INSERT IGNORE`, 0 = already there). A renewal order is marked only by `subscription_events.order_id`.
- Amount of a line: `price * quantity + tax_total` (minor units).

## How was it verified?
- `tests/subscriptions_test.php`; the step was not run on a dev DB (none in the cloud session).

Related: commerce/subscriptions-iyzico.md, commerce/payment-drivers.md, plans/subscriptions-iyzico-stripe.md
