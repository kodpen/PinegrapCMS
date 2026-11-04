---
name: paypal-express-return-query
description: How the classic PayPal Express Checkout return in submit_order.php reads token and PayerID: one string each, an array or a missing one empty (#5270)
---

## How does the PayPal Express return read its query string?
- `submit_order.php`, `mode=paypal_express_checkout_return`, before `DoExpressCheckoutPayment`: `token` and `PayerID` are read as one string each; an array or a missing value is `''`, like `page_id` at the top of `submit_order()`.
- An empty token goes to PayPal, which refuses it; the refusal shows on the checkout page through the usual `paypal_express_checkout` error.

## What was the symptom before #5270?
- Both went straight into `urlencode()`: `token[]=` threw a TypeError on PHP 8 (a blank error page), a missing one passed `null` (deprecated from PHP 8.1).
- Test: `tests/submit_order_paypal_return_test.php`.

Related: docs/memory/commerce/payment-driver-paypal.md
