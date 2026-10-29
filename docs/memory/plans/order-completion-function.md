---
name: order-completion-function
description: Plan (#2382; A1 #2199, A2 #2638–#2972, A3 #2998, #3021; A3b remains): the submit_order() tail as one session-free function; the Stripe webhook and PayTR complete orders through it
---

## What did the owner decide?
- 2026-10-27 (#2382, owner: "A1"): a payment the service confirms after the visitor left completes the order from the webhook. Built as A1 → A2 → A3 (`commerce/payment-callback.md`).
- The six frozen legacy gateways' code in `submit_order.php` is not touched; only the shared tail they fall through to moves.

## What is done?
- A1 (#2199): when `authorize()` answers `redirect` with a payment id, the incomplete order keeps that id in `orders.transaction_id` and the amount it was started for in `orders.total` (`pg_payment_started_order_values()`, `UPDATE … AND status = 'incomplete'`). iyzico's 3-D Secure page names none: nothing written.
- A2 (#2638–#2972): `pg_order_complete()` runs every stage of the tail session-free with a `$context` (`commerce/order-completion.md` and the files it names). What stays in `submit_order()`: `commerce/order-completion-context.md`.
- A3 (#2998, #3021): steps 11.0.120 (`orders.checkout_page_id`) and 11.0.121 (`orders.legal_acceptance`); the redirect keeps the payment method, figures, page and acceptance; the payment service's message completes the order through `pg_order_complete_from_payment()` (`commerce/order-completion-from-payment.md`, `commerce/payment-callback.md`).

## What did the architect decide for A3?
- #2984 (receipt from the recorded page, account linked not opened) and #3001 (legal acceptance column): recorded in `decisions-made-product-2.md`, `decisions-made-schema-2.md`; how it is built: `commerce/order-completion-from-payment.md`.

## What remains?
- A3b (S, in progress on `feature/order-completion-a3b`): account creation moves out of `submit_order()` into a session-free function both paths call; the visitor's path keeps the sign-in and device cookie; `pg_order_complete_from_payment()` calls it when the page's switch is on and no account has the billing e-mail; `pg_order_new_account_email()` / `pg_order_new_account_link()` read `$context`, not `$_SESSION`, so the receipt carries the set-password link on both paths. Tests: the message's path signs nobody in; the e-mail carries the link.

## Where to start?
- A3b, then PayTR (4c of `commerce/payment-driver-layer.md`): its callback completes an incomplete order through `pg_order_complete_from_payment()` once `pg_payment_notify_outcome()` answers `complete` (the answer carries `raw['currency']`).
- `commerce/order-completion-from-payment.md`, `commerce/order-completion-before-lock.md`, `commerce/payment-callback.md`.
