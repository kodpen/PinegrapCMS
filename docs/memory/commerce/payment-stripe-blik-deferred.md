---
name: payment-stripe-blik-deferred
description: BLIK on the Stripe checkout: not in the deferred Payment Element, so the checkout draws its own code field, authorize() confirms with the code and a waiting page follows (PR #12123)
module: payments
---

## Why is BLIK not in the Payment Element?
- Stripe (read 2026-11-27): "Finalize payments on the server" says "This integration path doesn't support BLIK"; payment method support says "BLIK doesn't support the deferred intent creation integration path". The checkout is that path (Payment Element in deferred mode, ConfirmationToken, intent made and confirmed in `authorize()`, `payment-driver-stripe.md`).
- Architect (2026-11-27): the checkout draws BLIK's code field itself (option B); intent-first was too large a change.

## What does BLIK ask of the page?
- PLN only; no manual capture, no `setup_future_usage`. A 6-digit code from the bank app, valid 2 minutes, sent only in the confirm call (`payment_method_options[blik][code]`).
- After confirm: `requires_action`, `next_action.type` `blik_authorize`; the buyer approves in the bank app within 60 seconds. Then `succeeded`, or `requires_payment_method` (+ `payment_intent.payment_failed`).
- Decline codes such as `code_invalid` come only from API version `2026-09-30`; the driver's `2024-06-20` gets `generic_decline` (timeout: code `payment_method_provider_timeout`).

## How is it built?
- Row `blik` of `pg_payment_stripe_methods()`: PLN, `immediate`, no `hold`, `step` `page`. `pg_payment_stripe_method_types()` skips `page` types, so BLIK is never in `allowedPaymentMethodTypes`. Switch `method_blik` on Site Settings › Commerce, "Payment methods".
- `pg_payment_stripe_blik_offered($currency, $settings, $recurring)`, pure: switch on, PLN, not Authorize, not recurring. `prepare()` answers `client.blik` only when the order has `page_step` (set by `_eo_hosted_card_html()`: checkout pages and the cart widget); form payments and booking deposits never get it.
- `_eo_hosted_card_html()` passes `_eo_hosted_blik_choice_html()` to `pg_payment_box()` (new `$choice`), drawn above the element: radios "Card" / "Card and other methods" and "BLIK", code field `payment_blik_code` (`inputmode="numeric"`, `autocomplete="one-time-code"`), disabled until BLIK is chosen. `payment_hosted.js` hides the element, posts the code without a token, refuses a code that is not 6 digits.
- `submit_order.php` and `express_order.php` take the code with `pg_payment_posted_secret()` before `liveform::add_fields_to_session()` (which reads `$_REQUEST`), so it is never in the session; it goes to `authorize()` as `blik_code`.
- `pg_payment_stripe_blik_authorize_with()`: body `payment_method_types` `[blik]`, `payment_method_data` (type, billing e-mail), the code, metadata, description, expand; Idempotency-Key `pg-pi-blik-` + sha1(order|code|units). Pinned in `tests/payment_stripe_methods_test.php`.
- `blik_authorize` with a client secret of this intent → answer `redirect` with `html` = `pg_payment_stripe_blik_step_html()` (no `redirect_url`), state as a 3-D Secure return. `submit_order.php` echoes it; no order is placed. The page counts 60 s, `stripe.handleNextAction({clientSecret})` waits, then goes to the `stripe_return` address; a Continue link and a 90 s fallback do the same.
- Return: `complete()` reads the intent: `succeeded` → paid; `requires_payment_method` → `pg_payment_stripe_blik_error()` (not the card sentences); still `blik_authorize` → voided, "not approved in time".

## Not verified?
- No Stripe call (no test keys) and no browser run: the first live try (any code works in a sandbox, failures by billing e-mail patterns) is the owner's.

Related: commerce/payment-stripe-method-list.md, commerce/payment-driver-stripe.md, commerce/payment-stripe-later-result.md
