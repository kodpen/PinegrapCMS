---
name: list-table-quick-edit-orders
description: All Orders list quick edit (view_orders.php): the status between complete and exported only; why every other column stays text
---

## Which cells change?
- Only Status (`pg_order_quick_edit_columns()`, `includes/fn/ecommerce.php`), between Complete and Exported, and only on a row whose status is one of them. `pg_order_quick_update()` writes it guarded by the status read, stamps the order, logs and announces `order.status_changed` (`commerce/order-status-moves.md`).
- Cancel stays on the row's Cancel button (`process_order_cancellation()`: reason, refund, mail); restore stays on the order screen.

## Why does nothing else change?
- The rule is the order screen's own saves (`list-table-quick-edit-values.md`). `view_order.php` saves only shipping data per address (ship and delivery dates, tracking numbers and carriers, shipped quantities, through `update_order()`), the refund, the payment mark and the status moves.
- The list's other columns are read only there: the billing first and last name, Type, Total (money, from the lines), User, Tracking Code (the visit's marketing code), the member ID (from the contact), Affiliate Code, Card Data, Order Date. None has a save with its own checks, so none becomes a field; making one would be a new order edit, not quick edit.
- Checked 2026-11-05 against the list's columns (`pg_order_list_columns()`, `includes/fn/commerce_list.php`).

Related: docs/memory/ui/list-table-quick-edit-values.md, docs/memory/commerce/order-status-moves.md
