---
name: payment-driver-layer-decisions
description: Decisions of the finished payment driver layer plan (2026-10-19 to #3070): drivers vs the frozen legacy six, ENUM appends, completion from a message, PayTR installments, next
---

## What did the owner decide (2026-10-19)?
- Build a driver layer (`payment-drivers.md`) and move only iyzico, Stripe and PayPal onto it; Authorize.Net, First Data, ClearCommerce/PayFuse, Payflow Pro, Payments Pro and Sage stay exactly where they are in `submit_order.php` and `recurring_payment_job.php`: frozen, no refactor, no removal. Express-order totals (`get_express_order.php`, `get_order_preview.php`) never change.
- Why: all eight gateways were inline in `submit_order.php`; classic Stripe took raw card fields (no SCA, Elements or wallets). PayTR, Sipay, Param and bank vPOS are standard at Turkish competitors.

## What else is fixed?
- One active card driver plus optional redirect drivers; `submit_order.php` has one driver branch, legacy branches are reached as before (`payment-iyzico-checkout.md`).
- 2026-10-20: a new gateway or method value is appended to the `config.ecommerce_payment_gateway` / `orders.payment_method` ENUM by its step's migration (`install_column_info` append), approved in advance (`workflow/decisions-made-schema.md`, #2389); no VARCHAR change, so add-ons cannot add one. Refund and cancel go to the order's own driver (`payment-refunds.md`).
- 2026-10-27 (#2382): a verified payment message for an incomplete order completes it, only for the order's own payment, total and currency, exactly once (`order-completion-from-payment.md`).
- 2026-10-29 (#3057): PayTR instalments by a most-installments driver setting; what PayTR took is recorded, an installment difference the customer paid included (`payment-driver-paytr.md`, `workflow/decisions-made-product-2.md`).
- PayPal Checkout v2 runs beside classic Express Checkout (kept). Saved cards and wallets: PSP tokens only, never a PAN.

## What was built, where?
- Contract and registry #740; iyzico #812, its 3-D Secure return #1710; Stripe PaymentIntents #937 and webhook #2143/#3021; one list of checkout methods #1743; refund and cancel through the driver #873; PayPal v2 #2377, its webhooks #2613; driver fields on the classic pages #2556; PayTR #3036, refunds #3055, instalments #3070.

## What is open?
- Sipay or Param after PayTR, by demand; prefer a PSP that fronts the banks over per-bank vPOS.
- The decisions list entry for the frozen legacy gateways (`workflow/decisions-made-product.md`) is the owner's to add.

Related: commerce/payment-drivers.md, commerce/payment-driver-paytr.md, commerce/payment-callback.md
