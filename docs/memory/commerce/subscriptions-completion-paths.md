---
name: subscriptions-completion-paths
description: Which order completion makes a store-run subscription (the visitor's return or the payment service's message, under one lock), and what a renewal payment no row waits for does (#11422)
module: subscriptions
---

## Which completion makes the subscriptions? (#11422)
- `pg_subscription_checkout_record($order_id, $driver, $answer, $lines = null)` (`includes/fn/subscriptions.php`; lines read with `pg_order_lines_sql()` when null; a line that got none is logged; nothing without a `card_ref`).
- Called from three places: `submit_order.php` after it completed the order; `submit_order.php` when `pg_order_complete()` answered null because the payment service's message completed the order first; `pg_payment_callback_receive()` after `pg_order_complete_from_payment()` completed it.
- The message's card: Stripe's order `payment_intent.succeeded` answers `card_ref` when the intent kept its card (`setup_future_usage` off_session, `pg_payment_stripe_intent_card_ref()`). iyzico's message carries none, so only its return makes them.
- `pg_subscription_checkout_create()` runs under `GET_LOCK('pg_subscription_checkout_<order id>', 10)`: the table has no unique key on the line, and the return and the message can arrive together. The second finds the first's rows (`pg_subscription_create_from_order_line()`).

## What went wrong before?
- Only the request that completed the order made the rows. When Stripe's message won (3-D Secure, or the visitor closed the page), no subscription was made: one charge, never renewed.

## What does a renewal payment no row waits for do? (#11422)
- `pg_subscription_renewal_confirmed()` answers `unrecorded` for a row of that driver that is not held and whose renewals hold no such payment (io `recorded`: a `renewed` event with the pi id, or a renewal order with it as `transaction_id`). `pg_payment_callback_renewal()` logs it and sends a `payment_notice` alert; nothing is written. Otherwise `nothing`, as before.
- Symptom fixed: a row cancelled while it waited for the customer's confirmation, then confirmed on Stripe's page, took the money with no order and no log line.
- The cron's own paid charge is never flagged: its order is written before the lock is let go, and the message waits for that lock.
- A held row that never gets an outcome: `subscriptions-held-renewal.md`.

## Tested?
- `tests/subscriptions_test.php`, `tests/payment_stripe_webhook_test.php`, `tests/subscriptions_renewal_test.php` (stand-in io and source checks). No database, no Stripe test-mode run.

Related: docs/memory/commerce/subscriptions.md, docs/memory/commerce/subscriptions-renewal.md, docs/memory/commerce/subscriptions-stripe.md
