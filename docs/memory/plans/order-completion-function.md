---
name: order-completion-function
description: Plan (#2382; A1 #2199, A2 #2638–#2972, A3 decided in #2984): the submit_order() tail as one session-free function with a once-only lock; then the Stripe webhook and PayTR complete orders through it
---

## What did the owner decide?
- 2026-10-27 (#2382, owner: "A1"): a payment the service confirms after the visitor left completes the order from the webhook (the visitor may never return). Built as A1 → A2 → A3; until A3 lands the webhook only alerts (`commerce/payment-callback.md`).
- The six frozen legacy gateways' code in `submit_order.php` is not touched; only the shared tail they fall through to moves.

## What is done?
- A1 (#2199): when `authorize()` answers `redirect` with a payment id, the incomplete order keeps that id in `orders.transaction_id` and the amount it was started for in `orders.total` (`pg_payment_started_order_values()`, `UPDATE … AND status = 'incomplete'`). Stripe's 3-D Secure redirect has one; iyzico's 3-D Secure page names none, so nothing is written for it. Completion writes both again.
- A2 (#2638, #2687, #2713, #2740, #2766, #2844, #2896, #2914, #2937, #2972): `pg_order_complete()` runs every stage of the tail session-free with a `$context` (`commerce/order-completion.md` and the files it names). Stays in `submit_order()`: auto-registration, the six frozen legacy gateways and their recurring profiles, the session values the receipt page reads, the post-save hook, the redirect.

## What did the architect decide for A3? (#2984)
- Receipt settings: `orders.checkout_page_id` (step 11.0.120, `decisions-made-schema-2.md`) names the checkout page; written by the A1 redirect UPDATE and the completing UPDATE, read by A3 for the receipt settings and the auto-registration switch with `submit_order()`'s page query. 0 or a deleted page: plain-text receipt, fixed subject with the order number, always sent; no account.
- Guest account: A3 links an existing account by the billing e-mail when the page's switch is on (`user_id`, `auto_registration = 'existing'`) and opens none. A3b opens one without signing in (`decisions-made-product-2.md`).

## What remains?
- A3 (M): the migration step and the column writes; `pg_payment_notify_outcome()`'s `paid`/`authorized` branch completes an incomplete order through A2 when the order's `transaction_id` equals the intent, the amount equals `orders.total` (integer hundredths) and the currency is the store's; a mismatch logs and alerts and completes nothing. Tests: completes once; a second event, or an order already complete, is a no-op; a mismatch alerts. Then PayTR (4c of `payment-driver-layer.md`) uses the same function.
- A3b (S): account creation (user without a password, set-password link, registration group, blocked-address refusal) moves out of `submit_order()` into a session-free function both paths call; the visitor's path keeps the sign-in and device cookie; `pg_order_new_account_email()` / `pg_order_new_account_link()` read `$context`, not `$_SESSION`, so the receipt carries the set-password link on both paths. Tests: the webhook path signs nobody in; the e-mail carries the link.

## Where to start?
- A3: the step, then build the context from the order row and the line query of `submit_order()`, call `pg_order_complete()`, then `pg_erp_auto_invoice()`. A3b after A3 lands.
- `commerce/order-completion.md`, `commerce/order-completion-after-lines.md`, `commerce/order-completion-before-lock.md`, `commerce/payment-callback.md`.
