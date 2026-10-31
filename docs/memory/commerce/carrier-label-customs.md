---
name: carrier-label-customs
description: Customs declaration of carrier labels (2026.11.0, #3449, decided #3408): shop-defaults block in a driver's settings, carrier-neutral lines, when a route needs one, Shippo's customs_declaration
module: product_customs
---

## Which routes are declared?
- `pg_label_customs_needed($from, $to)`: every route that crosses a border except one inside the EU customs union (`pg_label_customs_eu_countries()`, EU27); domestic and a missing country need none (the driver refuses the missing country itself). US and GB routes are declared.
- Nothing changes on a running site until the shop declares customs: `pg_label_customs_in_use($goods, $account)` is true once a default HS code, default origin or signer is filled, or a line's product has `hs_code`/`origin_country`. Until then the shipment goes as before (no declaration), as #3408 asked.

## What is the shop-defaults block?
- `pg_label_customs_fields()` (`includes/fn/product_customs.php`): `customs_contents` (merchandise default, gift, sample, documents, return), `customs_incoterm` (DDU default, DAP, DDP: DDU/DAP the recipient pays duties), `customs_non_delivery` (return default, abandon), `customs_hs_code`, `customs_origin_country` (select of the ISO list, "None" first), `customs_signer` (empty = sender name). A driver appends them to its `_fields()`; they live in the sealed `connect_apps` row like every field, no schema.
- `pg_label_settings_decide()` marks a bad default through `pg_label_customs_settings_problems()` (the C1 parsers `pg_product_hs_code_parse()`, `pg_product_origin_country_parse()`), for every driver that has the fields.

## How is a line declared?
- `pg_shipment_label_shipment()` gives `goods`: every line's `name`, `quantity`, `price` (order_items.price, minor units, `round()`), `weight` (kg a unit, from pounds), `hs_code`, `origin_country` (selected only when `pg_product_customs_ready()`, `pg_shipment_label_customs_columns()`). Outbound and return labels both.
- `pg_label_customs_lines($goods, $defaults, $parcel_weight)` (pure, carrier-neutral, C3 Sendcloud reuses it): the product's valid value first, the shop default second (a stored value the screen would refuse counts as empty); no HS code or no origin anywhere refuses with a translated reason naming the product, never a guessed code; name folded and cut to 100 characters (multibyte safe); a weightless line shares what the parcel weighs beyond the known lines, at least 0.01 kg a unit. Answer: ok, error, lines (name, quantity, unit_price, unit_weight, hs_code, origin_country).

## What does Shippo get?
- `pg_label_shippo_customs()` (pure): `customs_declaration` created inline with `POST /shipments/` (contents_type MERCHANDISE/GIFT/SAMPLE/DOCUMENTS/RETURN_MERCHANDISE, a return label always RETURN_MERCHANDISE; non_delivery_option RETURN/ABANDON; incoterm; certify true; certify_signer), items with Shippo's totals per line: `net_weight` = unit × quantity (kg), `value_amount` = unit price × quantity / 100, `value_currency` the order's, `origin_country`, `tariff_number`. A refusal returns before any request (plain failure, nothing bought); a missing currency refuses too.
- Unverified (no live call): whether Shippo wants `eel_pfc` for exports from the US (not sent; Shippo's refusal is shown), the description length limit, and inline creation's exact answer. Tests: `tests/label_customs_test.php`, `tests/carrier_label_shippo_test.php` with `tests/fixtures/shippo/customs_declaration.json`.

## What is still open?
- The products external API fields (`hs_code`, `origin_country`) follow once #3298 (holding `includes/api/schema.php`) merges. Sendcloud (C3 #3474): `carrier-label-sendcloud-customs.md`; a line carries `sku` (`products.name`) since then.

Related: commerce/product-customs-fields.md, commerce/carrier-label-shippo.md, workflow/decisions-made-product-7.md
