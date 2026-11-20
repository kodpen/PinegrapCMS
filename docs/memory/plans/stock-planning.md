---
name: stock-planning
description: Plan (owner 2026-11-20, agent channel poll 163): a nightly stock plan per product (days left, run-out day, order quantity, dead stock), its screen, supplier orders, bell and API; SP1 to SP5
priority: 6
---

## What is asked?
- Owner (2026-11-20, agent channel poll 163, message 10064): "Evet, plana girsin": sales speed from every channel without the out-of-stock days, last year's weeks for the season, the supplier's lead time measured, per product the days left, the run-out day and the quantity to order net of open purchase orders, dead stock at average cost, one press per supplier, the bell, API and MCP.

## What is there today?
- Stock: `products.inventory`, `inventory_quantity`; a variant is its own row (`commerce/products-variant-model.md`); a bundle's stock comes from its components (`pg_bundle_stock()`).
- Sales: `order_items` of `orders` at `status IN ('complete','exported')` by `order_date`; marketplace orders are `type = 'marketplace'`, the counter `type = 'local'`. An ERP sales invoice moves stock only with `order_id = 0` (`erp/erp-stock-moves-and-cost.md`). Components: `order_item_components`, `erp_invoice_item_components`.
- No past daily stock: `stock_moves` exists only with locations and not on every path; `erp_stock_moves` holds ERP documents only. Out-of-stock days are known only from a record kept from now on.
- Minimum stock: `erp_stock_minimums`, the hourly `erp_stock_alert_job.php`, `erp_alert_low_stock()` (`erp/erp-alerts.md`); its screen sends `products[]` to `add_erp_purchase_order.php`, filled by `erp_purchase_order_rows_for_products()`.
- Purchase orders: `erp_purchase_orders` (`issue_date`, lines in `line_data`), open quantities by `erp_purchase_order_quantities()`, invoices by `purchase_order_id`. No product-to-supplier link; no draft status (a save numbers it).
- Cost: `erp_product_costs.avg_cost`. Per-product sums exist per period only (`pg_dashboard_best_sellers()`), none per day.

## What binds it?
- `erp/README.md`: ERP writers only, `erp_event()`, `validate_erp_access()`, endpoints in `includes/erp/api.php`.
- New `erp_*` tables and columns are approved in advance (`workflow/decisions-made-data.md`, 2026-10-26); each still comes through a 2026.11.0 migration step with the idempotent helpers, named in its PR.
- Nothing is ordered for the merchant: the press opens New purchase order filled in; nothing is saved or mailed until a person saves it.
- The plan only reads stock and sales; it never changes a count. A bundle is never planned; its components carry its sales.

## Which numbers does it start with?
- Settings in Accounting: safety days 7, an order covering 30 days after the goods come, dead stock after 90 days without a sale (the poll's 90). The first two are proposals: the architect decides whether the owner is asked.

## What is built?
- Features: `stock-planning-features.md` (P1 to P14). Steps: `stock-planning-steps.md`. Claim as `[plan:stock-planning] <step> — <topic>`.

## What is left out?
- Sending an order by itself, supplier price lists and pack sizes, a plan per stock location, untracked products, a learned model. Each is a later yes.

## Whose look?
- The owner's: the Stock Planning screen and its settings (SP3).
