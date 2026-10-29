---
name: shipping-today
description: How shipping works today (checked 2026-10-20): tracking tables and write paths, the four meanings of "shipped", estimated dates, known gaps and reusable Turkish validation helpers
---

## Where is shipping data kept?
- Tracking numbers: `shipping_tracking_numbers`, one row per parcel. Since 2026.11.0 (11.0.20) the row has carrier, status, date, source and warning columns and a unique (`ship_to_id`, `number`) key (shipment-row-schema.md), written (shipment-write-path.md) and read through `pg_shipment_view()`; a row without a carrier is still recognised by `pg_shipping_carrier()` (order-view-tracking-link.md).
- `ship_tos.ship_date` / `delivery_date` are ESTIMATES written at checkout (`submit_order.php`); there is no column for the real dates.
- `orders.tracking_code` is a campaign code; `orders.tracking_company` / `orders.notes` are reserved for ERP.
- The plan to change all this: docs/memory/plans/shipping-management.md. Old audit with line numbers: `git show 88e7ac6d:docs/_tespit_2026_10_08/kargo_yonetimi_kesif_raporu.md`

## Which paths write tracking numbers?
- `update_order()` (`update_order.php`): the shared path (panel `view_order.php`, `api.php` `update_order`, `includes/workspace/changes.php`). Replaces the list through `includes/fn/shipping.php`, fires `order.shipped`, the automatic invoice (erp/erp-auto-invoice.md), the `order_shipped` auto campaign (only `^^order_number^^`), the `order_review_request` start (product-review-request-email.md) and, once per address, `order.delivered` + `order_delivered` campaigns when a save enters or changes the delivery date to a day that has come.
- External API `api_orders_ship()` (`includes/api/resources/orders.php`), through `update_order()` with source `api`.
- Marketplace `mp_order_apply_tracking()` / `mp_order_mark_dates()` (`includes/api/outbound/orders.php`): same write path, carrier name and link on the row; delivered stage: `order.delivered` once, no e-mail.
- `shipworks.php` `updateshipment`: direct INSERT outside `update_order()` (no webhook, e-mail, date or log); kept unchanged.

## What does "shipped" mean?
Four meanings live side by side:
1. a non-empty tracking number: `_order_has_shipped()`, the external API and the customer cancel gate (order-cancel-shipping-gate.md);
2. `shipped_quantity >= quantity`: `view_orders.php`, `view_shipping_report.php`, dashboard;
3. `ship_date <= today`: `erp_order_shipment()` (`includes/erp/order_bridge.php`) and the order timeline;
4. the n11 package status.
- The timeline (`includes/fn/widgets.php`) shows "Delivered" once the estimated `delivery_date` has passed.

## Which gaps are known?
- No number format check (panel and ShipWorks only trim; API only checks length). An 11-digit number with no carrier in the method code is taken as UPS, 12/15 digits as FedEx, unless the site names a default carrier (`ECOMMERCE_DEFAULT_TRACKING_PROVIDER`, every order screen since #601).
- n11 import: city = district, state = province since #2004, as checkout keeps a Turkish address; older imports stay swapped (unchecked on a live order). Trendyol still has city = `city`.
- `mp_n11_request_collection()` is registered in the n11 connector but nothing calls `request_collection`.
- `verify_address()` (`shipping.php`) works only for US addresses (USPS Web Tools, whose retirement is unverified); `verified_shipping_addresses` is a manual list and sets no `address_verified`.
- Guest tracking: guest-shipment-lookup.md; bulk upload: shipping-bulk-import.md; carrier status job: shipment-tracking.md. Profile screens do not offer `order_delivered` yet.

## What can Turkish validation reuse?
- `erp_edoc_postcode_valid()` (5 digits), `erp_edoc_isbasi_phone()` (`0XXXXXXXXXX`), `erp_tax_number_check()` (TCKN/VKN) live in the ERP module and load only when it is on; checkout use means moving them to `includes/fn/`.
- `states` has 81 Turkish provinces (code = name, not plate number); there is no district data.
- PTT and international numbers follow UPU S10 (`AA123456789TR`, mod-11 check digit); not in the code.
