---
name: shipping-carrier-labels
description: Plan (approved 2026-10-19; 1-4, 6-8 partly done): carrier APIs for labels and barcodes, bulk labels, status write-back; aggregator first, then Yurtiçi and Aras direct, then abroad
---

## Why?
- No Turkish carrier API existed; competitors ship many with thermal labels and tracking write-back.

## What is decided?
- Base: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` of `shipping-management.md`; this plan never writes tracking numbers another way.
- First driver: one shipping aggregator (single API, many carriers, contracted rates), chosen by the owner; then Yurtiçi Kargo and Aras Kargo direct APIs. Owner (#3073): open integrations (public docs, test mode) first, then every aggregator and direct carrier; international too, the US and Europe first. Driver `includes/shipping/<driver>.php`: create shipment → label (PDF/ZPL) + barcode + tracking number; cancel; track; pickup request where supported.
- Panel: create on the order and in bulk (one PDF), reprint, status refresh.
- Status write-back: the job polls open shipments and calls `pg_shipment_delivered()` (events fire once); webhooks where offered.
- Return labels: the same driver creates a return shipment for customer returns with the named return carrier (step 8).
- UPS/FedEx/USPS rate code and box packing stay (their labels: Shippo, step 7).
- Marketplace orders: Trendyol common label (`createCommonLabel`, ZPL for TEX/Aras) is the marketplace connector's job.

## What remains?
1. Done #2378: driver interface, settings card, connection test (`commerce/carrier-label-drivers.md`).
2. Geliver driver (#3073). Done: PR #3096 (`commerce/carrier-label-geliver.md`), webhook #3245 (`carrier-webhook.md`).
3. Panel actions single + bulk; `shipment_labels` and `data/shipping_labels/`, retention 30/90 days (#3108, `workflow/decisions-made-schema-3.md`) (M).
   - 3a. Done #3117 (`commerce/shipment-labels.md`): table, files, Create Label, reprint, sweep, erasure, order delete.
   - 3b. Done #3137 (`commerce/shipment-label-bulk.md`): bulk create, one merged PDF, Cancel Label, return labels; bulk .zpl #3315.
4. Done #3157: status polling job, write-back through the single path (`commerce/shipment-tracking.md`).
5. Direct drivers (M each): Aras done #3259 (`commerce/carrier-label-aras.md`); Yurtiçi waits for docs.
   - 5c. API: done #3220 (`commerce/shipment-label-api.md`).
6. Aggregators, done: Basit Kargo #3163 (`commerce/carrier-label-basitkargo.md`), Kargonomi #3286 (`commerce/carrier-label-kargonomi.md`); MNG ApiZone (M) waits for docs.
7. International (#3124), done: Shippo #3192 (`commerce/carrier-label-shippo.md`), Sendcloud #3226 (`commerce/carrier-label-sendcloud.md`); 7b Shippo webhook #3407; 7c Sendcloud return labels #3409; Sendcloud webhook #3431.
   - 7d Customs, decided #3408 (`workflow/decisions-made-product-7.md`): C1 #3429 (`commerce/product-customs-fields.md`); C2 #3449 Shippo (`commerce/carrier-label-customs.md`); C3 #3474 Sendcloud (`commerce/carrier-label-sendcloud-customs.md`). Next: products API `hs_code`/`origin_country` after #3298 (holds `schema.php`).
8. Done: return label on the returns screen (#3178): 8a #3194 (`commerce/shipment-label-returns.md`), 8b #3239 (classic), 8c #3268 (widget).
9. Pickup requests, decided #3415 (`workflow/decisions-made-product-7.md`): orders list and label card action, `pickup_ref`/`pickup_at` on the label; Shippo and Sendcloud first (M).
   - P1 done #3475 (`commerce/shipment-label-pickup.md`): columns, Shippo, list and card.
   - P2 done #3496: Sendcloud; the others have no pickup endpoint. P3: API fields `pickup_ref`/`pickup_at` after #3298.

## Open questions?
- #3073: Geliver first; the owner confirms its prices; no aggregator-specific fields in the shipment row.
- Formats (#3123): PDF, ZPL, SVG; no converter.

## Where to start?
- 7d API fields after #3298; 9 P3 API fields after #3298. Yurtiçi waits for docs. Live checks need the owner's keys.
