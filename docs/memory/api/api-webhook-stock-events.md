---
name: api-webhook-stock-events
description: pg_product_stock_changed(): rules, callers, quiet writers, the out_of_stock flag; panel writers announcing product.updated
---

## What must a stock write do?
- `pg_product_stock_changed($product_id, $before, $after, $source)` (`includes/fn/ecommerce.php`), once per product after the write: stamps `products.timestamp` (`GET /products?updated_since=`), announces `inventory.changed`, `stock.low` once on crossing `ECOMMERCE_LOW_STOCK_THRESHOLD` downward, queues the marketplace (`pg_marketplace_product_changed()`).
- `$before`: prior stock (checkout, local sale: read just before the decrement), `null`: untracked so far; `$after`: stock left, `null` reads it back (checkout decrements in SQL). Returns it (next `$before` for a product met twice: two recipients).
- Pure, tested (`product_stock_events_test.php`): `pg_product_stock_events()` (events, payloads); `pg_product_stock_delta()`: whether a whole-row save moved stock (same count or tracking off: no; tracking on with a count: from no level).

## Who listens in the site?
- `pg_stock_notify_heard()` (from `pg_announce()` and the stock API): back-in-stock mails ([[back-in-stock-notices]]).

## What do the payloads carry?
- `inventory.changed`: `product_id`, `quantity`, `out_of_stock` (stock API keys), `source` (below).
- `stock.low`: `product_id`, `name`, `quantity`, `threshold`; only on the crossing (`before > threshold` or no level before), threshold set, product tracked.

## Who calls it, who stays quiet?
- Calls it, by `source`: `checkout` (`submit_order.php`); `local_sale` (`includes/local_sale.php`); `panel`: product screen save, list stock cell (`product_builder.php`), file manager `explorer_catalog_quick_edit` (`includes/explorer/handle_catalog_edit.php`; count changed); `barcode`: barcode screens (moves only; else marketplace queue only); `bulk_edit`: `edit_products.php`, file manager `explorer_products_bulk_edit` (`includes/explorer/handle_products_bulk.php`; moved rows; a selection, not import).
- Announce themselves (own payloads, no `source`): stock API (`includes/api/resources/inventory.php`), Workspace (`includes/workspace/changes.php`), ERP document (`includes/erp/stock.php`).
- Quiet (catalogue bulk rule: no `inventory.changed`/`stock.low`): product import, marketplace order import/cancellation (`mp_order_take_stock()`/`mp_order_return_stock()`, `includes/api/outbound/orders.php`; keep the priority-1 marketplace queue), ERP stock count (`erp_stock_count_apply()`). Each still stamps `timestamp` (`api_webhook_events()`, tested).

## Which panel writers announce `product.updated`?
- `pg_announce('product.updated', {id, name})` (as `api_products_update()`, Workspace), plus `inventory.changed` on a stock change: product screen save (`pg_pb_update_product()`, every write), list quick edit (`pg_pb_quick_update()`, changed cell only), file manager quick edit (price, count or short description differs). Bulk edits: quiet. Test: `tests/product_updated_event_test.php`.

## When is `out_of_stock` set?
- Tracked, none left: `out_of_stock = '1'`, `out_of_stock_timestamp` only on the way in; else `'0'`. Product screen: `pg_pb_apply_stock_flag()`; file manager quick edit: `pg_product_out_of_stock_sql()` (pure, tested); bulk edits only clear it; checkout, local sale set it from the stock left, `out_stock` bell once (`commerce/order-completion-stock-and-spots.md`).

## What was wrong before?
- #871, PR #1141: checkout, local sale only queued the marketplace (syncs sold old stock); product screen only stamped; bulk edit, barcode silent.
- #1161, PR #1186: marketplace order import/cancellation, ERP stock count: unstamped, unannounced.
- #1163, PR #1188: checkout, local sale had a stale `$before` (parallel sales: `stock.low` twice; restock meanwhile: none); local sale flagged by one line's quantity; a no-op barcode scan announced.
- #1164, PR #1190: product screen save, list quick edit announced no `product.updated` (Workspace rules missed saves).
- #1162: file manager quick/bulk edit silent; a quick edit to 0 kept `out_of_stock`.
