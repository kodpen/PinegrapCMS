---
name: order-cancellation-flow
description: process_order_cancellation as the only cancel path, admin cancel UI rules (no form in table rows), enum spelling cancelled, the one restore path, what the API status moves call.
---

## What is the single cancel entry point?
- `process_order_cancellation($order_id, $reason, $is_admin, $user_id, $attempt_refund = null)` (functions.php). Callers: `cancel_order.php` (customer + admin per-row), `edit_orders.php` (`action=cancel` bulk), `view_order.php` (detail modal). There is no other path; legacy `cancel_order()` was REMOVED and the gift-card invalidation logic moved into this function.
- It does status/shipment gates + DB update + log + optional email + optional refund through the payment method's driver. Returns `['status' => 'success'|'already'|'shipped'|'not_found'|'error', 'refund_status' => ...]`.
- 5th param `$attempt_refund`: `null` = follow config, `true`/`false` = force. The detail-screen modal passes `true` (operator confirmed the "payment will be cancelled" modal).
- Cancels once: the status UPDATE carries `AND status <> 'cancelled'` and a request that changes no row returns `already`. Two cancellations at once (double submit, row and bulk Cancel) used to both void the payment, mail the customer and announce `order.cancelled`; the second void's failure overwrote the first one's `refunded` (#733, #962).
- Gift cards (issued by the order, or that paid it) on cancel and restore: docs/memory/commerce/order-cancel-gift-cards.md

## Enum spelling
- Write `'cancelled'` (double L). The old single-L `'canceled'` is accepted only on the READ side (`view_orders.php` filter/badge) and is NEVER written.
- Why: `upgrade_to_2026_1_26()` removed `'canceled'` from the enum and turned existing `'canceled'` rows into `''`; writing it later silently stored `''` (error under strict mode).

## Admin cancel UI (`view_orders.php`)
- Per row an "İptal" button (shown unless status is `cancelled`/`incomplete`): `type="button"` + `data-order-id`; it POSTs the single hidden `#pg-admin-cancel-form` (CSRF + `pgConfirm()` modal). That form is a SIBLING of the page form.
- NEVER put a `<form>` in an order-table row: the table is inside the page's `<form action="edit_orders.php">`, nested forms are illegal and the parser drops the inner `<form>` (button submitted the outer form with empty action, hidden inputs and a second CSRF token leaked into it, the confirm handler never bound).
- Bind the listener by DELEGATION on `document`: the table is a DataTable and rows leave/enter the DOM on paging/sorting.
- Bulk: "Cancel Selected" (same pattern as "Delete Selected") -> `edit_orders.php` `action=cancel`, sequential loop.
- The detail-page cancel button must not depend on `ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay' && $transaction_id` (it hid the button for bank transfer / cash on delivery).

## Restore
- One path: `pg_order_restore_cancelled($order_id, $status)` (ecommerce.php), called by the order screen's Restore and by `POST /orders/{id}` moving a cancelled order to `complete`/`exported` (#877, PR #1258). It moves only a cancelled row (`AND status = 'cancelled'`, false otherwise), CLEARS `cancelled_at`/`cancelled_by`/`cancellation_reason` and (unless refunded) `refund_status`/`refunded_at`/`refund_reference`, and gives the expired gift cards their date back (`pg_order_restore_gift_cards()`). Callers write the audit row and announce `order.status_changed`.
- Before, the API wrote the status alone: the order timeline kept "Order Cancelled", the refund banner stayed and the gift cards stayed expired.

- The external API's status moves (which go through it, which are refused): docs/memory/commerce/api-order-status-moves.md

Related: docs/memory/commerce/order-cancellation-config.md, docs/memory/commerce/order-cancel-shipping-gate.md, docs/memory/commerce/order-cancel-gift-cards.md
