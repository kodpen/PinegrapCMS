---
name: erp-order-refund-lines-noted
description: The order screen's refund by lines on an order without an ERP invoice notes the lines it gave back (lines:<id>=<qty>) on order_refunds.notes, so the same line is not refunded twice (#7129)
---

# Refund by lines: lines noted on an order without an invoice

## What is noted?
`view_order.php`'s refund by lines (`refund_mode = lines`, ERP on) with source `order` (no issued ERP sales invoice) writes `erp_order_refund_lines_note()` on its `order_refunds` row, after the points marker: `Reward points taken back: 40 lines:101=1,-1=1`. Ids are the refund line ids: `order_items.id`, `-1` shipping, `-2` surcharge. Source `invoice` notes no lines: its return invoices count them.

## How is it read?
`erp_order_refund_lines()` reads `notes` of the order's `refund_type = 'refund'` rows through `erp_order_refund_lines_noted()` (pure, sums per id) and passes them to `erp_order_refund_order_lines()` as `returned_qty`; `remaining_qty` is the rest (never below 0). A second refund of the same line is refused by `erp_return_build()` ("You cannot return more than was sold"), so `pg_credit_points_lines_refunded()` does not take its points twice.

## Why are the taxes left out on a line given back in part?
Only quantities are noted, not the tax given back. With `returned_tax = 0` the piece that empties the line would take the whole line's tax again; left out, its rest is taxed at its rate like any piece. A line with nothing noted keeps the three zeros (`erp-return-documents.md`, #1073).

## What about older refunds and sites without the column?
Rows written before the change carry no marker and stay uncounted (the old behaviour). Without `order_refunds.notes` (`pg_schema_has()`) nothing is noted or read. Decision: `workflow/decisions-made-product-32.md` (option A, no schema change). Tests: `tests/erp_order_refund_test.php`.

## Symptom fixed
Line A 100, line B 300: a refund by lines of A was accepted again while money was left on the order, and A's reward points were taken back twice.
