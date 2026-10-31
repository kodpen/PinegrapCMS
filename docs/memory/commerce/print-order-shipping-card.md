---
name: print-order-shipping-card
description: The printed order (print_order.php) prints one shipping card per real recipient above its items, empty rows hidden, every parcel with its carrier
module: order_view
---

## What does the printed order show per recipient? (PR #3365, decision #3346)
- Above each real recipient's items (`ship_to_id > 0`), a card built by `pg_print_order_shipping_card()` (`includes/fn/order_view.php`): ship-to name, full name, company, address, address type, address verified (the 7px GIF, light paper), phone, requested arrival date, ship and delivery dates, shipping method, tracking numbers, shipping cost (only above 0), packages, custom shipping fields. Each row goes through `pg_order_view_row()`, so a row without a value is left out; a card with no value prints nothing.
- The address reads like the order screen's: the district (`ship_tos.district`, read behind `pg_ship_to_district_ready()`) before the city, only when `pg_order_view_district()` finds it differs from the city, and the country by name through `pg_order_view_country()`; the header's "Shipping Information" column and the billing line do the same. Before, the district was left out and the stored code (`TR`) printed.
- Before, `$output_shipping_details` was built (copied from the panel card, with a form-field prefill and an edit link) and never echoed, so a printed order to two addresses did not say which items went where.

## When is the district left out? (issue #3948, PR #4131)
- n11 and Trendyol imports store the district in both `ship_tos.city` and `ship_tos.district` (decision #3835), so the order screen, both address places of the printed order and the packing slip printed "Kadıköy, Kadıköy, İstanbul".
- `pg_order_view_district($district, $city)` (`includes/fn/order_view.php`) returns the trimmed district, or '' when it equals the city after `pg_list_search_fold()` (I, İ, ı, i one letter, lower case) with every space removed. All four places print only what it returns. Stored data, the connectors' mapping and the district sent to carrier drivers are unchanged. Tests: `tests/order_address_district_test.php`.

## How are tracking numbers printed?
- `pg_shipment_rows()` + `pg_shipment_view()` as on the order screen (row carrier first), then `pg_print_order_tracking_text()`: every number, one a line, carrier name in brackets when known, no link. Before, only numbers with a link were listed, and those were `pg-print-none`.
- Ship date, delivery date and parcels print only for a complete order (`$status != 'incomplete'`).

## What prints as before?
- Orders without a real recipient (digital-only, the "no shipping" recipient) get no card.
- The compact "Shipping Information" column in the page header stays, so name and address appear there and on the card.

Related: docs/memory/commerce/print-order-header-facts.md
