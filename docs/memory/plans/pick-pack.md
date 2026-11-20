---
name: pick-pack
description: Plan (owner 2026-11-20, poll 181): shelf addresses, pick waves in shelf order, barcode pack checks that stop a wrong or short parcel, label on a checked pack, measures, events; PP1 to PP7
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

## What does the architect decide?
- Schema: the shelf address as a column on `product_stock` and one on `products` (no locations), or a `product_bins` table (product, location, address)?
- Schema: `pick_waves` and `pick_wave_orders`, and an `order_packs` row per address (state, packer, times, counts of good and wrong scans, the short lines); each scan an `audit_log` line or a `pack_scans` table?
- Who packs: the orders right as today, or a new "packing only" permission column so warehouse staff see no prices or e-mails?
- Must a pack be checked before a label is made (a site switch, off by default), or is the check only offered?
- The ship-by day: the address's `ship_date` when set, else the paid day plus the method's handling days; Trendyol's `agreedDeliveryDate` stored later?

## What is built?
- Features: `pick-pack-features.md` (K1 to K16). Steps: `pick-pack-steps.md`. Claim as `[plan:pick-pack] <step> — <topic>`.

## What is left out?
- Weighing scales, box size choice, photos of the packed box, robot or conveyor links, cycle counting as its own screen, putaway of incoming goods: each a later yes.

## Whose look?
- No pictures (`workflow/look-pictures-none.md`): the wave and pack screens are seen on dev after PP3 and PP4.
