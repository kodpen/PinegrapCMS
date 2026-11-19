---
name: order-report-filters
description: Order Report filters: each Field's column, a filter with no field skipped and refused, "does not contain" on Product Name and Shipping fields (no line may match)
module: order_report
---

## Which column does a filter compare?
- `pg_order_report_filter_column($field, $from, $to)` (`includes/fn/order_report.php`): `orders.*` for the order's own fields, `order_items.product_name` for Product Name, `ship_tos.*` for the Shipping fields, the order date in site time (`pg_site_time_sql()`). `''` for an empty or unknown field.
- Every field the filter row offers has a column (`tests/order_report_filters_test.php` reads the screen's `$field_options`).

## What happens to a filter with no field?
- The WHERE loop skips it, so a report stored with `field = ''` (the ENUM takes it) opens; it showed "Query failed." on every open, from `IFNULL(, '')` (PR #10381).
- The save refuses it with "Please select a field for each filter.", the visitor report's message (`visitors/visitor-report-filters-and-form.md`).

## How do Product Name and the Shipping fields filter?
- The screen joins `order_items` / `ship_tos` and groups by `orders.id`, so a comparison keeps an order when one of its lines or recipients matches.
- "Does not contain" and "is not equal to" keep it only when none matches: `pg_order_report_filter_sql()` writes `NOT EXISTS` over the order's own joined rows. Row by row, "Product Name does not contain X" listed an order holding X through its other product (PR #10381).
- An order with no line or recipient is read as one empty row in both forms, so "Shipping State is not equal to (empty)" still leaves an order with no recipient out.
- The cart picking (`$sql_picked_carts`) reads the same WHERE (`order-report-screen.md`).
