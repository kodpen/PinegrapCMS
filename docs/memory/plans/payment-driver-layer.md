---
name: payment-driver-layer
description: Plan (approved 2026-10-19; 1, 2, 3a, 5 done: #740, #812, #937, #873; 2b, 3b open): payment drivers beside submit_order.php; iyzico and Stripe on it, PayTR, PayPal v2 next
---

## What did the owner decide?
- 2026-10-19: build a driver layer and move only iyzico, Stripe and PayPal onto it; Authorize.Net, First Data, ClearCommerce/PayFuse, Payflow Pro, Payments Pro and Sage stay exactly where they are in `submit_order.php` (frozen: no refactor, no removal). Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change.

## Why?
- All eight gateways were inline in `submit_order.php`. Stripe uses vendored stripe-php 1.17.2 and the Charges API with raw card fields through the server: no SCA, Elements or wallets. Shopify Payments is absent in Türkiye; PayTR, Sipay, Param and bank vPOS are standard at Turkish competitors; Apple/Google Pay lift mobile conversion.

## What is decided?
- Contract `includes/payments/driver.php` (step 1; `commerce/payment-drivers.md`): `pg_payment_<key>_` prepare, authorize, complete, capture, refund, cancel, notify, capabilities, fields; callers use `pg_payment_call()`. One active card driver plus optional redirect drivers, as today.
- `submit_order.php` has one branch (step 2, `commerce/payment-driver-iyzico.md`): a gateway or method with a driver is paid by it; legacy branches are reached exactly as before.
- Stripe: PaymentIntents driver with the Payment Element (Apple Pay, Google Pay, Link), a signed webhook, refunds through the driver; the Charges code stays as "Stripe (classic)".
- PayTR: iframe/redirect flow, hash verification, installment table, refund API, callback endpoint `payment_callback.php?driver=paytr`.
- PayPal: Checkout v2 driver beside classic Express Checkout (kept).
- Saved cards/wallets: tokens only as PSP references, never PAN; optional later.
- 2026-10-20: a new gateway or method value is appended to the `config.ecommerce_payment_gateway` / `orders.payment_method` ENUM by its step's migration (4.48 `install_column_info` append); no VARCHAR change, so add-ons cannot add one. Refund and cancel go to the order's own driver, card orders included (`commerce/payment-refunds.md`).

## What remains?
1. ~~Interface + registry + settings scaffolding + tests (M).~~ Done (PR #740).
2. ~~iyzico driver port with behaviour tests against today's results (M).~~ Done (PR #812); the inline iyzico code stays (frozen).
2b. The iyzico 3-D Secure return (`mode=iyzipay_threedsecure_return`) still runs in submit_order.php's Iyzipay block; moving it into the card driver's `complete()` is open (S).
3. ~~Stripe PaymentIntents driver + Payment Element in the express order widget (L).~~ Done (PR #937, `commerce/payment-driver-stripe.md`).
3b. Stripe webhook (`payment_callback.php?driver=stripe`, `notify()` with signature check, a `webhook_secret` field): a payment that succeeds after the visitor left, a dispute or refund made on Stripe's screen (M).
4. PayTR driver (M); PayPal v2 driver (M).
5. ~~Refund in `view_order.php` / `order_refund.php` and the cancel refund in `process_order_cancellation()` through the driver (S).~~ Done (PR #873, `commerce/payment-refunds.md`).
6. Memory: keep `commerce/payment-drivers.md` current; the decisions skill entry (legacy gateways frozen on purpose) is the owner's to add.

## Open questions?
- Sipay/Param after PayTR by demand; prefer a PSP that fronts the banks over per-bank vPOS.
- 3b: `payment_intent.succeeded` for an order still incomplete: alert only, or complete it from the webhook? Owner.
- Stripe's fields are drawn only by the designer's express order widget; classic checkout pages and order preview too? Owner.

## Where to start?
- 3b: a generic `payment_callback.php?driver=<key>` calling `notify()`; Stripe's events signed with the webhook secret. Step 4 (PayTR, PayPal v2) appends its ENUM value as 11.0.24 did and reuses `pg_payment_return_modes()`. Read `commerce/payment-drivers.md`, `commerce/payment-driver-stripe.md` first.
