---
name: api-customer-subscriptions
description: GET /customers/{id}/subscriptions, the pause/resume/cancel moves with dry_run (PR #4399), and the subscription.* webhooks announced from pg_subscription_mail_send()
---

## What does GET /customers/{id}/subscriptions answer?
- Route `customers.subscriptions`, scope `customers:read`, handler `api_customers_subscriptions()` (`includes/api/resources/customers.php`), object `Subscription` (`api_subscription_schema()`).
- Rows of `subscriptions` whose `user_id` is an account with `user.user_contact` = the customer, newest first, cursor paged by id. Product name from the first order's line, else the product. Never `card_ref`, `remote_id` or `driver`.
- `actions`: `pg_subscription_customer_actions()` minus `update_card` (the card page needs the customer's browser), so the same moves My Account offers.
- Legacy gateway profiles (`recurring_payment_*` columns) are not listed. No table → empty list.

## How do the moves work?
- `POST /customers/{id}/subscriptions/{subscription_id}/pause|resume|cancel`, scope `customers:write`, `dry_run`.
- `api_customer_subscription_move_check()`: row rule (`api_customer_reachable_or_fail()`), the row read with the customer's accounts (another customer's = 404), refusal words from `pg_subscription_operator_refusal()` as 409 `subscription_move_refused`. Then `api_dry_run_stop()`, then `pg_subscription_store_move()` with `pg_subscription_customer_io()`: same event, mail, card forgetting and webhook as My Account and the operator screen. A move lost to a concurrent one: the same 409.
- History detail `api: <application name> (#<app id>)` (`api_subscription_move_detail()`).

## When is a subscription.* webhook sent?
- `pg_subscription_mail_send()` (`includes/fn/subscription_renewal.php`) is the one place every written event passes (status writer, renewal run, a PSP's paid report); it calls `pg_subscription_announce()` when `event_id` > 0, before the mail decision, so a webhook delivered twice announces once.
- `pg_subscription_webhook_event()`: renewed → `subscription.renewed`; renewal_failed, confirm_needed → `subscription.failed` (status ended when the ladder ran out); status_paused → `.paused`; status_active from paused → `.resumed`; status_cancelled → `.cancelled`. Not announced: status_ended (the cancel or the last failure already was), status_active after a paid retry (the renewed event), price_notice, card_update_opened.
- Payload `pg_subscription_webhook_data()`: id, customer_id (null without a customer record), product_id, status, previous_status, kind, order_id (renewal order or null), amount, currency, next_charge_at (UTC), retry_count.

## Tested?
- `tests/api_customer_subscriptions_test.php` (SQL, presenter, detail, routes and the stop order, event names, payload); `tests/api_customers_test.php` counts 12 customers routes. Not run against a database or a live service.

Related: commerce/subscriptions-account.md, api/api-webhook-events.md, api/api-dry-run.md, commerce/subscriptions-decisions.md
