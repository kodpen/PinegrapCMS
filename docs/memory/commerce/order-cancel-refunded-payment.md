---
name: order-cancel-refunded-payment
description: Cancelling an order whose payment was refunded in full or in part: no void, the rest flagged, one helper for the cancel window and the cancellation (#974)
---

## Which helper decides what a cancellation does to the payment?
- `pg_order_cancel_payment_plan($total, $refunded, $method, $has_txn, $cancel_driver, $auto_refund)` (includes/fn/ecommerce.php, pure): `remaining` = `total - refunded_amount` (never below 0), `refunded`, and `action`:
  - `void`: auto refund on (or forced), a cancel driver, a `transaction_id`, nothing refunded yet;
  - `manual`: money left, a method took it (not `''`/`Offline Payment`), no void → `manual_required` and the `REFUND ACTION REQUIRED … Amount:` line with the remaining amount;
  - `none`: nothing left (refunded in full, free order) or no method took the money.
- `process_order_cancellation()` reads `refunded_amount` (guarded by `pg_schema_has()`) and acts on it; the cancel window of view_order.php asks it with the auto refund on, since that window forces the void.

## Why is a partly refunded payment not voided?
- A driver's `cancel` takes no amount: it reverses the whole payment. After a partial refund the remaining amount is flagged instead, with a second log line naming what was refunded before; the window says how much was refunded and that the rest is not returned automatically. Refunding the rest through the driver's `refund` on cancel was not chosen; open for the owner.

## What did it fix? (#974)
- A return refund (`pg_return_refund()`) gives the money back and leaves the order `complete` with `refunded_amount = total`. Cancelling it later: the window said the payment would NOT be refunded, while the cancellation, which did not read `refunded_amount`, asked iyzico to cancel the whole payment; iyzico refused, the order got `manual_required`, the "Refund Pending · <whole total>" banner, a MANUAL REFUND REQUIRED log line and the customer mail's "refunds are processed manually" paragraph. A method without a driver (PayPal Express) flagged the whole total too.
- Now such an order is cancelled without touching the payment: no driver call, no flag, no refund paragraph in the mail; the window says the payment was already refunded in full.

## What do the screens show?
- Refund Pending banner and the notice after the cancel: the payment money left (`total - refunded_amount`) plus what gift cards paid (commerce/order-cancel-gift-cards.md), not the whole total.

## Tests?
- `tests/order_cancel_payment_test.php` (void, refunded in full, partly refunded, manual cases, nothing taken). No running shop was used for the PR.

Related: commerce/payment-refunds.md, commerce/order-returns-refund.md, commerce/order-cancellation-flow.md
