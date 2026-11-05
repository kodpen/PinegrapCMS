---
name: gateway-masked-keys
description: Legacy gateway keys kept plain but drawn masked on the Commerce card; a blank box keeps them; the Givex password kept only for its saved hostnames
module: payments
---

## Which keys, and why not sealed?
- `pg_gateway_masked_fields()` (`includes/fn/payments.php`): `ecommerce_clearcommerce_password`, `ecommerce_paypal_payments_pro_api_signature`, `ecommerce_sage_merchant_key`, `ecommerce_stripe_api_key`, `ecommerce_paypal_express_checkout_api_signature`, `ecommerce_givex_password`.
- They stay plain in `config`: a sealed Stripe key or PayPal signature (56+ characters) would not fit its VARCHAR 100 column (41 plain characters sealed, `payment-gateway-secrets.md`). Sealing them needs wider columns, a schema step.

## What does the Commerce card do?
- `includes/settings/commerce.php` prints `pg_gateway_secret_shown()` (the mask `********` when one is stored, else empty), never the value. The Givex password box is `type="password"`.
- `commerce.save.php`: `pg_gateway_masked_for_save()` keeps the stored value for a blank or masked box; a typed value replaces it. No way to clear one from the box (as for the sealed secrets).

## What was the fault?
- The card printed all six into `value=` (Givex as a plain text box). The screen opens for role <= 2, so a manager read every gateway key in the page source.

## Why is the Givex password kept only for its hostnames?
- `send_givex_request()` sends the user id and password inside every request to the primary and secondary hostnames, both typed on the card. With a kept password, a manager could change a hostname and send the stored password to a server of their choice.
- `pg_givex_password_for_save()`: same hostnames (trimmed, case ignored) keep it; a changed or added hostname with a blank or masked box refuses the whole save with "The Givex hostname changed. Enter the password again." before anything is written; both hostnames cleared drop it; a typed password goes with any hostname. Same rule as the SMTP password.

## Tests
- `tests/gateway_masked_secrets_test.php`: the list, keep/replace, the Givex host rule, and a source pin that the card prints the mask and the save no longer writes the posted box raw.

Related: docs/memory/commerce/payment-gateway-secrets.md, docs/memory/commerce/givex-gift-card.md
