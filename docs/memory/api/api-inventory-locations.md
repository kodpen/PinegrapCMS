---
name: api-inventory-locations
description: Stock per location in the external API and MCP: GET /inventory/locations, /inventory, /inventory/moves, POST /inventory/transfer; scopes, refusals, dry run, inventory.changed
---

## Which endpoints read and move stock per location?
- `GET /inventory/locations` (`inventory.locations`): `pg_stock_locations()` in one page, default first; `enabled` filters. Empty on a site without locations.
- `GET /inventory` (`inventory.list`): tracked products by id, cursor paged; `quantity` is the total, `locations` the units at each location (`api_inventory_level_present()`, pure). A product without `product_stock` rows holds its total at the default (`pg_stock_available_at()`, as the checkout counts). `location=` keeps one location (unknown: 422 `location`); `in_stock` with a location reads that location's units, without one the total. The page's rows come from one `IN (...)` read.
- `POST /inventory/transfer` (`inventory.transfer`): `pg_stock_transfer()` as the stock history's dialog, with the application owner as the moves' `user_id` (its `$options`). Answer: `product_id`, `quantity`, `from`/`to` (`location_id`, `quantity` left there), `total` (unchanged), `ref`.
- `GET /inventory/moves` (`inventory.moves`, PR #8097): the stock history of `view_stock_moves.php`, newest first by id, cursor paged; StockMove (`api_stock_move_present()`) with `product_name` and `location_name` (null when gone), `user_id` (null for none; no user name, which `users:read` guards). Filters `product_id`, `location` (unknown: 422 `location`, where the screen drops it), `reason` (enum `pg_stock_reasons()`), `from`/`to` as moments (a bare `to` day counts whole). The WHERE is the screen's `pg_stock_move_filters()` + `pg_stock_move_condition()` plus moment bounds and cursor (`api_inventory_moves_where()`, pure). 503 `service_unavailable` before the 2026.11.0 tables. `/erp/stock/moves` is a different list: ERP document lines.
- `inventory.php` loads `includes/fn/stock_moves.php`; before PR #8097 it did not, and a transfer on a site with locations died on `pg_stock_transfer_check()`.
- Every route is also an MCP tool (`inventory_locations`, `inventory_list`, `inventory_moves`, `inventory_transfer`); nothing else to declare.

## Which scopes?
- Reads are under `products:read` (stock was always read through products, so no `inventory:read` was added and no owner ceiling changed); the transfer under `inventory:write`.

## What does a transfer refuse, and what does a rehearsal check?
- `api_inventory_transfer_refusal()` (pure) maps the writer's error words: `quantity` 422 `validation_failed`; `from`/`to` 422 `location`; `same` 422 `validation_failed` on `to`; `to_disabled` 409 `location_disabled`; `not_enough` 409; `untracked` 409 `not_tracked`; `no_locations` 409; anything else 409 `refused`. A bundle is 409 `bundle`; an unknown product 404. Messages are `pg_stock_transfer_error_text()`'s, so panel and API say the same.
- The dry-run stop comes after the product, the location check and the units at `from` (read without a lock; the writer checks again under `FOR UPDATE`).

## What do the events carry?
- `inventory.changed` and `stock.low` add `location_id`, `location_name`, `location_quantity` when a location changed (`commerce/stock-locations-completion.md`); `quantity` stays the total, which marketplaces and ERP keep receiving. A transfer is not announced (the total is the same) and queues no marketplace; the event catalogue text says so.

## What guards it?
- `tests/stock_locations_api_test.php`: the presenters, the refusal map, the route rows (paths, scopes, dry run, the note's bound, MCP offer). `tests/stock_moves_api_test.php`: StockMove, the WHERE for every reason, the moves route row.
