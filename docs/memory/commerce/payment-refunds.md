---
name: payment-refunds
description: Money back through payment drivers (PRs #873, #903): the order's driver for the order screen's refund and the cancellation's void, log_name, the outcome lines
---

## Which driver took an order's payment?
- `pg_payment_order_driver($method, $gateway = null)` (includes/fn/payments.php): a card order (`Credit/Debit Card`) → the card driver of the Payment Gateway value (null = `ECOMMERCE_PAYMENT_GATEWAY` now; the order does not record its gateway); any other method → its redirect driver, whatever the gateway (Pay With Iyzico → `pay_with_iyzico`). PayPal Express, Offline, '' → none. The checkout uses it too.
- Owner, 2026-10-20 (PR #903): card orders are voided on cancel too; a redirect method's order is refunded through its own driver. Before, the refund followed only the gateway and a card order was never voided.

## What does the order screen refund through?
- `pg_payment_refund_driver($payment_method)`: the order's driver when it defines `refund`.
- view_order.php shows the Refund button and `#payment-refund-modal` (post `submit_payment_refund`) when that driver exists, the order has a `transaction_id`, is not `cancelled`/`incomplete` and has money left (`total - refunded_amount`). The title and note name the driver's `label`.
- The post: same checks and ERP line amounts as before, then `pg_payment_call($key, 'refund', [orders row (SELECT *), hundredths, order_items rows])`. The answer's `log` lines are written first; a failure shows `error` (iyzico's own sentence); success writes `refunded_amount`, `order_refunds.transaction_id` = `refund_reference`, the `Refunded … for order #N.` line and, with full refund, status `cancelled`.
- The ERP line dialog (includes/erp/order_refund.php, `payment_refund_*` ids) only works out the amount.

## Which driver voids a cancelled order's payment?
- `pg_payment_cancel_driver($order['payment_method'])`: the order's driver when it defines `cancel`; none (legacy gateway, PayPal, Offline) → `REFUND ACTION REQUIRED`.
- Whether it is asked, for the cancellation and the cancel dialog alike: `pg_order_cancel_payment_plan()`, commerce/order-cancel-refunded-payment.md. The driver gets the orders row re-read after the UPDATE, `cancellation_reason` = the new reason (iyzico's description).

## What does the cancellation record and log?
- `pg_payment_cancel_outcome($order_id, $transaction_id, $answer, $service)` (pure): the driver's `log` lines first, then one of
  - `Auto-refund SUCCESS via <service> — order #N (payment <ref>).` → `refunded`, ref = `refund_reference` or the transaction id;
  - `Auto-refund FAILED via <service> — order #N. MANUAL REFUND REQUIRED. Gateway message: <raw.message or (no message)>`;
  - `Auto-refund EXCEPTION — order #N. MANUAL REFUND REQUIRED. <message>` when `raw.exception` is set (pg_payment_call() caught it).
  Both failures → `manual_required`.
- `<service>` = `pg_payment_log_name($key)`: the definition's optional `log_name`, default `label`. Both iyzico drivers say `Iyzipay`, so the lines are the gateway code's.
- A failure's `raw.message` is the service's own words (`''` when none); with `raw.exception` set it is PHP's text, for the log (pg_payment_call(), iyzico refund's unreadable body: PR #1260).

## What stays outside?
- `iyzipay_cancel_payment()` and the 3-D Secure return in submit_order.php (plan step 2b).

## How was it verified?
- `tests/payment_refunds_test.php`: requests through `pg_payment_call()` against a stand-in for iyzico's HTTP client, compared with the statements the old view_order.php and process_order_cancellation() blocks used (copied as `legacy_refunds_*()`), and the outcome against the old log lines for success, refusal with and without a message, no answer and a thrown exception. A throwaway sandbox (iyzico unreachable): card and PWI orders on Iyzipay and Stripe stores, buttons, notes, void attempts, log lines; no iyzico sandbox.

Related: docs/memory/commerce/payment-drivers.md, docs/memory/commerce/payment-driver-iyzico.md, docs/memory/commerce/order-cancellation-config.md
