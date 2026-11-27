---
name: payment-stripe-method-list
description: Stripe methods beside the card: list pg_payment_stripe_methods(), pure pg_payment_stripe_method_types() for page and intent, method_<type> switches in connect_apps
module: payments
---

## What is the list?
- `pg_payment_stripe_methods()` in `includes/payments/stripe.php`: Stripe type => `label`, `currencies` (uppercase ISO), `immediate` (the result is known when the visitor is back), `hold` (can be held: manual capture), `step` (`redirect`).
- `immediate` and `redirect`: `ideal`, `bancontact`, `eps` (EUR), `p24` (EUR, PLN), `klarna` (13 currencies), `afterpay_clearpay` (AUD, CAD, EUR, GBP, NZD, USD). Only `klarna` and `afterpay_clearpay` have `hold`.
- `sepa_debit` (EUR): not `immediate`, `step` `element` (IBAN and mandate in the Payment Element); the order waits for its payment: `payment-stripe-later-result.md`.

## Which types are sent?
- `pg_payment_stripe_method_types($currency, $settings, $recurring)`, pure: `card` (+ `link` with its switch), then each listed type switched on in `$settings['methods']` whose currencies hold `$currency`. Transaction type Authorize (`capture` `manual`) keeps only `hold` types. `$recurring` gives cards only.
- `prepare_with()` (order `recurring`) and `authorize_with()` (payment `recurring`) both call it, so the Payment Element's `allowedPaymentMethodTypes` and the intent's `payment_method_types` agree; renewal and card update call it with `$recurring` true.
- The currency is the site's base currency (`pg_payment_stripe_settings()['currency']`); the charge is never converted.

## Where are the switches?
- `connect_apps` row `payment_stripe`, values `method_<type>` = `'1'` or `''` (switch fields of `pg_payment_stripe_fields()`, built by `pg_payment_stripe_method_fields($currency)`); all off by default. `pg_payment_stripe_settings()['methods']` is type => bool.
- Drawn on Site Settings › Commerce, Payment methods card, under the Stripe gateway, below Offer Link: a heading "Payment methods", one switch a method, help "Currencies: …" (+ "Not offered while Transaction type is Authorize." for a method without `hold`).
- A method that does not take the base currency is drawn off and disabled with "Off: the store's base currency is …". The base currency is set on another screen, so the reason is drawn from `BASE_CURRENCY_CODE`.

## What did the field layer gain?
- `pg_payment_fields_normalize()` keeps `group` (string: a `pg-group-label` heading before the field) and `unavailable` (switch only: the reason). `pg_payment_settings_inputs_html()` draws both; `pg_payment_settings_decide()` stores an unavailable switch as `''` even when posted.

## What must the store do at Stripe?
- Turn each method on in the Stripe Dashboard first: an intent naming a type the account has not turned on is refused, the card too. The help line says so.

## Security policy?
- Checked per method: Stripe answers `next_action.redirect_to_url` (a `hooks.stripe.com` or `pm-redirects.stripe.com` address), and the checkout sends the visitor there with a 302. The built-in policy has no `form-action` and a navigation is not governed, so nothing was added. The Payment Element frames stay on `js.stripe.com`; method logos fall under `img-src`.

## How is it verified?
- `tests/payment_stripe_methods_test.php`: the list, types by currency and switch, Authorize, repeating order, page and intent agreeing, the authorize body, renewal and card update. `payment_drivers_test.php`: group and unavailable.
- No live try: the first one with test keys is the owner's.

Related: commerce/payment-driver-stripe.md, commerce/subscriptions-stripe.md, commerce/payment-drivers.md
