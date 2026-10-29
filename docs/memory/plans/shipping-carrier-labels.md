---
name: shipping-carrier-labels
description: Plan (approved 2026-10-19; 1-4, 6-8 partly done): carrier APIs for labels and barcodes, bulk labels, status write-back; aggregator first, then Yurtiçi and Aras direct, then abroad
---

## Why?
- No Turkish carrier API existed; competitors ship many carrier integrations with thermal labels and tracking write-back.

## What is decided?
- This plan starts only after `shipping-management.md` steps 1–3 exist: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` are the base; this plan never writes tracking numbers another way.
- First driver: one shipping aggregator (single API, many carriers, contracted rates without a carrier contract), chosen by the owner from the current Turkish aggregators; then Yurtiçi Kargo and Aras Kargo direct APIs. Owner (2026-10-29, #3073): start with the services whose integration is open (public reference and test mode); every aggregator and direct carrier follows; international drivers too, the US and Europe first, since the product is global. Driver interface `includes/shipping/<driver>.php`: create shipment → label (PDF/ZPL) + barcode + tracking number; cancel; track; pickup request where supported.
- Panel: "Create label" on the order and on selected orders (bulk, one PDF), reprint, status refresh; the carrier comes from the shipping method's `carrier` or the order's method code.
- Status write-back: the job polls open shipments and calls `pg_shipment_delivered()` so `order.delivered`, e-mail and SMS fire exactly once. Carrier webhooks are used when the driver offers them.
- Return labels: the same driver creates a return shipment for customer returns with the named return carrier (step 8).
- UPS/FedEx/USPS rate code and box packing stay untouched; their labels are a later driver on the same interface.
- Marketplace orders: Trendyol common label (`createCommonLabel`, ZPL for TEX/Aras) is the marketplace connector's job.

## What remains?
1. Driver interface, settings card (credentials encrypted), connection test (S). Done in PR #2378 (`commerce/carrier-label-drivers.md`).
2. Geliver driver (#3073). Done: PR #3096 (`commerce/carrier-label-geliver.md`), webhook #3245 (`carrier-webhook.md`).
3. Panel actions single + bulk; `shipment_labels` and `data/shipping_labels/`, retention 30/90 days (#3108, `workflow/decisions-made-schema-3.md`) (M).
   - 3a. Done in PR #3117 (`commerce/shipment-labels.md`): table, files, Create Label and reprint on the order, sweep, erasure, order delete.
   - 3b. Done in PR #3137 (`commerce/shipment-label-bulk.md`): bulk create and one merged PDF from the orders list, Cancel Label, return labels.
4. Status polling job in the dispatcher, write-back through the single path (S). Done in PR #3157 (`commerce/shipment-tracking.md`).
5. Direct drivers (M each): Aras done in PR #3259 (`commerce/carrier-label-aras.md`); Yurtiçi waits for docs.
   - 5c. API: done in PR #3220 (`commerce/shipment-label-api.md`).
6. Aggregators, done: Basit Kargo PR #3163 (`commerce/carrier-label-basitkargo.md`; one `.zpl` per bulk run open), Kargonomi PR #3286 (`commerce/carrier-label-kargonomi.md`); MNG ApiZone (M) waits for docs.
7. International (#3124), done: Shippo PR #3192 (`commerce/carrier-label-shippo.md`), Sendcloud PR #3226 (`commerce/carrier-label-sendcloud.md`); no customs yet.
8. Return label on the returns screen (#3178, `workflow/decisions-made-product-4.md`) (S).
   - 8a (2(a)). Done in PR #3194 (`commerce/shipment-label-returns.md`).
   - 8b (2(c)). Done in PR #3239 (classic view).
   - 8c. Done in PR #3268 (the widget's returns part).

## Open questions?
- Answered (#3073): Geliver first (public reference and test mode); the owner confirms its prices; no aggregator-specific fields in the shipment row.
- Formats (#3123): PDF, ZPL, SVG; ZPL never sent to a converter.

## Where to start?
- Step 5 Yurtiçi/Aras drivers (one with `track()` is tracked by the job as it is). Live checks of Geliver, Shippo and Sendcloud with the owner's keys.
