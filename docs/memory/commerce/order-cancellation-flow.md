---
name: order-cancellation-flow
description: process_order_cancellation as the only cancel path, admin cancel UI rules (no form in table rows), enum spelling cancelled, restore cleanup.
---

## What is the single cancel entry point?
- `process_order_cancellation($order_id, $reason, $is_admin, $user_id, $attempt_refund = null)` (functions.php). Callers: `cancel_order.php` (customer + admin per-row), `edit_orders.php` (`action=cancel` bulk), `view_order.php` (detail modal). There is no other path; legacy `cancel_order()` was REMOVED and the gift-card invalidation logic moved into this function.
- It does status/shipment gates + DB update + log + optional email + optional refund through the payment method's driver. Returns `['status' => 'success'|'already'|'shipped'|'not_found'|'error', 'refund_status' => ...]`.
- 5th param `$attempt_refund`: `null` = follow config, `true`/`false` = force. The detail-screen modal passes `true` (operator confirmed the "payment will be cancelled" modal).

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
- Restoring a cancelled order also CLEARS `cancelled_at`/`cancelled_by`/`cancellation_reason` and (unless refunded) `refund_status`/`refunded_at`/`refund_reference`; otherwise the order_view timeline keeps a dangling "İptal Edildi" step.

Related: docs/memory/commerce/order-cancellation-config.md, docs/memory/commerce/order-cancel-shipping-gate.md
