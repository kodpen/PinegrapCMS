---
name: api-webhook-stock-events
description: What a stock change announces: pg_product_stock_changed() (stamp, inventory.changed, stock.low on the crossing, marketplace queue) behind every site writer, its pure rules, which writers stay quiet
---

## What must a stock write do for the outside?
- Move `products.timestamp` (a system syncing with `GET /products?updated_since=` lists the product), announce `inventory.changed`, announce `stock.low` once when the level crosses `ECOMMERCE_LOW_STOCK_THRESHOLD` on the way down, and queue the marketplace (`pg_marketplace_product_changed()`).
- One call does all four: `pg_product_stock_changed($product_id, $before, $after, $source)` in `includes/fn/ecommerce.php`, called once per product after the row is written. `$before` is the stock the caller read before its write, `null` when the product did not track stock until now; `$after` is what the write left, `null` reads the row back (the checkout's decrement happens in SQL). It returns the stock left, so a loop that meets the same product twice (two recipients of one order) carries it on as the next row's `$before`.
- The rules are pure and tested (`tests/product_stock_events_test.php`): `pg_product_stock_events()` gives the events with their payloads; `pg_product_stock_delta()` decides whether a save of the whole row moved the stock (same count again: nothing; tracking switched on with a count: a change from no level; tracking switched off: nothing).

## What do the payloads carry?
- `inventory.changed`: `product_id`, `quantity`, `out_of_stock` (the keys the stock API's writer sends) and `source`: `checkout`, `local_sale`, `panel` (product screen and the list's quick edit), `barcode`, `bulk_edit`. The API, the Workspace and the ERP keep their own writers and payloads (no `source`).
- `stock.low`: `product_id`, `name`, `quantity`, `threshold`. Only on the crossing (`before > threshold`, or no level before) and only where the site set the threshold; a product that does not track stock announces nothing.

## Who calls it, and who stays quiet?
- Calls it: the checkout (`submit_order.php`), a local sale (`includes/local_sale.php`), the product screen's save and the product list's stock cell (`product_builder.php`), the barcode screens, the bulk edit of selected products (`edit_products.php`, only the rows whose stock really moved). A selection on the list is a few rows changed on purpose, not an import.
- Keep their own: the stock API (`includes/api/resources/inventory.php`), the Workspace (`includes/workspace/changes.php`) and an ERP document (`includes/erp/stock.php`) stamp and announce inside their own statements already.
- Quiet by the catalogue's bulk rule (no `inventory.changed`, no `stock.low`): the product import, a marketplace order import or cancellation (`mp_order_take_stock()`, `mp_order_return_stock()` in `includes/api/outbound/orders.php`, which keep their priority-1 marketplace queue call) and an ERP stock count (`erp_stock_count_apply()`). Quiet is not invisible: each still sets `timestamp = UNIX_TIMESTAMP()` in the statement that writes the stock, so `updated_since` lists the product. The catalogue line in `api_webhook_events()` says both; `tests/product_stock_events_test.php` checks the stamp in those statements.

## What was wrong before?
- A sale through the checkout or a local sale did not move the stamp and announced nothing (only the marketplace queue), so a system syncing with `updated_since` or `inventory.changed` kept selling the old stock; the product screen moved the stamp but announced nothing; the bulk edit and the barcode screens announced nothing. Issue #871, fixed in PR #1141.
- After that, a marketplace order import or cancellation and an ERP stock count still wrote the stock without the stamp, so `updated_since` never listed those products and nothing announced them. Issue #1161.
