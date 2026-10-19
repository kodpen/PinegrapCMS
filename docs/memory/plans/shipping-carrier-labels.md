---
name: shipping-carrier-labels
description: Plan (approved 2026-10-19, not started, after shipping-management): carrier APIs for label/barcode creation, bulk labels, status write-back; aggregator first, then Yurtiçi and Aras direct
---

## Why?
- Today the product shows tracking links for eight carriers (`includes/fn/ecommerce.php:6071`) and has live rates only for UPS/FedEx/USPS (`includes/fn/carrier_api.php`). No label is created, no Turkish carrier API exists, and `shipping-management.md` leaves label APIs out by design. Turkish competitors ship 10–26 carrier integrations with thermal labels, tracking write-back and customer SMS.

## What is decided?
- This plan starts only after `shipping-management.md` steps 1–3 exist: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` are the base; this plan never writes tracking numbers another way.
- First driver: one shipping aggregator (single API, many carriers, contracted rates without a carrier contract), chosen by the owner from the current Turkish aggregators; then Yurtiçi Kargo and Aras Kargo direct APIs. Driver interface `includes/shipping/<driver>.php`: create shipment → label (PDF/ZPL) + barcode + tracking number; cancel; track; pickup request where supported.
- Panel: "Create label" on the order and on selected orders (bulk, one PDF), label reprint, status refresh; the chosen carrier comes from the shipping method's `carrier` or the order's method code.
- Status write-back: the daily/hourly job polls open shipments and calls `pg_shipment_delivered()` so `order.delivered`, the campaign e-mail and SMS (`sms-and-iys.md`) fire exactly once. Carrier webhooks are used when the driver offers them.
- Return labels: the same driver creates a return shipment for `customer-returns-flow.md` with the named return carrier.
- UPS/FedEx/USPS rate code and box packing stay untouched; label creation for them is a later driver on the same interface.
- Marketplace orders: Trendyol common label (`createCommonLabel`, ZPL for TEX/Aras) is handled inside the marketplace connector, not here.

## What remains?
1. Driver interface, settings card (credentials encrypted), connection test (S).
2. Aggregator driver: create, label download, cancel, track (M).
3. Panel actions single + bulk, label storage under `data/shipping_labels/` with retention (M).
4. Status polling job in the dispatcher, write-back through the single path (S).
5. Yurtiçi and Aras direct drivers (M each); API `POST /orders/{id}/shipments/label` and payload fields (S).

## Open questions?
- Which aggregator: the owner picks after a price/API comparison; the driver interface must not leak aggregator-specific fields into the shipment row.
- Thermal printer output: PDF A6 always, ZPL only where the carrier supplies it.

## Where to start?
- Wait for `shipping-management.md` step 3; then step 1 here with the aggregator's sandbox credentials.
