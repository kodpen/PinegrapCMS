---
name: bug-hunt-areas-price-rounding
description: The bug hunt of price rounding (area 17, 2026-11-24): money in checkout, cart, offers, tax, shipping, refunds, the payment request and the ERP; four faults fixed, what was read clean
---

## Which area was hunted?
- Area 17, price rounding: every place that works money out in the cart, checkout, offers, tax, shipping, refunds, store credit, the payment drivers' requests and the ERP invoice lines and late fee.
- Looked at: integer minor units end to end, `round()` and never an `(int)` cut, a float `round()` of a percentage that moves on PHP 8.4+, the basket's lines against the total sent to the payment provider, a discount or refund below zero.
- Rules it checked against: `commerce/order-totals-overview.md`, `commerce/offer-percent-rounding.md`, `erp/erp-line-total-and-rate-rounding.md`.

## What did the hunt's PR fix?
- iyzico under a protected currency: each basket unit was converted and rounded on its own, so the items could sum a kurus or more beside the converted subtotal sent as the price, and iyzico refuses such a basket. `pg_payment_iyzico_basket()` now shares the converted subtotal by the lines' base totals (`commerce/payment-driver-iyzico.md`).
- The store credit refund bonus was a float `round(amount * percent / 100)`: 8.86% of 525.00 gave 46.51 on PHP 8.4+. Now `pg_percent_of()` (`commerce/store-credit-refunds.md`).
- The ERP late fee was a float `round(kurus_days * rate / 100 / 30)`: 1035.00 owed 30 days at 4.1% gave 42.43 on PHP 8.4+. Now `erp_multiply_units()` (`erp/erp-late-fee.md`).
- A return of a line priced below zero alone suggested a negative refund in the form's amount field; `pg_return_refund_estimate()` now stops at 0 (`commerce/order-returns-refund.md`).

## What was read clean?
- Amounts typed into the cart, donation, gift card, offer and order refund forms: `(int) round(x * 100)`. The checkout pages' and express order's figures in minor units through the shared helpers; offer percentages validated 0 to 100 on the editor and the API, so no price goes below zero.
- Shipping cost formulas: whole weight points times whole rates. The order screen's refund: worked out by lines with the ERP, capped at what is left.
- The ERP: line totals and rates through `money.php`; stock and profit cost rounding with `round()`.

## What was left?
- The affiliate commission (`pg_order_commission()`, the recurring commission profile) still multiplies floats and rounds once; a payout, not a customer figure, and its source is pinned by `order_submit_affiliate_test.php`.
- The iyzico basket in the base currency lists every line with a price (a subscription starting later included, a line below zero left out), while the price is the subtotal charged today; with such a cart the sums differ outside the rounding this hunt covers.
- `supplier_feed.php` takes VAT off a supplier cost with a float division (an import, not a sale).

## What was not run?
- No database, no iyzico sandbox and no PHP 8.4: the PHP 8.4 cases were found by emulating its `round()` and are pinned by exact-integer references in `store_credit_refunds_test.php` and `erp_late_fee_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/order-totals-overview.md
