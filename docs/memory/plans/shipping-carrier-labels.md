---
name: shipping-carrier-labels
description: Plan (approved 2026-10-19; steps 1-4 done, 6 Basit Kargo #3163): carrier APIs for labels and barcodes, bulk labels, status write-back; aggregator first, then Yurtiçi and Aras direct, then abroad
---

## Why?
- No label is created today and no Turkish carrier API exists; competitors ship many carrier integrations with thermal labels and tracking write-back.

## What is decided?
- This plan starts only after `shipping-management.md` steps 1–3 exist: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` are the base; this plan never writes tracking numbers another way.
- First driver: one shipping aggregator (single API, many carriers, contracted rates without a carrier contract), chosen by the owner from the current Turkish aggregators; then Yurtiçi Kargo and Aras Kargo direct APIs. Owner (2026-10-29, #3073): start with the services whose integration is open (public reference and test mode); every aggregator and direct carrier follows; international drivers too, the US and Europe first, since the product is global. Driver interface `includes/shipping/<driver>.php`: create shipment → label (PDF/ZPL) + barcode + tracking number; cancel; track; pickup request where supported.
- Panel: "Create label" on the order and on selected orders (bulk, one PDF), reprint, status refresh; the carrier comes from the shipping method's `carrier` or the order's method code.
- Status write-back: the job polls open shipments and calls `pg_shipment_delivered()` so `order.delivered`, the campaign e-mail and SMS (`sms-and-iys.md`) fire exactly once. Carrier webhooks are used when the driver offers them.
- Return labels: the same driver creates a return shipment for customer returns (`commerce/order-returns-panel.md`) with the named return carrier; on the returns screen as step 8.
- UPS/FedEx/USPS rate code and box packing stay untouched; their labels are a later driver on the same interface.
- Marketplace orders: Trendyol common label (`createCommonLabel`, ZPL for TEX/Aras) is handled inside the marketplace connector, not here.

## What remains?
1. Driver interface, settings card (credentials encrypted), connection test (S). Done in PR #2378 (`commerce/carrier-label-drivers.md`).
2. Geliver driver (#3073). Done in PR #3096 (`commerce/carrier-label-geliver.md`); the `TRACK_UPDATED` webhook later (M).
3. Panel actions single + bulk; `shipment_labels` and `data/shipping_labels/`, retention 30/90 days (#3108, `workflow/decisions-made-schema-3.md`) (M).
   - 3a. Done in PR #3117 (`commerce/shipment-labels.md`): table, files, Create Label and reprint on the order, sweep, erasure, order delete.
   - 3b. Done in PR #3137 (`commerce/shipment-label-bulk.md`): bulk create and one merged PDF from the orders list, Cancel Label, return labels.
4. Status polling job in the dispatcher, write-back through the single path (S). Done in PR #3157 (`commerce/shipment-tracking.md`).
5. Yurtiçi and Aras direct drivers (M each); API `POST /orders/{id}/shipments/label` and payload fields (S).
6. Further aggregators as their references open: Basit Kargo done in PR #3163 (`commerce/carrier-label-basitkargo.md`; one `.zpl` per bulk run still open), Kargonomi; MNG ApiZone a candidate (M each).
7. International: Shippo first (#3124, `workflow/decisions-made-product-3.md`), then Sendcloud for the rest of Europe (M each).
8. Return label on the returns screen (#3178, `workflow/decisions-made-product-4.md`): staff button, code into `carrier_code` and the mail, `shipment_labels.order_return_id` (S).

## Open questions?
- Answered (#3073): Geliver first (public reference and test mode); the owner confirms its prices; no aggregator-specific fields in the shipment row.
- Formats (#3123): PDF, ZPL and SVG; ZPL never sent to an external converter.

## Where to start?
- Step 8 (#3163 has landed) or step 5: Yurtiçi and Aras direct drivers; one with `track()` is tracked by the job as it is. Live check of the Geliver driver with the owner's test key.
