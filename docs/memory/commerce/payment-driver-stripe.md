---
name: payment-driver-stripe
description: Stripe on PaymentIntents (gateway 'Stripe PaymentIntents', 11.0.24, PR #937): Payment Element and ConfirmationToken, 3-D Secure return, refund, cancel, CSP; classic 'Stripe' untouched
---

## What is it?
- `includes/payments/stripe.php`, key `stripe`, card driver for the gateway value `Stripe PaymentIntents` (appended to the `config.ecommerce_payment_gateway` ENUM by 11.0.24). `Stripe` stays the classic Charges code ("Stripe (classic)").
- `connect_apps` `payment_stripe`: `publishable_key`, `secret_key` and `webhook_secret` (sealed), `link`. Mode from the keys; Transaction Type Authorize → `capture_method=manual`.
- No SDK: `pg_curl_tls()`, `Stripe-Version: 2024-06-20`; each operation has a `*_with($…, $settings, $http)` twin for tests.

## How does a card payment go?
- The designer's express order widget draws `_eo_hosted_card_html()` when `pg_payment_hosted_card_driver()` answers. `prepare()` gives script, publishable key, amount (Stripe units), currency, method types, capture method, locale.
- `assets/js/payment_hosted.js` (bundle `payment_hosted.min.js`) moves the box into `[data-pg-eo-cc-fields]`, disables the site's card inputs, mounts the Payment Element (deferred mode), and on submit puts a ConfirmationToken into `payment_token` and calls `requestSubmit()` (the form's handlers run once).
- `stripe.elements()` gets `captureMethod` and `allowedPaymentMethodTypes` from `prepare()`; they must equal the `capture_method` and `payment_method_types` `authorize()` sends, or Stripe refuses every confirmation (Authorize mode failed so before #937). A test compares them.
- `authorize()` creates and confirms the intent for the order total (metadata order id; idempotency key from order + token + amount). succeeded → paid, requires_capture → authorized, processing → pending, `redirect_to_url` → redirect with state; other next actions are cancelled and refused.
- Return `?mode=stripe_return`: `complete()` reads the intent from the session state, checks order, amount and currency. A changed total voids it (cancel, or full refund once taken); if the void fails the visitor reads that the store will refund it, not "cancelled", and the log says MANUAL REFUND REQUIRED.
- Return modes: `pg_payment_return_modes()`, `pg_payment_is_return_mode()`, `pg_payment_return_method()`. Classic checkout pages and order preview draw no Element, so the driver refuses them ("Please enter your card details.").

## Refund and cancel?
- `refund()`: `/v1/refunds` by `payment_intent`, or by `charge` for a classic `ch_` id. `cancel()`: cancel while not taken, full refund once taken.
- Visitor sentences are the site's; Stripe's words go to `Stripe payment for order #N was not accepted: …`.

## Security policy?
- A definition may list `csp` hosts (script-src, frame-src, connect-src; https origins, `https://*.` allowed). `waf_csp_add_payment_sources()` adds the card gateway's to the built-in policy (read from the config row). Stripe's: script `js.stripe.com`, `*.js.stripe.com`; frame those, `hooks.stripe.com`, `link.com`, `*.link.com`; connect `api.stripe.com`, `link.com`, `*.link.com` (Link hosts always listed). A custom policy must add them itself.

## Webhook?
- `notify()` (PR #2143): `Stripe-Signature` checked (HMAC-SHA256 of `t.body`, any `v1`, 300 s; no secret = refused). `payment_intent.succeeded` (metadata `order_id`) → `paid`; `charge.refunded` → `refunded`; `charge.dispute.created` → `pending`; else `raw.ignored`. Outcome: `payment-callback.md`.

## What is not done?
- Completing an incomplete order from the webhook: `plans/order-completion-function.md` (intent and amount on the order: #2199). Apple Pay: domain in the Dashboard; Link is set there.

## How was it verified?
- `tests/payment_stripe_test.php` (stand-in HTTP caller), `payment_stripe_webhook_test.php`; sandbox (Stripe unreachable); the script in jsdom with a fake Stripe.js; no real account.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-refunds.md, docs/memory/plans/payment-driver-layer.md
