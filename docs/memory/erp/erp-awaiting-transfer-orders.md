---
name: erp-awaiting-transfer-orders
description: Awaiting-payment (bank transfer) orders: derived state predicate, pg_order_mark_paid, ERP settlement hook, auto-cancel sweep in job.php, cancel-reason display.
---

## How is an awaiting-payment order identified? (migration 4.48)
- Derived state, not a new enum value: `pg_order_awaiting_payment($order)` (`includes/fn/ecommerce.php`, under `_pg_order_status_stage`): `payment_method` is `'Offline Payment'` or one of `pg_order_pending_payment_methods()` (`'PayPal Checkout'`, a capture PayPal left pending: `commerce/payment-driver-paypal.md`) `&& (int)paid_at === 0 && status IN ('complete','exported')`. The `awaiting_transfer` list filter, the auto-cancel sweep and verified reviews stay Offline Payment only. The order stays `complete`, so reports and `IN ('complete','exported')` queries work untouched.
- List filter `view_orders.php` `case 'awaiting_transfer'` builds the same predicate in SQL and is offered like `refund_pending` by probing the `paid_at` column (`$paid_at_column_exists`); the row gets a second "Ödeme bekleniyor" badge.
- Status badge while waiting: text `Awaiting Payment` in the `pending` colour.
- An order put on the customer's ERP account (`orders.erp_on_account = 1`, 10.60) is an `Offline Payment` with `paid_at = 0` too: `pg_order_awaiting_payment()` and the `awaiting_transfer` filter include it, the auto-cancel sweep skips it (`erp_on_account = 0`): docs/memory/erp/erp-credit-limit-and-on-account.md.

## Who records the payment?
- `pg_order_mark_paid(int $order_id, int $user_id): bool`: `UPDATE orders SET paid_at = time() WHERE id = ? AND paid_at = 0` (second click is a no-op; `log_activity` when a row changed). The line is `pg_order_paid_log_text()`, through `lang()`: "Order (#…) was marked paid.", "… automatically." on a system path (`$user_id = 0`); who did it is the log's user column (#941; until then English with `user_id=`).
- A recorded payment is the `order_paid` moment of the automatic invoice (`pg_erp_auto_invoice()`): docs/memory/erp/erp-auto-invoice.md.
- Caller `view_order.php` `submit_mark_paid` (shared `validate_token_field()` + the `pg_order_awaiting_payment` gate; button in `#button_bar` with its own form, only while waiting).
- `erp_invoice_refresh_paid()` (`includes/erp/settlement.php`) calls the same function when the invoice becomes `paid` and the order is `Offline Payment` (inside the receipt's transaction) and fills the invoice's empty `payment_date` with the closing receipt's `doc_date`: the ERP receipt is the confirmed payment moment of a transfer.

## How are unpaid transfer orders auto-cancelled?
- `job.php`, right after the abandoned-order block. Active when `ECOMMERCE_OFFLINE_PAYMENT_CANCEL_DAYS > 0` (`config.ecommerce_offline_payment_cancel_days`, `?? 0` in `init.php`; Settings → E-Ticaret offline payment row, 0–255, non-digits stripped and trimmed on save).
- Selects orders with `order_date` past the threshold, `paid_at = 0`, `status = 'complete'`, `COALESCE(erp_invoice_id,0) = 0`; cancels in batches of 50 with `process_order_cancellation($id, $reason, true, 0, false)`.
- An invoiced order is left to the accountant; a shipped one (`_order_has_shipped`) is skipped. The check is in the sweep because the admin flag bypasses the function's own shipping gate.
- Reason key `Cancelled automatically: bank transfer not received within {var:1} days.` is written to `cancellation_reason`.

## Where is the cancel reason shown?
- `view_order.php` under the status row (with the `cancelled_at` date) and in the customer timeline (`widgets.php`, "Cancelled — reason").
- The timeline's "Ödeme Alındı" event reads `paid_at > 0`; old orders keep the `transaction_id` → `order_date` proxy.

Related: docs/memory/commerce/order-payment-method-normalization.md
