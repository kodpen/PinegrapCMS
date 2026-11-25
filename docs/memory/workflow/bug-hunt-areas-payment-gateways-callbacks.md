---
name: bug-hunt-areas-payment-gateways-callbacks
description: Bug hunt of the payment gateways' return and notify endpoints (area 49, 2026-11-24): repeated messages, a message before the return, amounts; what it fixed and read clean
---

## Which area was hunted?
- Area 49: `payment_callback.php`, `includes/payments/callback.php`, the drivers' `notify()` (Stripe, PayPal Checkout, PayTR) and `complete()` (Stripe, PayPal Checkout), the return branch of `submit_order.php`, the gateway return in `order_preview.php` and `express_order.php`, `pg_order_complete_from_payment()`.
- Looked at: a message sent twice or with the return at the same moment, a message before the return, a message without any return, refund or dispute before the payment message, amount and currency against the order.
- Rules checked against: `commerce/payment-callback.md`, `order-completion-from-payment.md`, `order-completion-shipping-less.md`, `subscriptions-completion-paths.md`.

## What did the hunt's PR fix? (#11472)
- A Stripe message that completed the order before the visitor came back from 3-D Secure: the return opened a new empty order and showed "billing info is incomplete" or "There are no items" to a visitor who had paid. Now it leads to the receipt (`commerce/payment-return-after-message.md`).
- The return and the message at the same moment lowered the shipping recipient twice: `submit_order.php` used a relative decrement after the driver's `complete()` call. Now a compare-and-set like the message's (`commerce/order-completion-shipping-less.md`).

## What was read clean?
- A message sent twice: `pg_order_complete()`'s lock and status check, `pg_order_mark_paid()`'s `paid_at = 0` condition, the subscription lock.
- Amount and currency: Stripe and PayTR compare amount and `raw.currency` with the order (`pg_payment_notify_matches_order()`); PayPal's amount is null in another currency, so a pending capture is never recorded for it. Installments only with two or more (`pg_payment_paid_totals()`).
- Another shop's payment on the same account (transaction id differs: `unknown_order`); a payment on a cancelled order alerts; refund and dispute change nothing and alert; PayTR's failed payment is answered OK and ignored.
- Signatures: Stripe's HMAC and tolerance, PayPal's verify call (unclear answers 500, retried), PayTR's hash with `hash_equals()`.

## What was left, unverified?
- A card payment Stripe takes without a redirect writes no transaction id on the order before `pg_order_complete()`. If Stripe's message arrives inside that short window, the order is incomplete with an empty transaction id and the message alerts "not for its own payment" although the checkout completes it a moment later. A false alert, no money effect; not seen in a log, so no fix.

## What was not run?
- No database, no Stripe, PayPal or PayTR sandbox delivery.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/payment-callback.md
