---
name: payment-driver-paytr-installments
description: PayTR installments (decision #3057, PR #3070): the Most Installments setting, get-token fields, installment_count, the match rule and what the completion writes for a customer-paid difference
module: payments
---

## What does the setting do?
- `connect_apps` `payment_paytr` option `max_installment` (Most Installments): `1` single payment (default) or 2–12; `pg_payment_paytr_max_installment()` reads anything else as 0. Above 1 the get-token fields are `no_installment=0`, `max_installment=<setting>`, else `1`/`0`; the token hash covers both, `payment_amount` stays the order total.

## How is the payment matched and recorded?
- Who pays the difference is chosen in the PayTR Merchant Panel; the store cannot see it. Store pays: `total_amount` = the total, the order completes as a single payment would (the count is still recorded). Customer pays: `total_amount` is above the started `orders.total`.
- `notify` reads `installment_count` (`pg_payment_paytr_installment_count()`, 1–99, else 0) into the answer's `installments`. `pg_payment_paid_totals()` (fn/payments.php): the total, or above it with ≥ 2 installments → match; below, or above in one payment / no count → alert, nothing completed.
- The difference is written as `installment_charges` (+ `payment_installment`, `orders.total` raised), as `pg_payment_checkout_totals()` writes iyzico's; `surcharge` stays the card surcharge: the ERP invoice and receipt already add `installment_charges` as their own line, so writing it to `surcharge` too would bill it twice. The commission stays on the total without it.
- `installment_count` is not in PayTR's callback hash; it only lets a signed `total_amount` above the total through, never changes the amount.

## How was it verified?
- `tests/payment_paytr_test.php` (fixed hash vector with installments, sent fields per setting, the notification cases), `tests/order_completion_test.php` (`pg_payment_paid_totals()`, the written figures). No live PayTR call; the fields follow dev.paytr.com.

Related: commerce/payment-driver-paytr.md, commerce/order-completion-from-payment.md, commerce/payment-iyzico-checkout.md
