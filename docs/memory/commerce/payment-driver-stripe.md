---
name: payment-driver-stripe
description: Stripe on PaymentIntents (gateway 'Stripe PaymentIntents', 11.0.24, PR #937): Payment Element and ConfirmationToken, 3-D Secure return, refund, cancel, CSP; local methods apart
---

## What is it?
- `includes/payments/stripe.php`, key `stripe`, card driver for the gateway value `Stripe PaymentIntents` (`config.ecommerce_payment_gateway` ENUM, 11.0.24). `Stripe` stays the classic Charges code ("Stripe (classic)").
- `connect_apps` `payment_stripe`: `publishable_key`, `secret_key` and `webhook_secret` (sealed), `link`. Mode from the keys; Transaction Type Authorize → `capture_method=manual`.
- No SDK: `pg_curl_run()`, `Stripe-Version: 2024-06-20`; each operation has a `*_with(…, $settings, $http)` twin for tests.

## How does a card payment go?
- The express order widget draws `_eo_hosted_card_html()` when `pg_payment_hosted_card_driver()` answers; `prepare()` gives script, publishable key, amount (Stripe units), currency, method types, capture method, locale.
- `assets/js/payment_hosted.js` (bundle `payment_hosted.min.js`) moves the box into `[data-pg-eo-cc-fields]`, disables the site's card inputs, mounts the Payment Element (deferred mode), and on submit puts a ConfirmationToken into `payment_token` and calls `requestSubmit()` (the form's handlers run once).
- `stripe.elements()` gets `captureMethod` and `allowedPaymentMethodTypes` from `prepare()`; they must equal the `capture_method` and `payment_method_types` `authorize()` sends, or Stripe refuses every confirmation (Authorize mode, #937); a test compares them.
- `authorize()` creates and confirms the intent for the order total (metadata order id; idempotency key from order + token + amount). succeeded → paid, requires_capture → authorized, processing → pending, `redirect_to_url` → redirect with state, `blik_authorize` → BLIK's waiting page; other next actions are cancelled and refused.
- Return `?mode=stripe_return`: `complete()` reads the intent from the session state, checks order, amount, currency. A changed total voids it (cancel, or full refund once taken); a failed void tells the visitor the store will refund it and logs MANUAL REFUND REQUIRED.
- Return modes: `pg_payment_return_modes()`, `pg_payment_is_return_mode()`, `pg_payment_return_method()`. Classic order preview and express order draw it too (`payment-hosted-classic-pages.md`); no token is refused ("Please enter your card details.").

## Refund and cancel?
- `refund()`: `/v1/refunds` by `payment_intent`, or by `charge` for a classic `ch_` id; Idempotency-Key per order, payment, amount, `refunded_amount`; no answer: unclear. `cancel()`: cancel while not taken, full refund once taken.
- The visitor reads the site's words; Stripe's go to the log.

## Security policy?
- A definition may list `csp` hosts (script-src, frame-src, connect-src; https origins, `https://*.` allowed). `waf_csp_add_payment_sources()` adds the card gateway's to the built-in policy (read from the config row). Stripe's: script `js.stripe.com`, `*.js.stripe.com`; frame those, `hooks.stripe.com`, `link.com`, `*.link.com`; connect `api.stripe.com`, `link.com`, `*.link.com` (Link hosts always listed). A custom policy adds them itself.

## Webhook?
- `notify()` (PR #2143): `Stripe-Signature` checked (HMAC-SHA256 of `t.body`, any `v1`, 300 s; no secret = refused). `payment_intent.succeeded` (metadata `order_id`) → `paid`; `payment_intent.payment_failed` → `cancelled` (pending orders: `payment-stripe-later-result.md`); `charge.refunded` → `refunded`; `charge.dispute.created` → `pending`; else `raw.ignored`. Outcome: `payment-callback.md`.

## What else?
- Webhook completing an incomplete order: `order-completion-from-payment.md`. Apple Pay: domain in the Dashboard.

## How was it verified?
- `tests/payment_stripe_test.php`, `payment_stripe_webhook_test.php`, `payment_stripe_methods_test.php`, `payment_hosted_classic_test.php`; no account.

Related: commerce/payment-drivers.md, payment-stripe-local-methods.md, payment-driver-layer-decisions.md, subscriptions-stripe.md
