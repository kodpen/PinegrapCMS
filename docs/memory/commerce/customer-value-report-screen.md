---
name: customer-value-report-screen
description: Customers Report screen (view_customer_report.php, Reports menu): periods, tiles, months, cohorts, groups, top customers, who sees names, states before data (#10020)
module: customer_values
---

## Where is it, and who opens it?
- `view_customer_report.php`, linked as "Customers Report" in the Reports menu under the order reports. Whoever passes `validate_ecommerce_report_access()`, on a site with commerce on. Help topic `commerce-cont#customers-report` (passed in `pg_page_shell()`).
- It reads only `pg_customer_value_summary()` (`customer-value-summary.md`); no order is summed on the screen.

## What does it show?
- A period (Last month, 3, 6, 12, 24 months, All time; default 12, kept in the session, `pg_customer_value_period_pick()`). The months are the whole months before the summary's month.
- Tiles (`pg_customer_value_figures()`): Customers, Repeat purchase rate (two orders or more in the period), Orders per customer, Value per customer, Days between orders. Chart: value from first-time customers, returning customers dashed (`pg_customer_value_month_chart()`).
- By month table; Cohorts (last 12 first-order months, the share ordering again 1-12 months later, blank for a month not begun: `pg_customer_value_cohort_rows()`); Customer groups (fixed bounds, every customer of all time); top 20 of all time and of the last 12 months; what is not counted (marketplace orders in another unit, orders with no e-mail and no contact).
- The cell bounds are `pg_customer_value_cell_age()` (30 included in the first row) and `pg_customer_value_cell_band()`. The group of a cell is `pg_customer_value_cell_group()`, the architect's mapping (`plans/customer-value.md`, agent channel message 7143); `pg_customer_value_group_bounds()` gives the bounds the screen states under the table.

- On a phone (under 576 px) the groups table stacks: each last-order row a block, its three cells one under the other under their column's name (`data-label`, rule `.pg-customer-groups` in `backend.src.css`), no sideways scroll (architect, agent channel message 7172).

## Who sees names?
- A reader for whom `validate_contacts_access($user, true)` holds: names are read when shown from the top customers' last orders (`pg_customer_value_order_names()`, by `order_number`, which has no index: about 70 ms at 200,000 orders). The order opens only with the commerce right too.
- Others get the figures with no name column and the note "Names are shown to users who may manage contacts." (`pg_customer_value_top_rows()`).

## What before the first rebuild, or with little data?
- No summary: how many orders of how many the running pass has read (`customer_values_pass`), or that the figures come after the scheduled jobs' next run.
- Fewer than 20 customers (`pg_customer_value_too_little()`): a warning over the figures, which still show.

## Tests?
- `tests/customer_values_test.php`: tiles on hand-counted orders, months and chart, cohorts, the cells' edges and groups, names hidden without the contacts right.
