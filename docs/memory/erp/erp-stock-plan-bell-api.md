---
name: erp-stock-plan-bell-api
description: Stock plan low stock bell and GET /erp/stock-plan (PR #NNNN): products announced, the once-per-drop stamp, minimum notice for unplanned products, API fields and filters, MCP tool
---

## What does the low stock bell say for a planned product? (P13)
- `erp_alert_low_stock()` (hourly `erp_stock_alert_job.php`, same Low stock switch) first announces the planned products whose plan state is `order_now` or `runs_out` (`erp_alert_plan_states()`), in one `erp_low_stock` row, most urgent (fewest days left) first.
- The row carries the first product's days left and order-by day in `order_total` as `days|Y-m-d` (`erp_alert_plan_notice()`; the day empty when the lead time is unknown). A non-empty `order_total` with `|` is what tells it from the minimum notice.
- Sentence (`pg_notification_stock_plan_sentence()`, `includes/notifications.php`): "<product> runs out in X day(s), order by <date>." plus "N more product(s) should be ordered too."; link `erp_stock_plan.php`. Visibility and the API's `subject` are those of `erp_low_stock`.

## How is it told once per drop?
- `erp_stock_plan.notice_at` is stamped when announced and cleared when the plan has left `order_now`/`runs_out` (or the product stops tracking stock or goes off sale), so it is told again when it turns again. The nightly rebuild never writes `notice_at`.
- The days left are the night's; the plan state changes only at the night rebuild, so a planned product is announced at most once a night.

## What happens to the minimum notice?
- A product with a plan row whose state is not `no_history` gets no minimum notice (`erp_alert_low_stock_minimum_where(true)`); a product without history, or every product before the plan table exists, keeps the minimum notice as before.
- The job's result: `count` is both notices' products, `planned` and `plan_notification_id` the plan's.

## What does GET /erp/stock-plan return? (P14)
- Route `erp.stock_plan.list` (`erp:read`), reader `includes/erp/api_stock_plan.php`, object `ErpStockPlanRow` (schema pair in `tools/check_api_schema.php`). Its MCP tool `erp_stock_plan_list` comes from the route.
- Filters: `state` (runs_out, order_now, soon, fine, no_history), `supplier_id`, `product_id`; tracked products only; cursor paged by product id.
- Fields: product, stock now, `units_per_day`, `season`, `supplier_id`/`supplier_title` (null with none), `lead_days`, `lead_source`, `days_left`, `runout_date`, `order_by_date`, `quantity`, `state`, `last_sale_date`, `dead`, `dead_value` (kurus at `avg_cost`, null when no cost).
- Dead stock is worked out when read with `erp_stock_plan_dead()`, the history start from the marks and the dead-stock days of `erp_stock_plan_settings()`. 503 `service_unavailable` before the plan table exists.

- Not verified on a database or in a browser when written; tests: `tests/erp_stock_plan_bell_test.php`.

Related: docs/memory/erp/erp-stock-plan-forecast.md, docs/memory/erp/erp-alerts.md, docs/memory/erp/erp-api-seam.md
