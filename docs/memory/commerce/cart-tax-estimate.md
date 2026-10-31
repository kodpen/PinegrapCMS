---
name: cart-tax-estimate
description: The cart widget's tax estimate (pg_cart_estimate_tax): stored line taxes while each taxable line has one that fits today's amount, else the default-rate preview (#3455, PRs #3530, #3920)
module: widgets_cart
---

## How does the cart estimate its tax?
- The cart widget (includes/fn/widgets_cart.php) reads each active line's amount, `tax_total`, `products.taxable` and `products.tax_rate` and asks `pg_cart_estimate_tax()` (pure, `tests/cart_tax_estimate_test.php`).
- While every line the preview would tax (taxable product, effective rate above 0 on the default country's zone) has a `tax_total`, the stored taxes are summed: the checkout took them on the buyer's address.
- When any such line still has `tax_total` 0, the **whole cart** is previewed on `get_default_tax_rate()`, product rates first (`get_effective_tax_rate()`), once per rate on the sum of the lines (`pg_tax_by_rate()`). Not only the missing lines: the checkout takes the tax once per rate over every line, so completing one batch would round twice and could differ by a minor unit.
- The stored taxes must also still fit today's amounts (PR #3920): with `order_items.tax_rate` read as `charged_rate`, the lines of each charged rate must give their stored tax again through `pg_tax_by_rate()`; a line with a tax but no charged rate (NULL or 0) cannot be checked. Either miss means the whole-cart preview. A rate other than the default one (the buyer's zone) stays trusted while the amounts are unchanged. On a schema without the column the key is absent and the stored taxes are trusted as before.
- Detection rather than clearing `tax_total` at every writer: quantities and prices are written by the cart, bundles, minimum/maximum quantity, offers and donations, and one missed writer would bring the stale figure back.
- Tax off, or the default country in no zone: the stored sum (0 before any checkout).

## What was the symptom? (#3455)
- `tax_total` is written only by the checkout's tax pass. The cart used the stored sum whenever it was above 0, so a product added after the visitor once reached the checkout was left out: 9.998 + 7.499 at 20% showed 1.999,60 instead of 3.499,40.
- A quantity or donation amount changed after the checkout kept the old stored tax (twice the quantity, the same tax) until the checkout ran again (PR #3920).
