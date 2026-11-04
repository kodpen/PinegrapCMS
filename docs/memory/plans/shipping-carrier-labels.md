---
name: shipping-carrier-labels
description: Plan (approved 2026-10-19): carrier APIs for labels and barcodes, bulk labels, status write-back; aggregator first, then Yurtiçi and Aras direct, then abroad
---

## Why?
- No Turkish carrier API existed; competitors ship many with labels and tracking.

## What is decided?
- Base: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` of `shipping-management.md`; this plan never writes tracking numbers another way.
- First driver: one shipping aggregator (single API, many carriers, contracted rates), chosen by the owner; then Yurtiçi Kargo and Aras Kargo direct APIs. Owner (#3073): open integrations (public docs, test mode) first, then every aggregator and direct carrier; international too, the US and Europe first. Driver `includes/shipping/<driver>.php`: create shipment → label (PDF/ZPL) + barcode + tracking number; cancel; track; pickup request where supported.
- Panel: create on the order and in bulk (one PDF), reprint, status refresh.
- Status write-back: the job polls open shipments and calls `pg_shipment_delivered()` (events fire once); webhooks where offered.
- Return labels: the same driver creates a return shipment for customer returns with the named return carrier (step 8).
- UPS/FedEx/USPS rate code and box packing stay (their labels: Shippo, step 7).
- Marketplace orders: Trendyol common label (`createCommonLabel`, ZPL for TEX/Aras) is the marketplace connector's job.

## What remains?
- Done: 1 #2378, 2 #3096 #3245, 3a #3117, 3b #3137 #3315, 4 #3157, 5 Aras #3259, 5c #3220, 6 Basit Kargo #3163 #3645, Kargonomi #3286 #3633, 7 Shippo #3192 #3407, Sendcloud #3226 #3409 #3431, 7d C1 #3429, C2 #3449, C3 #3474, C4 #3975, 8a #3194, 8b #3239, 8c #3268, 9 P1 #3475, P2 #3496, P3 #3937.
5. Direct drivers (M each): Yurtiçi waits for docs.
6. Aggregators: MNG ApiZone (M) waits for docs.

## Where to start?
- Yurtiçi waits for docs. Live checks need the owner's keys.
