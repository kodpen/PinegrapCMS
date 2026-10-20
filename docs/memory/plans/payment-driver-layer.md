---
name: payment-driver-layer
description: Plan (approved 2026-10-19; steps 1, 2, 5 done, PRs #740, #812; 2b open): payment drivers beside submit_order.php; iyzico on it, Stripe, PayTR, PayPal v2 next; legacy gateways frozen
---

## What did the owner decide?
- 2026-10-19: build a driver layer and move only iyzico, Stripe and PayPal onto it; Authorize.Net, First Data, ClearCommerce/PayFuse, Payflow Pro, Payments Pro and Sage stay exactly where they are in `submit_order.php` (frozen: no refactor, no removal). Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change.

## Why?
- All eight gateways were inline in `submit_order.php`. Stripe uses vendored stripe-php 1.17.2 and the Charges API with raw card fields through the server: no SCA, Elements or wallets. Shopify Payments is absent in Türkiye; PayTR, Sipay, Param and bank vPOS are standard at Turkish competitors; Apple/Google Pay lift mobile conversion.

## What is decided?
- Contract `includes/payments/driver.php` (step 1; `commerce/payment-drivers.md`): `pg_payment_<key>_` prepare, authorize, complete, capture, refund, cancel, notify, capabilities, fields; callers use `pg_payment_call()`. One active card driver plus optional redirect drivers, as today.
- `submit_order.php` gains one branch (done in step 2, `commerce/payment-driver-iyzico.md`): when the gateway or method has a driver, the driver handles the payment and the rest of the file is unchanged. Legacy branches are reached exactly as before.
- Stripe: new driver on PaymentIntents with Elements/Payment Request (Apple Pay, Google Pay, Link), webhook `payment_intent.succeeded` with signature check, refunds through the driver; the old Charges code stays for sites that do not switch (setting "Stripe (classic)" vs "Stripe").
- PayTR: iframe/redirect flow, hash verification, installment table, refund API, callback endpoint `payment_callback.php?driver=paytr`.
- PayPal: Checkout v2 driver beside classic Express Checkout (kept).
- Saved cards/wallets: tokens only as PSP references, never PAN; optional later.

## What remains?
1. ~~Interface + registry + settings scaffolding + tests (M).~~ Done (PR #740).
2. ~~iyzico driver port with behaviour tests against today's results (M).~~ Done (PR #812); the inline iyzico code stays (frozen).
2b. The iyzico 3-D Secure return (`mode=iyzipay_threedsecure_return`) still runs in submit_order.php's Iyzipay block; moving it into the card driver's `complete()` is open (S).
3. Stripe PaymentIntents driver + Elements in the express order widget + webhook (L).
4. PayTR driver (M); PayPal v2 driver (M).
5. ~~Refund in `view_order.php` / `order_refund.php` and the cancel refund in `process_order_cancellation()` through the driver (S).~~ Done (`commerce/payment-refunds.md`).
6. Memory: keep `commerce/payment-drivers.md` current; the decisions skill entry (legacy gateways frozen on purpose) is the owner's to add.

## Open questions?
- Cancel never voids a card order (`Credit/Debit Card`), though the dialog says "via iyzico". Owner: void it through the gateway's card driver?
- The order screen refunds a Pay With Iyzico order only when the gateway is Iyzipay. Owner: use its own driver whatever the gateway?
- Sipay/Param after PayTR by demand. Bank vPOS direct integrations are per-bank work; prefer a PSP that fronts the banks.
- `config.ecommerce_payment_gateway` and `orders.payment_method` are ENUMs: a new gateway ("Stripe" PaymentIntents beside "Stripe (classic)", PayTR, PayPal v2) needs a value appended by a migration step (default: per step, the 4.48 `install_column_info` append), and an add-on cannot add one at all. Owner: keep appending, or turn both columns into VARCHAR before add-ons?

## Where to start?
- Step 3 (Stripe) needs its gateway value in the ENUM first (open question above) and a generic return mode recognised at the top of `submit_order.php` and in `order_preview.php` (`pg_payment_return_mode()` knows only the two iyzico ones). Read `commerce/payment-drivers.md`, `commerce/payment-driver-iyzico.md`, the decisions skill first.
