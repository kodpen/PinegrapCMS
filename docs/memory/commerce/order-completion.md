---
name: order-completion
description: pg_order_complete() (#2638) and its stage functions (#2687): the session-free completion of an incomplete order, the once-only lock, what each writes
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

## Which stage functions follow it? (#2687)
- `submit_order()` calls them where the code stood, so the order of writes is unchanged; the plan's last A2 step makes `pg_order_complete()` call them.
- `pg_order_complete_receipt($order_id, $number, $receipt, $customer_email, $new_account)`: `$receipt` is the checkout page's receipt settings (email, subject, format, header, footer, page_id). BCC from the order's lines (`pg_order_receipt_bcc_list()`, pure: store address first, each valid product address once). The renderers still read the session for a new account's link; a request without a visitor has none.
- `pg_order_complete_ship_dates($order_id, $order_date, $auto_registration, $user_id)`: address book entries (`new`/`existing`), ship and delivery dates (`pg_order_transit_date()`, `pg_order_ship_date_adjusted()`, pure).
- Per order line, in `submit_order()`'s product loop: `commerce/order-completion-lines.md`; comments, e-mailed page and campaigns: `commerce/order-completion-line-comments.md`. After the loop: `commerce/order-completion-after-lines.md`.

## What stays in submit_order()?
- Everything not moved yet (the writes before the lock and the gift card error mail: A2d2; the reward points and contact groups the loop collects, the post-save hook: A2d3), the frozen legacy gateways and their recurring profiles, and the session values the receipt page reads.

## How is it tested?
- `tests/order_completion_test.php`: the tail of `submit_order()` with each function's body spliced in at its call, as one ordered list of writes; one number, `order.created`, SMS; the query; the pure helpers.
- Not run against a database or a real checkout here.

Related: commerce/order-completion-lines.md, commerce/payment-callback.md, commerce/order-status-moves.md, plans/order-completion-function.md
