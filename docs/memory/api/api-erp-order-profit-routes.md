---
name: api-erp-order-profit-routes
description: GET /erp/orders/{id}/profit and GET /erp/profitability (#10736): erp:read, what each answers, the period, channel and product filters, the break-even on a product row, the MCP tools
---

## Which routes, and behind what?
- Declared in `includes/erp/api.php`, handlers and presenters in `includes/erp/api_order_profit.php`. Scope `erp:read`, so the owner ceiling of the ERP scopes holds them as it holds the profit report (architect, 2026-11-20, channel message 10189: profit only with ERP access, on every surface).
- Their MCP tools come from the routes (`erp_orders_profit`, `erp_profitability`); nothing was added under `includes/mcp/`.
- Before the tables of 11.0.250 both answer 503 `service_unavailable`. Read only: no row is computed or written.

## What does the order route answer?
- `ErpOrderProfit`: order_id, computed, currency, channel, state (final, estimate, incomplete), stale, revenue, the five costs, net, margin, and the cost rows (kind, source, source_ref, amount). An order not worked out yet: computed false, figures null, no rows. No order: 404.

## What does the profitability route answer?
- `ErpProfitability`: the Profitability screen's figures (`erp_profitability_report()`): totals, channels, products (most profitable first) with estimate and incomplete order counts, uncomputed, capped, target_margin.
- Filters: `month` or `from` and `to` (`erp_api_report_period()`, at most two years, this month by default); `channel` web or `marketplace:<id>` (anything else 422); `product_id` (`erp_profitability_for_product()`, totals of that product only); `limit` 1-1000, default 50, `products_count` before it.
- Each product row has `break_even` (price, target_price, average_price) from the last 90 days (`erp/erp-order-profit-break-even-bell.md`).

## Where is it tested?
- `tests/erp_order_profit_api_test.php`: the route rows, both answers' fields against their schema, the product filter, the limit.
