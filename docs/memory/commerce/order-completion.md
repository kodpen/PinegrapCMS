---
name: order-completion
description: pg_order_complete() (#2638, every stage since #2972): the session-free completion of an incomplete order, the once-only lock, its context, what it writes in order
module: order_completion
---

## What is it?
- `pg_order_complete($order_id, $values, $documents, $context)` in `includes/fn/order_completion.php`: makes a cart an order, every stage in one call (#2972). Fed by the order id, the column values the caller settled, the legal document types accepted and `$context`; never `$_SESSION`, `$_POST` or the liveform (a test reads the module for them).
- The visitor's return and a payment service's message (`pg_order_complete_from_payment()`, #3021, `order-completion-from-payment.md`) complete through the same code.

## What is the lock?
- The first write is `UPDATE orders SET … status = 'complete' WHERE id = ? AND status = 'incomplete'` (`pg_order_complete_query()`, pure). `mysqli_affected_rows() < 1` → returns null and writes nothing more: of two requests completing one order exactly one goes on.
- `submit_order()` on null (a second submit of the same checkout): logs "A second checkout of an order that was already completed changed nothing.", empties the cart session and goes to the receipt with `?order_id=`. Before, both requests completed the order: two numbers, two `order.created`, mails and stock twice. Since A2d2 the key code use, gift card redemption and commission rows run behind the lock too; auto-registration stays before it (`order-completion-before-lock.md`).

## What does it write, in order?
- The UPDATE (columns from `pg_order_complete_columns()`, in the caller's order, every value as quoted text as the inline query did; `ip_address` through `IFNULL(INET_ATON(?), 0)`; `checkout_page_id` (A3a, #2998) from `pg_order_checkout_page_id()` only while `pg_schema_has()` finds it); unknown column or non-scalar value → null and an `error_log` line.
- `pg_order_drop_saved_lines()`, the order number (`LOCK TABLES next_order_number WRITE`, read, advance, unlock, `UPDATE orders SET order_number`), `pg_legal_documents_store()`, `api_webhook_enqueue('order.created')`, `pg_sms_order_received()`. Returns the order number.
- A failed query ends the request (`output_error`), as before.

## Which stage functions follow it?
- After the SMS, in this order: before-lock writes moved behind it (`order-completion-before-lock.md`), receipt, ship dates, `order_completed` campaigns, `pg_order_complete_lines()` (the product loop: each line's functions, returns the summed reward points and the contact groups), then the after-line functions (`order-completion-after-lines.md`).
- `pg_order_complete_receipt($order_id, $number, $receipt, $customer_email, $new_account)`: `$receipt` is the checkout page's receipt settings (email, subject, format, header, footer, page_id). BCC from the order's lines (`pg_order_receipt_bcc_list()`, pure: store address first, each valid product address once). `$new_account` is the account the context hands over (`order-completion-account.md`).
- `pg_order_complete_ship_dates($order_id, $order_date, $auto_registration, $user_id)`: address book entries (`new`/`existing`), ship and delivery dates (`pg_order_transit_date()`, `pg_order_ship_date_adjusted()`, pure).
- Per line: `order-completion-lines.md`, `order-completion-line-comments.md`.

## What stays in submit_order()?
- The sign-in around auto-registration before the call; after it the legacy recurring profiles, the receipt page's session values, the post-save hook, the ERP invoice (`order-completion-context.md`).

## How is it tested?
- `tests/order_completion_test.php`: the tail of `submit_order()` with each function's body spliced in at its call, as one ordered list of writes; one number, `order.created`, SMS; each stage called once by the completion and never by `submit_order()`; the query; the pure helpers.
- Not run against a database or a real checkout here.

Related: commerce/order-completion-context.md, commerce/order-completion-lines.md, commerce/payment-callback.md, commerce/order-status-moves.md
