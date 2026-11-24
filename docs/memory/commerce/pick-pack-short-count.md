---
name: pick-pack-short-count
description: Short pick's count fix (#11346): drawer switch asks the shelf figure, the location gets max(0, shelf − units held by paid unsent orders), the count movement note, the held query
module: pack
---

## How is the count set down?
- Each row of a product that tracks stock (`products.inventory = 1`, `pg_pack_tracked_products()`) has the switch "Set this location's count to what was found" and a number, default 0. Help: "The units still on the shelf." and "The units of orders waiting to be sent are taken off."
- `pg_pack_count_fixes()` keeps only switched-on, still missing, tracked products with a whole number 0..`PG_PACK_COUNT_MAX` (the shelf figure).
- Each is `pg_stock_set()` at the order's location (`pg_pack_address()['location_id']`, 0 without locations) with reason `count`, ref the order number, note "Counted at a short pick: {shelf} on the shelf, {held} held by orders waiting to be sent", then `pg_marketplace_product_changed()`. The audit line's `counted` holds shelf, held and count per product.

## Why is the shelf figure lowered? (#11346)
- A location's count is the shelf less the units paid, unsent orders took at completion (`includes/fn/stock_sales.php`). Writing the shelf figure made those units sellable again. Example: shelf 3, orders B and C hold 3 → count 0, not 3.
- Architect (2026-11-24): keep asking for the shelf; write `pg_pack_count_after()` = max(0, shelf − held). Shelf below held writes 0; the short stays recorded.
- Held = `pg_pack_held_units()` over `pg_pack_held_rows()` (one query per product): per address, `order_items.stock_taken` (the quantity when NULL) plus bundle components' `stock_taken`, of orders `complete`/`exported`, paid, placed in the last `PG_PICK_WAITING_DAYS`, at the same location (`pg_pick_location_of()`). An address with a parcel number or a shipped unit holds nothing.
- The short pack's own address counts only units neither scanned (in the box) nor missing.

## What pins it?
- `tests/pack_short_test.php` (`pg_pack_held_units()`, `pg_pack_count_after()`). The query was not run on a database.

Related: docs/memory/commerce/pick-pack-short.md, docs/memory/commerce/order-stock-taken.md
