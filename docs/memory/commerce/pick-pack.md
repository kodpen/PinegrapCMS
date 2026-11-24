---
name: pick-pack
description: Pick and pack start file: shelf address, Orders › Pick waves, Orders › Pack barcode check, label on a checked pack, Short…, Packing card, API; K1-K16 with PRs, what is not built
---

## What is it, and what binds it?
- Owner (2026-11-20): pick and pack in the store (agent channel poll 181, message 10582): shelf addresses, waves in shelf order, a barcode check that stops a wrong or short parcel, label and marketplace accept on a checked pack, short picks, measures, events, API.
- A parcel is an address (`ship_tos`), as labels count it. Order statuses stay as they are: packed and short are the `order_packs` row, shown beside the status, never a status word.
- Stock moves only through `pg_stock_change()` / `pg_stock_set()` (`stock-locations-model.md`); packing takes no stock, a short pick's count fix is a `stock_moves` line with reason `count`.
- A label only through `pg_shipment_label_create()` (`shipment-labels.md`); a marketplace's own label stays its own. Works with and without stock locations, and by typed code without a scanner or camera.
- Architect (2026-11-20): four tables, no column on `products` or `product_stock`; no scan table, the counts sit on the `order_packs` row with one `audit_log` line when a pack closes; the label gate is a site switch, off by default; a "Packing only" right.

## Which file holds what?
- Tables, keys, deletions, the "Packing only" right: `pick-pack-tables.md`. Pure rules (sort, wave rule, verdicts, pack state): `pick-pack-rules.md`.
- Shelf address on the product screen, Modify Selected, import, inventory API: `pick-pack-shelf-address.md`. Pick waves, a wave's screen, the printed pick list, the order code: `pick-pack-waves.md`.
- Pack screen, scans, what a checked pack does, `order.packed`: `pick-pack-pack.md`. The label switch: `pick-pack-label-gate.md`. Short…, count fix, `pack_short` alert, Packing card: `pick-pack-short.md`.
- API routes and MCP tools: `api/api-pick-pack-routes.md`.

## Which features are built, and by which PR?
- The four tables and the "Packing only" right: #10873. K1 shelf address per product and location, four writers: #10966. K2 shelf sort ("A-3" before "A-10", none last): #10843.
- K3 wave by rule (carrier, marketplace, location, ship-by, single-item) and the ship-by day: #11131. K4 one open wave per address, a cancelled order leaves: #10843, #11131. K5 at most 200 addresses a wave, oldest ship-by first: #10843.
- K6 pick list on paper and phone, ticks: #11131. K8 order code (Code 128) on the packing slip and pick list, scanned on Pack: #11131, #11218.
- K9 one unit a scan, K10 refusals with reason and count, K11 typed code and camera: #10843, #11218. K12 checked pack: label, marketplace accept, audit line: #11218; its site switch: #11261.
- K13 short pick, count fix, `pack_short` alert: #11259. K14 Packing card: #11259. K15 `order.packed` #11218, `order.pack_short` #11259. K16 routes and MCP read tools for waves and packs: #11263.

## What is not built?
- K3 "Add to a wave" on the orders list: `pg_pick_wave_make()` already takes chosen addresses.
- K7 single-item waves packed straight from the pick: the pure choice `pg_pick_single_item_address()` is there, no screen uses it.
- K9 a box barcode counting its pack size. K16 an API route or MCP tool for the Packing card.
- Left out by the plan, each a later yes: weighing scales, box size choice, photos of the box, robot or conveyor links, cycle counting as a screen, putaway of incoming goods; Trendyol's `agreedDeliveryDate` as the ship-by day.

## How was it checked?
- Unit tests: `pick_pack_test.php`, `pick_waves_test.php`, `pick_pack_schema_test.php`, `product_bins_test.php`, `pack_screen_test.php`, `pack_before_label_test.php`, `pack_short_test.php`, `api_pick_waves_test.php`. The screens were not opened in a browser.

Related: docs/memory/commerce/stock-locations-model.md, docs/memory/commerce/shipment-labels.md, docs/memory/workflow/decisions-made-product-90.md
