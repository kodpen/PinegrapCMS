---
name: gateway-masked-keys
description: Legacy gateway keys kept plain, never drawn back on their Connections pages; a blank box keeps them; the Givex password kept only for its saved hostnames
module: payments
---

## Which keys, and why not sealed?
- `pg_gateway_masked_fields()` (`includes/fn/payments.php`): `ecommerce_clearcommerce_password`, `ecommerce_paypal_payments_pro_api_signature`, `ecommerce_sage_merchant_key`, `ecommerce_stripe_api_key`, `ecommerce_paypal_express_checkout_api_signature`, `ecommerce_givex_password`.
- They stay plain in `config`: a sealed Stripe key or PayPal signature (56+ characters) would not fit its VARCHAR 100 column (41 plain characters sealed, `payment-gateway-secrets.md`). Sealing them needs wider columns, a schema step.

## Where are they typed?
- Since CX11g1 (#10919) and CX11g2 (#10973) all six on their pages of Connections (`ui/connections-payment-gateways-page.md`, `ui/connections-payment-services-page.md`); the Commerce card draws and writes none.
- The page draws an empty `type="password"` box ("Stored. Leave empty to keep it."). `pg_gateway_masked_for_save()` keeps the stored value for a blank or masked box; a typed value replaces it; Disconnect empties the service's keys.

## What was the fault?
- The card printed all six into `value=` (Givex as a plain text box). The screen opens for role <= 2, so a manager read every gateway key in the page source.

## Why is the Givex password kept only for its hostnames?
- `send_givex_request()` sends the user id and password inside every request to the primary and secondary hostnames, typed with them on Givex's page. With a kept password, a manager could change a hostname and send the stored password to a server of their choice.
- `pg_givex_password_for_save()`, called by `pg_payment_method_keys_decide()`: same hostnames (trimmed, case ignored) keep it; a changed or added hostname with a blank or masked box refuses the whole save with "The Givex hostname changed. Enter the password again." before anything is written; both hostnames cleared drop it; a typed password goes with any hostname. Same rule as the SMTP password.

## Tests
- `tests/gateway_masked_secrets_test.php`: the list, keep/replace, the Givex host rule, a source pin that the card no longer writes them; the page's Givex rule: `tests/connections_payment_services_test.php`.

Related: docs/memory/commerce/payment-gateway-secrets.md, docs/memory/commerce/givex-gift-card.md
