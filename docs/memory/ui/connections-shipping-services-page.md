---
name: connections-shipping-services-page
description: The shipping services on Connections (CX11g3): the label services' keys and UPS's, FedEx's, USPS's client ID and secret on their pages; the Shipping card keeps switches, choices, environment
module: carrier_labels, carrier_api, connections_screen
---

## Where are a shipping service's keys typed?
- On its page (CX11g3 #10988, plan `plans/connections-merges-build.md`): `connections.php?service=geliver`, `basitkargo`, `kargonomi`, `aras`, `shippo`, `sendcloud` (`pg_connections_screen_label_pages()`) and `ups`, `fedex`, `usps` (`pg_connections_screen_carrier_pages()`).
- The payment services moved in CX11g1 and g2: `connections-payment-gateways-page.md`, `connections-payment-services-page.md`.

## Which fields of a label service are keys?
- `pg_label_fields()` marks each field `connection`: every secret, and a text field the driver declares with `'connection' => true` (Aras Kargo's User Name and Customer Code). `pg_label_fields_part($fields, 'keys'|'card')` splits them.
- The card keeps the rest: Mode, Label Format, sender address IDs, carrier and service codes, Kargonomi's Warehouse ID, Shippo's sender address, customs, Pickup Location, the switch, "Create the label automatically once the order is paid", the status webhook row and "Save and test the connection".

## What does the label service's page do?
- "Connection details": the keys (a secret never drawn back), "Save" and, where the driver has `test`, "Save and try". Posts `label_save` through `pg_label_page_post()` (switch and automatic label as stored) to `pg_label_settings_decide()` with the keys only, then `pg_label_settings_save()`: store, test, status webhook for newly typed keys.
- `pg_label_settings_decide()` keeps a stored value of a field not given, so the card and the page never blank each other.
- Status has "Disconnect" (`label_remove`) while a key is stored: the status webhook is removed first (`pg_carrier_webhook_sync()` as if off), then `pg_label_forget_keys()` empties the keys; switch and choices stay.

## What does a carrier's page do (UPS, FedEx, USPS)?
- The portal line, the client ID and secret, "Save" and "Save and try" (a fresh token). `pg_carrier_api_keys_decide()` (pure): an empty secret box keeps the stored one; both are required while the card's switch is on. Disconnect (`carrier_remove`, `pg_carrier_api_forget()`) empties both and the cached token; the environment stays.
- The card keeps the UPS and FedEx switches, their Account Number, Environment, Verify US Addresses and "Save and test the connection". `pg_carrier_api_settings_save()` now writes only a changed environment.

## What does the Shipping card show and refuse?
- A connection block per service (`pg_label_connection_html()`, `pg_carrier_api_settings_fields()`): Connected teal / Not connected gray, "Manage in Connections" / "Connect in Connections".
- Newly switched on while the keys are missing marks the switch: "Set up the … keys on its page of Connections first." (`pg_label_choice_refusal()`; `pg_carrier_api_choice_refusal()` for UPS, FedEx, and Verify US Addresses for USPS, `pg_carrier_api_switch()`). What was on always saves.

## Who may change them, and the registry?
- Who may open Site Settings › Ecommerce (`connections_commerce_may_manage()`).
- Registry: `place` `connections.php`, `found_at` `#settings/commerce/pgset-shipping`. Label services by `pg_label_ready()`; carriers by `pg_carrier_api_connected()`: keys stored and, for UPS and FedEx, the switch on. ShipWorks stays on the card. No row added: `pg_connections_count()` stays 84.
- Old anchors (`ups_client_id`, `label_geliver__api_token`, …) land on `pgset-shipping`. No data step, table or column.

Tests: `tests/connections_shipping_services_test.php`, `tests/carrier_labels_test.php`.
