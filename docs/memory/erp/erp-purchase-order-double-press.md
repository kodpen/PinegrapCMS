---
name: erp-purchase-order-double-press
description: Two presses at once on a purchase order: the invoice draft is made under the order's row lock, status moves (sent, cancelled, opened again) are guarded updates that announce once.
---

## Why is the draft from an order made under a lock?
- `erp_purchase_order_to_invoice()` opens a transaction, locks the order row (`SELECT … FOR UPDATE`), reads what is left (`erp_purchase_order_order_progress()`), writes the draft (`erp_invoice_draft_save()` nests in it) and commits.
- Before, two presses at once (screen or `POST /erp/purchase-orders/{id}/invoice`) both read the same remaining quantity and made two drafts with the same goods; issuing both brought the stock in twice and raised the average cost on goods never received.
- Now the second press waits, then finds every line on the first draft and is refused with the usual "already on a purchase invoice or a draft".

## How are status moves kept to one?
- `erp_purchase_order_set_status()` moves only from the status it starts from: a cancel `WHERE status NOT IN ('cancelled', 'received')`, opening again `WHERE status = 'cancelled'`. No row moved means another request got there first: the refusal is read again from the order and nothing is announced.
- A cancel therefore never overwrites an order whose last goods came in between the read and the write.
- `erp_purchase_order_mark_sent()` claims the first send with `UPDATE … SET sent_at = … WHERE sent_at = 0`; only the request that moved the row announces `erp.purchase_order.sent`. Neither update touches a cancelled or received order.

Tests: tests/erp_purchase_order_double_press_test.php. Related: docs/memory/erp/erp-purchase-orders.md
