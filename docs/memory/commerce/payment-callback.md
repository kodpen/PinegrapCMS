---
name: payment-callback
description: payment_callback.php?driver=<key> (PR #2143): what reaches notify(), how the order is found, what each answer does (alerts; records a pending payment), status codes, firewall entry
---

## What is it?
- A root endpoint a payment service's servers post to; `includes/payments/callback.php` holds the logic. No session; the driver's signature check is the only credential. POST only (405), a valid key whose driver defines `notify` (404), body up to 1 MB (413).
- Headers come from `$_SERVER` by lowercase dashed name (`pg_payment_request_headers()`), not `getallheaders()`.
- The address: `pg_payment_callback_url($key)`; every driver with `notify()` shows it read-only on its settings fields ("Webhook Address").

## How is the order found?
- `order_reference` all digits → `orders.id`; otherwise it is the payment id kept as `orders.transaction_id` (`pg_payment_notify_order_terms()`). An order whose own transaction id differs from the message's is treated as unknown (another shop on the same service account).

## What does each answer do?
- `pg_payment_notify_outcome()` (pure) → http, status, log, alert:
  - not signed, unreadable, no secret: 400 `refused`; driver threw: 500 `error` (the service retries); `raw.ignored`: 200 `ignored`; no matching order: 200 `unknown_order`.
  - `paid` for an order placed with its payment pending (`pg_order_payment_pending()`, PayPal Checkout) at `orders.total`: 200 `recorded`, `mark_paid`, the receiver runs `pg_order_mark_paid()`; another amount alerts (`payment-driver-paypal-webhook.md`).
  - Otherwise `paid`/`authorized` for an order not `incomplete`: 200 `nothing`. For an `incomplete` one: the driver's line + "… still incomplete. The order was not completed …".
  - `refunded`, `cancelled`, `pending` (Stripe's dispute): the line + "The order was not changed."
- An `alerted` line goes to `log_activity(…, 'SYSTEM')` and to `pg_ops_alert('payment_notice', …)`; the alert keeps its once-a-day rule, the log line is written every time.

## Why alert only?
- Decided (#2382): the webhook will complete an incomplete order, once the completion tail of `submit_order()` runs without a session (`plans/order-completion-function.md`, A2/A3). Until then an incomplete order is not changed; the only order write is the pending payment above.
- Since #2199 a Stripe order sent to 3-D Secure already keeps the PaymentIntent in `transaction_id` and the started amount in `total` (`pg_payment_started_order_values()`), so the order is found by its own payment and another intent is `unknown_order`.

## Firewall?
- In `waf_is_api_request()` and `waf_country_api_scripts()` (service servers, any country). No offence on a bad signature: a wrong secret stored here would ban the service.

## How was it verified?
- `tests/payment_stripe_webhook_test.php`, `tests/payment_paypal_webhook_test.php`. No real Stripe or PayPal delivery.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-driver-stripe.md, docs/memory/platform/ops-alerts.md
