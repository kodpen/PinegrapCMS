---
name: payment-driver-layer
description: Plan (approved 2026-10-19; 1, 2, 2b, 3a, 4a, 4b, 5 done; 3b, 4c open): payment drivers beside submit_order.php; iyzico and Stripe on it; one checkout method list, then PayPal v2 and PayTR
---

## What did the owner decide?
- 2026-10-19: build a driver layer and move only iyzico, Stripe and PayPal onto it; Authorize.Net, First Data, ClearCommerce/PayFuse, Payflow Pro, Payments Pro and Sage stay exactly where they are in `submit_order.php` (frozen: no refactor, no removal). Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change.

## Why?
- All eight gateways were inline in `submit_order.php`; classic Stripe takes raw card fields through the server (no SCA, Elements or wallets). Shopify Payments is absent in Türkiye; PayTR, Sipay, Param and bank vPOS are standard at Turkish competitors; Apple/Google Pay lift mobile conversion.

## What is decided?
- Contract `includes/payments/driver.php` (step 1; `commerce/payment-drivers.md`): `pg_payment_<key>_` prepare, authorize, complete, capture, refund, cancel, notify, capabilities, fields; callers use `pg_payment_call()`. One active card driver plus optional redirect drivers, as today.
- `submit_order.php` has one branch (step 2, `commerce/payment-driver-iyzico.md`): a gateway or method with a driver is paid by it; legacy branches are reached exactly as before.
- PayTR: iframe/redirect flow, hash verification, installment table, refund API, callback endpoint `payment_callback.php?driver=paytr`.
- PayPal: Checkout v2 driver beside classic Express Checkout (kept; `commerce/payment-driver-paypal.md`).
- Saved cards/wallets: tokens only as PSP references, never PAN; optional later.
- 2026-10-20: a new gateway or method value is appended to the `config.ecommerce_payment_gateway` / `orders.payment_method` ENUM by its step's migration (4.48 `install_column_info` append); no VARCHAR change, so add-ons cannot add one. Refund and cancel go to the order's own driver, card orders included (`commerce/payment-refunds.md`).

## What remains?
- Done: 1 contract and registry (PR #740), 2 iyzico (#812), 2b its 3-D Secure return (#1710), 3a Stripe PaymentIntents (#937), 4a one list of checkout methods (#1743, `commerce/payment-method-lists.md`), 4b PayPal Checkout v2 (#1827, `commerce/payment-driver-paypal.md`), 5 refund and cancel through the driver (#873).
3b. Stripe webhook (`payment_callback.php?driver=stripe`, `notify()` with signature check, a `webhook_secret` field): a payment that succeeds after the visitor left, a dispute or refund made on Stripe's screen (M).
4c. PayTR driver (M), after 3b's callback: it confirms payments only server to server, so orders complete from the callback (3b's owner question); its iframe needs frame-src CSP.
6. Memory: keep `commerce/payment-drivers.md` current; the decisions list entry (legacy gateways frozen on purpose, `workflow/decisions-made-product.md`) is the owner's to add.

## Open questions?
- Sipay/Param after PayTR by demand; prefer a PSP that fronts the banks over per-bank vPOS.
- Since 2b no request reaches submit_order.php's inline Iyzipay and Pay With Iyzico code: remove it or keep it? Owner.
- 3b: `payment_intent.succeeded` for an order still incomplete: alert only, or complete it from the webhook? Owner.
- 4b: a PayPal capture PENDING on PayPal (review, eCheck) places the order with a log warning and nothing learns of a later denial: keep, refuse the order, or wait for a PayPal webhook on 3b's callback? Owner (PR #1827).
- Stripe's fields are drawn only by the designer's express order widget; classic checkout pages and order preview too? Owner.

## Where to start?
- 3b: a generic `payment_callback.php?driver=<key>` calling `notify()`, Stripe's events signed with the webhook secret (`commerce/payment-driver-stripe.md`).
