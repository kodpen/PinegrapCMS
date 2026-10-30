---
name: shipping-tr-district
description: Shipping plan step 8 (decision #3569): ship_tos.district and states.plate_code in three PRs; D1 schema and model, D2 checkout and order screens, D3 drivers, imports and the API field
---

## What state is it in?
- Step 8 of `plans/shipping-management.md`, decided in #3569 (`workflow/decisions-made-schema-6.md`). One PR at a time, each from `development` after the previous one merged.
- D1 - in progress: step 11.0.134 adds `ship_tos.district` and `states.plate_code` and fills the plate codes of the Turkish states; `pg_ship_to_district_ready()`; a subscription renewal copies the district. No UI (`commerce/tr-district-plate-code.md`).

## What does D2 do?
- The optional district box on checkout (express order widget and the classic shipping address page) and on the order screens (`view_order.php`, the address edit), shown when the country is Turkey or the chosen method's driver asks for a district.
- Saved only when `pg_ship_to_district_ready()`; free text, at most 50 characters, never required. Texts through `php tools/tr_add.php`.

## What does D3 do?
- Carrier drivers (Aras `ReceiverTownName`, Geliver `districtName`, Basit Kargo `town`, Kargonomi's district lookup) send `district` first and fall back to `city` as today, so older orders ship as before.
- The n11 and Trendyol imports (`mp_order_ship_to()`, `includes/api/outbound/orders.php`) write `district`.
- The API `ship_tos` address (`api_order_shipments()`, `includes/api/resources/orders.php`) gains `district` with its entry in `includes/api/schema.php`; left out of D1 because that file was held by draft #3298.

## Where to start?
- D2: `includes/fn/widgets_express_order.php`, `includes/fn/widgets_shipping_address.php`, `shipping_address_and_arrival.php`, `view_order.php`; check open PRs on them first.
