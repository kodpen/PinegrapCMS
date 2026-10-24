---
name: order-returns-events
description: The order.return.* webhooks (requested, approved, received, refunded): payload, where each is announced and why refunded is not from the move, what is not announced, the Workspace trigger
---

## What does an order.return.* event carry?
- `pg_return_announce()` → `pg_announce()` with `pg_return_event_payload()` (pure): `id` is the ORDER id (every `order.*` event; `ws_events_record_of()` tags and links the order by it), `order_number`, `return_id`, `ship_to_id`, `status` after the move, `source`, `reason`, `items` [{order_item_id, quantity}]. Extras come after and never replace these.
- `tests/returns_events_test.php` gives the order, the delivery, the person and the lines different ids, so a field read from the wrong column fails.

## Where is each one announced?
- `order.return.requested`: `pg_return_create()` after COMMIT and RELEASE_LOCK, never on a repeat. Any source announces (My Account, the API, later a panel form).
- `order.return.approved` / `.received`: `pg_return_move()` after its conditional UPDATE took the row, so the losing side of two moves announces nothing; one screen and each request of a bulk approval announce.
- `order.return.refunded`: `pg_return_refund()` after its lock, with `amount`, `refund_method` (driver, manual) and `unclear`. Not from `pg_return_move()`: the refund moves to refunded before the service is asked and reverts on a refusal. An unclear answer announces with `unclear: true` (the return stays refunded, commerce/payment-unclear-answers.md); a refusal announces nothing.
- Rejected, shipped, closed and a withdrawal are not announced; the catalogue lines (`api_webhook_events()`) say so.

## What does the Workspace get?
- `order.return.requested` is in `pg_event_workspace_events()` and `ws_events_catalog()` (shop block, record order, right ecommerce, no filter): a scheduled action can start on it; the placeholders are the order's. Not in `ws_events_watched()` (its docblock says so): watching channels are not told, and `ws_watch_line()` has no line for it.
- The other three are not kept for the Workspace: nothing starts on them.

## Routes?
- commerce/order-returns-api.md.
