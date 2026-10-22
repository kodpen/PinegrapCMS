---
name: api-order-status-moves
description: What POST /orders/{id} does with a status (plain write, cancellation, restore) and which moves it refuses; incomplete is the cart's status and is not accepted (#877, PR #PRNUM)
---

## Which status moves does the order update accept?
- `api_orders_status_move($from, $to)` in `includes/api/resources/orders.php` decides, before the dry-run stop:
  - same status: nothing;
  - between `complete` and `exported`: a plain `SET status`;
  - to `cancelled`: `process_order_cancellation()` (refund, mail, gift cards, `order.cancelled`);
  - from `cancelled` to `complete`/`exported`: `pg_order_restore_cancelled()`, the order screen's Restore;
  - anything to or from `incomplete`: 422 `validation_failed` on `status`.
- The route's `status` enum is `complete`, `exported`, `cancelled`; `incomplete` stays in the list filter and in the stats endpoint.

## Why is incomplete refused?
- `incomplete` is the cart. A paid order set to it left the panel lists, and the abandoned-cart job (`job.php`, `status = 'incomplete'` and a recent `order_date`) could mail the customer about the order they had paid for.
- A cart set to `complete` became an "order" with no number, no payment and no stock move. Only the checkout makes a cart an order. The order screen does not cancel a cart either, so the API does not.

## Why does leaving cancelled go through the restore?
- A plain status write kept `cancelled_at`, `cancelled_by`, the reason and a `manual_required` refund flag on a completed order (the screen kept the cancel line and the refund banner) and left the gift cards the cancellation expired closed.
- The audit row is `restore`; `order.status_changed` is sent with `previous: cancelled`. A restore that loses a race (another request already moved the order) answers 422.

Related: docs/memory/commerce/order-cancellation-flow.md, docs/memory/commerce/order-cancel-gift-cards.md
