---
name: order-completion-function
description: Plan (#2382; A1 done in #2199, A2a in #2638): submit_order() completion tail as one session-free function with a once-only lock; then the Stripe webhook and PayTR complete orders through it
---

## What did the owner decide?
- 2026-10-27 (#2382, owner: "A1"): a payment the service confirms after the visitor left completes the order from the webhook (the visitor may never return). Built as A1 → A2 → A3; until A3 lands the webhook only alerts (`commerce/payment-callback.md`).
- The six frozen legacy gateways' code in `submit_order.php` is not touched; only the shared tail they fall through to moves.

## What is done?
- A1 (#2199): when `authorize()` answers `redirect` with a payment id, the incomplete order keeps that id in `orders.transaction_id` and the amount it was started for in `orders.total` (`pg_payment_started_order_values()`, `UPDATE … AND status = 'incomplete'`). Stripe's 3-D Secure redirect has one; iyzico's 3-D Secure page names none, so nothing is written for it. Completion writes both again.

- A2a (#2638): `pg_order_complete()` (`commerce/order-completion.md`) holds the lock and the writes from the completing UPDATE to the order SMS; `submit_order()` calls it.

## What remains?
- A2 (L), steps of about 1,000 product lines: each moves the next part of the tail into session-free functions of `includes/fn/order_completion.php` (`commerce/order-completion.md`), called by `submit_order()` where the code stood (same order of writes). `tests/order_completion_test.php` pins that order.
  - A2a: done (#2638).
  - A2b: done (#2687): `pg_order_complete_receipt()`, `pg_order_complete_ship_dates()`.
  - A2c, per-line functions: A2c1 (#2713), A2c2 (#2740), A2c3a (#2766), A2c3b (#2844), A2c4 (#2896) done (`commerce/order-completion-lines.md`, `commerce/order-completion-line-comments.md`).
  - A2d, cut in three:
    - A2d1 (in progress): after the loop: private access, rewards, contact groups, abandoned campaigns, visitor record, log, notification, ERP invoice, as stage functions called where the code stood.
    - A2d2: the writes before the lock (key code use, gift card redemption, auto-registration, affiliate commission): each runs once behind the lock, or stays with the visitor's request where only it can do them (card redemption needs the posted codes); the step names which. The gift card error mail goes with the redemption.
    - A2d3: `pg_order_complete()` calls the stage functions in order (the product loop, with the reward points and contact groups it collects, included), so one call completes the whole order.
  - Stays in `submit_order()`: the six frozen legacy gateways and their recurring profiles (a card the visitor typed), the session values the receipt page reads, the redirect.
- A3 (S-M): `pg_payment_notify_outcome()`'s `paid`/`authorized` branch completes an incomplete order through A2 when the order's `transaction_id` equals the intent, the amount equals `orders.total` (integer hundredths) and the currency is the store's; a mismatch logs and alerts and completes nothing. Tests: completes once; a second event, or an order already complete, is a no-op; a mismatch alerts. Then PayTR (4c of `payment-driver-layer.md`) uses the same function.

## Open questions?
- For A3: the receipt e-mail's settings (on, subject, format, header, footer, page) live on the checkout page (`order_preview_pages` / `express_order_pages`), and the order does not name the page it was placed from. A3 needs them without the visitor; if that takes a column, it is an architect question first.

## Where to start?
- A2d: after the product loop of `submit_order.php` (private access, rewards, contact groups, abandoned campaigns, visitor record, log, notification, ERP invoice), then the writes before the lock, then `pg_order_complete()` calling the stage functions; `includes/fn/order_completion.php`, `tests/order_completion_test.php`.
- `commerce/order-completion.md`, `commerce/order-completion-lines.md`, `commerce/order-completion-line-comments.md`, `commerce/payment-callback.md`.
