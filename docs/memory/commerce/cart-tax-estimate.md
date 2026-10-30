---
name: cart-tax-estimate
description: The cart widget's tax estimate (pg_cart_estimate_tax): stored line taxes while every taxable line has one, else the default-rate preview over the whole cart (#3455, PR #3530)
module: widgets_cart
---

## How does the cart estimate its tax?
- The cart widget (includes/fn/widgets_cart.php) reads each active line's amount, `tax_total`, `products.taxable` and `products.tax_rate` and asks `pg_cart_estimate_tax()` (pure, `tests/cart_tax_estimate_test.php`).
- While every line the preview would tax (taxable product, effective rate above 0 on the default country's zone) has a `tax_total`, the stored taxes are summed: the checkout took them on the buyer's address.
- When any such line still has `tax_total` 0, the **whole cart** is previewed on `get_default_tax_rate()`, product rates first (`get_effective_tax_rate()`), once per rate on the sum of the lines (`pg_tax_by_rate()`). Not only the missing lines: the checkout takes the tax once per rate over every line, so completing one batch would round twice and could differ by a minor unit.
- Tax off, or the default country in no zone: the stored sum (0 before any checkout).

## What was the symptom? (#3455)
- `tax_total` is written only by the checkout's tax pass. The cart used the stored sum whenever it was above 0, so a product added after the visitor once reached the checkout was left out: 9.998 + 7.499 at 20% showed 1.999,60 instead of 3.499,40.
