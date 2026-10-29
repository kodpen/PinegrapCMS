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
  - Otherwise `paid`/`authorized` for an order not `incomplete`: 200 `nothing`. For an `incomplete` one whose own payment it is, for its total, in the store's currency (`pg_payment_notify_matches_order()`): `complete`, the receiver completes it (`commerce/order-completion-from-payment.md`) and answers `completed` (log line with the order number), `nothing` (another request was first: no line) or `alerted` (refused). Any mismatch: the line + "… not for its own payment, its total or the store's currency. The order was not completed …".
  - `refunded`, `cancelled`, `pending` (Stripe's dispute): the line + "The order was not changed."
- An `alerted` line goes to `log_activity(…, 'SYSTEM')` and to `pg_ops_alert('payment_notice', …)`; the alert keeps its once-a-day rule, the log line is written every time.

## Why complete here? (A3, #3021)
- Decided (#2382): a payment the service confirms after the visitor left completes the order (`order-completion-from-payment.md`). Before #3021 the message only alerted.
- The match: the order keeps the payment id in `transaction_id` (since #2199, written when the visitor is sent away), the answer's `amount` (integer hundredths) equals `orders.total`, its `raw['currency']` (Stripe and PayPal answers carry it since #3021) equals `BASE_CURRENCY_CODE`, any case. iyzico's 3-D Secure page names no payment: its orders still only alert.

## What does the order keep for a later completion? (A3a, #2998; #3021)
- Before the visitor is sent away, `pg_order_payment_started_query()` (pure) writes on the incomplete order: the payment id, `total`, `payment_method`, `subtotal`, `discount`, `tax`, `shipping`, `gift_card_discount`, `surcharge` (minor units, `round()`) and, behind `pg_schema_has()`, `checkout_page_id` (11.0.120) and `legal_acceptance` (11.0.121; JSON from `pg_order_legal_acceptance_json()` while the documents are on, else NULL).

## Firewall?
- In `waf_is_api_request()` and `waf_country_api_scripts()` (service servers, any country). No offence on a bad signature: a wrong secret stored here would ban the service.

## How was it verified?
- `tests/payment_stripe_webhook_test.php`, `tests/payment_paypal_webhook_test.php`. No real Stripe or PayPal delivery.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-driver-stripe.md, docs/memory/platform/ops-alerts.md
