---
name: payment-driver-paypal
description: PayPal Checkout on Orders v2 (method 'PayPal Checkout', 11.0.50): redirect, capture in complete(), a PENDING capture placed awaiting payment, refund and cancel by capture id, no webhook yet
---

## What is it?
- `includes/payments/paypal.php`, key `paypal`, redirect driver for the payment method `PayPal Checkout` (appended to the `orders.payment_method` ENUM by 11.0.50). `PayPal Express Checkout` stays the classic NVP code in submit_order.php.
- `connect_apps` `payment_paypal`: `client_id`, `client_secret` (sealed), `mode` (`live` default, `sandbox`); switch on the Ecommerce card's redirect rows. Return mode `paypal_return`.
- No SDK: a client-credentials token per operation, then `api-m(.sandbox).paypal.com`, `Prefer: return=representation`. Each operation has a `*_with($…, $settings, $http)` twin for tests.

## How does a payment go?
- `authorize()`: POST `/v2/checkout/orders`, intent CAPTURE, one purchase unit (`custom_id` = order id, amount only), return_url = cancel_url = the checkout return address, PAY_NOW, NO_SHIPPING. Redirect to the `payer-action` (or `approve`) link; state `paypal_order`, `order_id`, `amount`.
- `complete()`: GET the order (the return's query is not read); id, `custom_id`, amount and currency must match the state. APPROVED → capture (`PayPal-Request-Id: pg-capture-<id>`, so a reload repeats nothing); a total changed since approval → not captured, nothing taken. COMPLETED (lost first answer) → read its capture. CREATED / PAYER_ACTION_REQUIRED / VOIDED → "cancelled". Capture COMPLETED → paid, PENDING → pending; DECLINED, FAILED, 422 INSTRUMENT_DECLINED → refused. A capture for another amount is refunded in full.
- `transaction_id` = the capture id. Transaction Type Authorize is not followed: always CAPTURE.
- The wait (session state) goes on when the return was not this checkout's, PayPal was not reached, or the capture answer may hide a capture (5xx, not JSON, no capture in a 2xx; `pg_payment_paypal_capture_unclear()`); only PayPal's own refusal ends it. A retry from the checkout page runs `authorize()` (a new PayPal order), not `complete()`.
- A PENDING capture (PENDING_REVIEW, eCheck) places the order unpaid: `pg_order_checkout_paid_at()` writes `paid_at = 0`, so `pg_order_awaiting_payment()` / `pg_order_payment_pending()` (`includes/fn/ecommerce.php`) read it as awaiting payment: list badge, order screen banner "Payment pending on PayPal" (check before shipping), Payment Received records it (`erp/erp-awaiting-transfer-orders.md`); the auto invoice waits for that moment. Nothing learns of a later denial until PayPal's webhooks arrive (plan 4b follow-up).
- Only methods in `pg_order_pending_payment_methods()`: another method's older paid orders may have `paid_at = 0`. Stripe `processing` keeps `paid_at` as before.

## Refund and cancel?
- `refund()`: POST `/v2/payments/captures/<capture>/refund` with the amount; request id from order, capture, amount and `refunded_amount` (a double click is one refund). COMPLETED → refunded, PENDING → pending; unreached → unclear. `cancel()`: the same call with `{}` (what is left of the capture). Log name `PayPal`.

## Limits?
- PayPal takes no Turkish lira: `pg_payment_paypal_currencies()`; another store currency is refused at authorize. The method still shows while switched on.
- HUF, JPY, TWD go in whole units; a fraction is refused.
- No webhook: money moves only in the visitor's return. A visitor who approves and never returns leaves an uncaptured PayPal order that expires; nothing is taken.

## How was it verified?
- `tests/payment_paypal_test.php` against `tests/fixtures/paypal/`, shaped from PayPal's Orders v2 / Payments v2 docs; `tests/order_payment_pending_test.php`. No live or sandbox account was called, no browser round trip ran.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-method-lists.md, docs/memory/commerce/payment-refunds.md, docs/memory/plans/payment-driver-layer.md
