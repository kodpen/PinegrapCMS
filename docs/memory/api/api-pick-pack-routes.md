---
name: api-pick-pack-routes
description: GET /pick-waves, GET /pick-waves/{id} (the pick list), GET /orders/{id}/packs and POST /products/{id}/shelf (PUT too): scopes, fields, status words, MCP tools, what is not built
---

## Which routes are there?
- All in `includes/api/resources/pick_waves.php` (PP6 of the pick-pack plan). Read only for waves and packs: making, ticking, scanning and closing stay on the Pick waves and Pack screens (`commerce/pick-pack-waves.md`, `commerce/pick-pack-pack.md`).
- `pick_waves.list` GET /pick-waves (`orders:read`, `PickWave` list): `pg_pick_wave_list($status, $before_id, $limit)`, newest first, cursor on the id, `status` open, picked or closed.
- `pick_waves.get` GET /pick-waves/{id} (`orders:read`, `PickWaveDetail`): the wave plus `lines` (`pg_pick_wave_lines()` through `pg_pick_list_ticked()`: shelf order, ticked lines last, `picked`), `orders` (`pg_pick_wave_addresses()`, a cancelled order keeps its row with `removed_at`), `picked_lines` of `total_lines`.
- `orders.packs` GET /orders/{id}/packs (`orders:read`, `OrderPack` list): one per `ship_tos` row with active lines, read by `pg_pack_read()` (never writes); `missing` is `pg_pack_missing()` of the lines.
- `products.shelf` POST /products/{id}/shelf, `also_accepts` PUT (IIS may refuse PUT), `inventory:write`, dry run: `shelf` (required, nullable, max 40, `pg_bin_clean()`), `location` as on POST /products/{id}/inventory; written by `pg_bins_write()` at `pg_bin_location_key()`. 409 `bundle`; 503 `service_unavailable` before step 11.0.264. Answer `ProductShelf` (product_id, location, shelf or null).

## Which words does a status read?
- A wave: open, picked, closed (`api_pick_wave_statuses()`). A pack: waiting (no `order_packs` row yet), open, packed, short (`api_pick_pack_status_name()`).
- Null instead of 0 or '': a wave's `name` and `location_id`, a pack's `wave_id` and `packer_id`, a line's `product_id` (product deleted) and `shelf`.

## What is left out?
- No price, total, customer name or address on any of them, as on the screens. Rows are not narrowed for the user role (3): the screens narrow none; a "Packing only" user holds no `orders:read`.
- Each GET is an MCP tool by itself (`pick_waves_list`, `pick_waves_get`, `orders_packs`). The Packing card (K14) has no route or MCP tool: not built (`commerce/pick-pack.md`).

## How is it tested?
- `tests/api_pick_waves_test.php` (route rows, presenters against their `*_schema()`, status words); `php tools/check_api_schema.php` pairs `PickWave` and `OrderPack` with their presenters. Not run against a database.

Related: docs/memory/commerce/pick-pack-waves.md, docs/memory/commerce/pick-pack-pack.md, docs/memory/commerce/pick-pack-shelf-address.md
