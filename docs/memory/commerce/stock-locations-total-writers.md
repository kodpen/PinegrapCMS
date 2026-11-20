---
name: stock-locations-total-writers
description: Stock locations (#10814): writers that change only the total go through pg_stock_total_write() at a location; tracking off deletes rows, on rebuilds from the total; the source pin
module: stock_sales
---

## Which writers may change the total alone?
- Architect (2026-11-20): on a site with locations a tracked product's total is never written directly; a writer that changes it alone goes through `pg_stock_total_write()` (`stock_sales.php`). Reason: the writer sums the rows, so a change that skips them is undone (#10814); no gap repair inside `pg_stock_change()`.
- `pg_stock_total_write()` answers null without locations or for an untracked product (no rows): the caller's old statement runs unchanged.
- Before #10814 a total-only write (total 7, row still 10) was undone by the next location write, which sums the rows: a scan of +1 gave 11, not 8, and the sold units went back on sale.
- Location: marketplace order take (`mp_order_take_stock()`, reason `marketplace`) and cancel (`mp_order_return_stock()`, `cancel`), ERP moves and counts (`erp`; a count runs inside its transaction), Workspace stock (`workspace`): the default. Order cancellation and bundle return: the order's `orders.stock_location_id`, still to do in part 2 of #10814 (`bundles.php`, `ecommerce.php`).
- `pg_stock_total_delta()` (pure): set is the difference to the total, adjust the amount; a decrease takes only what the location holds, never from another (#5042). An ERP count or Workspace set can so end above the figure asked for.
- `pg_stock_change()` has no repair that closes a gap between total and rows; a writer breaking the rule is caught by the source pin in `tests/stock_total_writers_test.php` (every file whose SQL writes `products.inventory_quantity`, with its count).

## What happens to rows when tracking is switched?
- `pg_stock_tracking_sync($ids)` (`stock_locations.php`): an untracked product's rows are deleted (no move, the total stays), a tracked one without rows gets its default row from the total. Called by the product screen save, Modify Selected, the Catalog Manager bulk edit, `POST /products/{id}` with `inventory`, the product import and Workspace. Before #10814 old rows came back over the total set meanwhile.

## What guards it?
- `tests/stock_total_writers_test.php`: the issue's case ends at 8, a decrease takes only what the location holds, tracking off and on, null without locations, no gap repair, the source pin of total writers.
