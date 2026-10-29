---
name: decisions-made-product-2
description: Decisions list, product part 2 (2026-10-29 on): the guest account and receipt of an order the payment service's message completes; PayTR instalments and who pays their difference
---

## What happens to a guest order the payment service's message completes? (#2984)
- Decided (architect, 2026-10-29; `commerce/order-completion-from-payment.md`): the checkout page's auto-registration switch holds whichever request completes the order. A3 links the order to the account whose e-mail is the billing e-mail (`decisions-made-access.md`, #67 item 8: no verification) and opens none; A3b (#3035, `commerce/order-completion-account.md`) opens the account without signing anyone in, and the customer's receipt copy carries the set-password link as on the visitor's path. A blocked address gets no account; the order still completes. Reason: the owner's decision (#2382) completes the order the visitor left, and the switch is the site's setting.
- The receipt is the one sent by the checkout page `orders.checkout_page_id` names (`decisions-made-schema-2.md`); an order that names no page (started before 2026.11.0's step 11.0.120) or whose page is gone gets a plain-text receipt with a fixed subject and the order number, always sent, and no account link.

## Who pays a PayTR instalment's difference? (#3057)
- Decided (architect, 2026-10-29; plan `payment-driver-layer.md` 4c2): PayTR instalments are offered by a driver setting (the most instalments, a `connect_apps` option, 0 or 1 = single payment as today). Who pays the difference is set in PayTR's own panel: the store, and PayTR charges the cart total (4c2a); or the customer, and PayTR's notification carries a `total_amount` above the started amount with `installment_count` ≥ 2, which the completion records as `orders.surcharge`, `installment_charges` and `payment_installment` and adds to `orders.total`, as the checkout does from `pg_payment_checkout_totals()` for iyzico (4c2b). Any other amount still alerts and completes nothing. Reason: the product's rule since iyzico is that the customer pays the instalment difference and the order records it; what PayTR charged is what the order shows, whichever way the panel is set.

Related: docs/memory/workflow/decisions-made-product.md (part 1), docs/memory/workflow/decisions-made.md
