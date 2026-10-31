---
name: order-report-totals
description: Order Report figures: gift card, card surcharge and installment charge columns so every row adds up to Total; carts read from their active lines in every summarize-by
module: order_report
---

## Why do the order report's columns add up to Total?
- `orders.total` is subtotal - discount + tax + shipping - gift card + card surcharge + installment charge (`submit_order.php`, `pg_payment_checkout_totals()`). Each row, group and the Grand Total print Subtotal, Discount, Tax, Shipping, then Gift card, Card surcharge and Installment Charge, then Total.
- A charge column is printed only when its Grand Total is not zero (`pg_order_report_charge_columns()`), so a shop without them sees the old six figures.

## Where do a row's figures come from?
- `pg_order_report_order()` (`includes/fn/order_report.php`) builds every order's figures; `pg_order_report_bucket()` and `pg_order_report_add()` sum them into each group and the grand total, the same way with no summarize-by and with one to three.
- A placed order reads its `orders.*` columns; `orders.total` is read, never recomputed.
- A cart (incomplete order) reads its active lines (`pg_order_item_active_sql()`), as the orders list's Cart total does: subtotal, tax and shipping from the lines, the discount from `orders.discount` (written only when the cart was sent to pay), Total = subtotal - discount + tax + shipping, the gift card and charges 0. A cart with no active line reads 0.
- The cart query always runs; the report's own filters leave no cart when they exclude carts.

## What was wrong before?
- The three charges had no column: a 3-installment iyzico order read Subtotal 1.000,00, Tax 200,00, Total 1.236,00; a gift-card order read 100,00 + 20,00 + 10,00 = Total 80,00.
- A line saved for later counted in a cart's figures; with no summarize-by a cart printed its `orders.*` figures (0) instead of its lines; a report without a status filter read an undefined cart array in summarize-by 1-3.

Tests: `tests/order_report_totals_test.php`.

Related: docs/memory/ui/report-table-rows.md, docs/memory/commerce/cart-save-for-later.md
