---
name: order-report-ready-reports
description: Ready reports: the registry in includes/fn/ready_reports.php, view_order_report.php?ready=<key> opening one as a new unsaved report, sales by region counted as the Sales map card counts (#8691)
module: ready_reports
---

## What is a ready report?
- A report defined in code, opened by an address parameter as a new, unsaved report; no table, no column. Saving it makes it the person's own saved report, as any new report (`plans/report-screens.md`).
- The registry is `pg_ready_reports()` in `includes/fn/ready_reports.php` (pure, loaded by `functions.php`): each entry has `key`, `screen` (`order_report`; `visitor_report` for `visitors/visitor-report-ready-reports.md`), `title` (English, printed through `lang()`), `levels` (one to three, each `by` and `order`), `filters` (field, operator, value) and `period` (calendar days counted, today included; 0 for none).

## How does the order report open one?
- `view_order_report.php?ready=<key>`: `pg_ready_report_requested($_GET['ready'] ?? null, 'order_report')` returns the entry, or null for a value that is not a string, an unknown key (keys are exact, case included) or another screen's key; null opens the plain new report (Year, Month, Day and `pg_order_report_default_filters()`). An `id` wins: a saved report ignores `ready`.
- Only while the form has not come back from a save: the levels (a level the entry does not name is -None-), the order by, the Name field (the title) and `pg_ready_report_filters()` (the entry's own filters, no order date). The heading reads "Create Order Report: <title>".
- The period is the date changer's range: on opening (no `start_month` in the address) `pg_ready_report_range($report, $days)` writes its days through today into the new report's session range; the arrows carry `&ready=<key>`, so a step stays on the ready report (RS8b, #9718). Before, a "days ago" Order Date filter hid the date changer and the period ("Sales by region" showed 153 orders of no named days); a ready report saved now keeps no date filter.
- Links build the address with `pg_ready_report_url($key, $days)`; `&days=N` replaces the period (`order-report-ready-strip.md`).

## Which entries are there?
- `sales_by_region`, "Sales by region": Billing Country, then Billing State, both by Total; the default filters (not Incomplete, not Cancelled); period 30. The others and the strip: `order-report-ready-strip.md`.

## Why does sales by region equal the Sales map card?
- Orders: the card sums `orders.status IN ('complete', 'exported')` (`pg_sales_map_collect()`); `orders.status` holds only incomplete, complete, exported, cancelled, so leaving out the two others keeps the same orders. A row whose status was stored as `''` (an unknown value, `order-cancellation-flow.md`) is the one difference.
- Field: the card groups `orders.billing_country`, `orders.billing_state`; both are levels of the screen already, so none was added. The card shows its home country's regions when a map of it ships, its countries otherwise: Country then State shows both. The card merges spellings of a region (`pg_sales_map_match_region()`); the report groups the stored text, so two spellings are two rows with the same sum.
- Period: the card's `d30` starts at `strtotime('-29 days', today's midnight)`; the range's first day starts at that same midnight (`mktime()` in the site's zone, `init.php` sets it), across a clock change too.
- Money: both sum `orders.total` for placed orders (`ui/order-report-totals.md`).

## Who links to it?
- The dashboard's Sales map card's "See all" (`includes/dashboard/v2/cards/salesmap.php`); each place's own link still opens the orders list. Before #8691 "See all" opened the plain new report, by year, month and day.

Tests: `tests/order_report_ready_test.php`.
