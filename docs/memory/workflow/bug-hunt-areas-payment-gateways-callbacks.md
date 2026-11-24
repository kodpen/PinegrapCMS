---
name: bug-hunt-areas-payment-gateways-callbacks
description: Bug hunt of the payment gateways' return and notify endpoints (area 49, 2026-11-24): repeated messages, a message before the return, amounts; what it fixed and read clean
---

## Which area was hunted?
- Area 49: `payment_callback.php`, `includes/payments/callback.php`, the drivers' `notify()` (Stripe, PayPal Checkout, PayTR) and `complete()` (Stripe, PayPal Checkout), the return branch of `submit_order.php`, the gateway return in `order_preview.php` and `express_order.php`, `pg_order_complete_from_payment()`.
- Looked at: a message sent twice or with the return at the same moment, a message before the return, a message without any return, refund or dispute before the payment message, amount and currency against the order.
- Rules checked against: `commerce/payment-callback.md`, `order-completion-from-payment.md`, `order-completion-shipping-less.md`, `subscriptions-completion-paths.md`.

## What did the hunt's PR fix?
- Being written.

## What was read clean?
- Being written.

## What was not run?
- No database, no Stripe, PayPal or PayTR sandbox delivery.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/payment-callback.md
