---
name: payment-driver-paypal
description: PayPal Checkout on Orders v2 (method 'PayPal Checkout', 11.0.39, PR #1827): redirect, read and capture in complete(), refund and cancel by capture id, no webhook; classic Express Checkout untouched
---

## What is it?
- `includes/payments/paypal.php`, key `paypal`, redirect driver for the payment method `PayPal Checkout` (appended to the `orders.payment_method` ENUM by 11.0.39). `PayPal Express Checkout` stays the classic NVP code in submit_order.php.
- `connect_apps` `payment_paypal`: `client_id`, `client_secret` (sealed), `mode` (`live` default, `sandbox`); switch on the Ecommerce card's redirect rows. Return mode `paypal_return`.
- No SDK: OAuth client-credentials token per operation, then `api-m.paypal.com` / `api-m.sandbox.paypal.com` with `Prefer: return=representation`. Each operation has a `*_with($…, $settings, $http)` twin; `$http($method, $url, $headers, $body)`.

## How does a payment go?
- `authorize()`: POST `/v2/checkout/orders`, intent CAPTURE, one purchase unit (`custom_id` = order id, `description` = "Order <reference_code>", amount only, no items), `payment_source.paypal.experience_context` with return_url = cancel_url = the checkout return address, PAY_NOW, NO_SHIPPING, locale. Redirect to the `payer-action` (or `approve`) link; state `paypal_order`, `order_id`, `amount`.
- `complete()`: the return's query (`token`, `PayerID`) is not read. GET the order; id, `custom_id`, amount and currency must match the state. APPROVED → capture (`PayPal-Request-Id: pg-capture-<id>`, so a reload repeats nothing); a total changed since approval → not captured, nothing taken. COMPLETED (lost first answer) → read its capture. CREATED / PAYER_ACTION_REQUIRED / VOIDED → "cancelled". Capture COMPLETED → paid, PENDING → pending; DECLINED, FAILED, 422 INSTRUMENT_DECLINED → refused. A capture for another amount is refunded in full.
- `transaction_id` = the capture id. Transaction Type Authorize is not followed: always CAPTURE.
- The wait (session state) goes on when the return was not this checkout's, PayPal was not reached, or the capture answer may hide a capture (5xx, not JSON, no capture in a 2xx; `pg_payment_paypal_capture_unclear()`); only PayPal's own refusal ends it. A retry from the checkout page runs `authorize()` (a new PayPal order), not `complete()`.
- A PENDING capture (PENDING_REVIEW, eCheck) places the order with a log line naming `status_details.reason` and "Check it on PayPal before shipping"; nothing tells the store if PayPal later denies it (no `notify()`). Whether to keep that is an open owner question on PR #1827.

## Refund and cancel?
- `refund()`: POST `/v2/payments/captures/<capture>/refund` with the amount; request id from order, capture, amount and `refunded_amount` (a double click is one refund). COMPLETED → refunded, PENDING → pending; unreached → unclear. `cancel()`: the same call with `{}` (what is left of the capture). Log name `PayPal`.

## Limits?
- PayPal takes no Turkish lira: `pg_payment_paypal_currencies()`; another store currency is refused at authorize ("PayPal does not take payments in this currency."). The method still shows while switched on.
- HUF, JPY, TWD go in whole units; a fraction is refused.
- No webhook: money moves only in the visitor's return. A visitor who approves and never returns leaves an uncaptured PayPal order that expires; nothing is taken.
- No CSP sources (nothing of PayPal's in the page).

## How was it verified?
- `tests/payment_paypal_test.php` against saved answers in `tests/fixtures/paypal/`, shaped from PayPal's Orders v2 and Payments v2 documentation. No live or sandbox account was called: token shape, `payer-action` link, capture representation and error `details[].issue` are as documented, unconfirmed.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-method-lists.md, docs/memory/commerce/payment-refunds.md, docs/memory/plans/payment-driver-layer.md
