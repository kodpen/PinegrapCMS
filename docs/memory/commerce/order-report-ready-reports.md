---
name: order-report-ready-reports
description: Ready reports: the registry in includes/fn/ready_reports.php, view_order_report.php?ready=<key> opening one as a new unsaved report, sales by region counted as the Sales map card counts (#8691)
module: ready_reports
---

## What is a ready report?
- A report defined in code, opened by an address parameter as a new, unsaved report; no table, no column. Saving it makes it the person's own saved report, as any new report (`plans/report-screens.md`).
- The registry is `pg_ready_reports()` in `includes/fn/ready_reports.php` (pure, loaded by `functions.php`): each entry has `key`, `screen` (`order_report`), `title` (English, printed through `lang()`), `levels` (one to three, each `by` and `order`), `filters` (field, operator, value) and `period` (calendar days counted, today included; 0 for none).

## How does the order report open one?
- `view_order_report.php?ready=<key>`: `pg_ready_report_requested($_GET['ready'] ?? null, 'order_report')` returns the entry, or null for a value that is not a string, an unknown key (keys are exact, case included) or another screen's key; null opens the plain new report (Year, Month, Day and `pg_order_report_default_filters()`). An `id` wins: a saved report ignores `ready`.
- Only while the form has not come back from a save: the levels (a level the entry does not name is -None-), the order by, the Name field (the title) and `pg_ready_report_filters()`, which appends the period as Order Date "is greater than or equal to" `period - 1` Day(s) Ago. The heading reads "Create Order Report: <title>".
- With that date filter the date changer is left out, as for any report with a date filter.
- Links build the address with `pg_ready_report_url($key)`.

## Which entries are there?
- `sales_by_region`, "Sales by region": Billing Country, then Billing State, both by Total; the default filters (not Incomplete, not Cancelled); period 30.

## Why does sales by region equal the Sales map card?
- Orders: the card sums `orders.status IN ('complete', 'exported')` (`pg_sales_map_collect()`); `orders.status` holds only incomplete, complete, exported, cancelled, so leaving out the two others keeps the same orders. A row whose status was stored as `''` (an unknown value, `order-cancellation-flow.md`) is the one difference.
- Field: the card groups `orders.billing_country`, `orders.billing_state`; both are levels of the screen already, so none was added. The card shows its home country's regions when a map of it ships, its countries otherwise: Country then State shows both. The card merges spellings of a region (`pg_sales_map_match_region()`); the report groups the stored text, so two spellings are two rows with the same sum.
- Period: the card's `d30` starts at `strtotime('-29 days', today's midnight)`; the report compares the order's day in site time (`pg_site_time_sql()`) with `pg_report_dynamic_value('days ago', 29, 'date')`, the same first day, across a clock change too.
- Money: both sum `orders.total` for placed orders (`ui/order-report-totals.md`).

## Who links to it?
- The dashboard's Sales map card's "See all" (`includes/dashboard/v2/cards/salesmap.php`); each place's own link still opens the orders list. Before #8691 "See all" opened the plain new report, by year, month and day.

Tests: `tests/order_report_ready_test.php`.
