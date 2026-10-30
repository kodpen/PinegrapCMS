---
name: carrier-label-sendcloud-customs
description: Sendcloud customs data (2026.11.0, #3474, decided #3408): route opened only by a filled shop block, parcel_items and customs_information of API v3, incoterm through the option, what is unverified
---

## When does a Sendcloud parcel carry customs data?
- Only on a route that needs it (`pg_label_customs_needed()`) and only when the shop filled the customs block of the Sendcloud settings: a default HS code, origin country or signer (`pg_label_sendcloud_customs_filled()`, the account half of `pg_label_customs_in_use()`). A product's own HS code does not open the route: the decision ties it to the block.
- Block empty: `pg_label_sendcloud_route_error()` keeps the old text ("A parcel from NL to US needs a customs declaration, which is not sent to Sendcloud yet."), nothing priced or announced. Routes inside the EU customs union and domestic ones send exactly what they sent before.
- Returns (Returns API, `carrier-label-sendcloud-returns.md`) are still EU only: no customs data goes with a return yet.

## What is sent?
- Followed: Sendcloud's public OpenAPI file of the Shipments API v3 (`https://sendcloud.dev/.openapi/v3/shipments/openapi.yaml`, version 3.0.0, read 2026-10-30) and of the shipping options (`…/v3/shipping-options/openapi.yaml`).
- `parcels[0].parcel_items` from `pg_label_customs_lines()` (product values first, shop defaults second): `description`, `quantity`, `weight` `{value (kg a unit, number), unit: kg}`, `price` `{value (a unit, from minor units /100, string), currency (order's)}`, `hs_code` (dots removed; more than 12 digits refuses: Sendcloud's maxLength), `origin_country`, `sku` (`products.name`) only when there is one.
- `customs_information`: `invoice_number` = order number (required, 40 chars), `export_reason` from the contents type (merchandise commercial_goods, gift, sample commercial_sample, documents, return returned_goods). `export_type` left to Sendcloud's default `commercial_b2c`.
- Refusals before any call, each a plain failure: a line with no HS code or origin anywhere (named), no order currency, no order number, an HS code over 12 digits.

## How is the shipping option chosen?
- Without a set option, `pg_label_sendcloud_option_pick($options, false, $incoterm)`: a declared parcel accepts options needing `export_documents` (Sendcloud makes the papers from the data) and skips one whose `functionalities.incoterm` puts duties on the wrong side: DDU/DAP take ddu, dap, dap_np or none; DDP takes ddp, dap_dp or none. An undeclared parcel still skips export-document options.
- Sendcloud v3 has no incoterm, non-delivery or signer field: the incoterm acts only through this choice (or the option code the shop sets, e.g. `dhl_express:worldwide/incoterm=dap`); non-delivery and signer are not sent.

## What is unverified?
- No live call. Whether "weight/price of the item" is per unit (taken so, as v2 documented); which carriers refuse without `export_type`; test mode's unstamped letter on a customs route. Tests: `tests/carrier_label_sendcloud_test.php` with `tests/fixtures/sendcloud/shipping_options_customs.json`.

Related: commerce/carrier-label-sendcloud.md, commerce/carrier-label-customs.md, workflow/decisions-made-product-7.md
