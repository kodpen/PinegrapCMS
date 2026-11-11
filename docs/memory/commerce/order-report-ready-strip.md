---
name: order-report-ready-strip
description: The order report's ready reports strip and entries (region, over time, payment method, source), the &days= period, the Ecommerce summary card's See all (#8724)
module: ready_reports
---

## Which ready reports does the order report have?
- In `pg_ready_reports()`, all with the new report's filters (not Incomplete, not Cancelled: the complete and exported orders the shop cards sum) and 30 days:
- `sales_by_region` "Sales by region" (`order-report-ready-reports.md`); `sales_over_time` "Sales over time": Year, Month, Day, each Alphabetical (the query's time order), drawn as a line over the days (`ui/report-view.md`); `sales_by_payment_method` "Sales by payment method": Payment Method by Total; `sales_by_source` "Sales by source": the UTM Source level (`utm_source`) by Total. The UTM source was chosen over Referral Source Code: it is the source every campaign link and the visitor reports carry.
- No sales by product: the order report has no product level (its levels are the order's own columns), so the strip cannot give it; adding one changes the report's query and grouping, a step of its own.

## What is the strip?
- `pg_ready_report_strip($screen, $levels, $current)`: every entry of the screen whose levels are all in the screen's summarize-by values, with its address and whether it is the one open. `view_order_report.php` prints it under the toolbar as `<nav class="pg-ready-strip" aria-label="Ready reports">` with a wrapping list of pill links; the open one is solid with `aria-current="page"`. The strip's links open each entry with its own period.

## How does an address set the period?
- `&days=N` with `?ready=`: `pg_ready_report_days()` takes digits only, 1 to 366 (`7`, not ` 7`, `7.5`, `0007`, an array); anything else is 0 and the entry's own period applies. Read only with a ready report open; `pg_ready_report_filters($report, $days)` writes it as Order Date "is greater than or equal to" `days - 1` Day(s) Ago, so `days=1` is today.

## Where does the Ecommerce summary card's See all go?
- `pg_dashboard_v2_shop_url($can_orders, $can_reports, $days)`: with the order reports right (role below 3 or commerce reports, the gate of `validate_ecommerce_report_access()`) the ready report sales over time with the card's days (`pg_dashboard_v2_shop_days()`: Today 1, Last 7 days 7, Last 30 days 30); with the orders list only, `view_orders.php` as before; otherwise no link. The card's window starts `days - 1` days before today's midnight, the same first day the report's filter reads. Before #8724 it opened the orders list, or the saved reports list for a reports-only user.

## What does Save do with a ready report?
- It saves a new report: a ready report opens without `id`, the form posts no `ready` or `days` (its address is `view_order_report.php`), so the save's `field_in_session('id')` is empty and `INSERT INTO order_reports` runs. The saved report opens by its id, which wins over `ready`.

Tests: `tests/order_report_ready_test.php`, `tests/dashboard_v2_shop_test.php`.
