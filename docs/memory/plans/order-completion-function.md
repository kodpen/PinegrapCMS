---
name: order-completion-function
description: Plan (#2382; A1 done in #2199): submit_order() completion tail as one session-free function with a once-only lock; then the Stripe webhook and PayTR complete orders through it
---

## What did the owner decide?
- 2026-10-27 (#2382, owner: "A1"): a payment the service confirms after the visitor left completes the order from the webhook ("en makül güncel yol olsun … global pinegrap kullanıcıları ihtiyaç duyabilir"; the visitor may never return). Built as A1 → A2 → A3; until A3 lands the webhook only alerts (`commerce/payment-callback.md`).
- The six frozen legacy gateways' code in `submit_order.php` is not touched; only the shared tail they fall through to moves.

## What is done?
- A1 (#2199): when `authorize()` answers `redirect` with a payment id, the incomplete order keeps that id in `orders.transaction_id` and the amount it was started for in `orders.total` (`pg_payment_started_order_values()`, `UPDATE … AND status = 'incomplete'`). Stripe's 3-D Secure redirect has one; iyzico's 3-D Secure page names none, so nothing is written for it. Completion writes both again.

## What remains?
- A2 (L, cut into steps of about 1,000 lines): move the completion tail of `submit_order($type)` (final `UPDATE orders … status = 'complete'`, order number, saved lines, legal documents, stock, gift cards, offers, affiliate commission, e-mails, SMS, `order.created` webhook) into one function fed by the order row and its items, not `$_SESSION` or the posted form. `UPDATE … WHERE status = 'incomplete'` and its affected-rows count is the once-only lock: the visitor's return and the webhook may both call it, exactly one completes. Tests pin the tail's writes before the move (red first).
- A3 (S-M): `pg_payment_notify_outcome()`'s `paid`/`authorized` branch completes an incomplete order through A2 when the order's `transaction_id` equals the intent, the amount equals `orders.total` (integer hundredths) and the currency is the store's; a mismatch logs and alerts and completes nothing. Tests in `tests/payment_stripe_webhook_test.php`: completes once, a second event is a no-op, amount/currency/intent mismatch → alert, already complete → no-op. Then PayTR (4c of `payment-driver-layer.md`) uses the same function.

## Open questions?
- None.

## Where to start?
- `submit_order.php` from the driver branch (`pg_payment_checkout_driver()`) to the end of `submit_order()`; `commerce/payment-callback.md`; `commerce/order-status-moves.md`.
