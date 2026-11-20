---
name: api-sales-watch-report
description: GET /reports/sales-watch (#10817, orders:read, an MCP tool): per channel the norm and the 12 hours before and after, the last pass, 7 days of sales alerts, products taken off sale
---

## What does it answer?
- `api_reports_sales_watch()` in `includes/api/resources/reports.php`, route `reports.sales_watch` (scope `orders:read`), type `SalesWatchReport` (`api_sales_watch_report_schema()`). Offered as an MCP tool by the route rule (`pg_mcp_route_is_offered()`).
- `watching` (commerce, operations alerts and at least one sales alert on), `alerts_on`, `level`, `close_products`, `last_pass` (the operations alert state file).
- `channels`: per channel `key` (`site`, `mp_<id>`), `label`, `learnt` (false under 20 orders in 8 weeks), `data_until` (a marketplace's last sync), `paused_until`, `last_12_hours_orders` and `_expected`, `next_12_hours_expected`, `next_hours` (hour by hour).
- `alerts`: sales group `notifications` rows of the last 7 days, newest first, at most 200 (`code`, `label`, `detail`, `at`, `product_id`).
- `closed`: entries of `sales_watch_closed` whose product is still off sale (`product_id`, `name`, `closed_at`, `price` as money, `reason` `half` or `cost`).

## Does the call judge or send anything?
- No. It reads the norm from the pass's cache (`pg_sales_watch_norm_data()`) and the rows already written. Putting a product back is the product update route.

## How is it tested?
- `tests/api_sales_watch_report_test.php` (the pure presenters); `php tools/check_api_schema.php`.

Related: docs/memory/commerce/sales-watch.md, docs/memory/commerce/sales-watch-price.md
