---
name: pick-pack-features
description: Plan pick-pack, features K1 to K16: shelf addresses, waves and their rules, the pick list, the pack check, the checked pack, short picks, measures, events, API
---

## How is a shelf address kept?
- (K1) One address per product and stock location ("A-03-2"), typed on the product screen's stock card, by Change Selected, by product import and by `POST /products/{id}/inventory`; without locations one per product. Kept in `product_bins`; no column on `products` or `product_stock`.
- (K2) Sorting compares the address part by part, numbers as numbers ("A-3" before "A-10"); a product with none comes last, by product code.

## What is a wave?
- (K3) A wave is a set of addresses to pick together: chosen on the orders list ("Add to a wave"), or made by a rule: paid, not cancelled, no checked pack yet, filtered by carrier, marketplace, stock location, ship-by day, single-item addresses only. Ship-by day: the address's `ship_date`, else the paid day plus the method's handling days.
- (K4) An address is in one open wave at most; a cancelled order leaves its wave by itself and the list says so.
- (K5) Sizes: at most 200 addresses a wave; a rule fills oldest ship-by day first.

## What is the pick list?
- (K6) One line per product and location: shelf address, code, name, variant options, picture, total units, how many addresses want it; in shelf order. Printed (light paper) and on a phone, where a line is ticked as picked.
- (K7) Single-item waves are packed straight from the pick: each scanned item finds its address, oldest first.

## How is a pack checked?
- (K8) The packer scans the order's code (Code 128 of the order number, printed on the packing slip and the pick list) or picks the address; the screen lists its lines with picture, options and units.
- (K9) Each product scan counts one unit (a box's barcode with its pack size later); the product is found as the stock screens find it.
- (K10) A product not on the address, a unit past the line, a binned product: refused with a sound and the reason; the refusal is counted. A variant of the right group is named as such ("This is size M; the order wants L").
- (K11) Typed code when no scanner; a phone camera through `BarcodeDetector`, as event check-in does.

## What happens when a pack is checked?
- (K12) All units scanned: the pack is `packed` with packer and time; the label is made through the existing path and opened for printing when the address has none; a marketplace package still `Created` is accepted (Picking) through `mp_sync_approve()`; `order.packed` is announced. Scan counts sit on the pack's row; one `audit_log` line when it closes. A site switch (off by default) makes a checked pack a must before a label.

## What is a short pick?
- (K13) "Short" closes the pack as `short` with the missing lines: the address waits, the order screen and the wave show it, staff are told through `pg_ops_alert()` code `pack_short` once a day, `order.pack_short` is announced. The location's count can be set down from there, as a `stock_moves` line with reason `count`.

## What is measured?
- (K14) A "Packing" card: addresses waiting, the oldest, those due today or late by ship-by day, packed today; per packer this week: packs, units an hour, refused scans. Read from the pack records, nothing kept beside.

## Which events and API?
- (K15) `order.packed` and `order.pack_short` (order, address, wave, packer id, units, missing lines by product id; no customer data) through `pg_announce()`, in the catalogue, fixtures and flows.
- (K16) `GET /pick-waves`, `GET /pick-waves/{id}` (the pick list), `GET /orders/{id}/packs`; `PUT /products/{id}/shelf` for addresses; an MCP read tool for waves and the packing card.
