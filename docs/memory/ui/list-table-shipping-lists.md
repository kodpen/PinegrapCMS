---
name: list-table-shipping-lists
description: Arrival dates, ship date adjustments, verified shipping addresses, order reports and duplicate products on the list table driver (TD10b); the shipping report as a plain report table
---

## Which screens and how are they drawn?
- `view_arrival_dates.php`, `view_ship_date_adjustments.php`, `view_verified_shipping_addresses.php`, `view_order_reports.php`, `duplicate_products.php`: printed whole in browser mode (`pg_list_table_printed()`), each in `pg_list_table_printed_lists()` under its `pg_list_state()` name; the column keys are the old sort codes, so the toolbar sort menu and a heading click agree. A toolbar search box beside the sort menu.
- The first column opens the record (`data-pg-open`): the arrival date, the adjustment's zip prefix, the address's company (`#id` when empty), the order report. The old Edit/View buttons are gone. Dates sort by their value (`Ymd`, Unix time), an adjustment by its signed days.
- An arrival date outside its start/end keeps the `status_disabled` name and the "Inactive" word under it.
- The verified addresses' state filter is a form in the toolbar (still a GET that the session keeps).

## How does duplicate products work now?
- Its columns depend on the filter (`$columns` built in order; a row's cells are cut to them with `array_intersect_key()`); a key `$sort_columns` lacks (the zones, a custom field without a label) is not sortable.
- Rows are selected by the driver; `ids_field` `products[]` fills the hidden fields the unchanged POST handler reads. "Duplicate Selected" is the bar's button; the letters, word and attribute fields sit above the list, since the list fills the window to its bottom. The name is plain text, not a link: a click selects.

## Why is the shipping report not on the driver?
- It is a report, not a list: one column per day of the period, headings that open that day's orders, a Grand Total foot. It is a plain `table table-hover` in a `table-responsive`, products in the order the report builds them, no sorting (`data-pg-sort` would wrap the heading links in buttons).

Tests: `tests/commerce_lists_td10b_test.php`.

Related: docs/memory/ui/list-table-order-side-lists.md, docs/memory/ui/list-table-printed.md, docs/memory/ui/report-table-rows.md
