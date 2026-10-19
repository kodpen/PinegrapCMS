---
name: payment-driver-layer
description: Plan (approved 2026-10-19, not started): payment driver interface beside submit_order.php, Stripe PaymentIntents + wallets, PayTR as first local PSP; legacy gateways frozen, never removed
---

## What did the owner decide?
- 2026-10-19: build a driver layer and move only iyzico, Stripe and PayPal onto it; Authorize.Net, First Data, ClearCommerce/PayFuse, Payflow Pro, Payments Pro and Sage stay exactly where they are in `submit_order.php` (frozen: no refactor, no removal). Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change.

## Why?
- All eight gateways are inline in `submit_order.php` (validation 1527–1762, charge 1810–2783, iyzico 2783–3200, PayPal Express 3424–3464, recurring 4461–5227). Stripe uses vendored stripe-php 1.17.2 and the Charges API with raw card fields passing through the server: no SCA, no Elements, no wallets. Shopify Payments is still absent in Türkiye, so local PSPs decide; PayTR, Sipay, Param and bank virtual POS are standard at every Turkish competitor; Apple/Google Pay lift mobile conversion.

## What is decided?
- Interface `includes/payments/driver.php`: `prepare(order, amount)` → client config, `authorize/capture`, `complete(return)` for 3DS/redirect returns, `refund(order, amount, lines)`, `cancel`, `capabilities()` (wallets, installments, recurring, refund). Drivers under `includes/payments/<name>.php`; settings per driver on the Ecommerce card; one active card driver plus optional redirect drivers, as today.
- `submit_order.php` gains one branch: when `ECOMMERCE_PAYMENT_GATEWAY` names a driver-based gateway, the driver handles the payment and the rest of the file is unchanged. Legacy branches are reached exactly as before.
- Stripe: new driver on PaymentIntents with Elements/Payment Request (Apple Pay, Google Pay, Link), webhook `payment_intent.succeeded` with signature check, refunds through the driver; the old Charges code stays for sites that do not switch (setting "Stripe (classic)" vs "Stripe").
- iyzico: existing 3DS, installments, protected currency, `AmountBaseRefund` moved behind the interface with the same behaviour; `iyzipay_3ds_state` kept.
- PayTR: iframe/redirect flow, hash verification, installment table, refund API, callback endpoint `payment_callback.php?driver=paytr`.
- PayPal: Checkout v2 driver beside classic Express Checkout (kept).
- Saved cards/wallets for customers: tokens stored only as PSP references, never PAN; optional later.

## What remains?
1. Interface + driver registry + settings scaffolding + tests on pure helpers (M).
2. iyzico driver port with behaviour tests against today's results (M).
3. Stripe PaymentIntents driver + Elements in the express order widget + webhook (L).
4. PayTR driver (M); PayPal v2 driver (M).
5. Refund path in `view_order.php` and `includes/erp/order_refund.php` calls the driver when present (S).
6. Memory: `docs/memory/commerce/payment-drivers.md`; decisions skill entry that legacy gateways are frozen on purpose.

## Open questions?
- Sipay/Param after PayTR by demand. Bank vPOS direct integrations are per-bank work; prefer a PSP that fronts the banks.

## Where to start?
- Step 1 and 2 together: the iyzico port proves the interface without changing behaviour. Read `docs/memory/commerce/order-cancellation-*.md` and the decisions skill first.
