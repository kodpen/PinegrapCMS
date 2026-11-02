---
name: order-export-filter-joins
description: Order exports of view_orders.php select DISTINCT while a product name or shipping filter joins order_items or ship_tos, so no row is written twice (PR #5210)
---

## Why can an export filter repeat rows?
- The Orders screen's product name filter joins `order_items` and the shipping filters (and the ship date) join `ship_tos`: one row per item or recipient of an order.
- The list query groups by `orders.id`; the exports (multiple files: orders, ship_tos, order_items, tracking numbers; single file) did not, so an order with two items matching the product name, or a multi-recipient order under a shipping filter, came out twice in each CSV (and an item once per recipient).

## How is it kept to one row?
- `$export_distinct` is `'DISTINCT '` while `$join_order_items` or `$join_ship_tos` is set, `''` otherwise (no temporary table without a join); every export query opens with `SELECT {$export_distinct}`. The selected columns are one table's, so the repeated rows are identical and collapse.
- `tests/view_orders_export_distinct_test.php` checks that every query of the file carrying one of the joins collapses its rows (the switch, `DISTINCT` or `GROUP BY`). A new export query with those joins does the same.
