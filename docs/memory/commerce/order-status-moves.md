---
name: order-status-moves
description: pg_order_status_move(), the one order status rule the API and the Workspace follow; cancel only through process_order_cancellation(), restore only through its helper; full refund cancels (PR #PRNUM)
---

## What decides an order's status move?
- `pg_order_status_move($from, $to)` (`includes/fn/order_status.php`, pure):
  - `none`: the same status;
  - `set`: a plain write between `complete` and `exported` (also out of a lost `''`);
  - `cancel`: to `cancelled` → `process_order_cancellation()`;
  - `restore`: `cancelled` → `complete`/`exported` (its only targets) → `pg_order_restore_cancelled()`;
  - `refuse`: to or from `incomplete`; out of `cancelled` to anything else; to a value the column does not hold; `canceled` (one L, stored as `''` since 2026.1.26) either way.
- `pg_order_status_move_refusal()`: the basket sentence when `incomplete` is on a side, else "An order cannot be moved from X to Y."
- Who follows it: the external API (`api_orders_status_move()` returns it, `commerce/api-order-status-moves.md`) and the Workspace (`ws_change_check()` refuses, `ws_change_write_order()` carries it out, `ws_change_audit_target()` its audit line, `core/audit-trail-workspace.md`).
- Tests: `tests/` `order_status_move`, `api_order_status`, `workspace_changes`, `workspace_audit`.

## Why is incomplete refused?
- `incomplete` is the cart. A placed order set to it left the panel lists, and the abandoned-cart job (`job.php`) could mail the customer about a paid order.
- A cart set to `complete` became an order with no number, payment or stock move; only the checkout makes a cart an order. The order screen offers neither, nor a cancel for a cart.

## Which paths cancel and restore?
- Cancel, only `process_order_cancellation()`: `cancel_order.php`, `edit_orders.php` bulk, the order screen's Cancel and full refund, the API, the Workspace, the marketplace sync.
- Restore, only `pg_order_restore_cancelled()`: the order screen's Restore, the API, the Workspace. Each caller writes the `restore` audit line and announces `order.status_changed`.
- The other status writes are plain moves: the order list's quick edit (`pg_order_quick_update()`, guarded), the exports (`view_orders.php`, `shipworks.php`: complete → exported), the checkout and the local sale (a cart becomes `complete`).

## What did the Workspace do before? (PR #PRNUM)
- It took an order out of `cancelled` with a plain `UPDATE orders SET status`: `cancelled_at`, `cancelled_by`, the reason and an unfinished refund flag stayed, and the gift cards the cancellation expired stayed expired. It also moved an order to or from `incomplete`.
- Now: a restore another request beat fails with "Only cancelled orders can be restored."; a plain move carries `AND status = <the status read>` and fails with "The record changed meanwhile, so nothing was written.", so a cancellation in between is never written over.

## What does a full refund on the order screen do? (PR #PRNUM)
- `view_order.php`'s refund, when `refunded_amount` reaches `total`: after the `Refunded …` log line, `process_order_cancellation($order_id, lang('The full amount was refunded.'), true, <operator id>, false)`, then the one `refund` audit line. No void (`$attempt_refund = false`): the money is back; `pg_order_cancel_payment_plan()` sees nothing left, so no flag either. What gift cards paid is still flagged (REFUND ACTION REQUIRED, `manual_required`).
- Before, it wrote `status = 'cancelled'` by hand: no date, reason or canceller, the review request stayed, the gift cards the order issued stayed spendable, no `order.cancelled`, no customer e-mail.
- The customer now gets the cancellation e-mail (unless switched off, or a marketplace order) with the reason "The full amount was refunded." and no refund paragraph, except "processed manually" when gift cards paid part.

Related: commerce/order-cancellation-flow.md, commerce/order-cancel-refunded-payment.md, commerce/payment-refunds.md
