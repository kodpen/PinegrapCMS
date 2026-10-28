---
name: orders-list-amount-column
description: Orders list amount cell: an incomplete order shows its cart total (muted, titled "Cart total"), never added to the filter totals; one GROUP BY query per page
module: order_list
---

## Why did an incomplete order read 0?
- `orders.total` is written by `submit_order()`; a cart that never reached payment keeps 0, so the list showed 0,00 and the operator had to open each order (#2203).

## What does the cell show now?
- `pg_order_list_amount_html($status, $total, $cart_total)` (`includes/fn/order_list.php`): an `incomplete` order with `total = 0` and cart lines shows the sum of its lines, `fg-secondary fst-italic` with the title "Cart total"; every other order its `orders.total`.
- `pg_order_list_cart_totals($rows)` sums the incomplete orders of the page in one query (`pg_order_list_cart_totals_sql()`): `SUM(price * CAST(quantity AS signed))` (price may be negative, quantity unsigned), lines saved for later out (`pg_order_item_active_sql()`).

## What stays on orders.total?
- The "Total Sales Amount (Current filter)" card, the chart, the sort on the amount column and the CSV/Excel exports: a cart is not a sale.

## Where are the tests?
- `tests/order_list_test.php`.
