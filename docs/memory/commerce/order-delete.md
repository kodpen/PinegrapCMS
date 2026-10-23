---
name: order-delete
description: What deleting an order removes (delete_order.php, the only DELETE FROM orders), the kept legal document copies among them, and who calls it
---

## Who deletes an order?
- Only `delete_order($request)` (`delete_order.php`); no other code runs `DELETE FROM orders`. Callers: the order screen's Delete (`view_order.php`), the orders list bulk delete (`edit_orders.php`), the panel action `pg_panel_delete_order()` (`includes/panel/commerce.php`) and the customer's own unfinished order (`order_history_delete_order.php`).
- It writes the audit row (`pg_audit_record('delete', 'orders', …)`) and the site log line "Order ({var:1}) was deleted.".

## What goes with it?
- `order_items`, `ship_tos`, `order_item_gift_cards`, `applied_gift_cards`, the order's `form_data` rows (never `order_id = 0`), `shipping_tracking_numbers`, its return requests and their lines (`pg_returns_delete_for_order()`), and the kept legal document copies in `order_documents` when the table exists.
- A ready or paused "order abandoned" or review request (product-review-request-email.md) e-mail campaign of the order is set to cancelled, not deleted. The session's active order is dropped when it is the one deleted.

## Why are the legal document copies deleted?
- They hold the buyer's name, address, phone, e-mail, IP and browser. Before PR #1088 (issue #766) they stayed after the order was gone: personal data nothing could reach (not the panel, not the API), kept through a deletion request. And MySQL 5.7 does not keep AUTO_INCREMENT across a restart, so a new order could get the deleted one's id; `pg_legal_documents_store()` then found copies "already stored" and the new customer's receipt and document page showed the old customer's documents.
- Test: `test_legal_documents_deleted_with_the_order` (`tests/legal_documents_test.php`).
