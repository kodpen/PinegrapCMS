---
name: payment-local-methods
description: Plan (owner 2026-11-27, poll 246): the Stripe driver offers local payment methods and pay-later (iDEAL, Bancontact, SEPA debit, BLIK, Klarna) beside cards; steps LP1 to LP5
priority: 5
---

## What is asked?
- Owner (2026-11-27, poll 246, `workflow/decisions-made-product-117.md`): local payment methods and pay-later through Stripe. The list it belongs to and its rules: `plans/global-features.md`.

## What stands today? (count of 2026-11-27, `includes/payments/stripe.php`)
- The driver sends `card`, with `link` when its switch is on, at four places: the page's Payment Element, the PaymentIntent, the renewal and the card update. The first two must agree or Stripe refuses (`commerce/payment-driver-stripe.md`).
- The charge is in the site's base currency, so a method is offered only where that currency is its own (iDEAL and SEPA in euro, BLIK in zloty).
- A next step is followed only when it is a redirect; any other is refused. An intent still `processing` is stored as paid at once, and a later failure is ignored for an order: only PayPal has a waiting state (`pg_order_payment_pending()`).

## Which steps? (M about 1,000 product lines, S under 400; each waits for the one before it)
- LP1 The method list and the redirect methods: done (#12059). One list in the driver names each method; Site Settings › Commerce, Stripe has the group "Payment methods" (`commerce/payment-stripe-method-list.md`).
- LP2 A result that comes later, and SEPA Direct Debit: done (#12087, `commerce/payment-stripe-later-result.md`).
- LP3 (S) BLIK, drawn by the site: Stripe's deferred Payment Element cannot offer it (`commerce/payment-stripe-blik-deferred.md`).
  - With zloty and its switch on, the checkout offers "BLIK" beside the Payment Element with a six-digit code field; `authorize()` creates and confirms the intent with the code.
  - On `blik_authorize` the page shows a waiting step (about a minute, Stripe.js reads the result), then goes on as the return does today, or back to the form with the reason.
  - No order before the approval; the code is never stored or logged; the other methods keep their path.
- LP4 (S) The method named. The order screen, the order's mail and the API's order say which method paid (read from the intent), stored where the card's brand is stored today; `orders.payment_method` keeps Stripe's value. If no stored place fits, the step stops and asks the architect: a new value or column is the owner's.
- LP5 (S) Close-out: `commerce/payment-driver-stripe.md` corrected, new `commerce/payment-stripe-local-methods.md`, this file deleted.

## What does every step keep?
- No library, no table, no column, no new value of the payment method list (`commerce/payment-driver-layer-decisions.md`).
- Each request body is pinned by a unit test. A try against Stripe needs test keys: with none in the session the PR says so, and the first live try is the owner's.
- The site's Content Security Policy is checked for each method's redirect.

## What is left out?
- A second global provider (Mollie, Adyen): a new gateway is a new value of the stored list, so it is asked of the owner on its own.
- Pay-later messages on product pages, and a local method saved for renewals.
