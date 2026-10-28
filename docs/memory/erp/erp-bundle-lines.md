---
name: erp-bundle-lines
description: A product bundle on an ERP invoice (#2727): one line, the component snapshot in erp_invoice_item_components, one stock move per component on issue, return and cancel, the profit report.
---

## What does an invoice keep of a bundle line?
- The invoice keeps one line for the bundle (its price and tax rate). `erp_stock_snapshot_components()` (`includes/erp/stock.php`) keeps its contents in `erp_invoice_item_components` (quantity per bundle): an order's invoice copies the line's `order_item_components`, any other issue (manual invoice, an order line sold before components were recorded) takes `product_bundle_items` as they stand. Drafts keep none.

## Which stock moves does it make?
- `erp_stock_post_invoice()` writes one move per component (line qty × per-bundle qty), never one for the bundle; a purchase shares the line net by component cost (`erp_stock_split_by_cost()`: avg or last cost × qty, by quantity when none is costed). A bundle with no contents makes no move.
- A return takes back each component move's share (`erp_stock_return_moves()`, ratio = returned / invoice line qty); a cancel turns each move round by `reverses_id`. The profit report sums the moves of a line and estimates a bundle with no move from its components' costs.
- The order bridge passes `order_item_id` on each line of `erp_order_lines()` for the copy.

Related: docs/memory/commerce/product-bundles.md, docs/memory/erp/erp-stock-moves-and-cost.md
