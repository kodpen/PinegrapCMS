---
name: card-surcharge-rounding
description: Card surcharge (ECOMMERCE_SURCHARGE_PERCENTAGE) as a figure: pg_percent_of() in minor units, the same on every PHP version; the classic pages post what submit_order() charges (PR #NNNN)
---

## How is the card surcharge worked out?
- `pg_percent_of($total, ECOMMERCE_SURCHARGE_PERCENTAGE)`: the exact product of what is left to pay (after the gift cards) and the percentage, rounded half away from zero, the offer percentages' rule (docs/memory/commerce/offer-percent-rounding.md). The setting is `config.ecommerce_surcharge_percentage` decimal(6,3), the helper's three places, so `'2.900'` is taken as written.
- In minor units everywhere: `submit_order.php` (the charge), `pg_express_order_charged_totals()` (the express order widget), and the classic `get_express_order.php` / `get_order_preview.php`, which add the total up in major units and so take `pg_percent_of(round($grand_total * 100), …) / 100`.
- Where the surcharge holds tax, it is then a minor unit lower when it would leave a rate's total unreachable (`pg_order_charges_reachable()`, docs/memory/erp/erp-order-charges-vat.md), the same on every page.

## What did it fix? (PR #NNNN)
- It was `round(P / 100 * $total)` (`round(…, 2)` on major units on the classic pages), which PHP 8.4 and later no longer pre-round: 2.9% of 5.00 (0.145) was charged 0.14, 4.35% of 50.00 (2.175) 2.17, where PHP 8.3 charged 0.15 and 2.18.
- On major units it moved far more often: 3% of 5.50 is 0.16499999... in binary, so the classic express order page showed 0.16 while `submit_order.php` charged 0.17. `submit_order.php` refuses a total higher than the posted `total_with_surcharge`, so a card order was sent back to review the new total, which the page drew the same way again.
- On PHP 8.3 no figure moved in minor units (0 mismatches in 63 million totals and percentages against a port of 8.3's `round()`). The classic pages' old figure differed in 4 of 3 million random carts, at a half minor unit after a gift card of over 1,000,000.00 had left their major-unit sum a few billionths off; there it was already a minor unit below what `submit_order.php` charged on 8.3.

## Which test holds it?
- `tests/surcharge_percent_test.php`: the widget's figures, 20,000 random totals and percentages to three places (half at a half minor unit) against an exact reference, the classic page's major-unit sum giving the same charge and posted total, the old float formulas on PHP < 8.4 (CI's 8.3), and a source scan that fails on any `surcharge_percent… / 100` left.

Related: docs/memory/commerce/express-order-widget-totals.md
