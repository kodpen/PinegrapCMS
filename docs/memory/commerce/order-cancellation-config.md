---
name: order-cancellation-config
description: Order cancel config defines, cancel_order.php endpoint, order_view cancel tokens/visibility flags, new cancel and refund columns, email and driver auto-refund gates.
---

## Which config defines control cancellation? (data/config.php)
- `ECOMMERCE_ORDER_CANCEL_ALLOWED` (bool, default `false`): customer self-service cancel enabled.
- `ECOMMERCE_ORDER_CANCEL_UNTIL_SHIPPED` (bool, default `true`): cancel only before shipping.
- `ECOMMERCE_CANCEL_EMAIL_NOTIFICATION` (bool, default `true`): customer email on cancel.
- `ECOMMERCE_ORDER_CANCEL_AUTO_REFUND` (bool, default `false`): opt-in Iyzipay auto-refund.

## Which endpoint and order_view tokens are there?
- `cancel_order.php`: CSRF + ownership check + status update. Refund is manual (gateway dashboard); a REFUND ACTION REQUIRED log entry is left.
- Tokens: `__cancel_form` (pre-built form: CSRF + order_id + reason + submit + refund warning), `__cancel_status` (alert for the `?cancelled=1|shipped|already` flash).
- Visibility flags (auto-wired in order_view default tree): `can_cancel` (config on + status != 'cancelled' + optionally not yet shipped), `is_cancelled`, `cancel_flash_success` / `cancel_flash_shipped` / `cancel_flash_already`.

## Which columns were added?
- 2026.1.26 `orders`: `cancelled_at INT(10) UNSIGNED`, `cancelled_by INT(10) UNSIGNED`, `cancellation_reason VARCHAR(500)`.
- 2026.1.27 `orders` (defensive `SHOW COLUMNS` probe `_orders_has_refund_columns()`): `refund_status ENUM('', 'pending', 'refunded', 'failed', 'manual_required')`, `refunded_at INT(10) UNSIGNED NULL`, `refund_reference VARCHAR(255)` (gateway transaction ID).

## When is the customer e-mailed? (inside `process_order_cancellation`, defensive try/catch)
- Gated by `ECOMMERCE_CANCEL_EMAIL_NOTIFICATION`. HTML mail to the customer's `billing_email_address` via the `email()` helper (Turkish text: `Order Cancelled`, `Cancellation reason`, refund note).
- Silently skipped when `EMAIL_ADDRESS` is missing or `billing_email_address` is empty.

## When is the money given back automatically? (opt-in)
- When `ECOMMERCE_ORDER_CANCEL_AUTO_REFUND` is true (or the caller forces it): the payment method's driver voids the payment (`pg_payment_call(…, 'cancel', …)`) and the result is written to `orders.refund_status` (`commerce/payment-refunds.md`).
- A failed cancel does NOT roll back the cancellation: it is logged and the operator refunds manually.
- Tried only when the order's driver can cancel (`pg_payment_cancel_driver()`: a card order → the gateway's card driver, iyzico on `Iyzipay`; `Pay With Iyzico` → its own) AND `transaction_id` is set AND `total > 0`.

Related: docs/memory/commerce/order-cancellation-flow.md, docs/memory/commerce/payment-refunds.md
