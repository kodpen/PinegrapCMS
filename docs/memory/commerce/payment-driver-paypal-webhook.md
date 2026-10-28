---
name: payment-driver-paypal-webhook
description: PayPal Checkout's notify(): webhook_id, PayPal's verify-webhook-signature check, CAPTURE.COMPLETED records an order awaiting payment, DENIED alerts
---

## What does PayPal send, and where?
- `payment_callback.php?driver=paypal` (the "Webhook Address" on the driver's settings). The store adds it to its REST app with the events Payment capture completed and Payment capture denied, and stores the webhook's ID in the optional `webhook_id` field (`connect_apps` `payment_paypal` options).
- Only these matter because a capture is made in the visitor's return (`complete()`); the webhook only settles a capture PayPal left PENDING (`payment-driver-paypal.md`).

## How is a message checked?
- `pg_payment_paypal_notify_with()`: token, then POST `/v1/notifications/verify-webhook-signature` with the five `paypal-*` headers (`auth-algo`, `cert-url`, `transmission-id`, `-sig`, `-time`), `webhook_id` and the event. The event is inserted into that body byte for byte (`pg_payment_paypal_verify_body()`): decoding and encoding it again changes escaped slashes and numbers and the check fails.
- No ID stored, a header missing, PayPal's 4xx or `verification_status` other than SUCCESS → failure, 400 `refused`. Token or check not reached, or a 5xx → unclear, 500, so PayPal sends it again.

## What does each event do?
- `PAYMENT.CAPTURE.COMPLETED` → `paid`; `PAYMENT.CAPTURE.DENIED` → `cancelled` (log line "DENIED: nothing was taken. Do not ship it"); every other event and an id that is not PayPal's shape → ignored (200).
- The order: `custom_id` when it is an order id, else the capture id kept as `orders.transaction_id`; a capture other than the order's own is `unknown_order`. `amount` in hundredths only when the currency is the store's.
- `pg_payment_notify_outcome()` (`payment-callback.md`): `paid` for an order `pg_order_payment_pending()` and `amount` = `orders.total` → `recorded`, `mark_paid`; the receiver calls `pg_order_mark_paid($id, 0)` ("marked paid automatically", the auto invoice's paid moment). Another amount → alert, nothing recorded. Already paid → `nothing`. DENIED → alert, the order is not changed (the operator cancels it).

## How was it verified?
- `tests/payment_paypal_webhook_test.php` (fixture `tests/fixtures/paypal/webhook_capture_completed.json`, shaped from PayPal's webhook event and verify-signature docs). No real PayPal delivery or signature check ran.

Related: docs/memory/commerce/payment-driver-paypal.md, docs/memory/commerce/payment-callback.md, docs/memory/erp/erp-awaiting-transfer-orders.md
