---
name: shipping-carrier-labels
description: Plan (approved 2026-10-19; steps 1-2 done, 3a #3117): carrier APIs for labels and barcodes, bulk labels, status write-back; aggregator first, then Yurtiçi and Aras direct, then abroad
---

## Why?
- No label is created today and no Turkish carrier API exists (live rates only for UPS/FedEx/USPS); competitors ship 10–26 carrier integrations with thermal labels and tracking write-back.

## What is decided?
- This plan starts only after `shipping-management.md` steps 1–3 exist: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` are the base; this plan never writes tracking numbers another way.
- First driver: one shipping aggregator (single API, many carriers, contracted rates without a carrier contract), chosen by the owner from the current Turkish aggregators; then Yurtiçi Kargo and Aras Kargo direct APIs. Owner (2026-10-29, #3073): start with the services whose integration is open (public reference and test mode); every aggregator and direct carrier follows in time; international drivers too, the US and Europe first, since the product is global. Driver interface `includes/shipping/<driver>.php`: create shipment → label (PDF/ZPL) + barcode + tracking number; cancel; track; pickup request where supported.
- Panel: "Create label" on the order and on selected orders (bulk, one PDF), label reprint, status refresh; the chosen carrier comes from the shipping method's `carrier` or the order's method code.
- Status write-back: the daily/hourly job polls open shipments and calls `pg_shipment_delivered()` so `order.delivered`, the campaign e-mail and SMS (`sms-and-iys.md`) fire exactly once. Carrier webhooks are used when the driver offers them.
- Return labels: the same driver creates a return shipment for customer returns (`commerce/order-returns-panel.md`) with the named return carrier.
- UPS/FedEx/USPS rate code and box packing stay untouched; label creation for them is a later driver on the same interface.
- Marketplace orders: Trendyol common label (`createCommonLabel`, ZPL for TEX/Aras) is handled inside the marketplace connector, not here.

## What remains?
1. Driver interface, settings card (credentials encrypted), connection test (S). Done in PR #2378 (`commerce/carrier-label-drivers.md`).
2. Geliver driver (#3073). Done in PR #3096 (`commerce/carrier-label-geliver.md`); the `TRACK_UPDATED` webhook later (M).
3. Panel actions single + bulk; `shipment_labels` and `data/shipping_labels/`, retention 30/90 days (#3108, `workflow/decisions-made-schema-3.md`) (M).
   - 3a. Done in PR #3117 (`commerce/shipment-labels.md`): table, files, Create Label and reprint on the order, sweep, erasure, order delete.
   - 3b. Next: bulk labels for selected orders merged on the fly into one PDF (none kept); cancel through `cancel()`, then `state` and the parcel pruned through the write path; return labels (`is_return`).
4. Status polling job in the dispatcher, write-back through the single path (S).
5. Yurtiçi and Aras direct drivers (M each); API `POST /orders/{id}/shipments/label` and payload fields (S).
6. Further aggregators as their references open: Basit Kargo (SVG labels, no test mode, 600 requests a minute; #3123), Kargonomi; MNG ApiZone a candidate (M each).
7. International: Shippo first (#3124, `workflow/decisions-made-product-3.md`), then Sendcloud for the rest of Europe (M each).

## Open questions?
- Answered (#3073): Geliver first, the only aggregator with a public reference and test mode on 2026-10-29; the owner confirms its prices and contract. The driver interface must not leak aggregator-specific fields into the shipment row.
- Formats (#3123): PDF, ZPL and SVG as the service gives them; SVG into the bulk PDF where dompdf renders it; ZPL never sent to an external converter.

## Where to start?
- Step 3b: helpers in `includes/fn/shipment_labels.php`; the bulk action on `edit_orders.php`. Live check of the Geliver driver with the owner's test key.
