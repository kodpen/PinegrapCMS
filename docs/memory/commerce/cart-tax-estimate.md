---
name: cart-tax-estimate
description: Cart widget tax (pg_cart_estimate_tax): stored line taxes while they fit, else the default-rate preview; the order discount's share off as the checkout takes it (#3455, #9045)
module: widgets_cart
---

## How does the cart estimate its tax?
- The cart widget (includes/fn/widgets_cart.php) reads each active line's price, quantity, `tax_total`, `products.taxable`, `products.tax_rate` and recurring start, in the order of the ids, and asks `pg_cart_estimate_tax()` (pure, `tests/cart_tax_estimate_test.php`). `pg_cart_estimate_line_taxes()` gives each line's tax and the rate it was taken at.
- While every line the preview would tax (taxable product, effective rate above 0 on the default country's zone) has a `tax_total`, the stored taxes are summed: the checkout took them on the buyer's address.
- When any such line still has `tax_total` 0, the **whole cart** is previewed on `get_default_tax_rate()`, product rates first (`get_effective_tax_rate()`), once per rate on the sum of the lines (`pg_tax_by_rate()`). Not only the missing lines: the checkout takes the tax once per rate over every line, so completing one batch would round twice and could differ by a minor unit.
- The stored taxes must also still fit today's amounts (PR #3920): with `order_items.tax_rate` read as `charged_rate`, the lines of each charged rate must give their stored tax again through `pg_tax_by_rate()`; a line with a tax but no charged rate (NULL or 0) cannot be checked. Either miss means the whole-cart preview. A rate other than the default one (the buyer's zone) stays trusted while the amounts are unchanged. On a schema without the column the key is absent and the stored taxes are trusted as before.
- Detection rather than clearing `tax_total` at every writer: quantities and prices are written by the cart, bundles, minimum/maximum quantity, offers and donations, and one missed writer would bring the stale figure back.
- Tax off, or the default country in no zone: the stored sum (0 before any checkout).

## What was the symptom? (#3455)
- `tax_total` is written only by the checkout's tax pass. The cart used the stored sum whenever it was above 0, so a product added after the visitor once reached the checkout was left out: 9.998 + 7.499 at 20% showed 1.999,60 instead of 3.499,40.
- A quantity or donation amount changed after the checkout kept the old stored tax (twice the quantity, the same tax) until the checkout ran again (PR #3920).

## How does the order discount come off the cart's tax? (#9045)
- The estimated line taxes (stored or preview) are of the lines before the discount. `pg_cart_estimate_tax()` puts them through `pg_order_charged_today_totals()`, the step `pg_express_order_charged_totals()` starts from: the discount, at most today's subtotal, is spread over the lines charged today and the tax taken again per rate (`pg_order_charged_tax()`). A recurring line that starts later takes no share and keeps its own tax, which the cart still shows.
- Rates handed on: the charged rate of a stored tax, the preview rate, 0 for a line with no tax, null for a stored tax with no kept rate (then the tax is lowered by the discount's share, as the checkout does).
- `test_cart_tax_estimate_matches_checkout` pins the cart's tax to the express order widget's on the same stored lines.
- Symptom: ₺4.999,00 at 20% with a 10% code showed Vergi ₺999,80 and Toplam ₺5.498,90 in the cart while the checkout charged ₺899,82 / ₺5.398,92; it stayed so after the checkout, as stored taxes are pre-discount.
