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
- LP1 (M) The method list and the redirect methods. One list in the driver names each method: Stripe's type, its currencies, whether its result comes at once. A pure function gives the types for a currency and the driver's settings; the Payment Element and the PaymentIntent both call it.
  - Site Settings › Commerce, Stripe: a group "Payment methods" of switches, one a method, each with a line naming its currencies; all off at first, so a site changes nothing until its owner turns one on. A method whose currency is not the site's is shown switched off with the reason.
  - First methods, all by redirect: iDEAL, Bancontact, EPS, Przelewy24, Klarna, Afterpay / Clearpay. An order that repeats, the renewal and the card update keep cards only.
  - Tests: the types by currency and switches, the page and the intent agreeing, a repeating order.
- LP2 (M) A result that comes later. An intent `processing` at the return stores the order as waiting for its payment, the way PayPal's pending does (`commerce/payment-driver-paypal.md`): `payment_intent.succeeded` marks it paid, and `payment_intent.payment_failed` closes it as PayPal's denied payment is closed. The buyer's page and mail say the payment is being confirmed. Then SEPA Direct Debit joins the list.
- LP3 (S) A step shown on the page. The checkout lets Stripe's script show a next step it can show itself, not only a redirect; what it cannot show is refused as today. BLIK joins the list. The worker reads Stripe's current documentation for what BLIK asks of the page before building.
- LP4 (S) The method named. The order screen, the order's mail and the API's order say which method paid (read from the intent), stored where the card's brand is stored today; `orders.payment_method` keeps Stripe's value. If no stored place fits, the step stops and asks the architect: a new value or column is the owner's.
- LP5 (S) Close-out: `commerce/payment-driver-stripe.md` corrected, new `commerce/payment-stripe-local-methods.md`, this file deleted.

## What does every step keep?
- No library, no table, no column, no new value of the payment method list (`commerce/payment-driver-layer-decisions.md`).
- Each request body is pinned by a unit test. A try against Stripe needs test keys: with none in the session the PR says so, and the first live try is the owner's.
- The site's Content Security Policy is checked for each method's redirect.

## What is left out?
- A second global provider (Mollie, Adyen): a new gateway is a new value of the stored list, so it is asked of the owner on its own.
- Pay-later messages on product pages, and a local method saved for renewals.
