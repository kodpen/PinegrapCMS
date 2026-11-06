---
name: order-refunded-amount-writes
description: How orders.refunded_amount is written: every writer adds to the column (pg_order_refunded_add_expr()), the order screen's refund re-reads the total after the add
module: payments
---

## Who writes orders.refunded_amount?
- The order screen's refund (view_order.php, `submit_payment_refund`), a return's refund (includes/fn/returns.php) and a store credit refund (includes/fn/store_credit_refunds.php). All three add: `refunded_amount = COALESCE(refunded_amount, 0) + <minor units>`. No writer sets an absolute total.

## Why does the order screen add instead of writing the total it read?
- It reads the order before the payment service call, a network round-trip. A return or store credit refund recorded during that call added its own amount; writing back `read + refund` dropped it, so the refunded total was too low and a later refund could go past what was paid.
- The value comes from `pg_order_refunded_add_expr($cents)` (includes/fn/payments.php, pure, rounds to whole minor units). After the UPDATE the screen re-reads `refunded_amount`, and the full-refund cancellation compares that fresh total with `orders.total`.

## Is the cap check fresh?
- The remaining amount (`total - refunded_amount`) is read after the screen takes its per-order `GET_LOCK` (`pg_payment_refund_lock_name()`), so a second screen refund waits. A return refund does not take that lock; one landing during the payment call is now counted, but is not checked against this refund's cap.

## How is it tested?
- `tests/order_refunded_amount_test.php`: the expression, rounding, and that the screen's handler adds, then reads back before the full-refund check.
