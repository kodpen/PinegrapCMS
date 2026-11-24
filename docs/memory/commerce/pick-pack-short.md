---
name: pick-pack-short
description: Short picks (PP5, #11259): the "Short…" drawer, the count fix as a stock_moves count line, Finish the pack, pack_short alert, order.pack_short, the Packing dashboard card
---

## How is a pack closed short?
- Pack screen foot "Short…" (quiet) opens drawer `#pack_short`: a row per line not full ("2 missing", recounted from the cards when it opens), "Close as short" fill, Cancel quiet. `request=short` → `pg_pack_close_short()` under the address lock: status 2, `packer_id`, `short_at`, `units`, `scanned`, `missing` JSON (product_id => units, `pg_pack_missing()`).
- Refused when every unit is scanned or the pack is empty (`not_short`) or already closed. A short pack with nothing scanned is allowed.
- The answer turns the screen to the "Short" pill with what followed and "Next order", as a packed one does.

## How is a location's count set down?
- Each row of a product that tracks stock (`products.inventory = 1`, `pg_pack_tracked_products()`) has the switch "Set this location's count to what was found" and a number, default 0 (the units still on the shelf).
- `pg_pack_count_fixes()` keeps only switched-on, still missing, tracked products with a whole number 0..`PG_PACK_COUNT_MAX`. Each is `pg_stock_set()` at the order's location (`pg_pack_address()['location_id']`, 0 without locations) with reason `count`, ref the order number, note "Counted at a short pick", then `pg_marketplace_product_changed()`.

## What follows a short close?
- `pg_pack_after_short()`: the count fixes, one `pg_audit('pack', 'order_pack', …)` line with missing and counted, `pg_ops_alert('pack_short', …)` (group Deliveries, link Pick waves; once a day, no subject), `order.pack_short`.
- `order.pack_short` carries `pg_pack_event_payload()` as `order.packed` does, `missing` filled. Registered in the webhook catalogue and samples, the Workspace events, watch catalogue, flow outputs (units, units in the box, wave) and the Orders flow area.

## Where does a short pack show?
- An address closed short still waits: `pg_pick_waiting_addresses()` flags only status 1 as packed, so it stays in "or pick an address" and the Packing card's waiting count.
- `pg_pack_short_notice()`: "Short" pill, "Missing: Cap × 2" (`pg_pack_missing_list()`), "Finish the pack" to `pack.php?ship_to=N`. On the order screen under the shipping address (only when `pg_pick_waves_ready()`), and in a wave's Addresses view (`pg_pick_wave_addresses()` reads `pack_missing`).
- "Finish the pack" on the short Pack screen posts `request=reopen` (`pg_pack_reopen()`: status 0, missing NULL, scans kept, `short_at` left as it was).

## What does the Packing card show?
- New dashboard card `packing` (`includes/dashboard/v2/cards/packing.php`, renderer in `pg_dash_cards_shop.js`), width M, gate `shop`: the orders right, never "Packing only" alone.
- Tiles: Waiting, Oldest (earliest known ship-by day), Due today or late, Packed today. Rows "This week" a packer: packs, units an hour, refused. `pg_pack_card_figures()` from `pg_pack_card_data()`: packs packed since Monday (`pg_pack_week_start()`), short ones counted for refused scans only.
- Units an hour: each pack's first scan to close, at most `PG_PACK_TIME_CAP` (1 hour); no figure under one minute in all.

## What pins it?
- `tests/pack_short_test.php`. Not opened in a browser or run on a database.

Related: docs/memory/commerce/pick-pack-pack.md, docs/memory/commerce/pick-pack-rules.md, docs/memory/commerce/pick-pack-tables.md
