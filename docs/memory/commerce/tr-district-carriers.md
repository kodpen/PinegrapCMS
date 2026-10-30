---
name: tr-district-carriers
description: What sends and writes ship_tos.district (#3654): the four Turkish carrier drivers (district first, city fallback), the needs-district hook, the n11 and Trendyol imports
module: shipping
---

## What do the carrier drivers send?
- `pg_shipment_label_shipment()` puts `ship_tos.district` in the recipient as `district` ('' without the column or on an older order).
- `pg_label_recipient_district()` (`includes/fn/carrier_labels.php`) answers `district`, else `city` (which held the district before the column), trimmed. Aras `ReceiverTownName`, Geliver `districtName`, Basit Kargo `town` and Kargonomi's `GET /cities` lookup take it, so older orders and sites before step 11.0.134 ship as before.
- Aras and Kargonomi refuse a recipient with neither; a district without a city is enough.

## Which drivers ask for the district box?
- Aras and Kargonomi define `pg_label_<key>_needs_district()` (true): both refuse a shipment without one. Through `pg_ship_to_district_carrier_asks()` the methods of carriers aras, surat and ptt (Kargonomi's) draw the box for an address outside Turkey too.
- The check now loads the driver (`pg_label_info()`) before looking for the hook. Before #3654 it tested `function_exists()` first, so a driver not yet loaded in the request never asked.

## What do the marketplace imports write?
- `mp_n11_address()` answers `district` from n11's `district` / `districtName` / `town` (the same field it puts in `city`); `mp_trendyol_address()` from Trendyol's `district` (fixture `tests/fixtures/trendyol/v2-orders-page0.json`).
- `mp_order_ship_to()` appends `mp_order_ship_to_district_sql()`: `pg_ship_to_district_set_sql()`, cleaned by `pg_ship_to_district_clean()`, nothing while the column is missing.
- Trendyol still keeps the province in `city` and the district in `state` (n11 was swapped to the checkout's order); with `district` set the carriers get the right town, but a swap of Trendyol's two is a separate change.

## Where is it tested?
- Each `tests/carrier_label_<driver>_test.php` (district first, city fallback; the hook for Aras and Kargonomi), `tests/shipment_labels_test.php`, `tests/tr_district_box_test.php` (the drivers that ask), `tests/marketplace_n11_test.php` and `tests/marketplace_trendyol_orders_test.php` (the address `district`, the INSERT part).
