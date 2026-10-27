---
name: payment-driver-layer
description: Plan (approved 2026-10-19; 1, 2, 2b, 3a, 3b½, 4a, 5 done; 3b A1-A3, 4b, 4c open): payment drivers beside submit_order.php; iyzico and Stripe on it; then PayPal v2 and PayTR
---

## What did the owner decide?
- 2026-10-19: build a driver layer and move only iyzico, Stripe and PayPal onto it; Authorize.Net, First Data, ClearCommerce/PayFuse, Payflow Pro, Payments Pro and Sage stay exactly where they are in `submit_order.php` (frozen: no refactor, no removal). Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change.

## Why?
- All eight gateways were inline in `submit_order.php`; classic Stripe takes raw card fields through the server (no SCA). PayTR, Sipay, Param and bank vPOS are standard at Turkish competitors; Apple/Google Pay lift mobile conversion.

## What is decided?
- Contract `includes/payments/driver.php` (step 1; `commerce/payment-drivers.md`): `pg_payment_<key>_` prepare, authorize, complete, capture, refund, cancel, notify, capabilities, fields; callers use `pg_payment_call()`. One active card driver plus optional redirect drivers, as today.
- `submit_order.php` has one branch (step 2, `commerce/payment-driver-iyzico.md`): a gateway or method with a driver is paid by it; legacy branches are reached exactly as before.
- PayTR: iframe/redirect flow, hash verification, installment table, refund API, callback endpoint `payment_callback.php?driver=paytr`.
- PayPal: Checkout v2 driver beside classic Express Checkout (kept).
- Saved cards/wallets: PSP tokens only, never PAN; later.
- A new gateway or method value is appended to the ENUM by its step's migration, approved in advance (`workflow/decisions-made-schema.md`); no VARCHAR change, so add-ons cannot add one. Refund and cancel go to the order's own driver, card orders included (`commerce/payment-refunds.md`).

## What remains?
- Done: 1 contract and registry (PR #740), 2 iyzico (#812), 2b its 3-D Secure return (#1710), 3a Stripe PaymentIntents (#937), 4a one list of checkout methods (#1743, `commerce/payment-method-lists.md`), 5 refund and cancel through the driver (#873).
3b. Stripe webhook (`payment_callback.php?driver=stripe`, `notify()` with signature check, a `webhook_secret` field): a payment that succeeds after the visitor left, a dispute or refund made on Stripe's screen (M). First half done (PR #2143, alert only: `commerce/payment-callback.md`); second half decided in #2382 (A, below).
4b. PayPal Checkout v2 driver (M), after 4a: a redirect method appended to `orders.payment_method` by a 2026.11.0 step; Orders v2 create, approve redirect, capture in `complete()`; refund by capture id; no webhook needed. PR #2377 (`plans/payment-driver-paypal.md`).
4c. PayTR driver (M), after 3b's callback: it confirms payments only server to server, so it needs A2's completion function; its iframe needs frame-src CSP.
6. Memory: keep `commerce/payment-drivers.md` current; the frozen-gateways entry of the decisions list is the owner's to add.

## Open questions?
- Sipay/Param after PayTR by demand; prefer a PSP that fronts the banks over per-bank vPOS.
- Answered (#2438): the unreachable inline Iyzipay and Pay With Iyzico code goes, after a test pins both values to the driver branch.
- 3b decided (#2382, owner: A, A1 first): A1 writes the PaymentIntent id into the incomplete order's `transaction_id` and the started amount into `total` at authorize (no schema); A2, a plan of its own, moves the completion tail of `submit_order()` into one session-free function with a `status = 'incomplete'` once-only lock (the frozen gateways' code untouched; only the shared tail moves); A3 completes from the webhook through it, PayTR 4c too; alert-only until A2.
- Answered (#2407): the driver's fields are drawn on the classic checkout pages and classic express order too, a step after 3b.

## Where to start?
- 4b: PR #2377. 3b A1: `authorize()` in `includes/payments/stripe.php`; A2: its own plan first.
