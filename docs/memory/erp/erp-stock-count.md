---
name: erp-stock-count
description: ERP stock count (4.100): scanning and corrections, applying it to products in one transaction, why it writes no stock move and carries no cost, what is kept, the event and the marketplace hook.
---

## How is a count taken? (`includes/erp/stock_counts.php`, 2026.4.4 step 4.100)
- A document of its own (`erp_stock_counts`, `erp_stock_count_items`; screens `erp_stock_counts.php`, `erp_stock_count.php`): each barcode or stock code read adds one, or the typed quantity, to that product's count (`erp_stock_count_scan()` → `erp_product_by_barcode()` → `erp_stock_count_add()`).
- Only stock-tracked products, in whole pieces. Counts are corrected by hand (`erp_stock_count_save()`; an empty box takes the product off). Statuses: open (counting), applied, cancelled; only an open count changes.

## What does applying do?
- `erp_stock_count_apply()` sets `products.inventory_quantity` and `out_of_stock` for every product on the count, in one transaction, and keeps what the store had before on the line (`system_before`), so the difference stays on record. Products not counted are left as they are.
- It writes no `erp_stock_moves` row: a shelf correction is not a purchase or a sale and carries no cost, so the average cost does not move.
- Unlike the document flows, which change counts only after the commit (erp-stock-moves-and-cost.md), it writes `products` inside its transaction: on a `products` table still MyISAM (over the conversion limit) a failed apply keeps the rows it already set, the count stays open, and a second apply reads them as `system_before`.
- After the commit: `pg_marketplace_product_changed()` for each product whose stock changed, and the `erp.stock_count.applied` event (`line_count`, `changed`).

## How is the quantity beside a scan read?
- `erp_stock_count_scan_quantity()` (`erp_stock_count.php` → `erp_stock_count_scan()`): an empty box is one piece, otherwise the counted list's rule, `/^\d{1,7}$/`. Anything else (`1.200`, `2,5`, `abc`) is refused on the code field with "A count is a whole number of pieces." and nothing is counted.
- Before, the box was cast with `(int)`: `1.200` with "Set the count to this quantity" set the count to 1, `abc` set it to 0 and `2,5` added 2. Pinned by `tests/erp_stock_count_quantity_test.php` (#3643).
