---
name: carrier-label-roadmap
description: Carrier label drivers as a whole: the rules every driver keeps (one write path, polling then webhooks, return labels, what stays outside), the order they came in, which wait for documentation
---

## Which rules does every label driver keep?
- Base: the shipment row (`shipping_tracking_numbers` with `carrier`, `status`, `source='carrier'`) and the single write path `pg_shipment_record()` (`shipment-write-path.md`); a driver's tracking number reaches an order no other way.
- A driver (`carrier-label-drivers.md`): create a shipment → label (PDF, ZPL or SVG), barcode and tracking number; cancel; track; pickup where the service has one. Panel: create on the order and in bulk (one PDF), reprint, status refresh.
- Status write-back: the hourly job polls open shipments and calls `pg_shipment_delivered()`, so the events fire once (`shipment-tracking.md`); webhooks where the service offers them (`carrier-webhook.md`).
- Return labels: the same driver makes a return shipment for a customer return with the named return carrier (`shipment-label-returns.md`).
- Kept outside: the UPS/FedEx/USPS rate code and box packing stay (their labels go through Shippo); the Trendyol common label (`createCommonLabel`) is the marketplace connector's job (`trendyol-common-label.md`).

## In which order did drivers come, and what waits?
- Owner (#3073, `workflow/decisions-made-product-2.md`): open integrations first (public reference and test mode), then every aggregator and direct carrier; abroad too, the US and Europe first.
- Built in 2026.11.0: Geliver, Basit Kargo, Kargonomi, Shippo, Sendcloud, Aras (each `carrier-label-<key>.md`), bulk labels, pickups, returns, customs, webhooks.
- Waiting for documentation: Yurtiçi Kargo's direct API and the MNG ApiZone aggregator. Live checks of any driver need the owner's keys.
