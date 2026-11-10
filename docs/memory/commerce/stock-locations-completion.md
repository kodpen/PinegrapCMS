---
name: stock-locations-completion
description: Stock per location at sales, scans and the inventory API: the order's location, the checkout check, completion, barcode screens, the API location parameter, location keys on stock events
module: stock_sales
---

## Which location does an order take its units from?
- `pg_stock_order_location($order_id)` (`includes/fn/stock_sales.php`): the location stored on the order while it is enabled, else the first enabled location a recipient's shipping method names (`shipping_methods.stock_location_id`, recipients in `ship_tos.id` order), else the default (`pg_stock_pick_order_location()`, pure). 0 without locations.
- Completion (`pg_order_complete_lines()`) and the local sale store it with `pg_stock_order_location_save()`; a till has no shipping method, so a local sale uses the default.

## What does the checkout refuse? (#5042 question 2)
- `check_inventory()` counts the units at the cart's location (`pg_stock_quantity_at()`, `pg_stock_available_at()`: a product with no rows yet holds its total at the default, nothing elsewhere) in place of the total: a line the location cannot cover is shortened or removed with the old notices. A cart with a bundle counts at the location too (`pg_bundle_check_inventory()`, #8531). Nothing counts from another location.

## What does completion take?
- `pg_order_complete_line_stock()`, `pg_bundle_take_stock()` (optional `$location_id`; checkout passes the order's) and the local sale call `pg_stock_sale_take()`: the line's quantity or what the location holds, whichever is less (`pg_stock_sale_amount()`), through `pg_stock_change()`, read again and retried up to 3 times when a parallel writer moved the row (`pg_stock_change_from_row()`). A location with none left takes nothing and announces nothing; the out-of-stock flag still reads the total.
- Without locations `pg_stock_sale_take()` answers null and each caller runs its old decrement of the total: the same writes as before (source pins in the test).

## How do the barcode screens count?
- Both scan screens show a "Stock Location" select (`pg_stock_scan_location_field()`, default chosen) and send `location_id`; `pg_stock_requested_location()` refuses one that is not a location. A tracked product's units change at that location (`pg_stock_scan()`: a decrease takes what is there, down to 0); the screen's UPDATE then writes only the flag, user and timestamp. Untracked products and sites without locations keep the old total write.

## What does the inventory API do with `location`?
- `POST /products/{id}/inventory` and each batch item take `location` (0 or left out: the default). An unknown one: 422 `location` / item `invalid`, before the dry-run stop. With locations, `pg_stock_api_delta()` turns set/adjust into a delta at the location (adjust stops at 0 there), the writer recomputes the total, then `out_of_stock` follows the total. A set on an untracked product switches tracking on first. A refused change (past the column's top): 409 `refused` / item `refused`. The answer adds `location_id`, `location_quantity` (declared `integer?`).

## What do the events carry?
- `pg_product_stock_changed()` and `pg_product_stock_events()` take an optional `$location` (id, name, quantity left there): `inventory.changed` and `stock.low` then carry `location_id`, `location_name`, `location_quantity` (`pg_stock_event_location_fields()`); `quantity` stays the total. Without a location the payloads are unchanged.

## Which writers still change the total only?
- Marketplace order import and cancellation (`mp_order_take_stock()`, `mp_order_return_stock()`), order cancellation, ERP, Workspace, product import: the plan leaves their location to later steps; with locations a later `pg_stock_change()` recomputes the total from the rows.

## What guards it?
- `tests/stock_locations_completion_test.php`: the order location pick, units at a location, the sale and API amounts, the event keys and the payload parity, source pins of every caller's no-locations path, the API route params.
