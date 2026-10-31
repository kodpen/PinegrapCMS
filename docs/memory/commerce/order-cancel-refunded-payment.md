---
name: order-cancel-refunded-payment
description: What a cancellation does to the payment (pg_order_cancel_payment_plan()): refunded in full or in part, bank transfer received or not, the wording, the log order number (PR #1222, #974, #4298, #4299)
---

## Which helper decides what a cancellation does to the payment?
- `pg_order_cancel_payment_plan($total, $refunded, $method, $has_txn, $cancel_driver, $auto_refund, $paid_at = null)` (includes/fn/ecommerce.php, pure): `remaining` = `total - refunded_amount` (never below 0), `refunded`, and `action`:
  - `void`: auto refund on (or forced), a cancel driver, a `transaction_id`, nothing refunded yet;
  - `manual`: money left, a method took it and it arrived, no void → `manual_required` and the `REFUND ACTION REQUIRED … Amount:` line with the remaining amount;
  - `none`: nothing left (refunded in full, free order), no method, or the payment never arrived: `Offline Payment` or a pending method (`pg_order_pending_payment_methods()`) with `paid_at = 0`.
- `paid_at` null (schema without the column) counts as arrived: the same rule as `pg_order_cancel_refund_owed()`, so the flag and the owed figure agree.
- `process_order_cancellation()` reads `refunded_amount` and `paid_at` (guarded by `pg_schema_has()`) and acts on it; the cancel window of view_order.php asks it with the auto refund on, since that window forces the void, and words its note by `action` (no refund sentence for `none`).

## How is a bank transfer refund worded? (PR #NNNN, #4298)
- A received bank transfer (`paid_at > 0`) cancelled is `manual`: before, `Offline Payment` was always `none`, so a paid transfer raised no flag, banner, list card or dashboard lane, while the window of an unpaid one still said "refund it in your payment provider's panel".
- `pg_order_refund_instruction($method)` gives the banner and the post-cancel notices (view_order.php, cancel_order.php) "send the amount back by bank transfer" for `Offline Payment`, the provider sentence otherwise; the window and the `REFUND ACTION REQUIRED` log line have the same two wordings.

## How do the auto refund log lines name the order? (PR #NNNN, #4299)
- `pg_payment_cancel_outcome($order_number, …)` (payments.php) takes `pg_order_log_number()`, not the row id: its SUCCESS / FAILED / EXCEPTION lines name the order number like the cancellation's other lines.

## Why is a partly refunded payment not voided?
- A driver's `cancel` takes no amount: it reverses the whole payment. After a partial refund the remaining amount is flagged instead, with a second log line naming what was refunded before; the window says how much was refunded and that the rest is not returned automatically. Refunding the rest through the driver's `refund` on cancel was not chosen; open for the owner.

## What did it fix? (PR #1222, #974)
- A return refund (`pg_return_refund()`) leaves the order `complete` with `refunded_amount = total`. Cancelling it later asked iyzico to cancel the whole payment; iyzico refused and the order got `manual_required`, the "Refund Pending · <whole total>" banner, a MANUAL REFUND REQUIRED line and the mail's manual refund paragraph.
- Now such an order is cancelled without touching the payment: no driver call, no flag, no refund paragraph in the mail; the window says the payment was already refunded in full.

## What do the screens show?
- Refund Pending banner and the notice after the cancel: the payment money left (`total - refunded_amount`) plus what gift cards paid (commerce/order-cancel-gift-cards.md), not the whole total.

## Tests?
- `tests/order_cancel_payment_test.php` (void, refunded in full, partly refunded, manual cases, nothing taken, bank transfer received or not, pending method); `tests/payment_refunds_test.php` (outcome lines by order number). No running shop was used for the PR.

Related: commerce/payment-refunds.md, commerce/order-returns-refund.md, commerce/order-cancellation-flow.md
