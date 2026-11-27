---
name: payment-stripe-local-methods
description: Start file for Stripe local payment methods and pay-later (iDEAL, Klarna, SEPA Direct Debit, BLIK...): what binds them, which file holds what, how a method is added
module: payments
---

## What is it?
- The Stripe PaymentIntents driver (`payment-driver-stripe.md`) offers local methods and pay-later beside the card, each switched on by the store on Site Settings › Commerce, Stripe, group "Payment methods". Built in PRs #12059, #12087, #12123, #12161.
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): local payment methods and pay-later through Stripe, one of the global features.

## What binds every change to them?
- No library, no table, no column, no new value of the payment method list: the order's method stays `Credit/Debit Card`, the method that paid is kept in `orders.card_type` (`payment-driver-layer-decisions.md`, `payment-stripe-method-named.md`).
- The charge is in the site's base currency, never converted; a method is offered only where that currency is one of its own.
- The Payment Element's `allowedPaymentMethodTypes` and the intent's `payment_method_types` come from one function and must agree, or Stripe refuses every confirmation.
- Each request body is pinned by a unit test. No live try was made: there were no test keys; the first live try is the owner's.
- The site's Content Security Policy is checked for each new method's next step (redirect hosts, frames).

## Which file holds what?
- The list of methods, their switches, which types are sent, Authorize mode: `payment-stripe-method-list.md`.
- A result that comes later (intent `processing`: order placed awaiting payment, the webhook records it) and SEPA Direct Debit: `payment-stripe-later-result.md`.
- BLIK, drawn by the site beside the deferred Payment Element, with its code field and waiting page: `payment-stripe-blik-deferred.md`.
- The method named on View Order, the order mail and the API's `payment.paid_with`: `payment-stripe-method-named.md`.

## How is a method added?
- A row in `pg_payment_stripe_methods()` (`includes/payments/stripe.php`): label, currencies, `immediate`, `hold`, `step` (`redirect`, `element` or `page`). The switch, the help line and the types sent follow from it.
- A method whose result is not known on return needs `immediate` false (the later-result path). A `page` step needs its own drawing, as BLIK; check first that the deferred Payment Element supports the method.
- Check the method's next step against the security policy, pin its request body in `tests/payment_stripe_methods_test.php`, and its label becomes the name stored in `card_type`.

## What was left out?
- A second global provider (Mollie, Adyen): a new gateway is a new value of the stored list and is asked of the owner on its own.
- Pay-later messages on product pages, and a local method saved for renewals (renewals and card updates send cards only).
