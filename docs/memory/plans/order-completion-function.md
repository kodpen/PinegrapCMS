---
name: order-completion-function
description: Plan (#2382; A1 #2199, A2 #2638–#2972, A3a #2998; A3 waits for #3001): the submit_order() tail as one session-free function; the Stripe webhook and PayTR complete orders through it
---

## What did the owner decide?
- 2026-10-27 (#2382, owner: "A1"): a payment the service confirms after the visitor left completes the order from the webhook (the visitor may never return). Built as A1 → A2 → A3; until A3 lands the webhook only alerts (`commerce/payment-callback.md`).
- The six frozen legacy gateways' code in `submit_order.php` is not touched; only the shared tail they fall through to moves.

## What is done?
- A1 (#2199): when `authorize()` answers `redirect` with a payment id, the incomplete order keeps that id in `orders.transaction_id` and the amount it was started for in `orders.total` (`pg_payment_started_order_values()`, `UPDATE … AND status = 'incomplete'`). iyzico's 3-D Secure page names none: nothing written.
- A2 (#2638–#2972): `pg_order_complete()` runs every stage of the tail session-free with a `$context` (`commerce/order-completion.md` and the files it names). What stays in `submit_order()`: `commerce/order-completion-context.md`.
- A3a (#2998): step 11.0.120 adds `orders.checkout_page_id`; the redirect UPDATE (`pg_order_payment_started_query()`) also keeps the payment method, the figures and the page; the completing UPDATE writes the page. Both behind `pg_schema_has()` (`commerce/payment-callback.md`).

## What did the architect decide for A3? (#2984)
- Receipt settings: `orders.checkout_page_id` (step 11.0.120, `decisions-made-schema-2.md`) names the checkout page; A3 reads the receipt settings and the auto-registration switch from it. 0 or a deleted page: plain-text receipt, fixed subject with the order number, always sent; no account.
- Guest account: A3 links an existing account by the billing e-mail when the page's switch is on (`user_id`, `auto_registration = 'existing'`) and opens none. A3b opens one without signing in (`decisions-made-product-2.md`).

## What remains?
- A3 (M), waits for #3001: `pg_payment_notify_outcome()`'s `paid`/`authorized` branch completes an incomplete order through A2 when the order's `transaction_id` equals the intent, the amount equals `orders.total` (integer hundredths) and the currency is the store's (no answer carries one yet: add `raw['currency']`); a mismatch logs and alerts and completes nothing. Tests: completes once; a repeat or a complete order is a no-op; a mismatch alerts; receipt from the recorded page.
- Open for A3 (#3001): which legal document types count as accepted on the message's path, and the IP and browser `pg_legal_documents_store()` records there (today the request's).
- Settled for A3: values from the row A3a filled (`payment_method` '' = redirected before A3a: alert only); `submit_order()`'s line and page queries moved into shared functions; alert, not complete, when the lines charged today no longer sum to the kept `subtotal`; commission by `pg_order_commission()` from the kept figures; `shipping_less` as `SUM(ship_tos.shipping_cost) - orders.shipping`.
- A3b (S): account creation moves out of `submit_order()` into a session-free function both paths call; the visitor's path keeps the sign-in and device cookie; `pg_order_new_account_email()` / `pg_order_new_account_link()` read `$context`, not `$_SESSION`, so the receipt carries the set-password link on both paths. Tests: the webhook path signs nobody in; the e-mail carries the link.

## Where to start?
- A3 once #3001 is answered: build the context from the order row and the line query of `submit_order()`, call `pg_order_complete()`, then `pg_erp_auto_invoice()`. A3b after A3 lands; then PayTR (4c of `commerce/payment-driver-layer.md`) through the same function.
- `commerce/order-completion.md`, `commerce/order-completion-after-lines.md`, `commerce/order-completion-before-lock.md`, `commerce/payment-callback.md`.
