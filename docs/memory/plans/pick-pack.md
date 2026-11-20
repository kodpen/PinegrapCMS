---
name: pick-pack
description: Plan (owner 2026-11-20, poll 181): shelf addresses, pick waves in shelf order, barcode pack checks that stop a wrong or short parcel, label on a checked pack, measures, events; PP1a to PP7
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 181, message 10582): "Evet, plana girsin": shelf addresses, pick waves, barcode packing, label and marketplace status on a checked pack, short picks, measures, events and API.

## What is there today?
- Stock per location: `stock_locations`, `product_stock`, `stock_moves`; one writer `pg_stock_change()` (`commerce/stock-locations-model.md`); the order's location `pg_stock_order_location()` (`commerce/stock-locations-completion.md`).
- Barcodes: `product_barcodes` and `products.name`, read by `pg_barcode_scan_product()`; the scan screens only raise or lower stock (`commerce/barcode-stock-scan-checks.md`, `ui/barcode-scanner-detection.md`). A variant is its own product.
- Print Order and the packing slip, one order at a time, no order barcode (`ui/print-views-light-paper.md`).
- Labels per address (`ship_to_id`): one write path, bulk, automatic once paid (`commerce/shipment-label-auto.md`, `commerce/shipment-label-bulk-create.md`).
- Marketplace accept sends Picking only for a `Created` package (`mp_sync_approve()`, `commerce/marketplace-order-status-sync.md`).
- No shelf address, no list across orders, no check of what goes into the box, no record of who packed.

## What binds it?
- A parcel is an address (`ship_to_id`), as labels count it.
- Order statuses stay as they are (`commerce/order-status-moves.md`); "packed" and "short" are the plan's own record, shown beside the status, never a new status word.
- Stock moves only through `pg_stock_change()`; packing takes no stock (completion already did). A count fix is a staff action with a `stock_moves` reason.
- A label only through `pg_shipment_label_create()` and the automatic label's rule (`workflow/decisions-made-product-39.md`); a marketplace's own label stays its own.
- Marketplace calls only through the existing accept path; nothing new is pushed to a buyer.
- Works with and without stock locations; works by typed code when no scanner or camera.

## What did the architect decide? (2026-11-20, agent channel message 10748)
- Schema: four new tables, `product_bins` (an address per stock location), `pick_waves`, `pick_wave_orders` and `order_packs`; no column on `products` or `product_stock`. Asked of the owner in poll 193 (message 10746); PP1b waits for the yes, and the architect writes the columns into the schema decisions list.
- No `pack_scans` table: the scan counts sit on the `order_packs` row, and one `audit_log` line is written when a pack closes (K12).
- A checked pack before a label: a site switch, off by default (K12).
- The ship-by day as K3 says; Trendyol's `agreedDeliveryDate` later.
- A "packing only" right (no prices, no customer e-mail): asked in poll 193; if yes, its column joins PP1b under the `core/` role rules.

## What is built?
- Features: `pick-pack-features.md` (K1 to K16). Steps: `pick-pack-steps.md`. Claim as `[plan:pick-pack] <step> — <topic>`.

## What is left out?
- Weighing scales, box size choice, photos of the packed box, robot or conveyor links, cycle counting as its own screen, putaway of incoming goods: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the wave and pack screens are seen on dev after PP3 and PP4.
