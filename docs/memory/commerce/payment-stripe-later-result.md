---
name: payment-stripe-later-result
description: A Stripe PaymentIntent still processing at the return places the order awaiting payment; payment_intent.succeeded records it, payment_failed alerts; SEPA Direct Debit uses it
module: payments
---

## When is a Stripe order placed unpaid?
- The return answers `pending` when the intent's status is `processing` (`pg_payment_stripe_intent_answer()`). The order is written with `paid_at = 0` and reads as awaiting payment, the way a PayPal PENDING capture does.

Related: commerce/payment-stripe-method-list.md, commerce/payment-driver-stripe.md, commerce/payment-driver-paypal.md
