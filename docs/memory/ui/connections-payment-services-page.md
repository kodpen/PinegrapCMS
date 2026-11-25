---
name: connections-payment-services-page
description: Stripe, PayPal (Checkout and Express Checkout), PayTR and Givex on Connections (CX11g2): their keys on their pages; the cards keep the switches, Mode, Transaction Type and choices
module: payments, connections_screen
---

## Where are their keys typed?
- On their pages (CX11g2 #10973, `connections-one-place.md`): `connections.php?service=stripe`, `paypal`, `paytr`, `givex`. PayPal Express Checkout's keys are on PayPal's page below PayPal Checkout's: one product, so no service of its own and `pg_connections_count()` unchanged.
- The card gateways moved in CX11g1: `connections-payment-gateways-page.md`.

## What moved for the drivers (Stripe, PayPal Checkout, PayTR)?
- `pg_payment_driver_pages()`: their text and secret fields (`pg_payment_fields_part($fields, 'keys')`), with the portal link, the unreadable-keys warning and the Webhook Address. The page posts `driver_save` / `driver_remove`.
- The card keeps the selects and switches (`'card'` part): Stripe's Offer Link, PayPal's Mode, PayTR's Mode and Most Installments, the redirect switch, the Payment Gateway choice and Transaction Type.
- Each side decides only its own fields; `pg_payment_settings_store()` merges the stored values, so neither side blanks the other. The page keeps the stored switch (`pg_payment_driver_page_post()`).
- A key is required on the page only while the driver takes payments, as before. Disconnect (`pg_payment_settings_forget_keys()`) empties the key boxes and secrets; choices and switch stay.

## What moved for PayPal Express Checkout and Givex?
- `pg_payment_method_connections()`: the config columns, Express Checkout's API Username, API Password (sealed), API Signature (masked); Givex's two hostnames (the secondary optional), User ID and Password (masked). Actions `method_save` / `method_remove`.
- `pg_payment_method_keys_decide()` (pure): as `pg_payment_gateway_keys_decide()`, and Givex's password kept only for its saved hostnames (`commerce/gateway-masked-keys.md`).
- The cards keep the switches ("PayPal Express Checkout", "Accept Givex Cards"), Express Checkout's Transaction Type and Mode.

## What do the cards show and refuse?
- A connection block (`pg_payment_connection_block_html()`): "<name> connection", Connected teal / Not connected gray, "Manage in Connections" / "Connect in Connections".
- Newly chosen or switched on while the keys are missing marks the box: "Set up the … keys on its page of Connections first." (`pg_payment_driver_choice_refusal()`, `pg_payment_method_choice_refusal()`). What was already on always saves.

## Who may change them, and the registry?
- Who may open Site Settings › Ecommerce (`connections_commerce_may_manage()`; `pg_connections_screen_commerce_pages()`).
- Registry: `place` `connections.php`, `found_at` the card (`pgset-payments`, Givex `pgset-giftcards`), state `pg_payment_connection_ready()`: taking payments with the required keys stored (on PayPal's page either of the two).
- No data step, table or column: the same `connect_apps` rows and `config` columns.

Tests: `tests/connections_payment_services_test.php`, `tests/gateway_masked_secrets_test.php`, `tests/payment_drivers_test.php`.
