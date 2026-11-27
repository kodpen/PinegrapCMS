---
name: payment-stripe-blik-deferred
description: Why BLIK cannot join the Stripe method list as LP1/LP2 methods did: Stripe does not offer it on the deferred intent path (Payment Element, ConfirmationToken, server confirm) the checkout uses
module: payments
---

## What does Stripe's documentation say? (read 2026-11-27)
- "Finalize payments on the server": "This integration path doesn't support BLIK". Payment method support: "BLIK doesn't support the deferred intent creation integration path", and the Payment Element column marks BLIK "Not supported when collecting payment details before creating a PaymentIntent".
- The checkout is that path: the Payment Element is drawn in deferred mode, `payment_hosted.js` makes a ConfirmationToken, `authorize()` creates and confirms the intent in one call (`payment-driver-stripe.md`).

## What does BLIK ask of the page?
- PLN only; no manual capture, no `setup_future_usage` (one payment only).
- The buyer types a 6-digit code from the bank app (valid 2 minutes); it goes only in the confirm call as `payment_method_options[blik][code]` with `payment_method_data[type]=blik`.
- After confirm the intent is `requires_action`, `next_action.type` `blik_authorize`: the buyer approves in the bank app within 60 seconds; Stripe suggests a timer or dialog. Stripe.js (`handleNextAction` with the client secret, or `confirmBlikPayment`) waits for the result; then `succeeded`, or `requires_payment_method` with `payment_intent.payment_failed`.
- Decline codes (`code_invalid`, `code_expired`, `insufficient_funds`, `customer_declined`, `payment_limit_exceeded`, `partner_generic_decline`) come only from API version `2026-09-30`; the driver's `2024-06-20` gets `generic_decline`.
- The client secret must not be logged or put in a URL.

## What would it take?
- Either the intent made before the page (client secret on the page, client-side confirm), or a code field the site draws and a server confirm with the code, then a waiting step. Both change the checkout's flow, so step LP3 of `plans/payment-local-methods.md` waits on the architect's choice.
- No method in the list today needs a non-redirect next step: the redirect methods answer `redirect_to_url`, SEPA answers `processing`.

Related: commerce/payment-stripe-method-list.md, commerce/payment-driver-stripe.md, commerce/payment-stripe-later-result.md
