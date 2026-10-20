---
name: payment-driver-stripe
description: Stripe on PaymentIntents (gateway 'Stripe PaymentIntents', 11.0.24): Payment Element and ConfirmationToken, 3-D Secure return, refund, cancel, CSP; classic 'Stripe' untouched
---

## What is it?
- `includes/payments/stripe.php`, key `stripe`, card driver for the gateway value `Stripe PaymentIntents` (appended to the `config.ecommerce_payment_gateway` ENUM by 11.0.24; owner 2026-10-20: one value per step). `Stripe` stays the classic Charges code (shown as "Stripe (classic)").
- Settings in `connect_apps` `payment_stripe`: `publishable_key`, `secret_key` (sealed), `link` switch. Mode comes from the keys (mode row hidden for connect-settings drivers in prep.php/backend.src.js); Transaction Type Authorize → `capture_method=manual` (taken on Stripe's screen).
- HTTPS calls without the SDK, `Stripe-Version: 2024-06-20`, through `pg_curl_tls()`; every operation has a `*_with($…, $settings, $http)` twin for tests.

## How does a card payment go?
- The express order widget (designer) draws `_eo_hosted_card_html()` when `pg_payment_hosted_card_driver()` answers (capability `hosted_fields` + `prepare`): `prepare()` gives script, publishable key, amount (Stripe units), currency, method types, locale. `assets/js/payment_hosted.js` (bundle `payment_hosted.min.js`) moves the box into `[data-pg-eo-cc-fields]` before mounting, disables the site's card inputs, mounts the Payment Element (deferred mode), and on submit makes a ConfirmationToken into `payment_token`, then `requestSubmit()` again (document capture listener, so the form's own handlers run once).
- submit_order.php skips the card field checks for a hosted driver and passes `token`; `authorize()` creates + confirms the PaymentIntent for the order's total (metadata order id, idempotency key from order + token + amount). succeeded → paid, requires_capture → authorized, processing → pending, `redirect_to_url` → redirect with state (intent, order, amount); other next actions are cancelled and refused.
- Return `express_order.php?mode=stripe_return`: `complete()` reads the intent from the state (not the address), checks order/amount/currency, a changed total voids (cancel, or full refund once taken). The order keeps brand, last four (masked) and expiry (`card_last4`, `card_exp_month`, `card_exp_year` in the answer).
- Return modes are listed once: `pg_payment_return_modes()` (`<key>_return` for new drivers), `pg_payment_is_return_mode()`, `pg_payment_return_method()`; express_order.php, submit_order.php and the widget use them. order_preview.php does not: the classic checkout pages and order preview draw no Element, so the driver refuses them ("Please enter your card details.").

## Refund and cancel?
- `refund()`: `/v1/refunds` by `payment_intent`, or by `charge` for a classic `ch_` id. `cancel()`: cancel while not taken, full refund once taken.
- Visitor sentences are the site's for common decline codes and an unreachable Stripe; Stripe's words go to the log line `Stripe payment for order #N was not accepted: …`.

## Security policy?
- A definition may list `csp` hosts (script-src, frame-src, connect-src; https origins only). `waf_csp_add_payment_sources()` adds the card gateway's to the built-in policy (read from the config row: the header goes out before the constants exist). A custom operator policy must add Stripe itself.

## What is not done?
- Webhook (`payment_intent.succeeded` etc.) for a visitor who never returns: plan step 3b.
- Apple Pay needs the domain registered in the Stripe Dashboard; Link needs turning on there.

## How was it verified?
- `tests/payment_stripe_test.php` (stand-in HTTP caller). Sandbox: ENUM step run twice, settings pane, CSP header, checkout without/with token (Stripe unreachable), iyzico still validates card fields; the script against a probe page with a fake Stripe.js (jsdom). No real Stripe account.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-refunds.md, docs/memory/plans/payment-driver-layer.md
