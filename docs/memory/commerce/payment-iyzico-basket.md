---
name: payment-iyzico-basket
description: How pg_payment_iyzico_basket() makes the iyzico basket add up to the price: lines charged today only, shared over lines above zero (#11389, #11390)
---

## Why must the basket add up to the price?
- iyzico refuses a request whose basket items do not sum to `price` ("Payment could not be initiated"), and refuses an item of no price or below zero. `price` is the subtotal charged today (before discount, tax, shipping); `paidPrice` is the total.

## Which lines go into the basket?
- submit_order.php passes every cart line as `lines` but only the lines charged today in `subtotal`. The basket mirrors that: a recurring line whose `recurring_start_date` is later than today stays out, as it stays out of `$subtotal`.
- Lines at or below zero (and quantity 0) get no item of their own.

## How are the item prices worked out?
- `pg_payment_iyzico_basket($lines, $amount, $today = '')`: `$amount` is the request's price in minor units in the payment's currency (card: `$charged_subtotal`, PWI: `$subtotal`). It is shared over the kept lines by their base totals with `pg_tax_spread()` (largest remainder first), so a line below zero lowers the others: 50.00 and -15.00 give one item of 35.00. A line whose share comes to 0 is dropped; the sum still equals the price.
- Protected currency: `$amount` is the subtotal converted as a whole, so the lines share it instead of each unit being converted and rounded (2 x 39.95 + 15.00 at 0.0297 once came to 2.83 against 2.82, #11389).

## What did the fix change?
- Before #11390 lines below zero were dropped and later-starting subscriptions kept, so the items summed above the price (50.00 against 35.00) and iyzico refused the payment. The old signature `($lines, $rate, $converted)` is gone. Tests: `tests/payment_iyzico_test.php` (`*_basket_*`).
