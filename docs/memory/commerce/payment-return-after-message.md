---
name: payment-return-after-message
description: A payment return that comes after the service's message completed the order leads to its receipt (pg_payment_return_receipt()), not to a new empty order's checkout
module: payments
---

## What happens when the message completes the order first?
- Stripe's `payment_intent.succeeded` often reaches `payment_callback.php` before the visitor's browser comes back from the 3-D Secure page. The message completes the order (`order-completion-from-payment.md`).
- On the return, `init.php` lets go of the session's order because it is no longer `incomplete`. `order_preview.php` and `express_order.php` then call `pg_payment_return_receipt()` before `initialize_order()`.
- It reads the order id the driver kept in its session state (`pg_payment_return_state_order_id()`: Stripe and PayPal Checkout keep `order_id`). When that order is `complete` or `exported` and the session holds no order or another one (`pg_payment_return_shows_receipt()`), it drops the state, writes a site log line, sets `completed_order_id`, clears the checkout form and redirects to the checkout page's next page with `?order_id=`, as a completed checkout does.

## What did the visitor see before?
- `initialize_order()` opened a new, empty order and `submit_order()` checked that out: "billing info is incomplete" or "There are no items. The order cannot be submitted." The visitor had paid and got the receipt e-mail, but saw an error in place of the receipt page.

## What is unchanged?
- The return and the message arriving together is still handled in `submit_order.php`: `pg_order_complete()` answers null and the return shows the receipt.
- Subscriptions: the message makes them from Stripe's `card_ref`, the same card the return reads (`subscriptions-completion-paths.md`).
- iyzico's message completes no order, and PayTR has no return, so neither reaches this.

## Tested?
- `tests/payment_return_completed_test.php` (the pure helpers, and the call placed before `initialize_order()` in both screens). No real Stripe delivery.
