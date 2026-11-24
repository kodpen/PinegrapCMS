---
name: connections-payment-gateways-page
description: The card gateways on Connections (CX11g1): each gateway of the Payment Gateway list has a page with its keys; the card keeps the choice, Mode, Transaction Type, shop options
module: payments, connections_screen
---

## Where are a card gateway's keys typed?
- On its own page (CX11g1 #10919, `connections-one-place.md`): `connections.php?service=iyzico`, `stripe_classic`, `payflow_pro`, `payments_pro`, `authorize_net`, `clearcommerce`, `first_data`, `sage`. The list is `pg_payment_gateway_connections()` (`includes/fn/payments.php`): gateway value, name, each key's column, label, kind and width.
- "Connection details": the keys and "Save" (no "Save and try": the old gateways have no test call). A secret box is always drawn empty ("Stored. Leave empty to keep it."); First Data's "PEM File" is a select of the uploaded PEM files and moved with the Store Number.
- Status has "Disconnect" (`gateway_remove`) while any key is stored: it empties that gateway's columns.

## What does a save write?
- `pg_payment_gateway_keys_decide()` (pure, tested): text as typed (trimmed); an empty or masked secret keeps the stored one; a sealed column (`pg_gateway_secret_fields()`) is sealed with the site's key. Longer than its column, or too long to seal, is marked and nothing is written. Only changed columns are written, to the same `config` columns as before.

## What does the Payment Methods card keep?
- The Payment Gateway select, Mode (and Payments Pro's own Mode), Transaction Type, Surcharge and iyzico's options (Installment, 3D Secure, Pay with iyzico, Protected Currency). Owner (2026-11-21, via K3): Mode and Transaction Type stay on the card; only the keys move.
- Per gateway a `#pgsub-gateway-<key>-connection` block ("<name> connection", Connected teal / Not connected gray, "Manage in Connections" / "Connect in Connections"), shown for the chosen gateway by `show_or_hide_ecommerce_payment_gateway()`.
- A gateway newly chosen while its keys are missing marks "Payment Gateway" (`pg_payment_gateway_choice_refusal()`): "Set up the … keys on its page of Connections first." An unchanged choice always saves.
- The card neither draws nor writes the moved columns (`pg_payment_gateway_connection_columns()`).

## Who may change them, and what else changed?
- Who may open Site Settings › Ecommerce (`connections_commerce_may_manage()`, `$can['commerce']` in `pg_connections_screen_page_cards()`); the write checks the same.
- Registry: `place` `connections.php`, `found_at` `#settings/commerce/pgset-payments`, state `pg_payment_gateway_ready()`: connected while it takes the cards with every key stored (an active gateway without keys was shown connected before). New services `payflow_pro`, `payments_pro`, `stripe_classic` (owner 2026-11-21, via K3): `pg_connections_count()` 84 (`api/connections-registry.md`). The `paypal` row is PayPal Checkout and Express Checkout.
- Old anchors (each gateway's first key, e.g. `ecommerce_stripe_api_key`) land on `pgset-payments`. No data step, table or column.
- Stripe PaymentIntents, PayPal Checkout and Express Checkout, PayTR, Givex moved in CX11g2: `connections-payment-services-page.md`.

Tests: `tests/connections_payment_gateways_test.php`, `tests/gateway_secrets_test.php`, `tests/gateway_masked_secrets_test.php`.
