---
name: order-returns-refund-cancelled-order
description: A received return of a cancelled (or incomplete) order is never refunded: pg_return_refund_order_open(), code order_cancelled, API 409 refund_order_cancelled (#7559, PR #7579)
module: returns
---

## Which returns can be refunded?
Only those whose order is `complete` or `exported`
(`pg_return_refund_order_open()`), the same statuses
`pg_return_create_check()` creates a return on. `pg_return_refund()` checks
it under the order's return lock, after reading the plan and before the
return moves, and refuses with code `order_cancelled`.

## Why?
Cancelling an order voids its payment through the driver but does not raise
`orders.refunded_amount` (only the order screen's refund, the cancel's store
credit refund and returns write it). The refund plan's `remaining` therefore
still showed the whole order, and a return received before or after the
cancel could be refunded as store credit or a manual refund: the customer got
the money twice and `refunded_amount` exceeded what was paid (#7559). The
cancellation's own refund path (`pg_order_cancel_refund_owed()`) is the one
that gives a cancelled order's money back.

## What do the screen and the API do?
`view_return.php` shows no refund form for such a return, only a line that
the money goes back with the cancellation. `POST /returns/{id}` with
`action=refund` answers 409 `refund_order_cancelled`, from the plan in
`api_return_action_check()` and from the write's code in
`api_return_write_refuse()`.
