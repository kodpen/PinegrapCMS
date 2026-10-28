---
name: order-completion
description: pg_order_complete() (#2638): the session-free completion of an incomplete order, its once-only lock, what it writes, what submit_order() still does itself, the plan's next steps
module: order_completion
---

## What is it?
- `pg_order_complete($order_id, $values, $documents)` in `includes/fn/order_completion.php`: makes a cart an order. Fed by the order id, the column values the caller settled and the legal document types accepted; never `$_SESSION`, `$_POST` or the liveform (a test reads the module for them).
- Written so the visitor's return and a payment service's message (A3, `plans/order-completion-function.md`) complete through the same code.

## What is the lock?
- The first write is `UPDATE orders SET … status = 'complete' WHERE id = ? AND status = 'incomplete'` (`pg_order_complete_query()`, pure). `mysqli_affected_rows() < 1` → returns null and writes nothing more: of two requests completing one order exactly one goes on.
- `submit_order()` on null (a second submit of the same checkout): logs "A second checkout of an order that was already completed changed nothing.", empties the cart session and goes to the receipt with `?order_id=`. Before, both requests completed the order: two numbers, two `order.created`, mails and stock twice. Writes that run before the lock (key code use, gift card redemption, auto-registration, commission) still run in both; A2d settles them.

## What does it write, in order?
- The UPDATE (columns from `pg_order_complete_columns()`, in the caller's order, every value as quoted text as the inline query did; `ip_address` through `IFNULL(INET_ATON(?), 0)`); unknown column or non-scalar value → null and an `error_log` line.
- `pg_order_drop_saved_lines()`, the order number (`LOCK TABLES next_order_number WRITE`, read, advance, unlock, `UPDATE orders SET order_number`), `pg_legal_documents_store()`, `api_webhook_enqueue('order.created')`, `pg_sms_order_received()`. Returns the order number.
- A failed query ends the request (`output_error`), as before.

## What stays in submit_order()?
- Everything after the SMS until A2b–A2d move it (gift card error mail, receipt e-mail, ship dates, the per-product loop, rewards, contact groups, ERP invoice), the frozen legacy gateways and their recurring profiles, and the session values the receipt page reads.
- `submit_order()` builds the values from its own variables (card, ERP account, offer code, tracking/UTM arrays replace the old `$sql_*` fragments).

## How is it tested?
- `tests/order_completion_test.php`: the tail of `submit_order()` with the function's body spliced in at its call, as one ordered list of writes (key code → … → cart emptied); one order number, one `order.created`, one SMS; the query's text, quoting, refusals and columns. Each A2 step keeps the sequence green.
- Not run against a database or a real checkout here.

Related: commerce/payment-callback.md, commerce/order-status-moves.md, plans/order-completion-function.md
