---
name: api-webhook-stock-events
description: What a stock change announces through pg_product_stock_changed() and its pure rules, which writers stay quiet, and which panel writers announce product.updated
---

## What must a stock write do for the outside?
- Move `products.timestamp` (so `GET /products?updated_since=` lists it), announce `inventory.changed`, `stock.low` once when the level falls through `ECOMMERCE_LOW_STOCK_THRESHOLD`, and queue the marketplace (`pg_marketplace_product_changed()`).
- One call does all four: `pg_product_stock_changed($product_id, $before, $after, $source)` in `includes/fn/ecommerce.php`, once per product after the row is written. `$before`: the stock read before the write (`null`: not tracked until now); `$after`: what the write left, `null` reads the row back (the checkout decrements in SQL). It returns the stock left; a loop meeting a product twice (two recipients of one order) passes it as the next `$before`.
- Pure, tested rules (`tests/product_stock_events_test.php`): `pg_product_stock_events()` gives the events and payloads; `pg_product_stock_delta()` decides whether a whole-row save moved the stock (same count: nothing; tracking switched on with a count: a change from no level; switched off: nothing).

## What do the payloads carry?
- `inventory.changed`: `product_id`, `quantity`, `out_of_stock` (the stock API's keys) and `source`: `checkout`, `local_sale`, `panel` (product screen, list quick edit), `barcode`, `bulk_edit`. The API, Workspace and ERP keep their own writers and payloads (no `source`).
- `stock.low`: `product_id`, `name`, `quantity`, `threshold`. Only on the crossing (`before > threshold` or no level before), only where the threshold is set; an untracked product announces nothing.

## Who calls it, and who stays quiet?
- Calls it: the checkout (`submit_order.php`), a local sale (`includes/local_sale.php`), the product screen's save and the list's stock cell (`product_builder.php`), the barcode screens (only when the scan moved the stock, else just the marketplace queue; issue #1163), the bulk edit of selected products (`edit_products.php`, only rows whose stock moved; a few rows on purpose, not an import).
- Keep their own: the stock API (`includes/api/resources/inventory.php`), the Workspace (`includes/workspace/changes.php`) and an ERP document (`includes/erp/stock.php`) stamp and announce themselves.
- Quiet by the catalogue's bulk rule (no `inventory.changed`, no `stock.low`): the product import, a marketplace order import or cancellation (`mp_order_take_stock()`, `mp_order_return_stock()` in `includes/api/outbound/orders.php`, which keep their priority-1 marketplace queue call) and an ERP stock count (`erp_stock_count_apply()`). Quiet is not invisible: each still sets `timestamp = UNIX_TIMESTAMP()` in its stock statement, so `updated_since` lists it. The catalogue line in `api_webhook_events()` says both; `product_stock_events_test.php` checks those stamps.

## Which panel writers announce `product.updated`?
- The product screen's save (`pg_pb_update_product()`, after every write) and the list's quick edit (`pg_pb_quick_update()`, only when the cell changed) call `pg_announce('product.updated', {id, name})`, the payload of `api_products_update()` and the Workspace. A stock change there announces it beside `inventory.changed`. The bulk edit stays quiet. Test: `tests/product_updated_event_test.php`.

## What was wrong before?
- A checkout or local sale neither moved the stamp nor announced (only queued the marketplace), so systems syncing by `updated_since` or `inventory.changed` sold old stock; the product screen stamped but did not announce; the bulk edit and barcode screens announced nothing. Issue #871, PR #1141.
- Then a marketplace order import or cancel and the ERP stock count still wrote stock unstamped, so `updated_since` missed those products and nothing announced them. Issue #1161, PR #1186.
- Neither panel writer announced `product.updated`, so subscribers and Workspace event rules missed a name, price or description saved there. Issue #1164.
