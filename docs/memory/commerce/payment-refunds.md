---
name: payment-refunds
description: Money back through payment drivers (PR #873): the order screen's refund (the gateway's driver), the cancellation's void (the method's redirect driver), log_name, the outcome lines
---

## Which driver gives money back on the order screen?
- `pg_payment_refund_driver(ECOMMERCE_PAYMENT_GATEWAY)` (includes/fn/payments.php): the card driver of the store's Payment Gateway value, when it defines `refund`; iyzico for `Iyzipay`. Whatever method the order was paid with: the dialog has always talked to the gateway's service only.
- view_order.php shows the Refund button and `#payment-refund-modal` (post `submit_payment_refund`) when that driver exists, the order has a `transaction_id`, is not `cancelled`/`incomplete` and has money left (`total - refunded_amount`). The title and note name the driver's `label`.
- The post: same checks and ERP line amounts as before, then `pg_payment_call($key, 'refund', [orders row (SELECT *), hundredths, order_items rows])`. The answer's `log` lines are written first; a failure shows `error` (iyzico's own sentence); success writes `refunded_amount`, `order_refunds.transaction_id` = `refund_reference`, the `Refunded … for order #N.` line and, with full refund, status `cancelled`.
- The ERP line dialog (includes/erp/order_refund.php) uses `payment_refund_*` ids; it only works out the amount.

## Which driver voids a cancelled order's payment?
- `pg_payment_cancel_driver($order['payment_method'])`: the redirect driver of the order's method when it defines `cancel` (Pay With Iyzico → `pay_with_iyzico`). A card order stores `Credit/Debit Card`, not the gateway, so it gets none and falls to the `REFUND ACTION REQUIRED` line, as before the drivers.
- process_order_cancellation() asks only when the auto-refund is on (or forced), the order has a `transaction_id` and `total > 0`. The driver gets the orders row re-read after the UPDATE, with `cancellation_reason` set to the reason just written (iyzico's description).

## What does the cancellation record and log?
- `pg_payment_cancel_outcome($order_id, $transaction_id, $answer, $service)` (pure): the driver's `log` lines first, then one of
  - `Auto-refund SUCCESS via <service> — order #N (payment <ref>).` → `refunded`, ref = `refund_reference` or the transaction id;
  - `Auto-refund FAILED via <service> — order #N. MANUAL REFUND REQUIRED. Gateway message: <raw.message or (no message)>`;
  - `Auto-refund EXCEPTION — order #N. MANUAL REFUND REQUIRED. <message>` when `raw.exception` is set (pg_payment_call() caught it).
  Both failures → `manual_required`.
- `<service>` = `pg_payment_log_name($key)`: the definition's optional `log_name`, default `label`. Both iyzico drivers say `Iyzipay`, so the lines are the gateway code's.
- A failure's `raw.message` is the service's own words (`''` when none); the iyzico refund and cancel keep it there.

## What stays outside?
- `iyzipay_cancel_payment()` and the 3-D Secure return in submit_order.php (plan step 2b).
- The cancel dialog's "This will void the payment via iyzico." note still follows `ECOMMERCE_PAYMENT_GATEWAY == 'Iyzipay'`, though a card order is not voided (open question in the plan).

## How was it verified?
- `tests/payment_refunds_test.php`: requests through `pg_payment_call()` against a stand-in for iyzico's HTTP client, compared with the statements the old view_order.php and process_order_cancellation() blocks used (copied as `legacy_refunds_*()`), and the outcome against the old log lines for success, refusal with and without a message, no answer and a thrown exception. A throwaway sandbox (tools/setup_sandbox.sh, iyzico unreachable) gave the same error box, log lines and refund_status as the old code for a card refund, a PWI cancel and a card cancel; no iyzico sandbox.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/order-cancellation-config.md
