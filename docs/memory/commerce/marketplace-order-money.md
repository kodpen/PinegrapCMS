---
name: marketplace-order-money
description: Amounts of an imported marketplace order: per-unit price/tax/discount, the line's discount_total, mp_order_totals(), n11 line discounts that do not divide by the quantity.
---

## What amounts does a connector hand the importer?
- Per line, in minor units: `price`, `tax` and `discount` PER UNIT (what `order_items` stores), and optionally `discount_total`, the whole line's discount.
- n11 quotes prices in decimal major units (a comma may appear); `mp_n11_minor()` converts with `round()`. `sellerDiscount + sellerCouponDiscount` is the line's discount; `mallDiscount` is n11's money and is not counted (`n11-api-facts.md`).

## How are the order's totals built? (`mp_order_totals()`, includes/api/outbound/orders.php)
- `subtotal = Σ price × qty`, `tax = Σ tax × qty`, `discount = Σ discount_total` (or `discount × qty` when a line has no `discount_total`), `total = subtotal − discount + tax + shipping`.
- Why `discount_total`: n11 gives the discount for the whole line, and a line amount that does not divide by the quantity has no exact unit value. Summing the rounded unit value back was off by up to quantity/2 kuruş.
- Symptom before the fix: 3 × 100,00 TL with a 10,00 TL line discount was imported as discount 9,99 and total 290,01 TL (7 units: 10,01 TL discount); the invoice cut from the order carried the same difference.
- Tests: `tests/marketplace_order_totals_test.php`.

Related: docs/memory/commerce/marketplace-order-import.md
