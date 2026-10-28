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
- A2 (L), cut into steps of about 1,000 product lines; each step moves the next part of the tail into `pg_order_complete()` (`commerce/order-completion.md`), fed by the order row, its recipients and items, never `$_SESSION` or the posted form. `tests/order_completion_test.php` pins the tail's writes in order; each step keeps it green.
  - A2a: the function and its lock: `UPDATE orders … status = 'complete' WHERE … status = 'incomplete'` (columns from the caller), the affected-rows count, saved lines, order number, legal documents, `order.created`, the order SMS. A request that loses the lock goes to the receipt and writes nothing more.
  - A2b: after the number, up to the per-product loop: the gift card error mail, the receipt e-mail (each copy), ship and delivery dates and the address book, auto e-mail campaigns. The receipt e-mail reads the order, not the visitor's session (`pg_order_receipt_audience()` stays).
  - A2c: the per-product loop of `submit_order()` (stock and its announcement, reservations, memberships, private folders, gift cards issued, e-mail campaigns, product forms and their mails, comments): about 1,650 lines today, so A2c may split again where the loop allows.
  - A2d: after the loop: private access, reward points, contact groups, abandoned campaigns, visitor record, activity log and notification, ERP invoice. Then the writes still before the lock (key code use, gift card redemption, auto-registration, affiliate commission) are settled: run once behind the lock, or kept with the visitor's request where only the visitor's request can do them (card redemption needs the posted codes); the step names which.
  - Stays in `submit_order()`: the six frozen legacy gateways and their recurring profiles (a card the visitor typed), the session values the receipt page reads, the redirect.
- A3 (S-M): `pg_payment_notify_outcome()`'s `paid`/`authorized` branch completes an incomplete order through A2 when the order's `transaction_id` equals the intent, the amount equals `orders.total` (integer hundredths) and the currency is the store's; a mismatch logs and alerts and completes nothing. Tests in `tests/payment_stripe_webhook_test.php`: completes once, a second event is a no-op, amount/currency/intent mismatch → alert, already complete → no-op. Then PayTR (4c of `payment-driver-layer.md`) uses the same function.

## Open questions?
- None.

## Where to start?
- A2b: `pg_order_complete()` in `includes/fn/order_completion.php` and its call in `submit_order.php`; `tests/order_completion_test.php`.
- `submit_order.php` from the driver branch (`pg_payment_checkout_driver()`) to the end of `submit_order()`; `commerce/payment-callback.md`; `commerce/order-status-moves.md`.
