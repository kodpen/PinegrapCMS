---
name: bug-hunt-areas-tax-rates-calculation
description: Bug hunt of tax rates calculation (area 50, 2026-11-24): zone and product rates, tax-inclusive prices, per-rate rounding; two cart widget faults fixed, what read clean
---

## Which area was hunted?
- Area 50, tax rates calculation: the rate a line is taxed at by the buyer's zone and the product's own rate, tax-inclusive prices, and the tax rounded per rate over the lines against the order's tax.
- Read: `get_tax_rate_for_address()` (fn/forms.php); the rate, parse, format and default-rate helpers and the per-rate tax functions of fn/ecommerce.php, `update_order_item_taxes()` included.
- Also: the cart widget's estimate and the express order tax pass (fn/widgets_cart.php); the marketplace price and VAT rate (`mp_product_price()`, `mp_applied_vat_rate()`); the till's rate (`local_sale_line_rate()`); the tax zone API's rate check; the ERP's `erp_split_gross()` and `erp_account_zone_rate()`.
- Rules checked against: `commerce/product-tax-rate.md`, `commerce/cart-tax-estimate.md`, `commerce/express-order-tax-address.md`, `commerce/counter-sale-tax-switched-off.md`.

## What did the hunt's PR fix?
- The cart widget previewed the default rate's tax for an order the checkout took none on (tax-exempt, or an address in no tax zone): `pg_cart_estimate_line_taxes()` now trusts a line charged at rate 0 (`commerce/cart-tax-estimate.md`).
- A recurring line that starts later is taxed alone by the checkout; the cart grouped it with the other lines of its rate, so the stored taxes failed the check and the cart showed the default rate's preview. Both the check and the preview now take such a line alone through `pg_order_line_taxes()`.

## What was read clean?
- A zone at 0.000 is a zone: `get_tax_rate_for_address()` answers the string `'0.000'`, which `pg_order_line_tax_rate()` reads as a zone, and the product's own rate applies, as in `get_tax_inclusive_price()` and the marketplace rate.
- `ECOMMERCE_TAX === true` in the express order paths holds: init.php casts the column to a boolean before the define.
- The tax zone rate on the screen, the quick edit and the API is held to 0..100 at three places; the product rate goes through `pg_tax_rate_problem()` / `parse_tax_rate()`.
- Per-rate rounding: `pg_tax_by_rate()` with `pg_tax_spread()` adds up to the group's tax; `update_order_item_taxes()` takes lines charged later alone; the order discount's tax through `pg_order_charged_tax()`.

## What was left?
- A renewal order's line (`fn/subscription_renewal.php`) is written without `order_items.tax_rate`, so its invoice works the rate back out of the amounts; not a customer figure, and the subscriptions area had its own hunt.
- `pg_order_charged_tax()` on lines without a kept rate lowers the tax with a float `round()`; no case found where it differs on PHP 8.3, PHP 8.4 not run.

## What was not run?
- No database and no browser: the cart figures are pinned by `tests/cart_tax_estimate_test.php`.

Related: docs/memory/workflow/bug-hunt.md, docs/memory/commerce/cart-tax-estimate.md
